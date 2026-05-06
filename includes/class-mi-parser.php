<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Parser
{
    /**
     * Keyword from filename: Satoshi-Nakamoto.md -> Satoshi-Nakamoto
     */
    public static function keyword_from_filename($basename)
    {
        $name = preg_replace('/\.[^.]+$/', '', $basename);
        return $name === '' ? 'article' : $name;
    }

    /**
     * Parse MD body according to owner rules.
     *
     * @return array{ok:bool,error?:string,release_raw?:string,release_normalized?:string,meta_description?:string,slug?:string,title?:string,markdown?:string}
     */
    public static function parse_document($content, $filename_for_keyword = '')
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $content);

        if (count($lines) < 11) {
            return ['ok' => false, 'error' => __('File must have at least 11 lines (release/visibility/meta/slug/title + markdown).', 'markdown-importer')];
        }

        $line1 = trim($lines[0]);
        $release = self::parse_release_line($line1);
        if ($release === null) {
            return ['ok' => false, 'error' => __('Line 1 must be [[YYYY_MM_DD::HH_MM]] or [[now]].', 'markdown-importer')];
        }

        $visibility = self::parse_visibility_line(trim($lines[2]));
        if ($visibility === null) {
            return ['ok' => false, 'error' => __('Line 3 must be [[PRIVATE]], [[DRAFT]], [[PUBLIC]], or [[PUBLIC::password]].', 'markdown-importer')];
        }

        $meta_description = trim($lines[4]);
        $slug = trim($lines[6]);
        $title = trim($lines[8]);

        if ($slug === '' || $title === '') {
            return ['ok' => false, 'error' => __('Line 7 (slug) and line 9 (title) must not be empty.', 'markdown-importer')];
        }

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return ['ok' => false, 'error' => __('Invalid URL slug on line 7.', 'markdown-importer')];
        }

        $markdown = implode("\n", array_slice($lines, 10));
        $keyword = $filename_for_keyword !== '' ? self::keyword_from_filename($filename_for_keyword) : '';

        return [
            'ok' => true,
            'release_raw' => $release['raw'],
            'release_normalized' => $release['normalized'],
            'visibility' => $visibility['status'],
            'password' => $visibility['password'],
            'meta_description' => $meta_description,
            'slug' => $slug,
            'title' => $title,
            'markdown' => ltrim($markdown, "\n"),
            'keyword' => $keyword,
        ];
    }

    /**
     * @return array{raw:string,normalized:string}|null
     */
    private static function parse_release_line($line)
    {
        $line = trim($line);
        if (! preg_match('/^\[\[(.+)\]\]$/u', $line, $m)) {
            return null;
        }

        $inner = trim($m[1]);
        $lower = strtolower($inner);
        if ($lower === 'now') {
            return ['raw' => $line, 'normalized' => 'now'];
        }

        if (preg_match('/^(\d{4})[ _-](\d{2})[ _-](\d{2})::(\d{2})[: _-](\d{2})$/u', $inner, $d)) {
            $y = (int) $d[1];
            $mo = (int) $d[2];
            $day = (int) $d[3];
            $h = (int) $d[4];
            $mi = (int) $d[5];
            if (! checkdate($mo, $day, $y)) {
                return null;
            }
            if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) {
                return null;
            }
            $ymdhm = sprintf('%04d-%02d-%02d %02d:%02d', $y, $mo, $day, $h, $mi);
            return ['raw' => $line, 'normalized' => $ymdhm];
        }

        return null;
    }

    /**
     * @return array{status:string,password:string}|null
     */
    private static function parse_visibility_line($line)
    {
        if (! preg_match('/^\[\[(.+)\]\]$/u', trim($line), $m)) {
            return null;
        }
        $inner = trim($m[1]);
        $upper = strtoupper($inner);
        if ($upper === 'PRIVATE') {
            return ['status' => 'private', 'password' => ''];
        }
        if ($upper === 'DRAFT') {
            return ['status' => 'draft', 'password' => ''];
        }
        if ($upper === 'PUBLIC') {
            return ['status' => 'publish', 'password' => ''];
        }
        if (preg_match('/^PUBLIC::(.+)$/iu', $inner, $p)) {
            $password = sanitize_text_field(trim($p[1]));
            return ['status' => 'publish', 'password' => $password];
        }
        return null;
    }
}
