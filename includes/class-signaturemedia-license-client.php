<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'SignatureMedia_License_Client' ) ) :
class SignatureMedia_License_Client {
	/** @var string */ private $product;
	/** @var string */ private $api_base;
	/** @var string */ private $option_prefix;
	/** @var string */ private $plugin_file;
	/** @var string */ private $plugin_basename;
	/** @var string */ private $slug;         // plugin folder name
	/** @var string */ private $updates_mode; // 'none' (default) or 'server'

	public function __construct( array $args ) {
		$this->product         = isset( $args['product'] ) ? sanitize_key( $args['product'] ) : 'signaturemedia-silo-structure';
		$this->api_base        = isset( $args['api_base'] ) ? untrailingslashit( esc_url_raw( $args['api_base'] ) ) : '';
		$this->option_prefix   = isset( $args['option_prefix'] ) ? sanitize_key( $args['option_prefix'] ) : 'sm_product';
		$this->plugin_file     = isset( $args['plugin_file'] ) ? $args['plugin_file'] : __FILE__;
		$this->plugin_basename = plugin_basename( $this->plugin_file );
		$this->slug            = dirname( $this->plugin_basename );
		$this->updates_mode    = isset( $args['updates'] ) ? ( $args['updates'] === 'server' ? 'server' : 'none' ) : 'none';
	}

	public function init() : void {
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_post_' . $this->option_prefix . '_save_license', [ $this, 'handle_save_license' ] );
		add_filter( 'plugin_action_links_' . $this->plugin_basename, [ $this, 'plugin_action_links' ] );

		add_action( $this->option_prefix . '_validate_event', [ $this, 'schedule_validate' ] );
		if ( ! wp_next_scheduled( $this->option_prefix . '_validate_event' ) ) {
			wp_schedule_event( time() + 60, 'twicedaily', $this->option_prefix . '_validate_event' );
		}

		if ( $this->updates_mode === 'server' ) {
			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
			add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		}

		register_deactivation_hook( $this->plugin_file, [ $this, 'deactivate_license_remote' ] );
	}

	/* ===================== Settings UI ===================== */
	public function register_settings_page() : void {
		add_options_page(
			'Signature Media License',
			'Signature Media License',
			'manage_options',
			$this->option_prefix . '_license',
			[ $this, 'render_settings_page' ]
		);
	}

	public function plugin_action_links( array $links ) : array {
		$url = admin_url( 'options-general.php?page=' . $this->option_prefix . '_license' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'License', 'signaturemedia-silo-structure' ) . '</a>' );
		return $links;
	}

	public function render_settings_page() : void {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Stored value is encrypted in DB; decrypt for masked display only
		$stored_enc = get_option( $this->option_prefix . '_license_key', '' );
		$stored_plain = $stored_enc ? $this->decrypt_str( $stored_enc ) : '';
		$stat       = get_option( $this->option_prefix . '_license_status', [] );
		$valid      = ! empty( $stat['valid'] );
		$last_error = get_option( $this->option_prefix . '_last_error', '-' );

		$masked = '';
		if ( $stored_plain ) {
			$len = strlen( $stored_plain );
			if ( $len <= 8 ) {
				$masked = str_repeat( '•', $len );
			} else {
				$masked = substr( $stored_plain, 0, 4 ) . str_repeat( '•', max(0, $len - 8) ) . substr( $stored_plain, -4 );
			}
		}
		?>
		<div class="wrap">
			<h1>Signature Media License</h1>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( $this->option_prefix . '_save_license' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $this->option_prefix . '_save_license' ); ?>" />

				<table class="form-table" role="presentation">
					<tr>
						<th><label for="sm_license_key">License Key</label></th>
						<td>
							<input type="text" id="sm_license_key" name="license_key" class="regular-text" value="" autocomplete="off" />
							<p class="description">
								<?php if ( $masked ) : ?>
									<strong>Stored key:</strong> <code><?php echo esc_html( $masked ); ?></code><br/>
									Leave the field empty to keep the stored key. To replace the key, enter a new one and click the button.
								<?php else : ?>
									No license key stored. Enter one to activate.
								<?php endif; ?>
							</p>
							<p>
								<label><input type="checkbox" name="remove_license" value="1" /> Remove stored license and deactivate</label>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button( $stored_plain ? ( $valid ? 'Revalidate' : 'Activate' ) : 'Save & Activate' ); ?>
			</form>

			<?php if ( ! empty( $stat ) ) : ?>
				<hr/>
				<h2>Status</h2>
				<ul>
					<li><strong>Valid:</strong> <?php echo $valid ? 'yes' : 'no'; ?></li>
					<li><strong>Reason:</strong> <?php echo esc_html( $stat['reason'] ?? '-' ); ?></li>
					<li><strong>Plan:</strong> <?php echo esc_html( $stat['plan'] ?? '-' ); ?></li>
					<li><strong>Expires:</strong> <?php echo esc_html( $stat['expires_at'] ?? '-' ); ?></li>
					<li><strong>Sites used:</strong> <?php echo intval( $stat['sites_used'] ?? 0 ); ?> / <?php echo intval( $stat['max_sites'] ?? 0 ); ?></li>
					<li><strong>Last check:</strong> <?php echo esc_html( get_option( $this->option_prefix . '_last_check', '-' ) ); ?></li>
					<li><strong>Last error:</strong> <?php echo esc_html( $last_error ); ?></li>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle form submission:
	 * - If remove_license checked → deactivate remote and delete stored option
	 * - If license_key provided → encrypt & save → activate remote using plaintext
	 * - If no license_key provided and not removing → keep existing key and revalidate/activate with existing key
	 */
	public function handle_save_license() : void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $this->option_prefix . '_save_license' ) ) { wp_die( 'Nope.' ); }

		$stored_enc = get_option( $this->option_prefix . '_license_key', '' );
		$key_old_plain = $stored_enc ? $this->decrypt_str( $stored_enc ) : '';

		$key_new_raw = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		$remove = isset( $_POST['remove_license'] ) && $_POST['remove_license'] == '1';

		// If user asked to remove - deactivate remote and delete
		if ( $remove && $key_old_plain ) {
			$this->deactivate_license_remote( $key_old_plain );
			delete_option( $this->option_prefix . '_license_key' );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->option_prefix . '_license' ) );
			exit;
		}

		// If a new key was provided — store encrypted and activate with plaintext
		if ( $key_new_raw ) {
			$enc = $this->encrypt_str( $key_new_raw );
			update_option( $this->option_prefix . '_license_key', $enc, false );
			$this->activate_license_remote( $key_new_raw );
			wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->option_prefix . '_license' ) );
			exit;
		}

		// No new key & not removing: if we have an existing key, re-run activation/validation using it
		if ( $key_old_plain ) {
			$this->activate_license_remote( $key_old_plain ); // will validate/revalidate
		}
		// If no key_old and no new key — nothing to do.

		wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->option_prefix . '_license' ) );
		exit;
	}

	/* ===================== Remote calls ===================== */
	private function remote_post( string $endpoint, array $body ) {
		$args = [
			'timeout' => 15,
			'headers' => [ 'Accept' => 'application/json' ],
			'body'    => $body,
			'user-agent' => 'SignatureMedia-Client/' . $this->product . ' (' . home_url() . ')',
		];
		$url = trailingslashit( $this->api_base ) . ltrim( $endpoint, '/' );
		$res = wp_remote_post( $url, $args );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		return ( $code >= 200 && $code < 300 ) ? $data : new \WP_Error( 'http', 'HTTP ' . $code . ' ' . $raw );
	}

	private function remote_get( string $endpoint, array $query ) {
		$args = [
			'timeout' => 15,
			'headers' => [ 'Accept' => 'application/json' ],
			'user-agent' => 'SignatureMedia-Client/' . $this->product . ' (' . home_url() . ')',
		];
		$url = add_query_arg( $query, trailingslashit( $this->api_base ) . ltrim( $endpoint, '/' ) );
		$res = wp_remote_get( $url, $args );
		if ( is_wp_error( $res ) ) { return $res; }
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		return ( $code >= 200 && $code < 300 ) ? $data : new \WP_Error( 'http', 'HTTP ' . $code . ' ' . $raw );
	}

	public function activate_license_remote( string $key = '' ) : void {
		$key = $key ?: $this->get_stored_license_plain();
		if ( ! $key ) { return; }
		$data = $this->remote_post( 'activate', [
			'license_key' => $key,
			'product'     => $this->product,
			'site'        => $this->normalized_home(),
		] );
		$this->store_status_from_response( $data );
	}

	public function deactivate_license_remote( string $key = '' ) : void {
		$key = $key ?: $this->get_stored_license_plain();
		if ( ! $key ) { return; }
		$site = $this->normalized_home();
		$mac  = hash_hmac( 'sha256', $key . '|' . $site . '|deactivate', $key );

		$this->remote_post( 'deactivate', [
			'license_key' => $key,
			'product'     => $this->product,
			'site'        => $site,
			'auth'        => $mac,
		] );
		delete_option( $this->option_prefix . '_license_status' );
		update_option( $this->option_prefix . '_last_check', current_time( 'mysql' ), false );
		delete_option( $this->option_prefix . '_last_error' );
	}

	public function schedule_validate() : void {
		$key = $this->get_stored_license_plain();
		if ( ! $key ) { return; }
		$data = $this->remote_post( 'validate', [
			'license_key' => $key,
			'product'     => $this->product,
			'site'        => $this->normalized_home(),
			'version'     => $this->get_plugin_version(),
		] );
		$this->store_status_from_response( $data );
	}

	private function store_status_from_response( $data ) : void {
		if ( is_wp_error( $data ) || empty( $data ) ) {
			update_option( $this->option_prefix . '_last_error', is_wp_error( $data ) ? $data->get_error_message() : 'empty_response', false );
			update_option( $this->option_prefix . '_last_check', current_time( 'mysql' ), false );
			return;
		}
		update_option( $this->option_prefix . '_license_status', [
			'valid'      => ! empty( $data['valid'] ),
			'reason'     => $data['reason'] ?? 'ok',
			'plan'       => $data['plan'] ?? '',
			'expires_at' => $data['expires_at'] ?? '',
			'sites_used' => isset( $data['sites_used'] ) ? (int) $data['sites_used'] : 0,
			'max_sites'  => isset( $data['max_sites'] ) ? (int) $data['max_sites'] : 0,
		], false );
		update_option( $this->option_prefix . '_last_check', current_time( 'mysql' ), false );
		delete_option( $this->option_prefix . '_last_error' );
	}

	public function is_valid() : bool {
		$stat = get_option( $this->option_prefix . '_license_status', [] );
		return ! empty( $stat['valid'] );
	}

	private function get_plugin_version() : string {
		$plugin_data = get_file_data( $this->plugin_file, [ 'Version' => 'Version' ] );
		return isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '0.0.0';
	}

	/* ===================== Helpers ===================== */

	/**
	 * Return plaintext license key from stored option, or empty string
	 */
	private function get_stored_license_plain() : string {
		$stored_enc = get_option( $this->option_prefix . '_license_key', '' );
		return $stored_enc ? $this->decrypt_str( $stored_enc ) : '';
	}

	/**
	 * Derive encryption key from WP salts (AUTH_KEY etc). We make a SHA-256 binary key (32 bytes).
	 */
	private function crypto_key() : string {
		// wp_salt('auth') is a stable salt from wp-config.php
		$base = wp_salt( 'auth' );
		// Prefix with plugin-specific string to avoid reuse across plugins
		return hash( 'sha256', 'sm_license:' . $base, true ); // raw binary 32 bytes
	}

	/**
	 * Encrypt a string for storage. Prefers libsodium if available, falls back to AES-256-GCM with OpenSSL.
	 * Stored format prefixes:
	 *  - 's:' -> sodium (nonce + ciphertext, base64)
	 *  - 'o:' -> openssl (iv + tag + ciphertext, base64)
	 */
	private function encrypt_str( string $plain ) : string {
		if ( $plain === '' ) return '';
		$key = $this->crypto_key();

		// libsodium secretbox
		if ( function_exists( 'sodium_crypto_secretbox' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciph  = sodium_crypto_secretbox( $plain, $nonce, $key );
			return 's:' . base64_encode( $nonce . $ciph );
		}

		// fallback to AES-256-GCM
		$iv = random_bytes( 12 ); // 12 bytes recommended for GCM
		$tag = '';
		$ciph = openssl_encrypt( $plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
		// store iv (12) + tag (16) + ciphertext
		return 'o:' . base64_encode( $iv . $tag . $ciph );
	}

	/**
	 * Decrypt a stored blob created by encrypt_str()
	 */
	private function decrypt_str( string $blob ) : string {
		if ( $blob === '' ) return '';
		$key = $this->crypto_key();

		// sodium format
		if ( 0 === strpos( $blob, 's:' ) && function_exists( 'sodium_crypto_secretbox_open' ) && defined( 'SODIUM_CRYPTO_SECRETBOX_NONCEBYTES' ) ) {
			$raw = base64_decode( substr( $blob, 2 ), true );
			if ( $raw === false || strlen( $raw ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) return '';
			$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciph  = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain = sodium_crypto_secretbox_open( $ciph, $nonce, $key );
			return $plain === false ? '' : $plain;
		}

		// openssl format
		if ( 0 === strpos( $blob, 'o:' ) ) {
			$raw = base64_decode( substr( $blob, 2 ), true );
			if ( $raw === false || strlen( $raw ) < 12 + 16 ) return '';
			$iv  = substr( $raw, 0, 12 );
			$tag = substr( $raw, 12, 16 );
			$ciph= substr( $raw, 28 );
			$plain = openssl_decrypt( $ciph, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );
			return $plain === false ? '' : $plain;
		}

		// unknown format
		return '';
	}

	private function normalized_home() : string {
		$u = home_url();
		$parts = wp_parse_url( strtolower( $u ) );
		if ( empty( $parts['host'] ) ) {
			return untrailingslashit( strtolower( $u ) );
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] : 'https';
		$host   = preg_replace('/^www\./', '', $parts['host']);
		$path   = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';
		return $scheme . '://' . $host . $path;
	}
}
endif;
