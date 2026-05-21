<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Post_Type
{
    const POST_TYPE = 'mi_article';

    /** @var bool */
    private static $hooks_added = false;

    public static function register()
    {
        $labels = [
            'name' => __('Markdown Articles', 'markdown-importer'),
            'singular_name' => __('Markdown Article', 'markdown-importer'),
            'menu_name' => __('Markdown Articles', 'markdown-importer'),
        ];

        register_post_type(
            self::POST_TYPE,
            [
                'labels' => $labels,
                'public' => true,
                'publicly_queryable' => true,
                'exclude_from_search' => false,
                'show_ui' => true,
                /* Submenu under Posts; merged into main Posts list via posts_where (keep query post_type as string "post" for core screens). */
                'show_in_menu' => 'edit.php',
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'comments', 'trackbacks', 'revisions', 'author'],
                'has_archive' => false,
                /* Root URLs are handled via post_type_link + template_redirect (no /mi-article/ segment). */
                'rewrite' => false,
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]
        );

        register_taxonomy_for_object_type(
            'post_tag',
            self::POST_TYPE
        );

        register_taxonomy_for_object_type(
            'category',
            self::POST_TYPE
        );

        $post_tags = get_terms([
            'taxonomy'   => 'post_tag',
            'hide_empty' => false,
        ]);

        wp_update_term_count_now(
            wp_list_pluck($post_tags, 'term_taxonomy_id'),
            'post_tag',
        );

        $categories = get_terms([
            'taxonomy'   => 'category',
            'hide_empty' => false,
        ]);

        wp_update_term_count_now(
            wp_list_pluck($categories, 'term_taxonomy_id'),
            'category',
        );

        self::register_hooks();
    }

    private static function register_hooks()
    {
        if (self::$hooks_added) {
            return;
        }
        self::$hooks_added = true;

        add_filter('post_type_link', [self::class, 'filter_post_type_link'], 10, 2);
        add_action('template_redirect', [self::class, 'redirect_legacy_mi_article_url'], -1);
        add_action('template_redirect', [self::class, 'resolve_article_from_root_permalink'], 0);
        add_action('pre_get_posts', function ($query) {
            if (
                is_admin()
                && $query->is_main_query()
            ) {
                global $pagenow;
                if (
                    $pagenow === 'edit.php'
                    && $query->get('post_type') === 'post'
                    ) {
                        $query->set(
                            'post_type',
                            ['post', MI_Post_Type::POST_TYPE]
                        );
                    }
                }
            if (
                is_home()
                || is_search()
                || is_category()
                || is_tag()
                || is_author()
            ) {

                $query->set(
                    'post_type',
                    [
                        'post',
                        MI_Post_Type::POST_TYPE
                    ]
                );
            }
        }, 99, 2);
    }

    /**
     * Pretty permalink without /mi-article/ — same shape as normal Posts (/slug/).
     */
    public static function filter_post_type_link($post_link, $post)
    {
        if (! $post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return $post_link;
        }
        if ($post->post_status === 'draft' || $post->post_status === 'pending' || $post->post_status === 'auto-draft') {
            return $post_link;
        }

        return home_url(user_trailingslashit($post->post_name));
    }

    /**
     * Old URLs used /mi-article/{slug}/ — redirect to root /{slug}/.
     */
    public static function redirect_legacy_mi_article_url()
    {
        if (is_admin()) {
            return;
        }
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($uri === '' || stripos($uri, 'mi-article') === false) {
            return;
        }
        if (! preg_match('#/(?:index\.php/)?mi-article/([^/]+)/?#i', $uri, $m)) {
            return;
        }
        $slug = isset($m[1]) ? trim($m[1]) : '';
        if ($slug === '') {
            return;
        }
        wp_safe_redirect(home_url(user_trailingslashit($slug)), 301);
        exit;
    }

    /**
     * Map /{slug}/ requests to mi_article when nothing else matched (404).
     */
    public static function resolve_article_from_root_permalink()
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }
        if (! is_404()) {
            return;
        }

        global $wp_query;
        if (! $wp_query->is_main_query()) {
            return;
        }

        $slug = trim((string) get_query_var('name'));
        if ($slug === '') {
            $slug = trim((string) get_query_var('pagename'));
        }
        if ($slug === '' || strpos($slug, '/') !== false) {
            return;
        }

        $article_q = new WP_Query(
            [
                'post_type' => self::POST_TYPE,
                'name' => $slug,
                'post_status' => MI_Article_Service::overview_statuses(),
                'posts_per_page' => 1,
                'no_found_rows' => true,
            ]
        );

        if (! $article_q->have_posts()) {
            return;
        }

        $post = $article_q->posts[0];

        $wp_query->posts = $article_q->posts;
        $wp_query->post_count = count($article_q->posts);
        $wp_query->found_posts = (int) $article_q->found_posts;
        $wp_query->max_num_pages = (int) $article_q->max_num_pages;
        $wp_query->post = $post;
        $wp_query->queried_object = $post;
        $wp_query->queried_object_id = (int) $post->ID;

        $wp_query->is_404 = false;
        $wp_query->is_archive = false;
        $wp_query->is_search = false;
        $wp_query->is_home = false;
        $wp_query->is_posts_page = false;
        $wp_query->is_attachment = false;
        $wp_query->is_page = false;
        $wp_query->is_single = true;
        $wp_query->is_singular = true;

        $GLOBALS['post'] = $post;
        setup_postdata($post);

        status_header(200);
    }
}
