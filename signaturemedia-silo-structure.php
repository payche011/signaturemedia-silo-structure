<?php
/*
Plugin Name: Signature Media Silo Structure
Description: Enhanced silo content structure with toggleable features and additional content types.
Version: 2.0.4
Author: signaturemedia
Author URI: https://signaturemedia.com/
Text Domain: signaturemedia-silo-structure
*/

// Security: Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * =========================================================
 * Constants
 * =========================================================
 */
define( 'SIGNATUREMEDIA_SILO_PATH', plugin_dir_path( __FILE__ ) );        // Absolute path to plugin folder.
define( 'SIGNATUREMEDIA_SILO_URL', plugin_dir_url( __FILE__ ) );          // URL to plugin folder.
define( 'SIGNATUREMEDIA_SILO_BASENAME', plugin_basename( __FILE__ ) );    // Plugin basename (for hooks, etc.).

/**
 * GitHub updater settings — set these to enable auto-updates from GitHub Releases.
 * - Make a release on GitHub with a ZIP asset whose top-level folder matches this plugin folder.
 * - Example ZIP structure: signaturemedia-silo-structure/ (this file + includes/)
 */

define( 'SM_SILO_GH_USER', 'payche011' );
define( 'SM_SILO_GH_REPO', 'signaturemedia-silo-structure' );
/**
 * =========================================================
 * Includes
 * =========================================================
 */
// Core structure classes
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-post-types.php';      // Registers CPTs
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-taxonomies.php';      // Registers custom taxonomies (service_category)
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-rewrite.php';         // Handles custom rewrite rules and URL structure
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-admin.php';           // Admin menu pages and backend UI
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-query.php';           // Adjusts WP_Query behavior for silo archives
// Silo Archive CPT (shadow editor za Rank Math + ACF)
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-silo-archive-cpt.php';
// ACF integration (contains SignatureMedia_Silo_ACF_Location_Rule and SignatureMedia_Silo_Admin_ACF_Links)
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';

// Licensing client (server-based updates supported; we also add GitHub updater below)
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-mws-license-client.php';

$mws_license = new MWS_License_Client( [
	'product'       => 'signaturemedia-silo-structure', // = Product Slug na serveru
	'api_base'      => 'https://licenses.signaturemedia.com/wp-json/mws/v1',
	'option_prefix' => 'signaturemedia-silo-structure',
	'plugin_file'   => __FILE__,
] );
$mws_license->init();


add_action( 'plugins_loaded', function() use ( $mws_license ) {
	if ( ! is_admin() || empty( SM_SILO_GH_USER ) || empty( SM_SILO_GH_REPO ) ) return;

	// (Opcija) Gati update dok licenca nije validna – ukloni if želiš update bez licence.
	if ( method_exists( $mws_license, 'is_valid' ) && ! $mws_license->is_valid() ) return;

	$up = new SignatureMedia_GH_Updater( __FILE__, SM_SILO_GH_USER, SM_SILO_GH_REPO );
	$up->init();
} );


/**
 * =========================================================
 * Main plugin bootstrap
 * =========================================================
 */
class SignatureMedia_Silo_Structure {

	public function __construct() {
		// Core frontend/backend classes
		new SignatureMedia_Silo_Post_Types(); // Register custom post types
		new SignatureMedia_Silo_Taxonomies(); // Register custom taxonomies
		new SignatureMedia_Silo_Rewrite();    // Add custom rewrite rules
		new SignatureMedia_Silo_Query();      // Adjust queries for silo archives

		// >>> DODAJ OVO: pokreni shadow CPT <<<
		if ( function_exists( '\\SignatureMedia\\SiloArchive\\bootstrap' ) ) {
			\SignatureMedia\SiloArchive\bootstrap();
		}

		// Admin-only features
		if ( is_admin() ) {
			// Admin menu & pages
			if ( class_exists( 'SignatureMedia_Silo_Admin' ) ) {
				new SignatureMedia_Silo_Admin();
			}

			// Defensive: make sure ACF integration file is loaded (in case path changed)
			if ( ! class_exists( 'SignatureMedia_Silo_Admin_ACF_Links' ) || ! class_exists( 'SignatureMedia_Silo_ACF_Location_Rule' ) ) {
				$acf_integration = SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';
				if ( file_exists( $acf_integration ) ) {
					require_once $acf_integration;
				}
			}

			// Instantiate optional ACF admin helpers only if available
			if ( class_exists( 'SignatureMedia_Silo_Admin_ACF_Links' ) && ( function_exists( 'acf' ) || class_exists( 'ACF' ) ) ) {
				new SignatureMedia_Silo_Admin_ACF_Links();
			}

			if ( class_exists( 'SignatureMedia_Silo_ACF_Location_Rule' ) ) {
				new SignatureMedia_Silo_ACF_Location_Rule();
			}
		}
	}

	/**
	 * Runs on plugin activation
	 * - Registers post types & taxonomies
	 * - Flushes rewrite rules so pretty permalinks work immediately
	 * - Sets a default posts_per_page value to match template expectations
	 */
	public static function activate() {
		if ( class_exists( 'SignatureMedia_Silo_Post_Types' ) ) {
			( new SignatureMedia_Silo_Post_Types() )->register();
		}
		if ( class_exists( 'SignatureMedia_Silo_Taxonomies' ) ) {
			( new SignatureMedia_Silo_Taxonomies() )->register();
		}
		flush_rewrite_rules();

		// Optional: align with theme queries for silo archives
		if ( ! get_option( 'posts_per_page' ) || (int) get_option( 'posts_per_page' ) !== 9 ) {
			update_option( 'posts_per_page', 9 );
		}
	}

	/**
	 * Runs on plugin deactivation
	 * - Flushes rewrite rules to clean up
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}
}

/**
 * =========================================================
 * Bootstrap & Hooks
 * =========================================================
 */
new SignatureMedia_Silo_Structure();

register_activation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'deactivate' ] );

/**
 * =========================================================
 * Lightweight GitHub Releases Updater (public repo)
 * - Single-file, cached, works alongside your license client.
 * - Only runs in wp-admin; optionally gated by license validity.
 * =========================================================
 */
if ( ! class_exists( 'SignatureMedia_GH_Updater' ) ) :
class SignatureMedia_GH_Updater {
	private $plugin_file;
	private $plugin_basename;
	private $slug;
	private $repo_user;
	private $repo_name;

	public function __construct( string $plugin_file, string $repo_user, string $repo_name ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = dirname( $this->plugin_basename ); // plugin folder name
		$this->repo_user       = $repo_user;
		$this->repo_name       = $repo_name;
	}

	public function init() : void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
	}

	private function current_version() : string {
		$data = get_file_data( $this->plugin_file, [ 'Version' => 'Version' ] );
		return isset( $data['Version'] ) ? (string) $data['Version'] : '0.0.0';
	}

	public function inject_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) {
			return $transient;
		}

		// If repo not configured, bail early (keeps plugin lean until you set constants).
		if ( empty( $this->repo_user ) || empty( $this->repo_name ) ) {
			return $transient;
		}

		$current   = $this->current_version();
		$cache_key = 'sm_gh_upd_' . md5( $this->repo_user . '/' . $this->repo_name . '|' . $current );
		$resp      = get_transient( $cache_key );

		if ( false === $resp ) {
			$url  = "https://api.github.com/repos/{$this->repo_user}/{$this->repo_name}/releases/latest";
			$resp = wp_remote_get( $url, [
				'timeout' => 12,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress; ' . home_url(),
				],
			] );
			set_transient( $cache_key, $resp, 30 * MINUTE_IN_SECONDS );
		}

		if ( is_wp_error( $resp ) ) {
			return $transient;
		}
		if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) {
			return $transient;
		}

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$tag  = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'v' ) : null;
		if ( ! $tag || ! version_compare( $tag, $current, '>' ) ) {
			return $transient;
		}

		// Prefer a proper asset ZIP (top-level folder must match the plugin folder name).
		$zip = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $a ) {
				$u = isset( $a['browser_download_url'] ) ? (string) $a['browser_download_url'] : '';
				if ( $u && preg_match( '/\.zip$/i', $u ) ) {
					$zip = $u;
					break;
				}
			}
		}
		// Fallback to GitHub source zip (works, but folder name is auto-generated).
		if ( ! $zip && ! empty( $data['zipball_url'] ) ) {
			$zip = (string) $data['zipball_url'];
		}
		if ( ! $zip ) {
			return $transient;
		}

		$update = (object) [
			'slug'        => $this->slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $tag,
			'url'         => "https://github.com/{$this->repo_user}/{$this->repo_name}/releases/latest",
			'package'     => $zip,
		];
		$transient->response[ $this->plugin_basename ] = $update;
		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$out = new stdClass();
		$out->name     = $this->repo_name ?: 'Signature Media Silo Structure';
		$out->slug     = $this->slug;
		$out->version  = $this->current_version();
		$out->external = true;
		$out->homepage = ( ! empty( $this->repo_user ) && ! empty( $this->repo_name ) )
			? "https://github.com/{$this->repo_user}/{$this->repo_name}"
			: 'https://signaturemedia.com/';
		$out->sections = [
			'description' => '<p>Signature Media Silo Structure.</p>',
			'changelog'   => '<p>See latest release notes on GitHub.</p>',
		];
		return $out;
	}
}
endif;

// Initialize GitHub updater in admin only, optionally gated by license validity.
add_action( 'plugins_loaded', function() use ( $mws_license ) {
	// Only run in admin; skip if GitHub coordinates are not set.
	if ( ! is_admin() || empty( SM_SILO_GH_USER ) || empty( SM_SILO_GH_REPO ) ) {
		return;
	}

	// Optional gating: only allow updates if the license is valid.
	if ( method_exists( $mws_license, 'is_valid' ) && ! $mws_license->is_valid() ) {
		return;
	}

	$updater = new SignatureMedia_GH_Updater( __FILE__, SM_SILO_GH_USER, SM_SILO_GH_REPO );
	$updater->init();
} );

if ( ! class_exists( 'SignatureMedia_GH_Updater' ) ) :
class SignatureMedia_GH_Updater {
	private $plugin_file, $plugin_basename, $slug, $repo_user, $repo_name;

	public function __construct( string $plugin_file, string $repo_user, string $repo_name ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = dirname( $this->plugin_basename ); // = ime foldera plugina
		$this->repo_user       = $repo_user;
		$this->repo_name       = $repo_name;
	}

	public function init() : void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );

		// (Opcija) auto-update samo za ovaj plugin.
		if ( defined( 'SM_SILO_FORCE_AUTOUPDATE' ) && SM_SILO_FORCE_AUTOUPDATE ) {
			add_filter( 'auto_update_plugin', function( $update, $item ) {
				return ( isset( $item->plugin ) && $item->plugin === $this->plugin_basename ) ? true : $update;
			}, 10, 2 );
		}
	}

	private function current_version() : string {
		$data = get_file_data( $this->plugin_file, [ 'Version' => 'Version' ] );
		return isset( $data['Version'] ) ? (string) $data['Version'] : '0.0.0';
	}

	public function inject_update( $transient ) {
		if ( empty( $transient ) || ! is_object( $transient ) ) return $transient;
		if ( empty( $this->repo_user ) || empty( $this->repo_name ) ) return $transient;

		$current   = $this->current_version();
		$cache_key = 'sm_gh_upd_' . md5( $this->repo_user . '/' . $this->repo_name . '|' . $current );
		$resp      = get_transient( $cache_key );

		if ( false === $resp ) {
			$url  = "https://api.github.com/repos/{$this->repo_user}/{$this->repo_name}/releases/latest";
			$resp = wp_remote_get( $url, [
				'timeout' => 12,
				'headers' => [
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'WordPress; ' . home_url(),
				],
			] );
			set_transient( $cache_key, $resp, 30 * MINUTE_IN_SECONDS );
		}

		if ( is_wp_error( $resp ) ) return $transient;
		if ( (int) wp_remote_retrieve_response_code( $resp ) !== 200 ) return $transient;

		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		$tag  = isset( $data['tag_name'] ) ? ltrim( (string) $data['tag_name'], 'v' ) : null;
		if ( ! $tag || ! version_compare( $tag, $current, '>' ) ) return $transient;

		// Traži asset .zip (preporučeno)
		$zip = '';
		if ( ! empty( $data['assets'] ) ) {
			foreach ( $data['assets'] as $a ) {
				$u = $a['browser_download_url'] ?? '';
				if ( $u && preg_match( '/\.zip$/i', $u ) ) { $zip = $u; break; }
			}
		}
		// Fallback: GitHub source zip (radi, ali folder ime je generičko)
		if ( ! $zip && ! empty( $data['zipball_url'] ) ) $zip = (string) $data['zipball_url'];
		if ( ! $zip ) return $transient;

		$transient->response[ $this->plugin_basename ] = (object) [
			'slug'        => $this->slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $tag,
			'url'         => "https://github.com/{$this->repo_user}/{$this->repo_name}/releases/latest",
			'package'     => $zip,
		];
		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $this->slug ) return $result;

		$out = new stdClass();
		$out->name     = $this->repo_name ?: 'Signature Media Silo Structure';
		$out->slug     = $this->slug;
		$out->version  = $this->current_version();
		$out->external = true;
		$out->homepage = ( $this->repo_user && $this->repo_name ) ? "https://github.com/{$this->repo_user}/{$this->repo_name}" : home_url();
		$out->sections = [
			'description' => '<p>Signature Media Silo Structure.</p>',
			'changelog'   => '<p>See latest release notes on GitHub.</p>',
		];
		return $out;
	}
}
endif;
