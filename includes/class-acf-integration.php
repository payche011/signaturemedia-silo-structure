<?php
if (!defined('ABSPATH')) exit;

/**
 * Simple Silo Archive ACF Location Rule
 * Adds "Silo Archive Type" to ACF Location Rules
 */
class SignatureMedia_Silo_ACF_Location_Rule {

    public function __construct() {
        add_filter('acf/location/rule_types', [$this, 'add_location_rule_type']);
        add_filter('acf/location/rule_values/silo_archive_type', [$this, 'rule_values']);
        add_filter('acf/location/rule_match/silo_archive_type', [$this, 'rule_match'], 10, 3);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_script']);
    }

    public function add_location_rule_type($choices) {
        $choices['Silo']['silo_archive_type'] = 'Silo Archive Type';
        return $choices;
    }

    public function rule_values($choices) {
        $choices = [
            'problem_signs' => 'Problem Signs',
            'solutions' => 'Solutions',
        ];
        return $choices;
    }

    public function rule_match($match, $rule, $screen) {
        $valid_pages = ['silo-problem-acf', 'silo-solutions-acf'];
        if (isset($screen['page']) && in_array($screen['page'], $valid_pages) && isset($_GET['service'])) {
            $service_slug = sanitize_text_field($_GET['service']);
            $term = get_term_by('slug', $service_slug, 'service_category');
            if (!$term) {
                return false;
            }
            if ($screen['page'] === 'silo-problem-acf' && $rule['value'] === 'problem_signs') {
                $match = true;
            } elseif ($screen['page'] === 'silo-solutions-acf' && $rule['value'] === 'solutions') {
                $match = true;
            } else {
                $match = false;
            }
        }
        return $match;
    }

    /**
     * Get current archive type from various sources
     */
    public function get_current_archive_type() {
        // Check URL parameter first
        if (isset($_GET['silo_archive_type'])) {
            return $_GET['silo_archive_type'];
        }

        // Check if coming from our admin page
        if (isset($_GET['page']) && $_GET['page'] === 'silo-archive-content') {
            // Check referer or session
            $referer = wp_get_referer();
            if ($referer && strpos($referer, 'silo_archive_type=') !== false) {
                preg_match('/silo_archive_type=([^&]+)/', $referer, $matches);
                if (isset($matches[1])) {
                    return $matches[1];
                }
            }
        }

        // Default fallback
        return '';
    }

    /**
     * Enqueue admin script to handle archive type switching
     */
    public function enqueue_admin_script($hook) {
        // Only on taxonomy edit pages
        if ($hook !== 'term.php' || !isset($_GET['taxonomy']) || $_GET['taxonomy'] !== 'service_category') {
            return;
        }

        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add archive type selector to taxonomy edit page
            if ($('.acf-field-group').length > 0) {
                // Check if we have silo archive fields
                var hasSiloFields = false;
                $('.acf-field-group').each(function() {
                    var $group = $(this);
                    if ($group.find('[data-location*="silo_archive"]').length > 0) {
                        hasSiloFields = true;
                    }
                });

                if (hasSiloFields) {
                    // Add archive type switcher
                    var currentType = '<?php echo esc_js($this->get_current_archive_type()); ?>' || 'problem_signs';
                    var termId = getUrlParameter('tag_ID');
                    
                    if (termId) {
                        var switcherHtml = '<div class="silo-archive-switcher" style="margin: 20px 0; padding: 15px; background: #f0f8ff; border: 1px solid #b3d9ff; border-radius: 4px;">';
                        switcherHtml += '<h3 style="margin: 0 0 10px 0;">Archive Type</h3>';
                        switcherHtml += '<select id="silo-archive-type-select" style="margin-right: 10px;">';
                        switcherHtml += '<option value="problem_signs"' + (currentType === 'problem_signs' ? ' selected' : '') + '>Problem Signs Archive</option>';
                        switcherHtml += '<option value="solutions"' + (currentType === 'solutions' ? ' selected' : '') + '>Solutions Archive</option>';
                        switcherHtml += '</select>';
                        switcherHtml += '<button type="button" class="button" id="switch-archive-type">Switch Archive Type</button>';
                        switcherHtml += '<p style="margin: 10px 0 0 0; font-size: 13px; color: #666;">Switch between Problem Signs and Solutions archive field groups.</p>';
                        switcherHtml += '</div>';
                        
                        // Insert before the first ACF field group
                        $('.acf-field-group').first().before(switcherHtml);
                        
                        // Handle archive type switching
                        $('#switch-archive-type').on('click', function() {
                            var selectedType = $('#silo-archive-type-select').val();
                            var currentUrl = window.location.href;
                            var newUrl = updateUrlParameter(currentUrl, 'silo_archive_type', selectedType);
                            window.location.href = newUrl;
                        });
                    }
                }
            }

            // Helper functions
            function getUrlParameter(name) {
                name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
                var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
                var results = regex.exec(location.search);
                return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
            }

            function updateUrlParameter(url, param, paramVal) {
                var newAdditionalURL = "";
                var tempArray = url.split("?");
                var baseURL = tempArray[0];
                var additionalURL = tempArray[1];
                var temp = "";
                if (additionalURL) {
                    tempArray = additionalURL.split("&");
                    for (var i = 0; i < tempArray.length; i++) {
                        if (tempArray[i].split('=')[0] != param) {
                            newAdditionalURL += temp + tempArray[i];
                            temp = "&";
                        }
                    }
                }
                var rows_txt = temp + "" + param + "=" + paramVal;
                return baseURL + "?" + newAdditionalURL + rows_txt;
            }
        });
        </script>
        <?php
    }
}

/**
 * Enhanced Admin Integration for Silo Archives
 * Adds direct links to ACF editing with proper archive type
 */
class SignatureMedia_Silo_Admin_ACF_Links {

    public function __construct() {
        add_action('admin_footer', [$this, 'enhance_admin_archive_page']);
    }

    /**
     * Enhance the existing archive content admin page with ACF links
     */
    public function enhance_admin_archive_page() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'silo-archive-content') {
            return;
        }

        if (!function_exists('acf_get_field_groups')) {
            return;
        }

        ?>
        <style>
        .silo-acf-btn {
            background: #2271b1 !important;
            border-color: #2271b1 !important;
            color: white !important;
            text-decoration: none !important;
            font-size: 12px !important;
            padding: 4px 12px !important;
            border-radius: 3px !important;
        }
        
        .silo-acf-btn:hover {
            background: #135e96 !important;
            border-color: #135e96 !important;
            color: white !important;
        }
        </style>
        <?php
    }
}

/**
 * Template Integration Functions
 * Simple functions to check for and display ACF content
 */

/**
 * Check if Silo Archive has ACF content
 */
function silo_archive_has_acf_content($term_id, $archive_type) {
    if (!function_exists('get_field')) {
        return false;
    }
    
    // Get all ACF field groups
    $field_groups = acf_get_field_groups();
    
    foreach ($field_groups as $group) {
        // Check if this group has silo archive location rules
        if (isset($group['location'])) {
            foreach ($group['location'] as $location_group) {
                foreach ($location_group as $rule) {
                    if ($rule['param'] === 'silo_archive_type' && $rule['value'] === $archive_type) {
                        // This group matches our archive type, check if it has content
                        $fields = acf_get_fields($group);
                        if ($fields) {
                            foreach ($fields as $field) {
                                $value = get_field($field['name'], 'term_' . $term_id . '_' . $archive_type);
                                if (!empty($value)) {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Display Silo Archive ACF Content
 */
function display_silo_archive_acf_content($term_id, $archive_type) {
    if (!function_exists('get_field') || !function_exists('render_flexible_modules')) {
        return false;
    }

    $content_displayed = false;
    $post_id = 'term_' . $term_id . '_' . $archive_type;
    // error_log('Displaying ACF content for post_id: ' . $post_id);

    // Get all ACF field groups
    $field_groups = acf_get_field_groups(['post_id' => $post_id]);

    foreach ($field_groups as $group) {
        if (isset($group['location'])) {
            foreach ($group['location'] as $location_group) {
                foreach ($location_group as $rule) {
                    if ($rule['param'] === 'silo_archive_type' && $rule['value'] === $archive_type) {
                        $fields = acf_get_fields($group);
                        if ($fields) {
                            foreach ($fields as $field) {
                                $value = get_field($field['name'], $post_id);
                                // error_log('Field ' . $field['name'] . ' for ' . $post_id . ': ' . print_r($value, true));
                                if (!empty($value)) {
                                    if ($field['type'] === 'flexible_content') {
                                        render_flexible_modules($value);
                                        $content_displayed = true;
                                    } else {
                                        echo '<div class="silo-acf-field silo-field-' . esc_attr($field['name']) . '">';
                                        if (is_array($value)) {
                                            foreach ($value as $item) {
                                                if (is_array($item)) {
                                                    foreach ($item as $sub_field => $sub_value) {
                                                        echo '<div class="sub-field sub-field-' . esc_attr($sub_field) . '">';
                                                        echo wp_kses_post($sub_value);
                                                        echo '</div>';
                                                    }
                                                } else {
                                                    echo wp_kses_post($item);
                                                }
                                            }
                                        } else {
                                            echo wp_kses_post($value);
                                        }
                                        echo '</div>';
                                        $content_displayed = true;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    return $content_displayed;
}
