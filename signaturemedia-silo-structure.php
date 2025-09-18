<?php
/*
Plugin Name: Signature Media Silo Structure
Description: Enhanced silo content structure with toggleable features and additional content types.
Version: 2.3
Author: signaturemedia
Author URI: https://signaturemedia.com/
Text Domain: signaturemedia-silo-structure
Update URI: https://github.com/payche011/signaturemedia-silo-structure
*/

if ( ! defined( 'ABSPATH' ) ) exit;

/** Paths */
define( 'SIGNATUREMEDIA_SILO_PATH', plugin_dir_path( __FILE__ ) );
define( 'SIGNATUREMEDIA_SILO_URL',  plugin_dir_url( __FILE__ ) );
define( 'SIGNATUREMEDIA_SILO_BASENAME', plugin_basename( __FILE__ ) );

/** Core includes */
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-post-types.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-taxonomies.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-rewrite.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-admin.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-query.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-silo-archive-cpt.php';
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-acf-integration.php';

/** License — only handles status/activation; does not block updates */
require_once SIGNATUREMEDIA_SILO_PATH . 'includes/class-signaturemedia-license-client.php';
$sm_license = new SignatureMedia_License_Client([
  'product'       => 'signaturemedia-silo-structure',
  'api_base'      => 'https://licenses.signaturemedia.com/wp-json/signaturemedia/v1',
  'option_prefix' => 'sm_silo',
  'plugin_file'   => __FILE__,
  // 'updates'     => 'server', // keep disabled; you use GitHub updates via PUC
]);
$sm_license->init();

/** Main class */
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
      if ( class_exists('SignatureMedia_Silo_Admin') ) { new SignatureMedia_Silo_Admin(); }
      if ( class_exists('SignatureMedia_Silo_Admin_ACF_Links') && ( function_exists('acf') || class_exists('ACF') ) ) {
        new SignatureMedia_Silo_Admin_ACF_Links();
      }
      if ( class_exists('SignatureMedia_Silo_ACF_Location_Rule') ) {
        new SignatureMedia_Silo_ACF_Location_Rule();
      }
    }
  }
  public static function activate() {
    if ( class_exists('SignatureMedia_Silo_Post_Types') ) ( new SignatureMedia_Silo_Post_Types() )->register();
    if ( class_exists('SignatureMedia_Silo_Taxonomies') ) ( new SignatureMedia_Silo_Taxonomies() )->register();
    flush_rewrite_rules();
    if ( ! get_option('posts_per_page') || (int)get_option('posts_per_page') !== 9 ) update_option('posts_per_page', 9);
  }
  public static function deactivate() { flush_rewrite_rules(); }
}
new SignatureMedia_Silo_Structure();
register_activation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'SignatureMedia_Silo_Structure', 'deactivate' ] );

/**
 * === GitHub auto-updates via Plugin Update Checker (PUC) ===
 * 1) The repository and server must have the folder:
 * signaturemedia-silo-structure/
 * 2) Add the library to the repo at: lib/plugin-update-checker/
 * 3) On GitHub, publish a Release with a tag (e.g. v2.0.3) and attach a ZIP with THIS folder at the root.
 * 4) (optional) For private/rate-limit: add define('SM_SILO_GH_TOKEN','ghp_...') to wp-config.php.
 */
add_action('plugins_loaded', function () {
  if ( ! is_admin() ) return;

  $puc_bootstrap = __DIR__ . '/lib/plugin-update-checker/plugin-update-checker.php';
  if ( ! file_exists( $puc_bootstrap ) ) {
    add_action('admin_notices', function () {
      echo '<div class="notice notice-warning"><p><strong>Signature Media Silo Structure:</strong> Missing PUC library at <code>lib/plugin-update-checker/</code>. GitHub updates are disabled.</p></div>';
    });
    return;
  }

  require_once $puc_bootstrap;

  $checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/payche011/signaturemedia-silo-structure',
    __FILE__,
    'signaturemedia-silo-structure'
  );

  $checker->setBranch('main');                 // change if using a different default branch
  $checker->getVcsApi()->enableReleaseAssets(); // expects ZIP in Release (top-level folder = plugin folder)

  if ( defined('SM_SILO_GH_TOKEN') && SM_SILO_GH_TOKEN ) {
    $checker->setAuthentication( SM_SILO_GH_TOKEN );
  }
});