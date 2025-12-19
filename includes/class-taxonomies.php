<?php
/**
 * SignatureMedia Silo Taxonomies Class
 * Registers the service_category taxonomy for use within the silo structure.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SignatureMedia_Silo_Taxonomies' ) ) {

class SignatureMedia_Silo_Taxonomies {

  public function __construct() {
    add_action( 'init', [ $this, 'register' ], 5 );
  }

  public function register() {
    // Register the service_category taxonomy
    // Associated with our custom silo post types
    register_taxonomy( 'service_category', [ 'silo_service', 'silo_problem', 'silo_solution' ], [
      'hierarchical'      => true,
      'public'            => true,
      'show_ui'           => true,
      'show_in_rest'      => true,
      'show_in_nav_menus' => true,
      'show_tagcloud'     => true,
      'show_admin_column' => true,
      
      // We set rewrite to false here because class-rewrite.php 
      // builds all the URL logic manually to prevent page collisions.
      'rewrite'           => false, 
      
      'labels' => [
        'name'                       => __( 'Service Categories', 'signaturemedia-silo-structure' ),
        'singular_name'              => __( 'Service Category', 'signaturemedia-silo-structure' ),
        'search_items'               => __( 'Search Service Categories', 'signaturemedia-silo-structure' ),
        'all_items'                  => __( 'All Service Categories', 'signaturemedia-silo-structure' ),
        'parent_item'                => __( 'Parent Service Category', 'signaturemedia-silo-structure' ),
        'parent_item_colon'          => __( 'Parent Service Category:', 'signaturemedia-silo-structure' ),
        'edit_item'                  => __( 'Edit Service Category', 'signaturemedia-silo-structure' ),
        'update_item'                => __( 'Update Service Category', 'signaturemedia-silo-structure' ),
        'add_new_item'               => __( 'Add New Service Category', 'signaturemedia-silo-structure' ),
        'new_item_name'              => __( 'New Service Category Name', 'signaturemedia-silo-structure' ),
        'menu_name'                  => __( 'Service Categories', 'signaturemedia-silo-structure' ),
      ],
      'show_in_menu' => true,
      'capabilities' => [
        'manage_terms' => 'manage_categories',
        'edit_terms'   => 'manage_categories',
        'delete_terms' => 'manage_categories',
        'assign_terms' => 'edit_posts',
      ],
    ] );
  }
}

new SignatureMedia_Silo_Taxonomies();

}