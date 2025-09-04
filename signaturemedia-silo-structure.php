<?php
/*
Plugin Name: Signature Media Silo Structure
Description: Enhanced silo content structure with toggleable features and additional content types.
Version: 2.0.6
Author: signaturemedia
Author URI: https://signaturemedia.com/
Text Domain: signaturemedia-silo-structure
Update URI: https://github.com/payche011/signaturemedia-silo-structure
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Paths & constants
 */
define( 'SIGNATUREMEDIA_SILO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIGNATUREMEDIA_SILO_URL', plugin_dir_url( __FILE__ ) );
define( 'SIGNATUREMEDIA_SILO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Core includes
 */
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-post-types.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-taxonomies.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-rewrite.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-admin.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-query.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-silo-archive-cpt.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';

/**
 * License client (ostaje)
 */
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-mws-license-client.php';
$mws_license = new MWS_License_Client([
  'product'       => 'signaturemedia-silo-structure',
  'api_base'      => 'https://licenses.signaturemedia.com/wp-json/mws/v1',
  'option_prefix' => 'signaturemedia-silo-structure',
  'plugin_file'   => __FILE__,
  // 'updates'     => 'server', // <- ako želiš da update ide preko tvog license servera umesto GitHub-a
]);
$mws_license->init();

/**
 * Main bootstrap
 */
class SignatureMedia_Silo_Structure {
  public function __construct() {
    new SignatureMedia_Silo_Post_Types();
    new SignatureMedia_Silo_Taxonomies();
    new SignatureMedia_Silo_Rewrite();
    new SignatureMedia_Silo_Query();

    if ( function_exists('\\SignatureMedia\\SiloArchive\\bootstrap') ) {
      \SignatureMedia\SiloArchive\bootstrap();
    }

    if ( is_admin() ) {
      if ( class_exists( 'SignatureMedia_Silo_Admin' ) ) {
        new SignatureMedia_Silo_Admin();
      }
      if ( ! class_exists( 'SignatureMedia_Silo_Admin_ACF_Links' ) || ! class_exists( 'SignatureMedia_Silo_ACF_Location_Rule' ) ) {
        $acf_integration = SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';
        if ( file_exists( $acf_integration ) ) {
          require_once $acf_integration;
        }
      }
      if ( class_exists( 'SignatureMedia_Silo_Admin_ACF_Links' ) && ( function_exists( 'acf' ) || class_exists( 'ACF' ) ) ) {
        new SignatureMedia_Silo_Admin_ACF_Links();
      }
      if ( class_exists( 'SignatureMedia_Silo_ACF_Location_Rule' ) ) {
        new SignatureMedia_Silo_ACF_Location_Rule();
      }
    }
  }

  public static function activate() {
    if ( class_exists( 'SignatureMedia_Silo_Post_Types' ) ) {
      ( new SignatureMedia_Silo_Post_Types() )->register();
    }
    if ( class_exists( 'SignatureMedia_Silo_Taxonomies' ) ) {
      ( new SignatureMedia_Silo_Taxonomies() )->register();
    }
    flush_rewrite_rules();

    if ( ! get_option( 'posts_per_page' ) || (int) get_option( 'posts_per_page' ) !== 9 ) {
      update_option( 'posts_per_page', 9 );
    }
  }

  public static function deactivate() {
    flush_rewrite_rules();
  }
}

new SignatureMedia_Silo_Structure();
register_activation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'deactivate' ] );

/**
 * === GitHub auto-updates preko Plugin Update Checker-a (PUC) ===
 * 1) Ubaci biblioteku u: /lib/plugin-update-checker/
 * 2) Kreiraj GitHub Release sa tagom većim od Version u headeru (npr. v2.0.7)
 * 3) (preporučeno) attach-uj ZIP sa top-level folderom "signaturemedia-silo-structure/"
 */
add_action( 'plugins_loaded', function () {
  if ( ! is_admin() ) { return; }

  $bootstrap = __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
  if ( ! file_exists( $bootstrap ) ) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-warning"><p><strong>Signature Media Silo Structure:</strong> Missing PUC library at <code>lib/plugin-update-checker/</code>. Updates from GitHub are disabled.</p></div>';
    });
    return;
  }

  require_once $bootstrap;

  $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/payche011/signaturemedia-silo-structure',
    __FILE__,
    'signaturemedia-silo-structure'
  );

  // Branch (promeni u 'master' ako ti je drugačiji)
  $checker->setBranch('main');

  // Ako koristiš Release asset ZIP (preporučeno), ostavi uključeno:
  // Ako NEMAŠ attach-ovan ZIP u Release-u, ovaj red slobodno komentariši.
  $checker->getVcsApi()->enableReleaseAssets();

  // Personal access token (opciono: rate-limit / private repo)
  if ( defined('SM_SILO_GH_TOKEN') && SM_SILO_GH_TOKEN ) {
    $checker->setAuthentication( SM_SILO_GH_TOKEN );
  }

  // DEBUG: lakše dijagnostikovanje
  $GLOBALS['sm_puc'] = $checker;
});
