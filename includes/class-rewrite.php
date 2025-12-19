<?php
/**
 * SignatureMedia Silo Rewrite Class - FINAL STABLE VERSION
 * Handles custom silo rules, including Blog-in-Silo functionality.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SignatureMedia_Silo_Rewrite' ) ) {

class SignatureMedia_Silo_Rewrite {
    
    public function __construct() {
        add_action( 'init', [ $this, 'register_rewrite_tags' ], 10 );
        add_action( 'init', [ $this, 'add_rewrite_rules' ], 11 );
        add_action( 'wp_loaded', [ $this, 'maybe_force_rule_priority' ] );
        
        add_filter( 'post_type_link', [ $this, 'custom_post_type_permalinks' ], 10, 2 );
        add_filter( 'post_link', [ $this, 'custom_blog_post_permalinks' ], 10, 2 );
        
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_filter( 'template_include', [ $this, 'template_include' ] );
        add_action( 'pre_get_posts', [ $this, 'modify_main_query' ] );
        add_filter( 'request', [ $this, 'handle_request_conflicts' ] );
        add_action( 'wp_loaded', [ $this, 'maybe_flush_rewrite_rules' ] );
        add_filter( 'rewrite_rules_array', [ $this, 'remove_conflicting_rules' ] );
        add_filter( 'term_link', [ $this, 'custom_taxonomy_permalinks' ], 10, 3 );
        add_action( 'template_redirect', [ $this, 'redirect_old_urls' ] );
    }

    public function maybe_force_rule_priority() {
        if ( get_option( 'silo_rewrite_flush_needed' ) ) {
            $this->force_rule_priority();
        }
    }

    private function force_rule_priority() {
        $rules = get_option('rewrite_rules');
        if (!$rules) return;
        $our_rules = []; $other_rules = [];
        $prefix = '^' . $this->base_path(); 
        foreach ($rules as $pattern => $replacement) {
            if ($prefix !== '^' && strpos($pattern, $prefix) === 0) {
                $our_rules[$pattern] = $replacement;
            } elseif ($prefix === '^' && preg_match('#^\^([a-z0-9\-]+)/#i', $pattern)) {
                $our_rules[$pattern] = $replacement;
            } else {
                $other_rules[$pattern] = $replacement;
            }
        }
        $new_rules = array_merge($our_rules, $other_rules);
        update_option('rewrite_rules', $new_rules);
    }

    private function strip_prefix_enabled(): bool {
        return get_option('signaturemedia_strip_services_prefix', '0') === '1';
    }

    private function base_path(): string {
        return $this->strip_prefix_enabled() ? '' : 'services/';
    }

    private function build_url(string $relative): string {
        $base = $this->base_path();
        $path = ($base !== '' ? $base : '') . ltrim($relative, '/');
        return '/' . ltrim($path, '/');
    }

    public function register_rewrite_tags() {
        add_rewrite_tag( '%silo_problem%', '([^/]+)', 'silo_problem=' );
        add_rewrite_tag( '%silo_solution%', '([^/]+)', 'silo_solution=' );
        add_rewrite_tag( '%problem_archive%', '([01])', 'problem_archive=' );
        add_rewrite_tag( '%solution_archive%', '([01])', 'solution_archive=' );
        add_rewrite_tag( '%silo_category%', '([^/]+)', 'silo_category=' );
        add_rewrite_tag( '%post_identifier%', '([^/]+)', 'post_identifier=' );
    }

    public function add_query_vars( $vars ) {
        $vars[] = 'problem_archive'; $vars[] = 'solution_archive'; $vars[] = 'silo_problem';
        $vars[] = 'silo_solution'; $vars[] = 'silo_category'; $vars[] = 'post_identifier';
        return $vars;
    }

    public function modify_main_query( $query ) {
        if ( ! $query->is_main_query() || is_admin() || $query->is_page() ) return;
        if ( get_query_var( 'silo_problem' ) ) {
            $query->set( 'post_type', 'silo_problem' );
            $query->set( 'name', get_query_var( 'silo_problem' ) );
            $query->is_single = $query->is_singular = true;
            return;
        }
        if ( get_query_var( 'silo_solution' ) ) {
            $query->set( 'post_type', 'silo_solution' );
            $query->set( 'name', get_query_var( 'silo_solution' ) );
            $query->is_single = $query->is_singular = true;
            return;
        }
    }

    public function add_rewrite_rules() {
        if ( ! taxonomy_exists( 'service_category' ) ) return;
        $service_categories = get_terms(['taxonomy' => 'service_category','hide_empty' => false]);
        if ( is_wp_error( $service_categories ) || empty( $service_categories ) ) return;

        $base = $this->base_path(); 
        foreach ( $service_categories as $cat ) {
            $slug = $cat->slug;
            
            // PRODUCTION GUARD: Skip silo rules if a Page already exists with this slug
            if ( get_page_by_path( $slug, OBJECT, 'page' ) ) continue;

            add_rewrite_rule('^' . $base . $slug . '/problem-signs/([^/]+)/?$', 'index.php?post_type=silo_problem&name=$matches[1]&silo_problem=$matches[1]', 'top');
            add_rewrite_rule('^' . $base . $slug . '/solutions/([^/]+)/?$', 'index.php?post_type=silo_solution&name=$matches[1]&silo_solution=$matches[1]', 'top');
            add_rewrite_rule('^' . $base . $slug . '/problem-signs/?$', 'index.php?post_type=silo_problem&service_category=' . $slug . '&problem_archive=1', 'top');
            add_rewrite_rule('^' . $base . $slug . '/solutions/?$', 'index.php?post_type=silo_solution&service_category=' . $slug . '&solution_archive=1', 'top');
            add_rewrite_rule('^' . $base . $slug . '/([^/]+)/?$', 'index.php?silo_category=' . $slug . '&post_identifier=$matches[1]', 'top');
            add_rewrite_rule('^' . $base . $slug . '/?$', 'index.php?service_category=' . $slug, 'top');
        }
    }

    /**
     * Blog Silo Permalinks: Puts blog posts into /services/cat/post-name format.
     */
    public function custom_blog_post_permalinks( $post_link, $post ) {
        if ( ! is_object( $post ) || 'post' !== $post->post_type ) return $post_link;
        $service_terms = get_the_terms( $post->ID, 'service_category' );
        if ( ! empty( $service_terms ) && ! is_wp_error( $service_terms ) ) {
            $url = $this->build_url($service_terms[0]->slug . '/' . $post->post_name);
            return home_url( user_trailingslashit( ltrim($url, '/') ) );
        }
        return $post_link;
    }

    public function redirect_old_urls() {
    if ( is_admin() ) return;
    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
    $strip = $this->strip_prefix_enabled();
    
    $path = ltrim( parse_url( $request_uri, PHP_URL_PATH ), '/' );
    if ( empty($path) ) return;

    $first_segment = strtok( $path, '/' );

    // 1. ZAŠTITA: Ignoriši prave stranice I service-area putanju
    if ( get_page_by_path( $path, OBJECT, 'page' ) || $first_segment === 'service-area' ) {
        return; 
    }

    // 2. Redirekcija za legacy taksonomiju
    if ( strpos( $request_uri, '/service_category/' ) === 0 ) {
        $target_base = $strip ? '/' : '/services/';
        $new_url = preg_replace( '#^/service_category/#', $target_base, $request_uri );
        wp_safe_redirect( home_url( user_trailingslashit( ltrim( $new_url, '/' ) ) ), 301 ); 
        exit;
    }

    // 3. Enforce /services/ samo za validne silo kategorije
    if ( ! $strip && ! empty( $first_segment ) ) {
        if ( term_exists( $first_segment, 'service_category' ) ) {
            if ( strpos( $request_uri, '/services/' ) === false ) {
                $new_url = '/services/' . ltrim($request_uri, '/');
                wp_safe_redirect( home_url( user_trailingslashit($new_url) ), 301 ); 
                exit;
            }
        }
    }
}

    public function remove_conflicting_rules( $rules ) {
        $new_rules = [];
        foreach ( $rules as $pattern => $replacement ) {
            if ( strpos( $replacement, 'service_category' ) !== false && strpos( $pattern, 'service_category' ) !== false ) continue;
            $new_rules[$pattern] = $replacement;
        }
        return $new_rules;
    }

    public function custom_post_type_permalinks( $post_link, $post ) {
        if ( in_array($post->post_type, ['silo_service', 'silo_problem', 'silo_solution'], true) ) {
            $service_category = get_the_terms( $post->ID, 'service_category' );
            if ( $service_category && ! is_wp_error( $service_category ) ) {
                $slug = $service_category[0]->slug;
                if ( 'silo_service' === $post->post_type ) return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/{$post->post_name}"), '/' ) ) );
                if ( 'silo_problem' === $post->post_type ) return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/problem-signs/{$post->post_name}"), '/' ) ) );
                if ( 'silo_solution' === $post->post_type ) return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/solutions/{$post->post_name}"), '/' ) ) );
            }
        }
        return $post_link;
    }

    public function custom_taxonomy_permalinks( $termlink, $term, $taxonomy ) {
        if ( 'service_category' === $taxonomy ) {
            $url = $this->build_url( "{$term->slug}" );
            return home_url( user_trailingslashit( ltrim( $url, '/' ) ) );
        }
        return $termlink;
    }

    /**
     * Rešava konflikte između silo usluga i blog postova koji dele istu putanju.
     * Optimizovano da prvo proveri uslugu i prekine rad ako je pronađena.
     */
    public function handle_request_conflicts( $query ) {
        // Provera da li su naši interni identifikatori prisutni u upitu
        if ( isset( $query['silo_category'] ) && isset( $query['post_identifier'] ) ) {
            
            // SECURITY: Sanitizacija promenljivih pre korišćenja u get_posts
            $identifier    = sanitize_title( $query['post_identifier'] );
            $category_slug = sanitize_title( $query['silo_category'] );
            
            // PERFORMANCE: Prvo proveravamo silo_service (primarni sadržaj)
            $silo_service = get_posts([
                'name'        => $identifier,
                'post_type'   => 'silo_service',
                'post_status' => 'publish',
                'numberposts' => 1,
                'fields'      => 'ids' // Optimizacija: tražimo samo ID
            ]);

            if ( ! empty( $silo_service ) && has_term( $category_slug, 'service_category', $silo_service[0] ) ) {
                $query['post_type'] = 'silo_service';
                $query['name']      = $identifier;
                unset( $query['silo_category'], $query['post_identifier'] );
            } else {
                // PERFORMANCE: Blog post proveravamo SAMO ako usluga nije pronađena
                $blog_post = get_posts([
                    'name'        => $identifier,
                    'post_type'   => 'post',
                    'post_status' => 'publish',
                    'numberposts' => 1,
                    'fields'      => 'ids'
                ]);

                if ( ! empty( $blog_post ) && has_term( $category_slug, 'service_category', $blog_post[0] ) ) {
                    $query['post_type'] = 'post';
                    $query['name']      = $identifier;
                    unset( $query['silo_category'], $query['post_identifier'] );
                }
            }
        }
        return $query;
    }

    public function template_include( $template ) {
        if ( get_query_var( 'problem_archive' ) ) return locate_template(['archive-silo_problem.php', 'archive.php', 'index.php']) ?: $template;
        if ( get_query_var( 'solution_archive' ) ) return locate_template(['archive-silo_solution.php', 'archive.php', 'index.php']) ?: $template;
        return $template;
    }

    public function maybe_flush_rewrite_rules() {
        if ( get_option( 'silo_rewrite_flush_needed' ) ) {
            flush_rewrite_rules(); delete_option( 'silo_rewrite_flush_needed' );
        }
    }
}
}