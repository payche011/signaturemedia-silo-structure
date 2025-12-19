<?php
/**
 * Silo Archive CPT (shadow) – Rank Math + ACF container
 */
namespace SignatureMedia\SiloArchive;

if (!defined('ABSPATH')) exit;

// ---- Constants ----
const CPT               = 'silo_archive';
const META_LINKED_TERM  = '_silo_linked_term';
const META_ARCHIVE_TYPE = '_silo_archive_type'; // <-- UNIFIED!
const TYPE_PROBLEM      = 'problem_signs';
const TYPE_SOLUTION     = 'solutions';

// ---- 0) Register early ----
add_action('init', __NAMESPACE__ . '\\register_cpt', 0);
// Enable all extra behavior
add_action('init', __NAMESPACE__ . '\\bootstrap', 20);

function register_cpt() {
    register_post_type(CPT, [
        'label'              => 'Silo Archives',
        'public'             => true,         // keeps Rank Math metabox
        'publicly_queryable' => true,
        'exclude_from_search'=> true,
        'has_archive'        => false,
        'rewrite'            => false,
        'query_var'          => false,
        'show_ui'            => true,
        'show_in_menu'       => 'signature-media',
        'show_in_rest'       => true,
        'supports'           => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
        'map_meta_cap'       => true,
        'menu_icon'          => 'dashicons-archive',
    ]);
}

// Remove "Permalink" HTML in the editor for this CPT
add_filter('get_sample_permalink_html', function($html, $post_id, $new_title, $new_slug, $post){
  return ($post->post_type === CPT) ? '' : $html;
}, 10, 5);

// Remove slug metabox
add_action('admin_menu', function(){
  remove_meta_box('slugdiv', CPT, 'normal');
});

// ---- 1) Rank Math – allow metabox on this CPT ----
add_filter('rank_math/metabox/post_types', __NAMESPACE__.'\\allow_rank_math_on_cpt');
function allow_rank_math_on_cpt($types){
    $types[] = CPT;
    return array_unique($types);
}

// --------------------------------------------------
// Helper: compute the real frontend URL for a shadow
// --------------------------------------------------
function view_url_for(int $post_id): string {
    $tid  = (int) get_post_meta($post_id, META_LINKED_TERM, true);
    $type = (string) get_post_meta($post_id, META_ARCHIVE_TYPE, true);

    if (!$tid || !in_array($type, [TYPE_PROBLEM, TYPE_SOLUTION], true)) return '';

    $t = get_term($tid);
    if (!$t || is_wp_error($t)) return '';

    $segment = ($type === TYPE_PROBLEM) ? 'problem-signs' : 'solutions';
    return home_url('/services/' . $t->slug . '/' . $segment . '/');
}

// ---- 2) Safe bootstrap (hooks) ----
function bootstrap() {

    // Metabox for linking (term + type) — keep this one
    add_action('add_meta_boxes', __NAMESPACE__ . '\\add_silo_link_metabox');
    add_action('save_post_' . CPT, __NAMESPACE__ . '\\save_silo_link');

    // Admin columns
    add_filter('manage_edit-' . CPT . '_columns', __NAMESPACE__ . '\\columns');
    add_action('manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\\column_content', 10, 2);

    // "View Frontend" in list table
    add_filter('post_row_actions', __NAMESPACE__ . '\\row_actions', 10, 2);

    // Rank Math frontend override (SEO from shadow post)
    add_filter('rank_math/frontend/title',       __NAMESPACE__ . '\\maybe_override_title', 20);
    add_filter('rank_math/frontend/description', __NAMESPACE__ . '\\maybe_override_description', 20);
    add_filter('rank_math/frontend/robots',      __NAMESPACE__ . '\\maybe_override_robots', 20);

    // Sync ACF field if present
    add_filter('acf/update_value/name=archive_type', function($value, $post_id){
        update_post_meta($post_id, META_ARCHIVE_TYPE, $value);
        return $value;
    }, 10, 2);

    // 🗑️ Removed: add_view_frontend_metabox

    // Replace “Post updated. View post” link with our real frontend link
    add_filter('post_updated_messages', __NAMESPACE__ . '\\override_updated_messages');

    // Point the Preview button to the real frontend URL
    add_filter('preview_post_link', __NAMESPACE__ . '\\override_preview_link', 10, 2);

    // Admin bar buttons (both admin and frontend contexts)
    add_action('admin_bar_menu', __NAMESPACE__ . '\\admin_bar_buttons', 100);
}

// ---- Metabox: Silo Link (keep) ----
function add_silo_link_metabox() {
    add_meta_box('silo_link_box', 'Silo Link', __NAMESPACE__ . '\\render_silo_link_box', CPT, 'side', 'high');
}
function render_silo_link_box($post){
    wp_nonce_field('silo_link_save','silo_link_nonce');

    $linked = (int) get_post_meta($post->ID, META_LINKED_TERM, true);
    $type   = get_post_meta($post->ID, META_ARCHIVE_TYPE, true) ?: TYPE_PROBLEM;

    $terms = get_terms(['taxonomy'=>'service_category','hide_empty'=>false]);

    echo '<p><label>Service Term</label><br><select name="silo_linked_term"><option value="">— select —</option>';
    foreach($terms as $t){
        printf('<option value="%d"%s>%s</option>', $t->term_id, selected($linked,$t->term_id,false), esc_html($t->name));
    }
    echo '</select></p>';

    echo '<p><label>Archive Type</label><br>';
    printf('<label><input type="radio" name="silo_archive_type" value="%s"%s> Problem Signs</label><br>', TYPE_PROBLEM, checked($type, TYPE_PROBLEM, false));
    printf('<label><input type="radio" name="silo_archive_type" value="%s"%s> Solutions</label>',      TYPE_SOLUTION, checked($type, TYPE_SOLUTION, false));
    echo '</p>';
}
function save_silo_link($post_id){
    if (!isset($_POST['silo_link_nonce']) || !wp_verify_nonce($_POST['silo_link_nonce'],'silo_link_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post',$post_id)) return;

    $term_id = isset($_POST['silo_linked_term']) ? (int) $_POST['silo_linked_term'] : 0;
    $type    = isset($_POST['silo_archive_type']) ? sanitize_text_field($_POST['silo_archive_type']) : TYPE_PROBLEM;

    update_post_meta($post_id, META_LINKED_TERM, $term_id);
    update_post_meta($post_id, META_ARCHIVE_TYPE, in_array($type,[TYPE_PROBLEM,TYPE_SOLUTION],true) ? $type : TYPE_PROBLEM);
}

// ---- Admin table helpers ----
function columns($cols){
    $out = [];
    foreach($cols as $k=>$v){
        $out[$k] = $v;
        if ($k === 'title'){
            $out['silo_term'] = 'Service Term';
            $out['silo_type'] = 'Archive Type';
        }
    }
    return $out;
}
function column_content($col, $post_id){
    if ($col==='silo_term'){
        $tid=(int)get_post_meta($post_id, META_LINKED_TERM, true);
        if($tid){ $t=get_term($tid); echo $t && !is_wp_error($t)? esc_html($t->name.' (#'.$tid.')') : '-'; }
        else echo '-';
    }
    if ($col==='silo_type'){
        $type=get_post_meta($post_id, META_ARCHIVE_TYPE, true);
        echo $type ? esc_html($type) : '-';
    }
}
function row_actions($actions, $post){
    if ($post->post_type !== CPT) return $actions;

    $url = view_url_for($post->ID);
    if ($url){
        $actions['view_frontend'] = '<a href="'.esc_url($url).'" target="_blank">View Frontend</a>';
    }
    unset($actions['view']); // remove default "View" single link
    return $actions;
}

// ---- Replace “Post updated. View post” with “View Frontend” ----
function override_updated_messages(array $messages): array {
    global $post;
    if ($post && $post->post_type === CPT) {
        $url = view_url_for($post->ID);

        // Force our strings so the link always points right.
        $messages[CPT][1]  = $url
            ? sprintf(__('Silo Archive updated. <a href="%s" target="_blank">View Frontend</a>.'), esc_url($url))
            : __('Silo Archive updated.');
        $messages[CPT][6]  = $url
            ? sprintf(__('Silo Archive published. <a href="%s" target="_blank">View Frontend</a>.'), esc_url($url))
            : __('Silo Archive published.');
        $messages[CPT][10] = $url
            ? sprintf(__('Silo Archive draft updated. <a href="%s" target="_blank">View Frontend</a>.'), esc_url($url))
            : __('Silo Archive draft updated.');
    }
    return $messages;
}

// ---- Preview button -> real frontend URL ----
function override_preview_link($preview_link, \WP_Post $post) {
    if ($post->post_type === CPT) {
        $url = view_url_for($post->ID);
        if ($url) return $url;
    }
    return $preview_link;
}

// ---- Admin bar buttons ----
function admin_bar_buttons(\WP_Admin_Bar $bar) {

    // 1) While EDITING a Silo Archive in wp-admin: show "View Frontend"
    if (is_admin()) {
        if (!function_exists('get_current_screen')) return;
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== CPT) return;

        $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        if (!$post_id) return;

        $url = view_url_for($post_id);
        if ($url) {
            $bar->add_node([
                'id'    => 'silo-view-frontend',
                'title' => 'View Frontend',
                'href'  => $url,
                'meta'  => ['target' => '_blank', 'class' => 'ab-item']
            ]);
        }
        return;
    }

    // 2) On the FRONTEND archive page: show "Edit Silo Archive"
    if (!is_admin() && is_user_logged_in() && current_user_can('edit_posts')) {
        [$tid, $type] = detect_archive_context();
        if ($tid && $type) {
            $sid = get_or_create_shadow($tid, $type, false);
            if ($sid) {
                $bar->add_node([
                    'id'    => 'silo-edit-shadow',
                    'title' => 'Edit Silo Archive',
                    'href'  => get_edit_post_link($sid, 'link'),
                    'meta'  => ['class' => 'ab-item']
                ]);
            }
        }
    }
}

// ---- Rank Math frontend overrides (SEO from shadow post) ----
function detect_archive_context(): array {
    $slug = get_query_var('service_category');
    if (!$slug) return [0, ''];

    $term = get_term_by('slug', $slug, 'service_category');
    if (!$term || is_wp_error($term)) return [0, ''];

    $type = '';
    if (get_query_var('problem_archive'))  $type = TYPE_PROBLEM;   // 'problem_signs'
    if (get_query_var('solution_archive')) $type = TYPE_SOLUTION;  // 'solutions'

    return [(int) $term->term_id, (string) $type];
}

function get_or_create_shadow(int $term_id, string $type=TYPE_PROBLEM, bool $autocreate=true): int {
    $q=new \WP_Query([
        'post_type'=>CPT,'post_status'=>['publish','private','draft'],
        'posts_per_page'=>1,'no_found_rows'=>true,
        'meta_query'=>[
            ['key'=>META_LINKED_TERM,'value'=>$term_id],
            ['key'=>META_ARCHIVE_TYPE,'value'=>$type],
        ],
        'fields'=>'ids',
    ]);
    if (!empty($q->posts)) return (int)$q->posts[0];
    if (!$autocreate) return 0;

    $term=get_term($term_id); if(!$term||is_wp_error($term)) return 0;
    $id=wp_insert_post([
        'post_type'=>CPT,'post_status'=>'private',
        'post_title'=>sprintf('%s – %s', $term->name, $type===TYPE_PROBLEM?'Problem Signs':'Solutions'),
    ]);
    if ($id && !is_wp_error($id)){
        update_post_meta($id, META_LINKED_TERM, $term_id);
        update_post_meta($id, META_ARCHIVE_TYPE, $type);
    }
    return (int)$id;
}
function maybe_override_title($title){
    [$tid,$type]=detect_archive_context(); if(!$tid||!$type) return $title;
    $sid=get_or_create_shadow($tid,$type,false); if(!$sid) return $title;
    $v=get_post_meta($sid,'rank_math_title',true);
    return $v!=='' ? $v : $title;
}
function maybe_override_description($desc){
    [$tid,$type]=detect_archive_context(); if(!$tid||!$type) return $desc;
    $sid=get_or_create_shadow($tid,$type,false); if(!$sid) return $desc;
    $v=get_post_meta($sid,'rank_math_description',true);
    return $v!=='' ? $v : $desc;
}
function maybe_override_robots($robots){
    [$tid,$type]=detect_archive_context(); if(!$tid||!$type) return $robots;
    $sid=get_or_create_shadow($tid,$type,false); if(!$sid) return $robots;
    $v=get_post_meta($sid,'rank_math_robots',true);
    return !empty($v) ? $v : $robots;
}
