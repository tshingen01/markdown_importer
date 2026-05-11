<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Cta
{
    const OPTION = 'mi_cta_blocks';

    public static function all()
    {
        $data = get_option(self::OPTION, []);
        return is_array($data) ? $data : [];
    }

    public static function get_by_name($name)
    {
        $all = self::all();
        $key = self::normalize_name($name);
        return isset($all[$key]) ? $all[$key] : null;
    }

    /**
     * Find [[CTA::name]] tags in markdown and persist stub entries for names that do not exist yet.
     */
    public static function ensure_from_markdown($markdown)
    {
        $markdown = (string) $markdown;
        if ($markdown === '') {
            return;
        }
        if (! preg_match_all('/\[\[CTA::([^\]]+)\]\]/iu', $markdown, $matches, PREG_SET_ORDER)) {
            return;
        }
        foreach ($matches as $row) {
            $name = isset($row[1]) ? trim((string) $row[1]) : '';
            if ($name === '') {
                continue;
            }
            if (self::get_by_name($name) !== null) {
                continue;
            }
            $stub = sprintf(
                '<p class="mi-cta mi-cta-placeholder">%s</p>',
                esc_html__(
                    'This call-to-action block is empty. Edit it under Markdown Importer → CTA Buttons.',
                    'markdown-importer'
                )
            );
            self::save($name, $stub);
        }
    }

    public static function save($name, $code)
    {
        $all = self::all();
        $key = self::normalize_name($name);
        if ($key === '') {
            return new WP_Error('empty', __('CTA name is required.', 'markdown-importer'));
        }
        $all[$key] = [
            'name' => $name,
            'code' => $code,
            'updated' => time(),
        ];
        update_option(self::OPTION, $all, false);
        return true;
    }

    public static function delete($name)
    {
        $all = self::all();
        $key = self::normalize_name($name);
        if (isset($all[$key])) {
            unset($all[$key]);
            update_option(self::OPTION, $all, false);
        }
        return true;
    }

    public static function list_for_admin()
    {
        $out = [];
        foreach (self::all() as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = [
                'display_name' => isset($row['name']) ? (string) "[[CTA::" . $row['name']."]]" : '',
                'name' => isset($row['name']) ? (string) $row['name'] : '',
                'code' => isset($row['code']) ? (string) $row['code'] : '',
            ];
        }
        usort(
            $out,
            function ($a, $b) {
                return strcasecmp($a['name'], $b['name']);
            }
        );
        return $out;
    }

    private static function normalize_name($name)
    {
        return strtolower(trim((string) $name));
    }
}
