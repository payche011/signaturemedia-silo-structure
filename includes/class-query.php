<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SignatureMedia_Silo_Query {

    public function __construct() {
        // Execute AFTER rewrite logic
        add_action( 'pre_get_posts', [ $this, 'adjust_main_query' ], 20 );
    }

    /**
     * Adjusts the main query for silo archives on the frontend.
     */
    public function adjust_main_query( $query ) {

        // 1) Limit to the MAIN QUERY on the frontend (to not affect admin/secondary queries)
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // ADD THIS: If we are on a standard Page, do not adjust the query
        if ( $query->is_page() ) {
            return;
        }

        // 2) Do not affect single contexts and specific routes
        if (
            $query->is_singular() ||
            $query->get('silo_problem') ||
            $query->get('silo_solution') ||
            $query->get('silo_service') ||
            $query->is_feed() ||
            $query->is_search()
        ) {
            return;
        }

        // 3) Problem Signs archive (custom flag)
        if ( isset( $query->query['problem_archive'] ) ) {
            $query->set( 'posts_per_page', (int) get_option( 'posts_per_page' ) );
            $query->set( 'post_type', 'silo_problem' );
            return;
        }

        // 4) Solutions archive (custom flag)
        if ( isset( $query->query['solution_archive'] ) ) {
            $query->set( 'posts_per_page', (int) get_option( 'posts_per_page' ) );
            $query->set( 'post_type', 'silo_solution' );
            return;
        }

        // 5) Locations archive
        if ( $query->is_post_type_archive( 'locations' ) ) {
            $query->set( 'posts_per_page', (int) get_option( 'posts_per_page' ) );
            return;
        }

        // 6) Service Category archives
        if ( $query->is_tax( 'service_category' ) ) {

            $ppp  = (int) get_option( 'posts_per_page' );
            $term = get_queried_object();

            // Safety
            if ( ! $term || is_wp_error( $term ) ) {
                return;
            }

            // Is this a "problem-signs" branch?
            $is_problem_branch = ( $term->slug === 'problem-signs' );

            if ( ! $is_problem_branch && $term->parent ) {
                $ancestors = get_ancestors( $term->term_id, 'service_category', 'taxonomy' );
                foreach ( $ancestors as $ancestor_id ) {
                    $ancestor = get_term( $ancestor_id, 'service_category' );
                    if ( $ancestor && ! is_wp_error( $ancestor ) && $ancestor->slug === 'problem-signs' ) {
                        $is_problem_branch = true;
                        break;
                    }
                }
            }

            if ( $is_problem_branch ) {
                // /.../problem-signs/ -> only problems, NO child terms
                $query->set( 'post_type', [ 'silo_problem' ] );
                $query->set( 'posts_per_page', $ppp );
                $query->set( 'tax_query', [
                    [
                        'taxonomy'         => 'service_category',
                        'field'            => 'term_id',
                        'terms'            => (int) $term->term_id,
                        'include_children' => false, // key
                    ],
                ] );
                return;
            }

            // (Optional) If you have a similar top-level "solutions" branch
            if ( $term->slug === 'solutions' ) {
                $query->set( 'post_type', [ 'silo_solution' ] );
                $query->set( 'posts_per_page', $ppp );
                $query->set( 'tax_query', [
                    [
                        'taxonomy'         => 'service_category',
                        'field'            => 'term_id',
                        'terms'            => (int) $term->term_id,
                        'include_children' => false,
                    ],
                ] );
                return;
            }

            // Default: main service nodes -> a mix (but still strictly filtered to the CURRENT term)
            $query->set( 'post_type', [ 'silo_service', 'silo_problem', 'silo_solution' ] );
            $query->set( 'posts_per_page', $ppp );
            $query->set( 'tax_query', [
                [
                    'taxonomy'         => 'service_category',
                    'field'            => 'term_id',
                    'terms'            => (int) $term->term_id,
                    'include_children' => false, // prevents leaking of children on the parent listing
                ],
            ] );
            return;
        }
    }
}