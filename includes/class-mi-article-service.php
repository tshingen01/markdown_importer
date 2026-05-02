<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Article_Service
{
    const META_KEYWORD = '_mi_keyword';
    const META_MARKDOWN = '_mi_markdown';
    const META_IMAGE_MAP = '_mi_image_map';
    const META_RELEASE = '_mi_release_display';

    /**
     * Statuses shown in Article Overview (scheduled posts use WP status "future").
     *
     * @return string[]
     */
    public static function overview_statuses()
    {
        return ['publish', 'private', 'draft', 'future'];
    }

    public static function find_post_id_by_keyword($keyword)
    {
        $q = new WP_Query(
            [
                'post_type' => MI_Post_Type::POST_TYPE,
                'post_status' => self::overview_statuses(),
                'posts_per_page' => 1,
                'meta_key' => self::META_KEYWORD,
                'meta_value' => $keyword,
                'fields' => 'ids',
                'no_found_rows' => true,
            ]
        );
        if ($q->have_posts()) {
            return (int) $q->posts[0];
        }
        return 0;
    }

    public static function create_article(array $parsed, $release_form_value, array $image_map = [])
    {
        $release = MI_Staging::parse_release_input($release_form_value);
        $post_date = MI_Staging::post_date_from_release($release);
        $status = 'publish';

        $post_id = wp_insert_post(
            [
                'post_type' => MI_Post_Type::POST_TYPE,
                'post_title' => $parsed['title'],
                'post_name' => $parsed['slug'],
                'post_status' => $status,
                'post_content' => '',
                'post_excerpt' => $parsed['meta_description'],
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date($post_date),
            ],
            true
        );

        if (is_wp_error($post_id)) {
            return $post_id;
        }

        self::attach_meta($post_id, $parsed, $release, $image_map);

        return $post_id;
    }

    /**
     * Sideload image files from a directory and attach map basenames -> attachment IDs.
     *
     * @return array<string,int>
     */
    public static function import_images_for_post($post_id, $directory, $markdown = '')
    {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || ! is_dir($directory)) {
            return [];
        }
        $wanted = self::image_filenames_from_markdown((string) $markdown);
        $import_all = $wanted === [];
        $map = [];
        $dir = rtrim($directory, '/\\');
        $patterns = ['*.png', '*.jpg', '*.jpeg', '*.gif', '*.webp', '*.svg'];
        foreach ($patterns as $pat) {
            $found = glob($dir . DIRECTORY_SEPARATOR . $pat);
            if (! is_array($found)) {
                continue;
            }
            foreach ($found as $path) {
                $base = basename($path);
                if (preg_match('/\.md$/i', $base)) {
                    continue;
                }
                if (! $import_all && ! isset($wanted[$base])) {
                    continue;
                }
                $id = self::sideload_image_file($path, $post_id, $base);
                if (! is_wp_error($id) && (int) $id > 0) {
                    $map[$base] = (int) $id;
                }
            }
        }
        if ($map !== []) {
            $merged = self::merge_image_map($post_id, $map);
            update_post_meta($post_id, self::META_IMAGE_MAP, $merged);
        }
        return $map;
    }

    /**
     * @return array<string,bool> basenames used in image syntax
     */
    private static function image_filenames_from_markdown($markdown)
    {
        $out = [];
        if ($markdown === '') {
            return $out;
        }
        if (preg_match_all('/\[\[image::([^:]+)::([^:]+)::([^\]]+)\]\]/u', $markdown, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                if (! empty($row[3])) {
                    $out[trim($row[3])] = true;
                }
            }
        }
        if (preg_match_all('/\[\[image::([^:]+):::([^\]]+)\]\]/u', $markdown, $m2, PREG_SET_ORDER)) {
            foreach ($m2 as $row) {
                if (! empty($row[2])) {
                    $out[trim($row[2])] = true;
                }
            }
        }
        return $out;
    }

    public static function update_article($post_id, array $parsed, $release_form_value, array $image_map = [])
    {
        $release = MI_Staging::parse_release_input($release_form_value);
        $post_date = MI_Staging::post_date_from_release($release);

        $updated = wp_update_post(
            [
                'ID' => $post_id,
                'post_title' => $parsed['title'],
                'post_name' => $parsed['slug'],
                'post_excerpt' => $parsed['meta_description'],
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date($post_date),
            ],
            true
        );

        if (is_wp_error($updated)) {
            return $updated;
        }

        self::attach_meta($post_id, $parsed, $release, $image_map);
        return $post_id;
    }

    public static function attach_meta($post_id, array $parsed, $release_normalized, array $image_map)
    {
        update_post_meta($post_id, self::META_KEYWORD, $parsed['keyword']);
        update_post_meta($post_id, self::META_MARKDOWN, $parsed['markdown']);
        update_post_meta($post_id, self::META_RELEASE, $release_normalized);
        update_post_meta($post_id, '_mi_meta_description', $parsed['meta_description']);

        $merged = self::merge_image_map($post_id, $image_map);
        update_post_meta($post_id, self::META_IMAGE_MAP, $merged);
    }

    private static function merge_image_map($post_id, array $new_map)
    {
        $old = get_post_meta($post_id, self::META_IMAGE_MAP, true);
        if (! is_array($old)) {
            $old = [];
        }
        foreach ($new_map as $k => $v) {
            if ((int) $v > 0) {
                $old[$k] = (int) $v;
            }
        }
        return $old;
    }

    public static function set_visibility($post_id, $public)
    {
        $status = $public ? 'publish' : 'private';
        wp_update_post(
            [
                'ID' => (int) $post_id,
                'post_status' => $status,
            ]
        );
    }

    public static function get_article_payload($post_id)
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
            return null;
        }
        $kw = (string) get_post_meta($post_id, self::META_KEYWORD, true);
        $md = (string) get_post_meta($post_id, self::META_MARKDOWN, true);
        $rel = (string) get_post_meta($post_id, self::META_RELEASE, true);
        $meta = (string) get_post_meta($post_id, '_mi_meta_description', true);
        if ($meta === '') {
            $meta = $post->post_excerpt;
        }

        return [
            'id' => (int) $post_id,
            'keyword' => $kw,
            'slug' => $post->post_name,
            'title' => $post->post_title,
            'meta_description' => $meta,
            'markdown' => $md,
            'release_date' => MI_Staging::release_for_form($rel !== '' ? $rel : 'now'),
            'visibility' => $post->post_status === 'private' ? 'private' : 'public',
        ];
    }

    public static function save_article_from_request($post_id, $title, $slug, $meta_description, $markdown, $release_date, $visibility)
    {
        $slug = sanitize_title($slug);
        $release = MI_Staging::parse_release_input($release_date);
        $post_date = MI_Staging::post_date_from_release($release);
        $status = $visibility === 'private' ? 'private' : 'publish';

        wp_update_post(
            [
                'ID' => $post_id,
                'post_title' => $title,
                'post_name' => $slug,
                'post_excerpt' => $meta_description,
                'post_status' => $status,
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date($post_date),
            ]
        );
        update_post_meta($post_id, self::META_MARKDOWN, $markdown);
        update_post_meta($post_id, self::META_RELEASE, $release);
        update_post_meta($post_id, '_mi_meta_description', $meta_description);
    }

    public static function sideload_image_file($file_path, $post_id, $filename_hint = '')
    {
        if (! file_exists($file_path)) {
            return new WP_Error('missing', 'File not found');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $name = $filename_hint !== '' ? $filename_hint : basename($file_path);
        $tmp = wp_tempnam($name);
        if (! $tmp) {
            return new WP_Error('tmp', 'Temp file');
        }
        copy($file_path, $tmp);
        $file_array = [
            'name' => $name,
            'tmp_name' => $tmp,
        ];
        $id = media_handle_sideload($file_array, $post_id);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return $id;
        }
        return (int) $id;
    }
}
