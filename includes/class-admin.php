<?php
if (!defined('ABSPATH')) exit;

class SignatureMedia_Silo_Admin {

    public function __construct() {
    add_action('admin_menu', [$this, 'add_admin_menu']);
    add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
    add_action('admin_notices', [$this, 'maybe_show_slug_conflict_notice']);
    add_action('admin_init', [$this, 'handle_settings_save']); // NEW
    }


    public function add_admin_menu() {
    add_menu_page(
        __('Signature Media','signaturemedia-silo-structure'),
        __('Signature Media','signaturemedia-silo-structure'),
        'manage_options',
        'signature-media',
        '',
        'dashicons-admin-multisite',
        2
    );

    add_submenu_page(
        'signature-media',
        __('Silo Services','signaturemedia-silo-structure'),
        __('Service Silos','signaturemedia-silo-structure'),
        'manage_options',
        'silo-services',
        [$this, 'services_admin_page']
    );

    add_submenu_page(
    'signature-media',
    __('Silo Settings','signaturemedia-silo-structure'),
    __('Silo Settings','signaturemedia-silo-structure'),
    'manage_options',
    'silo-settings',
    [$this, 'settings_admin_page']
    );

}


public function handle_settings_save() {
    if (!is_admin()) return;

    if (
        isset($_POST['signaturemedia_silo_settings_submit']) &&
        check_admin_referer('signaturemedia_silo_settings')
    ) {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to perform this action.', 'signaturemedia-silo-structure'));
        }

        $strip = isset($_POST['strip_services_prefix']) ? '1' : '0';
        update_option('signaturemedia_strip_services_prefix', $strip === '1' ? '1' : '0');

        // request flush of rewrite rules on the next page load (perf-friendly)
        update_option('silo_rewrite_flush_needed', true);

        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' .
                 esc_html__('Settings saved. Permalinks updated.', 'signaturemedia-silo-structure') .
                 '</p></div>';
        });
    }
}


public function settings_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'signaturemedia-silo-structure'));
    }

    $strip = get_option('signaturemedia_strip_services_prefix', '0') === '1';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Silo Settings', 'signaturemedia-silo-structure') . '</h1>';
    echo '<form method="post" action="">';
    wp_nonce_field('signaturemedia_silo_settings');

    echo '<table class="form-table" role="presentation"><tbody>';

    echo '<tr>';
    echo '<th scope="row"><label for="strip_services_prefix">' .
         esc_html__('Strip "/services/" from URLs', 'signaturemedia-silo-structure') .
         '</label></th>';
    echo '<td>';
    echo '<fieldset><legend class="screen-reader-text">' .
         esc_html__('Strip "/services/" from URLs', 'signaturemedia-silo-structure') .
         '</legend>';
    echo '<label>';
    echo '<input type="checkbox" id="strip_services_prefix" name="strip_services_prefix" value="1" ' . checked(true, $strip, false) . ' />';
    echo ' ' . esc_html__('Enable clean URLs like /{service}/{post}', 'signaturemedia-silo-structure');
    echo '</label>';
    echo '<p class="description">' .
         esc_html__('When enabled, old /services/... URLs will 301 redirect to the new structure. Disabling reverts to /services/... and redirects back.', 'signaturemedia-silo-structure') .
         '</p>';
    echo '</fieldset>';
    echo '</td>';
    echo '</tr>';

    echo '</tbody></table>';

    echo '<p class="submit">';
    echo '<button type="submit" name="signaturemedia_silo_settings_submit" class="button button-primary">' .
         esc_html__('Save Changes', 'signaturemedia-silo-structure') .
         '</button>';
    echo '</p>';

    echo '</form>';
    echo '</div>';
}



    /**
 * Problem Signs ACF Fields Admin Page
 */
public function problem_acf_admin_page() {
       if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'signaturemedia-silo-structure'));
    }

    $selected_service = isset($_GET['service']) ? sanitize_text_field($_GET['service']) : '';

    // Get all service categories
    $terms = get_terms([
        'taxonomy'   => 'service_category',
        'hide_empty' => false,
        'orderby'    => 'name'
    ]);

    echo '<div class="wrap">';
    echo '<h1>' . __('Problem Signs ACF Fields Management', 'signaturemedia-silo-structure') . '</h1>';
    echo '<p class="description">Manage ACF flexible content for Problem Signs archive pages.</p>';

    // Service selector
    echo '<form method="get" class="silo-service-selector">';
    echo '<input type="hidden" name="page" value="silo-problem-acf">';
    echo '<label for="service">' . __('Select Service Category:', 'signaturemedia-silo-structure') . '</label>';
    echo '<select name="service" id="service" onchange="this.form.submit()">';
    echo '<option value="">' . __('-- Select Service --', 'signaturemedia-silo-structure') . '</option>';

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $selected = selected($selected_service, $term->slug, false);
            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
        }
    }
    echo '</select>';
    echo '</form>';

    if (!empty($selected_service)) {
        $term = get_term_by('slug', $selected_service, 'service_category');
        if ($term) {
            echo '<h2>' . sprintf(__('Problem Signs ACF Fields for: %s', 'signaturemedia-silo-structure'), $term->name) . '</h2>';

            if (function_exists('acf_form_head')) {
                acf_form_head();
            }

            if (function_exists('acf_form')) {
                echo '<form method="post" class="silo-acf-form">';
                $field_group_key = 'group_67f51aa13d7ca';
                acf_form([
                    'post_id' => 'term_' . $term->term_id . '_problem',
                    'field_groups' => [$field_group_key],
                    'form' => true,
                    'submit_value' => __('Save Problem Signs ACF Fields', 'signaturemedia-silo-structure'),
                ]);
                echo '</form>';

                // Add Preview Button
                $preview_url = home_url("services/{$term->slug}/problem-signs/");
                echo '<div style="margin-top: 20px;">';
                echo '<a href="' . esc_url($preview_url) . '" target="_blank" class="button button-primary" id="preview-button">Preview</a>';
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('ACF plugin is not active or misconfigured.', 'signaturemedia-silo-structure') . '</p></div>';
            }
        }
    }

    echo '</div>';
    }

/**
 * Solutions ACF Fields Admin Page
 */
public function solutions_acf_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'signaturemedia-silo-structure'));
    }

    $selected_service = isset($_GET['service']) ? sanitize_text_field($_GET['service']) : '';

    // Get all service categories
    $terms = get_terms([
        'taxonomy'   => 'service_category',
        'hide_empty' => false,
        'orderby'    => 'name'
    ]);

    echo '<div class="wrap">';
    echo '<h1>' . __('Solutions ACF Fields Management', 'signaturemedia-silo-structure') . '</h1>';
    echo '<p class="description">Manage ACF flexible content for Solutions archive pages.</p>';

    // Service selector
    echo '<form method="get" class="silo-service-selector">';
    echo '<input type="hidden" name="page" value="silo-solutions-acf">';
    echo '<label for="service">' . __('Select Service Category:', 'signaturemedia-silo-structure') . '</label>';
    echo '<select name="service" id="service" onchange="this.form.submit()">';
    echo '<option value="">' . __('-- Select Service --', 'signaturemedia-silo-structure') . '</option>';

    if (!empty($terms) && !is_wp_error($terms)) {
        foreach ($terms as $term) {
            $selected = selected($selected_service, $term->slug, false);
            echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($term->name) . '</option>';
        }
    }
    echo '</select>';
    echo '</form>';

    // If service is selected, show ACF fields
    if (!empty($selected_service)) {
        $term = get_term_by('slug', $selected_service, 'service_category');
        if ($term) {
            echo '<h2>' . sprintf(__('Solutions ACF Fields for: %s', 'signaturemedia-silo-structure'), $term->name) . '</h2>';

            // Initialize ACF form
            if (function_exists('acf_form_head')) {
                acf_form_head();
            }

            // Render ACF fields
            if (function_exists('acf_form')) {
                echo '<form method="post" class="silo-acf-form">';
                $field_group_key = 'group_67f51aa13d7ca';
                acf_form([
                    'post_id' => 'term_' . $term->term_id . '_solution',
                    'field_groups' => [$field_group_key],
                    'form' => true,
                    'submit_value' => __('Save Solutions ACF Fields', 'signaturemedia-silo-structure'),
                ]);
                echo '</form>';

                // Add Preview Button
                $preview_url = home_url("services/{$term->slug}/solutions/");
                echo '<div style="margin-top: 20px;">';
                echo '<a href="' . esc_url($preview_url) . '" target="_blank" class="button button-primary" id="preview-button">Preview</a>';
                echo '</div>';
            } else {
                echo '<div class="notice notice-error"><p>' . __('ACF plugin is required to manage flexible content.', 'signaturemedia-silo-structure') . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>' . __('Invalid service category selected.', 'signaturemedia-silo-structure') . '</p></div>';
        }
    }

    echo '</div>';
}

    public function enqueue_admin_styles($hook) {
         if (strpos($hook, 'silo-services') === false && 
            strpos($hook, 'silo-problem-acf') === false &&
            strpos($hook, 'silo-solutions-acf') === false) {
            return;
        }
        wp_enqueue_style(
            'signaturemedia-silo-admin',
            SIGNATUREMEDIA_SILO_URL . 'admin/css/admin.css',
            [],
            '2.0.0'
        );
        wp_enqueue_editor();
        if (function_exists('acf_enqueue_scripts')) {
            acf_enqueue_scripts();
        }
    }

    public function services_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'signaturemedia-silo-structure'));
        }
        
        // Handle form submission
        if (isset($_POST['new_service']) && check_admin_referer('add_service_category')) {
            $new_service = sanitize_text_field($_POST['new_service']);
            if (!empty($new_service) && !term_exists($new_service, 'service_category')) {
                wp_insert_term($new_service, 'service_category');
                echo '<div class="notice notice-success"><p>' . __('Service category added successfully!', 'signaturemedia-silo-structure') . '</p></div>';
            }
        }
        
        // Display form and existing services
        echo '<div class="wrap">';
        echo '<h1>' . __('Service Silos', 'signaturemedia-silo-structure') . '</h1>';
        echo '<form method="post" class="silo-form">';
        wp_nonce_field('add_service_category');
        echo '<div class="form-field">';
        echo '<label for="new_service">' . __('Add New Service Category', 'signaturemedia-silo-structure') . '</label>';
        echo '<input type="text" name="new_service" placeholder="' . esc_attr__('e.g. Foundation Repair', 'signaturemedia-silo-structure') . '" required>';
        submit_button(__('Add Service Category', 'signaturemedia-silo-structure'), 'primary');
        echo '</div>';
        echo '</form>';

        $terms = get_terms([
            'taxonomy'   => 'service_category',
            'hide_empty' => false,
            'orderby'    => 'name'
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            echo '<h2>' . __('Existing Service Categories', 'signaturemedia-silo-structure') . '</h2>';
            echo '<p class="description">Main service category content is managed via the taxonomy edit page. Use "Archive Pages" to manage Problem Signs and Solutions archive content.</p>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . __('Name', 'signaturemedia-silo-structure') . '</th><th>' . __('Slug', 'signaturemedia-silo-structure') . '</th><th>' . __('Count', 'signaturemedia-silo-structure') . '</th><th>' . __('Actions', 'signaturemedia-silo-structure') . '</th></tr></thead>';
            echo '<tbody>';
            foreach ($terms as $term) {
                $edit_link = get_edit_term_link($term->term_id, 'service_category');
                $view_link = get_term_link($term);
                // $archive_link = admin_url('admin.php?page=silo-archive-content&service=' . $term->slug);
                echo '<tr>';
                echo '<td><strong>' . esc_html($term->name) . '</strong></td>';
                echo '<td>' . esc_html($term->slug) . '</td>';
                echo '<td>' . $term->count . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($edit_link) . '">' . __('Edit Main Category', 'signaturemedia-silo-structure') . '</a> | ';
                echo '<a href="' . esc_url($view_link) . '" target="_blank">' . __('View', 'signaturemedia-silo-structure') . '</a> | ';
                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No service categories found.', 'signaturemedia-silo-structure') . '</p>';
        }
        echo '</div>';
    }

    /**
     * Show ACF fields information
     */
    private function show_acf_fields_info($term) {
        if (!function_exists('get_fields')) {
            return;
        }

        // Check for ACF fields that might be used for archives
        $problem_fields = [];
        $solution_fields = [];
        
        // Look for archive-specific ACF fields
        $all_fields = get_fields('term_' . $term->term_id);
        if (is_array($all_fields)) {
            foreach ($all_fields as $field_name => $field_value) {
                if (strpos($field_name, 'problem_signs') === 0) {
                    $problem_fields[$field_name] = $field_value;
                } elseif (strpos($field_name, 'solutions') === 0) {
                    $solution_fields[$field_name] = $field_value;
                }
            }
        }

        if (!empty($problem_fields) || !empty($solution_fields)) {
            echo '<div class="acf-fields-detected">';
            echo '<h3>ACF Fields Detected</h3>';
            echo '<p>The following ACF fields were found for this service category:</p>';
            
            if (!empty($problem_fields)) {
                echo '<h4>Problem Signs Fields:</h4>';
                echo '<ul>';
                foreach ($problem_fields as $field_name => $field_value) {
                    echo '<li><strong>' . esc_html($field_name) . '</strong>';
                    if (is_array($field_value) && !empty($field_value)) {
                        echo ' <em>(has content)</em>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            if (!empty($solution_fields)) {
                echo '<h4>Solutions Fields:</h4>';
                echo '<ul>';
                foreach ($solution_fields as $field_name => $field_value) {
                    echo '<li><strong>' . esc_html($field_name) . '</strong>';
                    if (is_array($field_value) && !empty($field_value)) {
                        echo ' <em>(has content)</em>';
                    }
                    echo '</li>';
                }
                echo '</ul>';
            }
            
            $edit_link = get_edit_term_link($term->term_id, 'service_category');
            echo '<p><a href="' . esc_url($edit_link) . '" class="button button-secondary">Edit ACF Fields for ' . esc_html($term->name) . '</a></p>';
            echo '<p class="description"><strong>Note:</strong> If ACF flexible content exists, it will take priority over the content editors below on the frontend.</p>';
            echo '</div>';
        }
    }

    /**
     * Display preview links for archive pages
     */
    private function display_archive_preview_links($term) {
        $problem_archive_link = home_url("services/{$term->slug}/problem-signs/");
        $solution_archive_link = home_url("services/{$term->slug}/solutions/");
        
        echo '<div class="silo-preview-links">';
        echo '<h3>' . __('Preview Archive Pages', 'signaturemedia-silo-structure') . '</h3>';
        echo '<div class="preview-links-grid">';
        echo '<div class="preview-link-item">';
        echo '<h4>Problem Signs Archive</h4>';
        echo '<p><a href="' . esc_url($problem_archive_link) . '" target="_blank" class="button button-secondary">' . __('Preview Problem Signs', 'signaturemedia-silo-structure') . '</a></p>';
        echo '<code>' . $problem_archive_link . '</code>';
        echo '</div>';
        echo '<div class="preview-link-item">';
        echo '<h4>Solutions Archive</h4>';
        echo '<p><a href="' . esc_url($solution_archive_link) . '" target="_blank" class="button button-secondary">' . __('Preview Solutions', 'signaturemedia-silo-structure') . '</a></p>';
        echo '<code>' . $solution_archive_link . '</code>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    /**
     * Add admin styles
     */
    private function add_archive_admin_styles() {
        ?>
        <style>
        .silo-archive-section {
            margin-bottom: 40px;
            padding: 20px;
            background: #f9f9f9;
            border: 1px solid #e5e5e5;
            border-radius: 5px;
        }
        .problem-signs-section {
            border-left: 4px solid #CC0000;
        }
        .solutions-section {
            border-left: 4px solid #333333;
        }
        .problem-signs-section h3 {
            color: #CC0000;
        }
        .solutions-section h3 {
            color: #333333;
        }
        .acf-fields-detected {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .silo-preview-links {
            margin-top: 30px;
            padding: 20px;
            background: #f0f8ff;
            border: 1px solid #b3d9ff;
            border-radius: 5px;
        }
        .preview-links-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .preview-link-item {
            background: white;
            padding: 15px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .preview-link-item h4 {
            margin: 0 0 10px 0;
        }
        .preview-link-item code {
            display: block;
            margin-top: 10px;
            padding: 5px;
            background: #f9f9f9;
            border-radius: 3px;
            font-size: 12px;
            word-break: break-all;
        }
        .archive-link {
            font-weight: bold;
            color: #0073aa !important;
        }
        </style>
        <?php
    }

    /**
     * Save archive content
     */
    private function save_archive_content($term_id) {
        // Sanitize and save problem archive content
        $problem_title = sanitize_text_field($_POST['problem_archive_title']);
        $problem_content = wp_kses_post($_POST['problem_archive_content']);
        
        update_term_meta($term_id, 'problem_archive_title', $problem_title);
        update_term_meta($term_id, 'problem_archive_content', $problem_content);

        // Sanitize and save solution archive content
        $solution_title = sanitize_text_field($_POST['solution_archive_title']);
        $solution_content = wp_kses_post($_POST['solution_archive_content']);
        
        update_term_meta($term_id, 'solution_archive_title', $solution_title);
        update_term_meta($term_id, 'solution_archive_content', $solution_content);
    }

    public function maybe_show_slug_conflict_notice() {
    $pages = get_pages(['fields' => 'all']);
    $page_slugs = wp_list_pluck($pages, 'post_name');
    $terms = get_terms([
        'taxonomy' => 'service_category',
        'hide_empty' => false,
        'fields' => 'slugs',
    ]);

    $conflicts = array_intersect($page_slugs, $terms);

    if ( ! empty( $conflicts ) ) {
        echo '<div class="notice notice-error"><p><strong>Silo Alert:</strong> The following slugs exist as both a Page and a Service Category: <code>' . implode( ', ', $conflicts ) . '</code>. Because of this, the plugin is confused about which one to show. <strong>Recommendation:</strong> Rename the Page or the Category slug to be unique.</p></div>';
    }
}
}