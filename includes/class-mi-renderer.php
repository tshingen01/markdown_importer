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
        $work = self::replace_image_tags($work);
        $work = self::replace_external_links_with_targets($work);
        $work = self::replace_article_keyword_links_with_targets($work);
        if (! class_exists('Parsedown')) {
            return '<div class="mi-article"><pre>' . esc_html($work) . '</pre></div>';
        }
        $p = new Parsedown();
        $p->setSafeMode(false);
        $inner = $p->text($work);
        $inner = wp_kses(self::sanitize_output_html($inner), self::allowed_html());
        return $inner;
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
    /**
     * [[image::alt text::filename.ext]] — full form (only this [[image::…]] prefix is an image, never [[keyword::…]]).
     * Alt optional: [[image::::filename.ext]] (empty alt: inner begins with "::", then basename).
     * Shorthand: [[image::filename.ext]] when the basename contains no "::" (whole inner is the file).
     */
    private static function replace_image_tags($text)
    {
        $cb = function ($alt, $file, $caption = '', $size = '', $align = '', $url = '', $target = '') {
            $error = '';
            $file = trim($file);
            $alt = trim($alt);
            $caption = trim($caption);
            $size = trim($size);
            $width = 'auto';
            $height = 'auto';
            if(strtolower($size) === 'auto') $width = '100%';
            if ($size !== '' && strtolower($size) !== 'full' && strtolower($size) !== 'auto'){
                $size = explode('x', trim($size));
                $width = trim($size[0]);
                $height = trim($size[1]);
                if (! ctype_digit($width) || ! ctype_digit($height)) {
                    $error = 'Syntax Error';
                }else {
                    $width .= 'px';
                    $height .= 'px';
                }
            }
            
            $align = strtolower(trim($align));
            
            $url = trim($url);
            $target = trim($target);
            
            if(! in_array($target, ['','self', 'blank', 'parent', 'top', 'unfencedTop', 'results'], true)) {
                $error = 'Syntax Error';
            }
            if(in_array($target, ['blank', 'parent', 'top', 'unfencedTop', 'results'], true)) {
                $target = '_' . $target;
            }    
            if ($file === '') {
                $error = 'Syntax Error';
            }
            if($error) {
                return '<div>'.  $error . '</div>';
            }
            $src = '';
            $att = self::find_attachment_by_basename($file);
            if ($att) {
                $src = wp_get_attachment_url($att);
            }
            if (! $src) {
                return null;
            }
            $a_tag_start = '<a href="' . esc_url($url) . '" target="' . esc_attr($target ?: '_self') . '">';
            $a_tag_end = '</a>';
            
            return '<figure class="wp-block-image size-full" style="text-align: ' . esc_attr($align) . ';">' . ($url ? $a_tag_start : '') . '<img src="' . esc_url($src) . '" alt="' . esc_attr($alt) . '" class="mi-inline-image" width="'. $width .'" height="'. $height .'" loading="lazy" />' .($url ? $a_tag_end : '') . '<figcaption>'. esc_html($caption) . '</figcaption></figure>';
        };
        return preg_replace_callback(
            '/\[\[(?i)image::([^\]]+)\]\]/u',
            function ($m) use ($cb) {
                $inner = trim($m[1]);
                if ($inner === '') {
                    return $m[0];
                }
                if (strpos($inner, '::') === false) {
                    return $cb($inner, '');
                }
                $parts = explode('::', $inner);
                $alt = isset($parts[0]) ? trim($parts[0]) : '';
                $file = isset($parts[1]) ? trim($parts[1]) : '';
                if ($file === '') {
                    return $m[0];
                }
                $caption = isset($parts[2]) ? trim($parts[2]) : '';
                $n=3;
                $size = count($parts) > $n ? trim($parts[$n]) : '';
                $size = explode('x', trim($size));
                if (strtolower($size[0]) === 'full' || strtolower($size[0]) === 'auto'){
                    $n += 1;
                    $size = $size[0];
                }else{
                    if (count($size) === 1) $size = explode('X', trim($size[0]));
                    if (count($size) > 1) {
                        $width = trim($size[0]);
                        $height = trim($size[1]);
                        if (! ctype_digit($width) || ! ctype_digit($height)) {
                            $size = '';
                        }else {
                            $n += 1;
                            $size = trim($size[0]) . 'x' . trim($size[1]);
                        }
                    }
                    else {
                        $size = '';
                    }
                }
                
                $align = count($parts) > $n ? trim($parts[$n]) : '';
                if (in_array($align, ['left', 'center', 'right'], true)) {
                    $n += 1;
                } else $align = '';
                $url = count($parts) > $n ? trim($parts[$n]) : '';
                $target = count($parts) > $n + 1 ? trim($parts[$n + 1]) : 'self';
                if(in_array($url, ['self', 'blank', 'parent', 'top', 'unfencedTop', 'results'], true)) {
                    return 'Syntax Error';
                }
                return $cb($alt, $file, $caption, $size, $align, $url, $target);
            },
            $text
        );
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
                $code = str_replace("\n", "", $cta['code']);
                if ($cta === null || $cta === '') {
                    return '<!-- missing CTA:' . esc_html($name) . ' -->';
                }
                return wp_kses(self::sanitize_output_html($code), self::allowed_html());
            },
            $text
        );
    }

    /**
     * [[keyword::title::target]] → internal mi_article link (runs after CTA / image / external).
     * Example: [[why-should-i-invest-in-crypto::Invest in Bitcoin now::self]], [[Bitcoin::Bitcoin::self]]
     *
     * Uses first/last "::" inside the brackets so the label may contain "::" and the keyword may contain ":".
     */
    private static function replace_article_keyword_links_with_targets($text)
    {
        return preg_replace_callback(
            '/\[\[([^\]]+)\]\]/u',
            function ($m) {
                $inner = trim($m[1]);
                if ($inner === '' || substr_count($inner, '::') < 2) {
                    return $m[0];
                }
                $lower = strtolower($inner);
                if (strpos($lower, 'cta::') === 0 || strpos($lower, 'image::') === 0) {
                    return $m[0];
                }
                if (preg_match('/^(?:https?:\/\/|mailto:)/i', $inner)) {
                    return $m[0];
                }
                $last = strrpos($inner, '::');
                if ($last === false) {
                    return $m[0];
                }
                $target = trim(substr($inner, $last + 2));
                $before = trim(substr($inner, 0, $last));
                $first = strpos($before, '::');
                if ($first === false || $first === 0) {
                    return $m[0];
                }
                $key = trim(substr($before, 0, $first));
                $label = trim(substr($before, $first + 2));
                if ($key === '' || $label === '' || $target === '') {
                    return $m[0];
                }
                $html = self::build_article_keyword_link_html($key, $target, $label);
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
        if (! is_post_publicly_viewable($post) && ! current_user_can('read_post', $pid)) {
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
        if (in_array($post->post_status, ['draft', 'future'], true) && current_user_can('read_post', $post->ID)) {
            return;
        }
        global $wp_query;
        $wp_query->set_404();
        status_header(404);
        nocache_headers();
    }
}
