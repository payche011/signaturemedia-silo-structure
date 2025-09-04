<?php
/**
 * SignatureMedia Silo Rewrite Class - Handles custom rewrite rules and permalink structures
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'SignatureMedia_Silo_Rewrite' ) ) {

class SignatureMedia_Silo_Rewrite {
    
    public function __construct() {
        // Register custom rewrite tags that WordPress can understand
        add_action( 'init', [ $this, 'register_rewrite_tags' ], 10 );
        
        // Add our custom rewrite rules (runs after taxonomies are registered)
        add_action( 'init', [ $this, 'add_rewrite_rules' ], 11 );

        
        add_action( 'init', [ $this, 'force_rule_priority' ], 999 );
        
        // Customize how permalinks are generated for our custom post types
        add_filter( 'post_type_link', [ $this, 'custom_post_type_permalinks' ], 10, 2 );
        
        // Customize how permalinks are generated for regular blog posts in service categories
        add_filter( 'post_link', [ $this, 'custom_blog_post_permalinks' ], 10, 2 );
        
        // Register our custom query variables so WordPress recognizes them
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        
        // Handle which template to load for our custom archives
        add_filter( 'template_include', [ $this, 'template_include' ] );
        
        // CRITICAL: Handle the main query modification
        add_action( 'pre_get_posts', [ $this, 'modify_main_query' ] );
        
        // Main logic: decide whether a URL should load a silo_service or regular post
        add_filter( 'request', [ $this, 'handle_request_conflicts' ] );
        
        // Auto-flush rewrite rules when needed (performance-friendly)
        add_action( 'wp_loaded', [ $this, 'maybe_flush_rewrite_rules' ] );
        
        // Remove default WordPress rules that conflict with our custom ones
        add_filter( 'rewrite_rules_array', [ $this, 'remove_conflicting_rules' ] );
        
        // Override taxonomy rewrite to prevent /service_category/ URLs
        add_filter( 'term_link', [ $this, 'custom_taxonomy_permalinks' ], 10, 3 );
        
        // Redirect old /service_category/ URLs to new /services/ URLs
        add_action( 'template_redirect', [ $this, 'redirect_old_urls' ] );

    }

    public function force_rule_priority() {

    $rules = get_option('rewrite_rules');
    if (!$rules) return;

    $our_rules = [];
    $other_rules = [];

    $prefix = '^' . $this->base_path(); // npr '^services/' ili '^'

    foreach ($rules as $pattern => $replacement) {
        if ($prefix !== '^' && strpos($pattern, $prefix) === 0) {
            $our_rules[$pattern] = $replacement;
        } elseif ($prefix === '^' && preg_match('#^\^([a-z0-9\-]+)/#i', $pattern)) {
            // bez prefiksa: naša pravila počinju sa ^{slug}/ ... (filtriraćemo kasnije kroz add_rewrite_rules)
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

/**
 * Vrati bazni deo putanje za rewrite pattern i URL gradnju.
 * Primer: 'services/' ili '' (bez prefiksa). Bez leading slash – koristimo dosledno.
 */
private function base_path(): string {
    return $this->strip_prefix_enabled() ? '' : 'services/';
}

/** 
 * Prepends base_path to a relative path and ensures leading slash for full URLs.
 * E.g. build_url('foundation/solutions/') -> '/services/foundation/solutions/' ili '/foundation/solutions/'
 */
private function build_url(string $relative): string {
    $base = $this->base_path();
    $path = ($base !== '' ? $base : '') . ltrim($relative, '/');
    return '/' . ltrim($path, '/');
}


    /**
     * Register custom rewrite tags
     */
    public function register_rewrite_tags() {
        add_rewrite_tag( '%silo_problem%', '([^/]+)', 'silo_problem=' );
        add_rewrite_tag( '%silo_solution%', '([^/]+)', 'silo_solution=' );
        add_rewrite_tag( '%problem_archive%', '([01])', 'problem_archive=' );
        add_rewrite_tag( '%solution_archive%', '([01])', 'solution_archive=' );
        add_rewrite_tag( '%silo_category%', '([^/]+)', 'silo_category=' );
        add_rewrite_tag( '%post_identifier%', '([^/]+)', 'post_identifier=' );
        // REMOVED: services_archive tag since we're using a Page now
    }
    

    /**
     * Add our custom variables to WordPress query variables
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'problem_archive';
        $vars[] = 'solution_archive';
        $vars[] = 'silo_problem';
        $vars[] = 'silo_solution';
        $vars[] = 'silo_category';
        $vars[] = 'post_identifier';
        // REMOVED: services_archive since we're using a Page now
        return $vars;
    }

   /**
     * FIXED: Modify the main query for our custom archives and singles
     */
    public function modify_main_query( $query ) {
        // Only modify the main query, not admin or sub-queries
        if ( ! $query->is_main_query() || is_admin() ) {
            return;
        }

        // NOVO: Handle individual silo_problem posts
        if ( get_query_var( 'silo_problem' ) ) {
            $query->set( 'post_type', 'silo_problem' );
            $query->set( 'name', get_query_var( 'silo_problem' ) );
            $query->is_single = true;
            $query->is_singular = true;
            $query->is_archive = false;
            $query->is_tax = false;
            $query->is_home = false;
            $query->is_posts_page = false;
            return;
        }

        // NOVO: Handle individual silo_solution posts
        if ( get_query_var( 'silo_solution' ) ) {
            $query->set( 'post_type', 'silo_solution' );
            $query->set( 'name', get_query_var( 'silo_solution' ) );
            $query->is_single = true;
            $query->is_singular = true;
            $query->is_archive = false;
            $query->is_tax = false;
            $query->is_home = false;
            $query->is_posts_page = false;
            return;
        }

        // FIXED: Handle problem archive - FORCE it to be archive, not taxonomy
        if ( get_query_var( 'problem_archive' ) ) {
            $query->set( 'post_type', 'silo_problem' );
            $query->is_home = false;
            $query->is_posts_page = false;
            $query->is_archive = true;
            $query->is_single = false;
            $query->is_singular = false;
            $query->is_tax = false; // FORCE this to false
            $query->is_post_type_archive = true; // SET this to true
            
            $service_category = get_query_var( 'service_category' );
            if ( $service_category ) {
                $query->set( 'service_category', $service_category );
            }
            
            // CRITICAL: Remove taxonomy query
            unset( $query->tax_query );
            $query->queried_object = null;
            $query->queried_object_id = null;
            
            return;
        }

        // FIXED: Handle solution archive - FORCE it to be archive, not taxonomy
        if ( get_query_var( 'solution_archive' ) ) {
            $query->set( 'post_type', 'silo_solution' );
            $query->is_home = false;
            $query->is_posts_page = false;
            $query->is_archive = true;
            $query->is_single = false;
            $query->is_singular = false;
            $query->is_tax = false; // FORCE this to false
            $query->is_post_type_archive = true; // SET this to true
            
            $service_category = get_query_var( 'service_category' );
            if ( $service_category ) {
                $query->set( 'service_category', $service_category );
            }
            
            // CRITICAL: Remove taxonomy query
            unset( $query->tax_query );
            $query->queried_object = null;
            $query->queried_object_id = null;
            
            return;
        }
    }

    /**
     * Remove default WordPress rewrite rules that conflict with our custom ones
     */
    public function remove_conflicting_rules( $rules ) {
        $new_rules = [];
        foreach ( $rules as $pattern => $replacement ) {
            // Remove default service_category taxonomy rules
            if ( strpos( $replacement, 'service_category' ) !== false && 
                 strpos( $pattern, 'service_category' ) !== false ) {
                continue;
            }
            // Remove any conflicting silo_service rules
            if ( strpos( $replacement, 'silo_service' ) !== false && 
                 strpos( $pattern, 'services' ) === false ) {
                continue;
            }
            $new_rules[$pattern] = $replacement;
        }
        return $new_rules;
    }

    /**
     * Add our custom rewrite rules with /services/ prefix
     * FIXED: Correct rule ordering and proper query parameters
     */
    public function add_rewrite_rules() {
    if ( ! taxonomy_exists( 'service_category' ) ) return;

    $service_categories = get_terms([
        'taxonomy'   => 'service_category',
        'hide_empty' => false,
    ]);
    if ( is_wp_error( $service_categories ) || empty( $service_categories ) ) return;

    $wp_categories = get_terms([
        'taxonomy'   => 'category',
        'hide_empty' => false,
    ]);

    $category_slugs = [];
    foreach ( $service_categories as $cat ) $category_slugs[] = $cat->slug;
    if ( ! is_wp_error( $wp_categories ) ) {
        foreach ( $wp_categories as $cat ) {
            if ( ! in_array( $cat->slug, $category_slugs, true ) ) {
                $category_slugs[] = $cat->slug;
            }
        }
    }

    $base = $this->base_path(); // 'services/' ili ''

    foreach ( $category_slugs as $slug ) {

        // 1) Single problem
        add_rewrite_rule(
            '^' . $base . $slug . '/problem-signs/([^/]+)/?$',
            'index.php?post_type=silo_problem&name=$matches[1]&silo_problem=$matches[1]',
            'top'
        );

        // 2) Single solution
        add_rewrite_rule(
            '^' . $base . $slug . '/solutions/([^/]+)/?$',
            'index.php?post_type=silo_solution&name=$matches[1]&silo_solution=$matches[1]',
            'top'
        );

        // 3) Problem archive
        add_rewrite_rule(
            '^' . $base . $slug . '/problem-signs/?$',
            'index.php?post_type=silo_problem&service_category=' . $slug . '&problem_archive=1',
            'top'
        );

        // 4) Solutions archive
        add_rewrite_rule(
            '^' . $base . $slug . '/solutions/?$',
            'index.php?post_type=silo_solution&service_category=' . $slug . '&solution_archive=1',
            'top'
        );

        // 5) Sub-services i blog postovi
        add_rewrite_rule(
            '^' . $base . $slug . '/([^/]+)/?$',
            'index.php?silo_category=' . $slug . '&post_identifier=$matches[1]',
            'top'
        );

        // 6) Service category „archive” (termin)
        add_rewrite_rule(
            '^' . $base . $slug . '/?$',
            'index.php?service_category=' . $slug,
            'top'
        );
    }
}


    /**
     * ENHANCED conflict resolution logic to handle both silo_service AND blog posts
     */
    public function handle_request_conflicts( $query ) {
        if ( isset( $query['silo_category'] ) && isset( $query['post_identifier'] ) ) {
            $identifier = $query['post_identifier'];
            $category_slug = $query['silo_category'];
            
            // Search for silo_service post with this slug
            $silo_service = get_posts([
                'name'        => $identifier,
                'post_type'   => 'silo_service',
                'post_status' => 'publish',
                'numberposts' => 1
            ]);
            
            // Search for regular blog post with this slug
            $blog_post = get_posts([
                'name'        => $identifier,
                'post_type'   => 'post',
                'post_status' => 'publish',
                'numberposts' => 1
            ]);
            
            // DECISION LOGIC:
            
            if ( ! empty( $silo_service ) && ! empty( $blog_post ) ) {
                // CONFLICT: Both exist, need to check which belongs to this category
                
                $silo_belongs_here = $this->post_belongs_to_service_category( $silo_service[0]->ID, $category_slug );
                $blog_belongs_here = $this->post_belongs_to_category( $blog_post[0]->ID, $category_slug );
                
                if ( $silo_belongs_here && ! $blog_belongs_here ) {
                    // Only silo service belongs here
                    return $this->setup_silo_service_query( $query, $identifier );
                } elseif ( $blog_belongs_here && ! $silo_belongs_here ) {
                    // Only blog post belongs here
                    return $this->setup_blog_post_query( $query, $identifier, $category_slug );
                } elseif ( $silo_belongs_here && $blog_belongs_here ) {
                    // Both belong - prioritize silo_service
                    return $this->setup_silo_service_query( $query, $identifier );
                }
                // If neither belongs, fall through to 404
                
            } elseif ( ! empty( $silo_service ) ) {
                // Only silo_service exists
                if ( $this->post_belongs_to_service_category( $silo_service[0]->ID, $category_slug ) ) {
                    return $this->setup_silo_service_query( $query, $identifier );
                }
                
            } elseif ( ! empty( $blog_post ) ) {
                // Only blog post exists
                if ( $this->post_belongs_to_category( $blog_post[0]->ID, $category_slug ) ) {
                    return $this->setup_blog_post_query( $query, $identifier, $category_slug );
                }
            }
            
            // No valid post found, clean up for 404
            unset( $query['silo_category'] );
            unset( $query['post_identifier'] );
        }
        
        return $query;
    }

    /**
     * Check if a post belongs to a service_category
     */
    private function post_belongs_to_service_category( $post_id, $category_slug ) {
        $terms = wp_get_post_terms( $post_id, 'service_category' );
        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                if ( $term->slug === $category_slug ) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if a post belongs to a regular WordPress category
     */
    private function post_belongs_to_category( $post_id, $category_slug ) {
        $category = get_term_by( 'slug', $category_slug, 'category' );
        if ( $category ) {
            $post_categories = wp_get_post_categories( $post_id );
            return in_array( $category->term_id, $post_categories );
        }
        return false;
    }

    /**
     * Setup query for silo_service posts
     */
    private function setup_silo_service_query( $query, $identifier ) {
        $query['post_type'] = 'silo_service';
        $query['name'] = $identifier;
        unset( $query['silo_category'] );
        unset( $query['post_identifier'] );
        return $query;
    }

    /**
     * Setup query for regular blog posts
     */
    private function setup_blog_post_query( $query, $identifier, $category_slug ) {
        $query['post_type'] = 'post';
        $query['name'] = $identifier;
        // Don't set category_name here as it might conflict
        unset( $query['silo_category'] );
        unset( $query['post_identifier'] );
        return $query;
    }

    /**
     * Generate custom permalinks for our custom post types with /services/ prefix
     */
    public function custom_post_type_permalinks( $post_link, $post ) {

    if ( 'silo_service' === $post->post_type || 'silo_problem' === $post->post_type || 'silo_solution' === $post->post_type ) {
        $service_category = get_the_terms( $post->ID, 'service_category' );
        if ( $service_category && ! is_wp_error( $service_category ) ) {
            $slug = $service_category[0]->slug;

            if ( 'silo_service' === $post->post_type ) {
                return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/{$post->post_name}"), '/' ) ) );
            }

            if ( 'silo_problem' === $post->post_type ) {
                return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/problem-signs/{$post->post_name}"), '/' ) ) );
            }

            if ( 'silo_solution' === $post->post_type ) {
                return home_url( user_trailingslashit( ltrim( $this->build_url("$slug/solutions/{$post->post_name}"), '/' ) ) );
            }
        }
    }

    return $post_link;
}

public function custom_blog_post_permalinks( $post_link, $post ) {
    if ( 'post' === $post->post_type ) {
        $categories = get_the_category( $post->ID );
        if ( ! empty( $categories ) ) {
            $service_categories = get_terms([
                'taxonomy'   => 'service_category',
                'hide_empty' => false,
            ]);
            if ( ! is_wp_error( $service_categories ) ) {
                $service_slugs = wp_list_pluck( $service_categories, 'slug' );
                foreach ( $categories as $category ) {
                    if ( in_array( $category->slug, $service_slugs, true ) ) {
                        $url = $this->build_url($category->slug . '/' . $post->post_name);
                        return home_url( user_trailingslashit( ltrim($url, '/') ) );
                    }
                }
            }
        }
    }
    return $post_link;
}



    /**
     * Custom taxonomy permalinks to use /services/ instead of /service_category/
     */
    function custom_taxonomy_permalinks( $termlink, $term, $taxonomy ) {
        if ( 'service_category' === $taxonomy ) {
            // koristi helper koji poštuje "strip /services/" opciju
            $url = $this->build_url( "{$term->slug}" );
            return home_url( user_trailingslashit( ltrim( $url, '/' ) ) );
        }
        return $termlink;
    }


    /**
     * Redirect old /service_category/ URLs to new /services/ URLs
     */
    public function redirect_old_urls() {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $strip = $this->strip_prefix_enabled();

    // Legacy: /service_category/ -> /services/ (ostaje radi kompatibilnosti)
    if ( strpos( $request_uri, '/service_category/' ) === 0 ) {
        $target_base = $this->strip_prefix_enabled() ? '/' : '/services/';
        $new_url = preg_replace( '#^/service_category/#', $target_base, $request_uri );
        wp_redirect( home_url( user_trailingslashit( ltrim( $new_url, '/' ) ) ), 301 );
        exit;
    }


    // Kada je strip uključen: /services/... -> bez prefiksa
    if ( $strip && preg_match('#^/services/(.+)$#', $request_uri, $m) ) {
        $new_url = '/' . ltrim($m[1], '/');
        wp_redirect( home_url( $new_url ), 301 );
        exit;
    }

    // Kada je strip isključen: „goli” pattern -> dodaj /services/
    if ( ! $strip ) {
        // Pokušaj da prepoznamo da li path liči na naše rute bez prefiksa
        // Uzimamo sve poznate service_category slugove da izbegnemo lažne pozitivne
        $terms = get_terms([
            'taxonomy' => 'service_category',
            'hide_empty' => false,
            'fields' => 'slugs',
        ]);
        if ( ! is_wp_error($terms) && ! empty($terms) ) {
            $first = strtok(ltrim($request_uri, '/'), '/');
            if ( in_array($first, $terms, true) ) {
                // Ne diramo već validne admin, feed, api itd.
                if ( strpos($request_uri, '/wp-') !== 0 && strpos($request_uri, '/feed') !== 0 && strpos($request_uri, '/wp-json') !== 0 ) {
                    $new_url = '/services' . rtrim($request_uri, '/');
                    $new_url .= '/';
                    wp_redirect( home_url( $new_url ), 301 );
                    exit;
                }
            }
        }
    }
}


    /**
     * FIXED: Handle template loading for our custom archive pages (removed services_archive handling)
     */
    public function template_include( $template ) {
        
        // REMOVED: services_archive handling since /services/ is now a regular Page
        
        // Handle problem signs archive pages
        if ( get_query_var( 'problem_archive' ) ) {
            $new_template = locate_template( array( 
                'archive-silo_problem.php', 
                'archive.php', 
                'index.php' 
            ) );
            if ( $new_template ) {
                return $new_template;
            }
        }

        // Handle solutions archive pages
        if ( get_query_var( 'solution_archive' ) ) {
            $new_template = locate_template( array( 
                'archive-silo_solution.php', 
                'archive.php', 
                'index.php' 
            ) );
            if ( $new_template ) {
                return $new_template;
            }
        }

        return $template;
    }

    /**
     * Performance-friendly rewrite rule flushing
     */
    public function maybe_flush_rewrite_rules() {
        if ( get_option( 'silo_rewrite_flush_needed' ) ) {
            flush_rewrite_rules();
            delete_option( 'silo_rewrite_flush_needed' );
        }
    }

    /**
     * Set flag to flush rewrite rules on next page load
     */
    public function flag_rewrite_flush() {
        update_option( 'silo_rewrite_flush_needed', true );
    }
}

// Initialize the class
new SignatureMedia_Silo_Rewrite();

// Auto-flush rewrite rules when service categories are modified
add_action( 'created_service_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

add_action( 'edited_service_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

add_action( 'delete_service_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

// Also flush when regular categories are modified (since they might overlap)
add_action( 'created_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

add_action( 'edited_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

add_action( 'delete_category', function() {
    update_option( 'silo_rewrite_flush_needed', true );
});

// Admin utility: Manual rewrite rule flushing
add_action( 'init', function() {
    if ( current_user_can( 'administrator' ) && isset( $_GET['flush_rules'] ) ) {
        flush_rewrite_rules();
        wp_die( 'Rewrite rules flushed successfully!' );
    }
}, 999 );

} // End class_exists check