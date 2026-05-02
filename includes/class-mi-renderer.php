<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Renderer
{
    public static function head_meta()
    {
        if (! is_singular(MI_Post_Type::POST_TYPE)) {
            return;
        }
        $post = get_queried_object();
        if (! $post || empty($post->ID)) {
            return;
        }
        $desc = (string) get_post_meta($post->ID, '_mi_meta_description', true);
        if ($desc === '') {
            $desc = (string) $post->post_excerpt;
        }
        if ($desc !== '') {
            echo '<meta name="description" content="' . esc_attr(wp_strip_all_tags($desc)) . '" />' . "\n";
        }
    }

    public static function filter_content($content)
    {
        if (! is_singular(MI_Post_Type::POST_TYPE)) {
            return $content;
        }
        $post = get_post();
        if (! $post) {
            return $content;
        }
        $md = get_post_meta($post->ID, '_mi_markdown', true);
        if (! is_string($md) || $md === '') {
            return $content;
        }
        $keyword = (string) get_post_meta($post->ID, '_mi_keyword', true);
        $html = self::markdown_to_html($md, $post->ID, $keyword);
        return $html;
    }

    public static function markdown_to_html($markdown, $post_id, $keyword = '')
    {
        $work = (string) $markdown;
        if ($keyword !== '') {
            $work = str_replace('[[' . $keyword . ']]', esc_html($keyword), $work);
        }
        $work = self::replace_image_tags($work, $post_id);
        $work = self::replace_cta_tags($work);
        if (! class_exists('Parsedown')) {
            return '<div class="mi-article"><pre>' . esc_html($work) . '</pre></div>';
        }
        $p = new Parsedown();
        $p->setSafeMode(false);
        $inner = $p->text($work);
        return '<div class="mi-article-content">' . $inner . '</div>';
    }

    private static function replace_image_tags($text, $post_id)
    {
        $map = get_post_meta($post_id, '_mi_image_map', true);
        if (! is_array($map)) {
            $map = [];
        }
        $cb = function ($file, $alt) use ($map) {
            $file = trim($file);
            $alt = trim($alt);
            if ($file === '') {
                return '';
            }
            $url = '';
            if (isset($map[$file]) && (int) $map[$file] > 0) {
                $url = wp_get_attachment_url((int) $map[$file]);
            }
            if (! $url) {
                $att = self::find_attachment_by_basename($file);
                if ($att) {
                    $url = wp_get_attachment_url($att);
                }
            }
            if (! $url) {
                return '<span class="mi-missing-image">' . esc_html($file) . '</span>';
            }
            return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" class="mi-inline-image" loading="lazy" />';
        };
        $text = preg_replace_callback(
            '/\[\[image::([^:]+)::([^:]+)::([^\]]+)\]\]/u',
            function ($m) use ($cb) {
                return $cb($m[3], $m[2]);
            },
            $text
        );
        $text = preg_replace_callback(
            '/\[\[image::([^:]+):::([^\]]+)\]\]/u',
            function ($m) use ($cb) {
                return $cb($m[2], '');
            },
            $text
        );
        return $text;
    }

    private static function find_attachment_by_basename($filename)
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($filename);
        $id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
                $like
            )
        );
        return $id > 0 ? $id : 0;
    }

    private static function replace_cta_tags($text)
    {
        return preg_replace_callback(
            '/\[\[CTA::([^\]]+)\]\]/u',
            function ($m) {
                $name = trim($m[1]);
                $cta = MI_Cta::get_by_name($name);
                if ($cta === null || $cta === '') {
                    return '<!-- missing CTA:' . esc_html($name) . ' -->';
                }
                return $cta;
            },
            $text
        );
    }
}
