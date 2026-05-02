<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Renderer
{
    public static function head_meta()
    {
        $post_type = get_post_type();
        if (! is_singular($post_type)) {
            return;
        }
        $post = get_post();
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
        $post_type = get_post_type();
        if (! is_singular($post_type)) {
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
        $html = self::markdown_to_html($md, $post->ID);
        return $html;
    }

    public static function markdown_to_html($markdown, $post_id)
    {
        $work = (string) $markdown;
        $work = self::replace_cta_tags($work);
        $work = self::replace_image_tags($work, $post_id);
        $work = self::replace_article_keyword_links($work, (int) $post_id);
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
            '/\[\[CTA::([^\]]+)\]\]/iu',
            function ($m) {
                $name = trim($m[1]);
                $cta = MI_Cta::get_by_name($name);
                if ($cta === null || $cta === '') {
                    return '<!-- missing CTA:' . esc_html($name) . ' -->';
                }
                return $cta['code'];
            },
            $text
        );
    }

    /**
     * [[Keyword]] → link to the mi_article with that keyword (no "::" in token — use [[CTA::x]] / [[image::…]] first).
     */
    private static function replace_article_keyword_links($text, $current_post_id)
    {
        $post_type = get_post_type();
        return preg_replace_callback(
            '/\[\[([^:\[\]]+)\]\]/u',
            function ($m) use ($current_post_id, $post_type) {
                $key = trim($m[1]);
                if ($key === '') {
                    return $m[0];
                }
                $pid = MI_Article_Service::find_post_id_by_keyword($key, 0);
                if ($pid <= 0) {
                    return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
                }
                $post = get_post($pid);
                if (! $post || $post->post_type !== $post_type) {
                    return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
                }
                if ($post->post_status === 'private' && ! current_user_can('read_post', $pid)) {
                    return '<span class="mi-private-article-link">' . esc_html($key) . '</span>';
                }
                $url = get_permalink($pid);
                $title = get_the_title($pid);
                $label = $title !== '' ? $title : $key;
                return '<a href="' . esc_url($url) . '" class="mi-article-link">' . esc_html($label) . '</a>';
            },
            $text
        );
    }

    /**
     * Block front-end access unless the post is publicly viewable (e.g. publish) or the user may read it (private/draft/public for editors).
     * Guests can view Public articles without logging in.
     */
    public static function template_redirect_private_article()
    {
        $post_type = get_post_type();
        if (! is_singular($post_type)) {
            return;
        }
        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }
        if (is_post_publicly_viewable($post)) {
            return;
        }
        if (current_user_can('read_post', $post->ID)) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}
