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
