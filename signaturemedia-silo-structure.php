<?php
/*
Plugin Name: Signature Media Silo Structure
Description: Enhanced silo content structure with toggleable features and additional content types.
Version: 2.0.4
Author: signaturemedia
Author URI: https://signaturemedia.com/
Text Domain: signaturemedia-silo-structure
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * =========================================================
 * Constants
 * =========================================================
 */
define( 'SIGNATUREMEDIA_SILO_PATH', plugin_dir_path( __FILE__ ) );        // Absolute path to plugin folder.
define( 'SIGNATUREMEDIA_SILO_URL', plugin_dir_url( __FILE__ ) );          // URL to plugin folder.
define( 'SIGNATUREMEDIA_SILO_BASENAME', plugin_basename( __FILE__ ) );    // Plugin basename (for hooks, etc.).

/**
 * GitHub updater settings (override in wp-config.php if you want):
 *
 * define('SM_SILO_GH_USER', 'your-user');
 * define('SM_SILO_GH_REPO', 'your-repo');
 * define('SM_SILO_GH_BRANCH', 'main');             // or 'master'
 * define('SM_SILO_GH_TOKEN', 'ghp_xxx');           // optional, for private or to avoid rate limits
 */
if ( ! defined( 'SM_SILO_GH_USER' ) )   define( 'SM_SILO_GH_USER',   'payche011' );
if ( ! defined( 'SM_SILO_GH_REPO' ) )   define( 'SM_SILO_GH_REPO',   'signaturemedia-silo-structure' );
if ( ! defined( 'SM_SILO_GH_BRANCH' ) ) define( 'SM_SILO_GH_BRANCH', 'main' );

/**
 * =========================================================
 * Includes
 * =========================================================
 */
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-post-types.php';      // Registers CPTs
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-taxonomies.php';      // Registers custom taxonomies (service_category)
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-rewrite.php';         // Handles custom rewrite rules and URL structure
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-admin.php';           // Admin pages and backend UI
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-query.php';           // Adjusts WP_Query behavior for silo archives
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-silo-archive-cpt.php';// Shadow CPT for archive editor
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php'; // ACF integration (location rule + admin links)

// Licensing client
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-mws-license-client.php';
$mws_license = new MWS_License_Client( [
	'product'       => 'signaturemedia-silo-structure', // must match product_slug on your license server
	'api_base'      => 'https://licenses.signaturemedia.com/wp-json/mws/v1',
	'option_prefix' => 'signaturemedia-silo-structure',
	'plugin_file'   => __FILE__,
	// 'updates'    => 'server', // <— enable if you later want updates from your license server instead of GitHub
] );
$mws_license->init();

/**
 * =========================================================
 * GitHub auto-updates (Plugin Update Checker)
 * - Uses GitHub Releases; supports release assets; optional token.
 * - Runs in wp-admin only; gated by valid license.
 * =========================================================
 */
add_action( 'plugins_loaded', function() use ( $mws_license ) {
	if ( ! is_admin() ) {
		return;
	}

	// Gate by license (optional): comment the next 3 lines if you want updates even when license is invalid.
	if ( method_exists( $mws_license, 'is_valid' ) && ! $mws_license->is_valid() ) {
		return;
	}

	$lib = SIGNATUREMEDIA_SILO_PATH . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( ! file_exists( $lib ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'Signature Media Silo Structure: Plugin Update Checker library is missing. Put it in lib/plugin-update-checker/.', 'signaturemedia-silo-structure' )
				. '</p></div>';
		} );
		return;
	}
	require_once $lib;

	$repo_url = sprintf( 'https://github.com/%s/%s/', SM_SILO_GH_USER, SM_SILO_GH_REPO );

	// Build update checker (v5 preferred; fallback to v4 if needed)
	if ( class_exists( 'Puc_v5_Factory' ) ) {
		$checker = Puc_v5_Factory::buildUpdateChecker(
			$repo_url,
			__FILE__,
			'signaturemedia-silo-structure'
		);
	} else {
		$checker = Puc_v4_Factory::buildUpdateChecker(
			$repo_url,
			__FILE__,
			'signaturemedia-silo-structure'
		);
	}

	// Use your default branch (main/master)
	if ( method_exists( $checker, 'setBranch' ) ) {
		$checker->setBranch( SM_SILO_GH_BRANCH );
	}

	// Prefer Release Assets (ZIP you attach to the release) to keep folder name correct
	if ( method_exists( $checker, 'getVcsApi' ) ) {
		$vcs = $checker->getVcsApi();
		if ( $vcs && method_exists( $vcs, 'enableReleaseAssets' ) ) {
			$vcs->enableReleaseAssets();
		}
	}

	// Optional: auth for private repos or to avoid rate-limits
	if ( defined( 'SM_SILO_GH_TOKEN' ) && SM_SILO_GH_TOKEN ) {
		if ( method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( SM_SILO_GH_TOKEN );
		}
	}
}, 20 );

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

		// Shadow CPT bootstrap (if function exists in included file)
		if ( function_exists( '\\SignatureMedia\\SiloArchive\\bootstrap' ) ) {
			\SignatureMedia\SiloArchive\bootstrap();
		}

		// Admin-only features
		if ( is_admin() ) {
			if ( class_exists( 'SignatureMedia_Silo_Admin' ) ) {
				new SignatureMedia_Silo_Admin();
			}

			// Defensive include in case paths change
			if ( ! class_exists( 'SignatureMedia_Silo_Admin_ACF_Links' ) || ! class_exists( 'SignatureMedia_Silo_ACF_Location_Rule' ) ) {
				$acf_integration = SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';
				if ( file_exists( $acf_integration ) ) {
					require_once $acf_integration;
				}
			}

			// Instantiate optional ACF helpers only if available
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
	public static function activate() : void {
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
	public static function deactivate() : void {
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
