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
// add_action('init', __NAMESPACE__ . '\\bootstrap', 20);


function register_cpt() {
    register_post_type(CPT, [
        'label'              => 'Silo Archives',
        'public'             => true,          // remains true so Rank Math metabox works
        'publicly_queryable' => true,         // ⬅️ prevent single display on frontend
        'exclude_from_search'=> true,
        'has_archive'        => false,
        'rewrite'            => false,         // no rewrite for this CPT
        'query_var'          => false,         // ⬅️ remove ?silo_archive=...
        'show_ui'            => true,
        'show_in_menu'       => 'signature-media',
        'show_in_rest'       => true,
        'supports'           => ['title','editor'],
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
    $types[] = CPT; // 'silo_archive'
    return array_unique($types);
}

// ---- 2) Safe bootstrap (hooks) ----
function bootstrap() {

    // Metabox for linking (term + type)
    add_action('add_meta_boxes', __NAMESPACE__ . '\\add_silo_link_metabox');
    add_action('save_post_' . CPT, __NAMESPACE__ . '\\save_silo_link');

    // Admin columns
    add_filter('manage_edit-' . CPT . '_columns', __NAMESPACE__ . '\\columns');
    add_action('manage_' . CPT . '_posts_custom_column', __NAMESPACE__ . '\\column_content', 10, 2);

    // "View Frontend" instead of default View
    add_filter('post_row_actions', __NAMESPACE__ . '\\row_actions', 10, 2);

    // Rank Math frontend override (SEO from shadow post)
    add_filter('rank_math/frontend/title',       __NAMESPACE__ . '\\maybe_override_title', 20);
    add_filter('rank_math/frontend/description', __NAMESPACE__ . '\\maybe_override_description', 20);
    add_filter('rank_math/frontend/robots',      __NAMESPACE__ . '\\maybe_override_robots', 20);

    // (If the 'archive_type' field exists in ACF FG, sync it with our meta key)
    add_filter('acf/update_value/name=archive_type', function($value, $post_id){
        update_post_meta($post_id, META_ARCHIVE_TYPE, $value);
        return $value;
    }, 10, 2);
}

// ---- Metabox: Silo Link ----
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
    $tid=(int)get_post_meta($post->ID, META_LINKED_TERM, true);
    $type=get_post_meta($post->ID, META_ARCHIVE_TYPE, true);
    if ($tid && in_array($type,[TYPE_PROBLEM,TYPE_SOLUTION],true)){
        $t=get_term($tid);
        if ($t && !is_wp_error($t)){
            $url = home_url('/services/'.$t->slug.'/'.($type===TYPE_PROBLEM?'problem-signs':'solutions').'/');
            $actions['view_frontend'] = '<a href="'.esc_url($url).'" target="_blank">View Frontend</a>';
        }
    }
    unset($actions['view']);
    return $actions;
}

// ---- Rank Math frontend overrides (SEO from shadow post) ----
function detect_archive_context(): array {
    // type comes from rewrite (problem_archive/solution_archive)
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