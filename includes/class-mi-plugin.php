<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Plugin
{
    /** @var self|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Must run on init — $wp_rewrite is null before init (add_rewrite_tag() fatal).
        add_action('init', [MI_Post_Type::class, 'register'], 0);
        add_action(
            'init',
            static function () {

                register_taxonomy_for_object_type(
                    'post_tag',
                    MI_Post_Type::POST_TYPE
                );

                register_taxonomy_for_object_type(
                    'category',
                    MI_Post_Type::POST_TYPE
                );

                if (get_option('mi_flush_rewrite_rules_flag')) {
                    flush_rewrite_rules();
                    delete_option('mi_flush_rewrite_rules_flag');
                }
            },
            99
        );
        add_action('template_redirect', [MI_Renderer::class, 'template_redirect_private_article'], 5);
        add_action('wp_head', [MI_Renderer::class, 'head_meta'], 1);
        add_filter('the_content', [MI_Renderer::class, 'filter_content'], 8);
        add_action(MI_Article_Service::CRON_HOOK_APPLY_UPGRADE, [MI_Article_Service::class, 'apply_scheduled_upgrade'], 10, 1);
        add_action('pre_get_posts', function ($query) {
            if (
                is_admin()
                || ! $query->is_main_query()
            ) {
                return;
            }
            if (
                is_tag()
                || is_category()
                || is_home()
                || is_search()
            ) {
                $query->set(
                    'post_type',
                    ['post', MI_Post_Type::POST_TYPE]
                );
            }
        }, 10);
        /**
         * Force Rank Math to use the correct post title
         */
        add_filter('rank_math/frontend/title', function($title) {
            if (is_singular(MI_Post_Type::POST_TYPE)) {
                $post = get_queried_object();
                if ($post && $post->post_title) {
                    // Manually construct the title
                    $separator = apply_filters('rank_math/frontend/title_separator', ' - ');
                    $site_name = get_bloginfo('name');
                    $new_title = $post->post_title . $separator . $site_name;
                    
                    // Log for debugging
                    error_log('MI_Renderer: Forcing title to: ' . $new_title);
                    
                    return $new_title;
                }
            }
            return $title;
        }, 999);

        /**
         * Also fix the WordPress document title
         */
        add_filter('document_title_parts', function($parts) {
            if (is_singular(MI_Post_Type::POST_TYPE)) {
                $post = get_queried_object();
                if ($post && $post->post_title) {
                    $parts['title'] = $post->post_title;
                    // Make sure site name is properly set
                    if (!isset($parts['site']) || empty($parts['site'])) {
                        $parts['site'] = get_bloginfo('name');
                    }
                }
            }
            return $parts;
        }, 999);

        /**
         * Ensure Rank Math can access the post data
         */
        add_filter('rank_math/frontend/setup_post_data', function($post) {
            if (is_singular(MI_Post_Type::POST_TYPE)) {
                global $post_obj;
                if (!$post && isset($post_obj)) {
                    $post = $post_obj;
                }
                if (!$post) {
                    $post = get_queried_object();
                }
                if ($post) {
                    error_log('MI_Renderer: Rank Math setup_post_data - Post ID: ' . $post->ID . ', Title: ' . $post->post_title);
                }
            }
            return $post;
        }, 999);

        /**
         * Alternative: Override the variables Rank Math uses
         */
        add_filter('rank_math/frontend/replacements', function($vars) {
            if (is_singular(MI_Post_Type::POST_TYPE)) {
                $post = get_queried_object();
                if ($post && $post->post_title) {
                    $vars['%title%'] = $post->post_title;
                    $vars['%title|lowercase%'] = strtolower($post->post_title);
                    $vars['%title|ucwords%'] = ucwords($post->post_title);
                    
                    // Log the replacements
                    error_log('MI_Renderer: Rank Math replacements set for title: ' . $post->post_title);
                }
            }
            return $vars;
        }, 999);
        if (is_admin()) {
            MI_Admin::instance();
            MI_Ajax::register();
        }
    }

    public static function activate()
    {
        update_option('mi_flush_rewrite_rules_flag', true);
    }
}
