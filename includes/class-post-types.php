<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SignatureMedia_Silo_Post_Types {

    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_action( 'init', [ $this, 'register_taxonomy' ], 9 ); // Register taxonomy before post types
    }

    public function register_taxonomy() {
        // Register the service_category taxonomy if it doesn't exist
        if ( ! taxonomy_exists( 'service_category' ) ) {
            register_taxonomy( 'service_category', ['silo_service', 'silo_problem', 'silo_solution'], [
                'labels' => [
                    'name' => __( 'Service Categories', 'signaturemedia-silo-structure' ),
                    'singular_name' => __( 'Service Category', 'signaturemedia-silo-structure' ),
                ],
                'public' => true,
                'hierarchical' => true,
                'show_ui' => true,
                'show_admin_column' => true,
                'query_var' => true,
                'rewrite' => false, // We handle rewriting custom
                'show_in_rest' => true,
            ]);
        }
    }

    public function register() {
        // Register Services
        register_post_type( 'silo_service', [
            'labels' => [
                'name' => __( 'Sub Services', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Sub Service', 'signaturemedia-silo-structure' ),
                'add_new_item' => __( 'Add New Sub Service', 'signaturemedia-silo-structure' ),
                'edit_item' => __( 'Edit Sub Service', 'signaturemedia-silo-structure' ),
                'view_item' => __( 'View Sub Service', 'signaturemedia-silo-structure' ),
            ],
            'public' => true,
            'has_archive' => false, // No archive needed, handled by taxonomy
            'rewrite' => false, // Disable default rewrite - we handle this completely custom
            'show_in_rest' => true,
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'supports' => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
            'menu_icon' => 'dashicons-admin-generic',
            'publicly_queryable' => true,
            'show_in_menu' => 'signature-media',
            'capability_type' => 'post',
            'hierarchical' => true,
            'taxonomies' => ['service_category'], // Associate with service_category
        ] );

        // Problems
        register_post_type( 'silo_problem', [
            'labels' => [
                'name' => __( 'Problem Signs', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Problem Sign', 'signaturemedia-silo-structure' ),
                'add_new_item' => __( 'Add New Problem Sign', 'signaturemedia-silo-structure' ),
                'edit_item' => __( 'Edit Problem Sign', 'signaturemedia-silo-structure' ),
                'archives' => __( 'Problem Signs Archive', 'signaturemedia-silo-structure' ),
            ],
            'public' => true,
            'has_archive' => false, // Custom archive handling
            'show_in_rest' => true,
            'rewrite' => false, // Custom rewrite rules
            'supports' => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon' => 'dashicons-warning',
            'publicly_queryable' => true,
            'show_in_menu' => 'signature-media',
            'capability_type' => 'post',
            'hierarchical' => true,
            'taxonomies' => ['service_category'], // Associate with service_category
        ] );

        // Solutions
        register_post_type( 'silo_solution', [
            'labels' => [
                'name' => __( 'Solutions', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Solution', 'signaturemedia-silo-structure' ),
                'add_new_item' => __( 'Add New Solution', 'signaturemedia-silo-structure' ),
                'edit_item' => __( 'Edit Solution', 'signaturemedia-silo-structure' ),
                'archives' => __( 'Solution Archives', 'signaturemedia-silo-structure' ),
            ],
            'public' => true,
            'has_archive' => false, // Custom archive handling
            'show_in_rest' => true,
            'rewrite' => false, // Custom rewrite rules
            'supports' => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon' => 'dashicons-yes',
            'publicly_queryable' => true,
            'show_in_menu' => 'signature-media',
            'capability_type' => 'post',
            'hierarchical' => true,
            'taxonomies' => ['service_category'], // Associate with service_category
        ] );

        // locations-served
        register_post_type( 'locations', [
            'labels' => [
                'name' => __( 'Locations Served', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Location', 'signaturemedia-silo-structure' ),
                'add_new_item' => __( 'Add New Location', 'signaturemedia-silo-structure' ),
                'edit_item' => __( 'Edit Location', 'signaturemedia-silo-structure' ),
                'archives' => __( 'Location Archives', 'signaturemedia-silo-structure' ),
            ],
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => true,
            'rewrite' => [ 'slug' => 'service-area', 'with_front' => false ],
            'supports' => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon' => 'dashicons-location',
            'publicly_queryable' => true,
            'show_in_menu' => 'signature-media',
            'capability_type' => 'post',
            'hierarchical' => true,
        ] );

        // Trigger rewrite flush after registration
        update_option( 'silo_rewrite_flush_needed', true );
    }
}
