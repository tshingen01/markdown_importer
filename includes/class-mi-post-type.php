<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Post_Type
{
    const POST_TYPE = 'mi_article';

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
                'show_in_menu' => false,
                'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
                'has_archive' => false,
                'rewrite' => ['slug' => 'mi-article', 'with_front' => false],
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]
        );
    }
}
