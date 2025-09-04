<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class SignatureMedia_Silo_Query {

    public function __construct() {
        // Izvrši POSLE rewrite logike
        add_action( 'pre_get_posts', [ $this, 'adjust_main_query' ], 20 );
    }

    /**
     * Podešava glavni query za silo arhive na frontend-u.
     */
    public function adjust_main_query( $query ) {

        // 1) Ograniči samo na MAIN QUERY na frontendu (da ne diramo admin/secondary upite)
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        // 2) Ne diraj single kontekste i specifične rute (ovo si imao, samo premešteno ispod main-query provere)
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

        // 5) Locations archive – koristi METODU nad query objektom (preciznije od globalnog conditional-a)
        if ( $query->is_post_type_archive( 'locations' ) ) {
            $query->set( 'posts_per_page', (int) get_option( 'posts_per_page' ) );
            return;
        }

        // 6) Service Category arhive
        if ( $query->is_tax( 'service_category' ) ) {

            $ppp  = (int) get_option( 'posts_per_page' );
            $term = get_queried_object();

            // Safety
            if ( ! $term || is_wp_error( $term ) ) {
                return;
            }

            // Da li smo u “problem-signs” grani?
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
                // /.../problem-signs/ → samo problemi, BEZ child termina
                $query->set( 'post_type', [ 'silo_problem' ] );
                $query->set( 'posts_per_page', $ppp );
                $query->set( 'tax_query', [
                    [
                        'taxonomy'         => 'service_category',
                        'field'            => 'term_id',
                        'terms'            => (int) $term->term_id,
                        'include_children' => false, // ključno
                    ],
                ] );
                return;
            }

            // (Opcionalno) Ako imaš sličnu top-granu “solutions”
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

            // Default: glavni service čvorovi → miks (ali i dalje strogo filtriran na AKTUELNI termin)
            $query->set( 'post_type', [ 'silo_service', 'silo_problem', 'silo_solution' ] );
            $query->set( 'posts_per_page', $ppp );
            $query->set( 'tax_query', [
                [
                    'taxonomy'         => 'service_category',
                    'field'            => 'term_id',
                    'terms'            => (int) $term->term_id,
                    'include_children' => false, // sprečava leak child-ova na parent listingu
                ],
            ] );
            return;
        }
    }
}
