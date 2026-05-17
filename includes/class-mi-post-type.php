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
            'name'          => __('Markdown Articles', 'markdown-importer'),
            'singular_name' => __('Markdown Article', 'markdown-importer'),
            'menu_name'     => __('Markdown Articles', 'markdown-importer'),
        ];

        register_post_type(
            self::POST_TYPE,
            [
                'labels' => $labels,

                'public' => true,
                'publicly_queryable' => true,
                'exclude_from_search' => false,

                'show_ui' => true,
                'show_in_menu' => 'edit.php',

                'supports' => [
                    'title',
                    'editor',
                    'thumbnail',
                    'excerpt',
                    'comments',
                    'trackbacks'
                ],

                'taxonomies' => [
                    'category',
                    'post_tag',
                ],

                'has_archive' => false,

                /* Root URLs handled manually */
                'rewrite' => false,

                'capability_type' => 'post',
                'map_meta_cap' => true,

                /*
                 * Show:
                 * - Allow Comments
                 * - Allow Pings
                 * - Make this post sticky
                 */
                'register_meta_box_cb' => [self::class, 'register_meta_boxes'],
            ]
        );

        self::register_hooks();
    }

    /**
     * Register meta boxes
     */
    public static function register_meta_boxes()
    {
        add_meta_box(
            'commentstatusdiv',
            __('Discussion'),
            'post_comment_status_meta_box',
            self::POST_TYPE,
            'normal',
            'core'
        );
    }

    /**
     * Register hooks
     */
    private static function register_hooks()
    {
        if (self::$hooks_added) {
            return;
        }

        self::$hooks_added = true;

        add_filter('post_type_link', [self::class, 'filter_post_type_link'], 10, 2);

        add_action('template_redirect', [self::class, 'redirect_legacy_mi_article_url'], -1);

        add_action('template_redirect', [self::class, 'resolve_article_from_root_permalink'], 0);

        add_filter('posts_where', [self::class, 'merge_mi_article_in_posts_admin_sql'], 99, 2);

    }


    /**
     * Pretty permalink without /mi-article/
     */
    public static function filter_post_type_link($post_link, $post)
    {
        if (! $post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return $post_link;
        }

        if (
            $post->post_status === 'draft'
            || $post->post_status === 'pending'
            || $post->post_status === 'auto-draft'
        ) {
            return $post_link;
        }

        return home_url(user_trailingslashit($post->post_name));
    }

    /**
     * Redirect old /mi-article/slug/
     */
    public static function redirect_legacy_mi_article_url()
    {
        if (is_admin()) {
            return;
        }

        $uri = isset($_SERVER['REQUEST_URI'])
            ? (string) wp_unslash($_SERVER['REQUEST_URI'])
            : '';

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

        wp_safe_redirect(
            home_url(user_trailingslashit($slug)),
            301
        );

        exit;
    }

    /**
     * Resolve /slug/ to mi_article
     */
    public static function resolve_article_from_root_permalink()
    {
        if (
            is_admin()
            || wp_doing_ajax()
            || wp_doing_cron()
        ) {
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
                'post_type'      => self::POST_TYPE,
                'name'           => $slug,
                'post_status'    => ['publish', 'future', 'draft', 'pending'],
                'posts_per_page' => 1,
                'no_found_rows'  => true,
            ]
        );

        if (! $article_q->have_posts()) {
            return;
        }

        $post = $article_q->posts[0];

        $wp_query->posts             = $article_q->posts;
        $wp_query->post_count        = count($article_q->posts);
        $wp_query->found_posts       = (int) $article_q->found_posts;
        $wp_query->max_num_pages     = (int) $article_q->max_num_pages;
        $wp_query->post              = $post;
        $wp_query->queried_object    = $post;
        $wp_query->queried_object_id = (int) $post->ID;

        $wp_query->is_404        = false;
        $wp_query->is_archive    = false;
        $wp_query->is_search     = false;
        $wp_query->is_home       = false;
        $wp_query->is_posts_page = false;
        $wp_query->is_attachment = false;
        $wp_query->is_page       = false;
        $wp_query->is_single     = true;
        $wp_query->is_singular   = true;

        $GLOBALS['post'] = $post;

        setup_postdata($post);

        status_header(200);
    }

    /**
     * Merge CPT into Posts admin screen
     */
    public static function merge_mi_article_in_posts_admin_sql($where, $query)
    {
        if (! is_admin() || ! $query->is_main_query()) {
            return $where;
        }

        global $pagenow;

        if ($pagenow !== 'edit.php') {
            return $where;
        }

        $pt = isset($_REQUEST['post_type'])
            ? sanitize_key(wp_unslash($_REQUEST['post_type']))
            : '';

        if ($pt !== '' && $pt !== 'post') {
            return $where;
        }

        if ($query->get('post_type') !== 'post') {
            return $where;
        }

        global $wpdb;

        $t  = $wpdb->posts;
        $mi = esc_sql(self::POST_TYPE);

        if (
            strpos($where, "{$t}.post_type = '{$mi}'") !== false
            || strpos($where, "{$t}.post_type = \"{$mi}\"") !== false
        ) {
            return $where;
        }

        $pair = "({$t}.post_type = 'post' OR {$t}.post_type = '{$mi}')";

        $search = "{$t}.post_type = 'post'";

        if (strpos($where, $search) !== false) {
            return str_replace($search, $pair, $where);
        }

        $search_in = "{$t}.post_type IN ('post')";

        if (strpos($where, $search_in) !== false) {
            return str_replace(
                $search_in,
                "{$t}.post_type IN ('post','{$mi}')",
                $where
            );
        }

        return $where;
    }
}