<?php
/**
 * Signature Media License Client — drop-in class for client-side licensing in a WordPress plugin.
 *
 * Usage in your main plugin file:
 *
 * require_once __DIR__ . '/includes/class-signaturemedia-license-client.php';
 * $sm_license = new SignatureMedia_License_Client([
 *   'product'       => 'signaturemedia-silo-structure', // fixed slug on the license server
 *   'api_base'      => 'https://licenses.signaturemedia.com/wp-json/signaturemedia/v1',
 *   'option_prefix' => 'sm_silo',        // unique option prefix for this plugin
 *   'plugin_file'   => __FILE__,         // absolute path to the main plugin file
 *   // 'updates'     => 'server',         // OPTIONAL (default 'none'); you use GitHub so keep disabled
 * ]);
 * $sm_license->init();
 */

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
		$this->slug            = dirname( $this->plugin_basename ); // e.g. signaturemedia-silo-structure
		$this->updates_mode    = isset( $args['updates'] ) ? ( $args['updates'] === 'server' ? 'server' : 'none' ) : 'none';
	}

	public function init() : void {
		// Settings
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_post_' . $this->option_prefix . '_save_license', [ $this, 'handle_save_license' ] );

		// Link in Plugins list
		add_filter( 'plugin_action_links_' . $this->plugin_basename, [ $this, 'plugin_action_links' ] );

		// Periodic validation (twice daily)
		add_action( $this->option_prefix . '_validate_event', [ $this, 'schedule_validate' ] );
		if ( ! wp_next_scheduled( $this->option_prefix . '_validate_event' ) ) {
			wp_schedule_event( time() + 60, 'twicedaily', $this->option_prefix . '_validate_event' );
		}

		// OPTIONAL: Server-driven updater (disabled by default; GitHub updater is recommended)
		if ( $this->updates_mode === 'server' ) {
			add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
			add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		}

		// Deactivate hook → remote deactivation
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
		$key        = get_option( $this->option_prefix . '_license_key', '' );
		$stat       = get_option( $this->option_prefix . '_license_status', [] );
		$valid      = ! empty( $stat['valid'] );
		$last_error = get_option( $this->option_prefix . '_last_error', '-' );
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
							<input type="text" id="sm_license_key" name="license_key" class="regular-text" value="<?php echo esc_attr( $key ); ?>" />
							<p class="description">Enter the license key provided by Signature Media.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( $key ? ( $valid ? 'Revalidate' : 'Activate' ) : 'Save & Activate' ); ?>
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

	public function handle_save_license() : void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( $this->option_prefix . '_save_license' ) ) { wp_die( 'Nope.' ); }
		$key_old = get_option( $this->option_prefix . '_license_key', '' );
		$key_new = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		update_option( $this->option_prefix . '_license_key', $key_new, false );

		if ( $key_new ) {
			$this->activate_license_remote( $key_new );
		} elseif ( $key_old && ! $key_new ) {
			$this->deactivate_license_remote( $key_old );
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=' . $this->option_prefix . '_license' ) );
		exit;
	}

	/* ===================== Remote calls ===================== */
	private function remote_post( string $endpoint, array $body ) {
		$args = [
			'timeout' => 15,
			'headers' => [ 'Accept' => 'application/json' ],
			'body'    => $body, // form-encoded is fine for WP REST
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
		$key = $key ?: get_option( $this->option_prefix . '_license_key', '' );
		if ( ! $key ) { return; }
		$data = $this->remote_post( 'activate', [
			'license_key' => $key,
			'product'     => $this->product,          // server ignores, but fine to send
			'site'        => $this->normalized_home(),
		] );
		$this->store_status_from_response( $data );
	}

	public function deactivate_license_remote( string $key = '' ) : void {
		$key = $key ?: get_option( $this->option_prefix . '_license_key', '' );
		if ( ! $key ) { return; }
		$site = $this->normalized_home();
		$mac  = hash_hmac( 'sha256', $key . '|' . $site . '|deactivate', $key ); // must match server rule

		$this->remote_post( 'deactivate', [
			'license_key' => $key,
			'product'     => $this->product, // ignored by server
			'site'        => $site,
			'auth'        => $mac,
		] );
		delete_option( $this->option_prefix . '_license_status' );
		update_option( $this->option_prefix . '_last_check', current_time( 'mysql' ), false );
		delete_option( $this->option_prefix . '_last_error' );
	}

	public function schedule_validate() : void {
		$key = get_option( $this->option_prefix . '_license_key', '' );
		if ( ! $key ) { return; }
		$data = $this->remote_post( 'validate', [
			'license_key' => $key,
			'product'     => $this->product,          // ignored by server
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

	/* ===================== Server-driven Updater (optional) ===================== */
	public function inject_update( $transient ) {
		if ( $this->updates_mode !== 'server' ) { return $transient; }
		if ( empty( $transient ) || ! is_object( $transient ) ) { return $transient; }
		$key = get_option( $this->option_prefix . '_license_key', '' );
		if ( ! $key || ! $this->is_valid() ) { return $transient; }

		$current   = $this->get_plugin_version();
		$cache_key = $this->option_prefix . '_upd_' . md5( $current . '|' . $this->slug );
		$data      = get_transient( $cache_key );
		if ( false === $data ) {
			$data = $this->remote_get( 'update', [
				'product'     => $this->product,
				'slug'        => $this->slug,
				'license_key' => $key,
				'site'        => $this->normalized_home(),
				'current'     => $current,
			] );
			set_transient( $cache_key, $data, HOUR_IN_SECONDS );
		}
		if ( is_wp_error( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return $transient; // no update
		}

		if ( version_compare( $data['version'], $current, '>' ) ) {
			$update = (object) [
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $data['version'],
				'url'         => $data['details_url'] ?? '',
				'package'     => $data['download_url'],
			];
			$transient->response[ $this->plugin_basename ] = $update;
		}
		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( $this->updates_mode !== 'server' ) { return $result; }
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}
		$key  = get_option( $this->option_prefix . '_license_key', '' );
		$info = $this->remote_get( 'update', [
			'product'     => $this->product,
			'slug'        => $this->slug,
			'license_key' => $key,
			'site'        => $this->normalized_home(),
		] );
		if ( is_wp_error( $info ) ) { return $result; }

		$out = new \stdClass();
		$out->name     = ucwords( str_replace( '-', ' ', $this->product ) );
		$out->slug     = $this->slug;
		$out->version  = $info['version'] ?? $this->get_plugin_version();
		$out->external = true;
		$out->homepage = ! empty( $info['details_url'] ) ? $info['details_url'] : home_url();
		$out->sections = [
			'description' => '<p>Proprietary add-on.</p>',
			'changelog'   => '<p>See changelog: <a target="_blank" rel="noopener" href="' . esc_url( $out->homepage ) . '">details</a></p>',
		];
		return $out;
	}

	/* ===================== Helpers ===================== */
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
