<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SignatureMedia_Silo_Taxonomies {

  public function __construct() {
    add_action( 'init', [ $this, 'register' ] );
  }

  public function register() {
    register_taxonomy( 'service_category', [ 'silo_service', 'silo_problem', 'silo_solution' ], [
      'hierarchical' => true,
      'show_in_rest' => true,
      'public' => true,
      'show_ui' => true,
      'show_in_rest' => true,
      'show_in_nav_menus' => true,
      'show_tagcloud' => true,
      'show_admin_column' => true,
      'rewrite' => [
        'slug' => '', // Empty slug for top-level category archives
        'with_front' => false,
        'hierarchical' => true,
      ],
      'labels' => [
        'name' => __( 'Service Categories', 'signaturemedia-silo-structure' ),
        'singular_name' => __( 'Service Category', 'signaturemedia-silo-structure' ),
        'search_items' => __( 'Search Service Categories', 'signaturemedia-silo-structure' ),
        'all_items' => __( 'All Service Categories', 'signaturemedia-silo-structure' ),
        'edit_item' => __( 'Edit Service Category', 'signaturemedia-silo-structure' ),
        'update_item' => __( 'Update Service Category', 'signaturemedia-silo-structure' ),
        'add_new_item' => __( 'Add New Service Category', 'signaturemedia-silo-structure' ),
        'new_item_name' => __( 'New Service Category Name', 'signaturemedia-silo-structure' ),
      ],
      'public' => true,
      'show_admin_column' => true,
      'show_in_menu' => 'signature-media',
      'capabilities' => [
        'manage_terms' => 'manage_categories',
        'edit_terms' => 'manage_categories',
        'delete_terms' => 'manage_categories',
        'assign_terms' => 'edit_posts',
      ],
    ] );
  }
}