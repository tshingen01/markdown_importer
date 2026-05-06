<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Renderer
{
    private static function can_view_private_articles()
    {
        return is_user_logged_in() && (current_user_can('manage_options') || current_user_can('edit_others_posts'));
    }

    private static function is_target_singular()
    {
        if (! is_singular()) {
            return false;
        }
        $post = get_queried_object();
        return $post instanceof WP_Post && $post->post_type === MI_Post_Type::POST_TYPE;
    }

    public static function head_meta()
    {
        if (! self::is_target_singular()) {
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
        if (! self::is_target_singular()) {
            return $content;
        }
        $post = get_post();
        if (! $post) {
            return $content;
        }
        // Respect native WP password protection flow.
        if (post_password_required($post)) {
            return $content !== '' ? $content : get_the_password_form($post);
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
        $work = self::replace_external_links_with_targets($work);
        $work = self::replace_article_keyword_links_three_part($work);
        $work = self::replace_article_keyword_links_with_targets($work);
        $work = self::replace_article_keyword_links($work, (int) $post_id);
        if (! class_exists('Parsedown')) {
            return '<div class="mi-article"><pre>' . esc_html($work) . '</pre></div>';
        }
        $p = new Parsedown();
        $p->setSafeMode(false);
        $inner = $p->text($work);
        $inner = wp_kses(self::sanitize_output_html($inner), self::allowed_html());
        return '<div class="mi-article-content">' . $inner . '</div>';
    }

    private static function sanitize_output_html($html)
    {
        // Remove dangerous script blocks before allowlist sanitization.
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string) $html);
        return (string) $html;
    }

    private static function allowed_html()
    {
        $allowed = wp_kses_allowed_html('post');
        if (! isset($allowed['img'])) {
            $allowed['img'] = [];
        }
        $allowed['img']['class'] = true;
        $allowed['img']['loading'] = true;
        if (! isset($allowed['a'])) {
            $allowed['a'] = [];
        }
        $allowed['a']['class'] = true;
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;
        if (! isset($allowed['span'])) {
            $allowed['span'] = [];
        }
        $allowed['span']['class'] = true;
        if (! isset($allowed['style'])) {
            $allowed['style'] = [];
        }
        $allowed['style']['type'] = true;
        $allowed['style']['media'] = true;
        return $allowed;
    }

    private static function replace_image_tags($text, $post_id)
    {
        $cb = function ($file, $alt) {
            $file = trim($file);
            $alt = trim($alt);
            if ($file === '') {
                return '';
            }
            $url = '';
            $att = self::find_attachment_by_basename($file);
            if ($att) {
                $url = wp_get_attachment_url($att);
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
                // var_dump($cta['code']);
                return wp_kses(self::sanitize_output_html($cta['code']), self::allowed_html());
            },
            $text
        );
    }

    /**
     * [[keyword::title::target]] → internal mi_article link (runs after CTA / image / external).
     * Example: [[why-should-i-invest-in-crypto::Invest in Bitcoin now::self]]
     */
    private static function replace_article_keyword_links_three_part($text)
    {
        return preg_replace_callback(
            '/\[\[([^\[\]:]+)::(.+?)::([^\]]+)\]\]/u',
            function ($m) {
                $html = self::build_article_keyword_link_html(trim($m[1]), trim($m[3]), trim($m[2]));
                return $html !== '' ? $html : $m[0];
            },
            $text
        );
    }

    /**
     * [[Keyword::target]] → link using the article title as label (legacy two-part form).
     */
    private static function replace_article_keyword_links_with_targets($text)
    {
        return preg_replace_callback(
            '/\[\[([^:\[\]]+)::([^\]]+)\]\]/u',
            function ($m) {
                $html = self::build_article_keyword_link_html(trim($m[1]), trim($m[2]), null);
                return $html !== '' ? $html : $m[0];
            },
            $text
        );
    }

    /**
     * @param string|null $link_label Explicit anchor text; null = use post title or keyword.
     */
    private static function build_article_keyword_link_html($key, $raw_target, $link_label)
    {
        $key = trim((string) $key);
        $raw_target = trim((string) $raw_target);
        if ($key === '' || $raw_target === '') {
            return '';
        }
        $pid = MI_Article_Service::find_post_id_by_keyword($key, 0);
        if ($pid <= 0) {
            return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
        }
        $post = get_post($pid);
        if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
            return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
        }
        if ($post->post_status === 'private' && ! self::can_view_private_articles()) {
            return '<span class="mi-private-article-link">' . esc_html($key) . '</span>';
        }
        $url = get_permalink($pid);
        $title = get_the_title($pid);
        $label = ($link_label !== null && $link_label !== '')
            ? $link_label
            : ($title !== '' ? $title : $key);
        return self::build_link_html($url, $label, $raw_target);
    }

    /**
     * [[Keyword]] -> link to the matching mi_article.
     */
    private static function replace_article_keyword_links($text, $current_post_id)
    {
        return preg_replace_callback(
            '/\[\[([^:\[\]]+)\]\]/u',
            function ($m) use ($current_post_id) {
                $key = trim($m[1]);
                if ($key === '') {
                    return $m[0];
                }
                $pid = MI_Article_Service::find_post_id_by_keyword($key, 0);
                if ($pid <= 0) {
                    return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
                }
                $post = get_post($pid);
                if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
                    return '<span class="mi-missing-article-link">' . esc_html($key) . '</span>';
                }
                if ($post->post_status === 'private' && ! self::can_view_private_articles()) {
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
     * [[https://example.com::Label::target]] → external link with target.
     */
    private static function replace_external_links_with_targets($text)
    {
        return preg_replace_callback(
            '/\[\[((?:https?:\/\/|mailto:)[^\]]+?)::([^:\]]+)::([^\]]+)\]\]/iu',
            function ($m) {
                $url = trim($m[1]);
                $label = trim($m[2]);
                $raw_target = trim($m[3]);
                if ($url === '' || $label === '') {
                    return $m[0];
                }
                return self::build_link_html($url, $label, $raw_target);
            },
            $text
        );
    }

    private static function normalize_target($raw_target)
    {
        $target = trim((string) $raw_target);
        if ($target === '') {
            return '_self';
        }
        $lookup = strtolower($target);
        $map = [
            'blank' => '_blank',
            '_blank' => '_blank',
            'self' => '_self',
            '_self' => '_self',
            'parent' => '_parent',
            '_parent' => '_parent',
            'top' => '_top',
            '_top' => '_top',
            'unfencedtop' => '_unfencedTop',
            '_unfencedtop' => '_unfencedTop',
        ];
        if (isset($map[$lookup])) {
            return $map[$lookup];
        }
        $safe = preg_replace('/[^A-Za-z0-9_-]/', '', $target);
        return $safe !== '' ? $safe : '_self';
    }

    private static function build_link_html($url, $label, $raw_target)
    {
        $target = self::normalize_target($raw_target);
        $rel = $target === '_blank' ? ' rel="noopener noreferrer"' : '';
        return '<a href="' . esc_url($url) . '" class="mi-article-link" target="' . esc_attr($target) . '"' . $rel . '>' . esc_html($label) . '</a>';
    }

    /**
     * Block front-end access unless the post is publicly viewable (e.g. publish) or the user may read it (private/draft/public for editors).
     * Guests can view Public articles without logging in.
     */
    public static function template_redirect_private_article()
    {
        if (! self::is_target_singular()) {
            return;
        }
        $post = get_queried_object();
        if (! $post instanceof WP_Post) {
            return;
        }
        if (is_post_publicly_viewable($post)) {
            return;
        }
        if ($post->post_status === 'private' && self::can_view_private_articles()) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}
