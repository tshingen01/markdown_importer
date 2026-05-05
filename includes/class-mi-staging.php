<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Staging
{
    const META_KEY = 'mi_staging_queue';

    public static function get_queue($user_id)
    {
        $q = get_user_meta($user_id, self::META_KEY, true);
        return is_array($q) ? $q : [];
    }

    public static function save_queue($user_id, array $queue)
    {
        update_user_meta($user_id, self::META_KEY, $queue);
    }

    public static function clear_queue($user_id)
    {
        delete_user_meta($user_id, self::META_KEY);
    }

    public static function make_item_id()
    {
        return wp_generate_password(12, false, false);
    }

    /**
     * Serialize release for admin fields: YYYY-MM-DD or "now" (from [[now]] in .md).
     */
    public static function release_for_form($normalized)
    {
        $n = (string) $normalized;
        if (strtolower($n) === 'now' || $n === 'not') {
            return 'now';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $n)) {
            return $n;
        }
        return 'now';
    }

    public static function parse_release_input($value)
    {
        $v = trim((string) $value);
        if ($v === '') {
            return 'now';
        }
        if (strtolower($v) === 'now' || preg_match('/^\[\[\s*now\s*\]\]$/iu', $v)) {
            return 'now';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        $ts = strtotime($v);
        if ($ts !== false) {
            return gmdate('Y-m-d', $ts);
        }
        return 'now';
    }

    public static function post_date_from_release($normalized)
    {
        $raw = (string) $normalized;
        $low = strtolower($raw);
        if ($low === 'now' || $low === 'not') {
            return current_time('mysql');
        }
        $tz = wp_timezone();
        try {
            $d = new DateTimeImmutable($raw . ' 12:00:00', $tz);
        } catch (Exception $e) {
            return current_time('mysql');
        }
        return $d->format('Y-m-d H:i:s');
    }
}
