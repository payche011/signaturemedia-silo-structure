<?php
// Exit if accessed directly or not called by WordPress uninstall process
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options/settings
delete_option('signaturemedia_silo_options');

// Remove custom post types and their posts
$post_types = ['silo_service','silo_problem','silo_solution'];
foreach ($post_types as $pt) {
    do {
        $ids = get_posts([
            'post_type'      => $pt,
            'post_status'    => 'any',
            'fields'         => 'ids',
            'posts_per_page' => 200,
            'no_found_rows'  => true,
        ]);
        foreach ($ids as $id) {
            wp_delete_post($id, true);
        }
    } while (!empty($ids));
}


// Remove terms from custom taxonomy
$terms = get_terms(array(
    'taxonomy' => 'service_category',
    'hide_empty' => false,
    'fields' => 'ids',
));
foreach ($terms as $term_id) {
    wp_delete_term($term_id, 'service_category');
}

// Remove custom tables if any (example)
// global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}signaturemedia_custom_table");

// Remove transients if any
// delete_transient('signaturemedia_silo_transient');

// Optionally: remove scheduled events, user meta, etc.
