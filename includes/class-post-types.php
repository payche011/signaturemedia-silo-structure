<?php
/**
 * SignatureMedia Silo Post Types Class
 * Registers custom post types for the silo structure.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SignatureMedia_Silo_Post_Types' ) ) {

class SignatureMedia_Silo_Post_Types {

    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
    }

    public function register() {
        // Register Sub Services
        register_post_type( 'silo_service', [
            'labels' => [
                'name'               => __( 'Sub Services', 'signaturemedia-silo-structure' ),
                'singular_name'      => __( 'Sub Service', 'signaturemedia-silo-structure' ),
                'add_new_item'       => __( 'Add New Sub Service', 'signaturemedia-silo-structure' ),
                'edit_item'          => __( 'Edit Sub Service', 'signaturemedia-silo-structure' ),
                'view_item'          => __( 'View Sub Service', 'signaturemedia-silo-structure' ),
            ],
            'public'              => true,
            'has_archive'         => false,
            'rewrite'             => false, // Custom rewrite handled in class-rewrite.php
            'show_in_rest'        => true,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ],
            'menu_icon'           => 'dashicons-admin-generic',
            'publicly_queryable'  => true,
            'show_in_menu'        => 'signature-media',
            'capability_type'     => 'post',
            'hierarchical'        => true,
            'taxonomies'          => ['service_category'],
        ] );

        // Problem Signs
        register_post_type( 'silo_problem', [
            'labels' => [
                'name'          => __( 'Problem Signs', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Problem Sign', 'signaturemedia-silo-structure' ),
                'add_new_item'  => __( 'Add New Problem Sign', 'signaturemedia-silo-structure' ),
                'edit_item'     => __( 'Edit Problem Sign', 'signaturemedia-silo-structure' ),
            ],
            'public'              => true,
            'has_archive'         => false,
            'show_in_rest'        => true,
            'rewrite'             => false,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon'           => 'dashicons-warning',
            'publicly_queryable'  => true,
            'show_in_menu'        => 'signature-media',
            'capability_type'     => 'post',
            'hierarchical'        => true,
            'taxonomies'          => ['service_category'],
        ] );

        // Solutions
        register_post_type( 'silo_solution', [
            'labels' => [
                'name'          => __( 'Solutions', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Solution', 'signaturemedia-silo-structure' ),
                'add_new_item'  => __( 'Add New Solution', 'signaturemedia-silo-structure' ),
                'edit_item'     => __( 'Edit Solution', 'signaturemedia-silo-structure' ),
            ],
            'public'              => true,
            'has_archive'         => false,
            'show_in_rest'        => true,
            'rewrite'             => false,
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon'           => 'dashicons-yes',
            'publicly_queryable'  => true,
            'show_in_menu'        => 'signature-media',
            'capability_type'     => 'post',
            'hierarchical'        => true,
            'taxonomies'          => ['service_category'],
        ] );

        // Locations Served
        register_post_type( 'locations', [
            'labels' => [
                'name'          => __( 'Locations Served', 'signaturemedia-silo-structure' ),
                'singular_name' => __( 'Location', 'signaturemedia-silo-structure' ),
            ],
            'public'              => true,
            'has_archive'         => false,
            'show_in_rest'        => true,
            'rewrite'             => [ 'slug' => 'service-area', 'with_front' => false ],
            'supports'            => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
            'menu_icon'           => 'dashicons-location',
            'publicly_queryable'  => true,
            'show_in_menu'        => 'signature-media',
            'capability_type'     => 'post',
            'hierarchical'        => true,
        ] );

        // Flag for rewrite flush
        update_option( 'silo_rewrite_flush_needed', true );
    }
}

new SignatureMedia_Silo_Post_Types();

}