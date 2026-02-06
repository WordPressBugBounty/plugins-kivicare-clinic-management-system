<?php

namespace App\baseClasses;

/**
 * Class for creating posts during plugin activation
 */
defined('ABSPATH') or die('Something went wrong');

final class KCPostCreator
{
    public static function createShortcodePost(){
        $posts = apply_filters(
            'kivicare_create_posts',
            array(
                'appointment'           => array(
                    'name'    => _x( 'appointment', 'Post slug', 'kivicare-clinic-management-system' ),
                    'title'   => _x( 'Appointment', 'Post title', 'kivicare-clinic-management-system' ),
                    'content' => '<!-- wp:shortcode -->[kivicareBookAppointment]<!-- /wp:shortcode -->',
                    'search_value' => '[kivicareBookAppointment]'
                ),
                'register_login'       => array(
                    'name'    => _x( 'register-login', 'Post slug', 'kivicare-clinic-management-system' ),
                    'title'   => _x( 'Register Login user', 'Post title', 'kivicare-clinic-management-system' ),
                    'content' => '<!-- wp:shortcode -->[kivicareRegisterLogin]<!-- /wp:shortcode -->',
                    'search_value' => '[kivicareRegisterLogin]'
                ),
            )
        );

        foreach ( $posts as $key => $post ) {
            self::createPost(
                esc_sql( $post['name'] ),
                'kivicare_' . $key . '_page_id',
                $post['title'],
                $post['content'],
                ! empty( $post['post_status'] ) ? $post['post_status'] : 'publish',
                $post['search_value']
            );
        }
    }

    public static function createPost( $slug, $option = '', $post_title = '', $post_content = '', $post_status = 'publish',$searchValue ='' ) {
        global $wpdb;

        $option_value = get_option( $option );

        if ( $option_value > 0 ) {
            $post_object = get_post( $option_value );
            if ( $post_object && 'page' === $post_object->post_type && ! in_array( $post_object->post_status, array( 'pending', 'trash', 'future', 'auto-draft' ), true ) ) {
                // Valid post is already in place.
                return;
            }
        }

        if ( strlen( $searchValue ) > 0 ) {
            // Search for an existing post with the specified post content (typically a shortcode).
            $valid_post_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$searchValue}%" ) );
            if(!empty($valid_post_found)){
                update_option( $option, $valid_post_found );
                return ;
            }
        }


        // Search for a matching valid trashed post.
        if ( strlen( $searchValue ) > 0 ) {
            // Search for an existing post with the specified post content (typically a shortcode).
            $trashed_post_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$searchValue}%" ) );
        } else {
            // Search for an existing post with the specified post slug.
            $trashed_post_found = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) );
        }

        if ( !empty($trashed_post_found) ) {
            $post_id   = $trashed_post_found;
            $post_data = array(
                'ID'          => $post_id,
                'post_status' => $post_status,
            );
            wp_update_post( $post_data );
        } else {
            $post_data = array(
                'post_status'    => $post_status,
                'post_type'      => 'page',
                'post_author'    => get_current_user_id(),
                'post_name'      => $slug,
                'post_title'     => $post_title,
                'post_content'   => $post_content,
                'comment_status' => 'closed',
            );
            $post_id   = wp_insert_post( $post_data );
        }

        update_option( $option, $post_id );

        return;
    }
}