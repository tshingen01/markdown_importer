<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Parser
{
    /**
     * Validate required MD structure fields used by bulk upload preflight.
     *
     * @return array{
     *   ok:bool,
     *   errors:array<int,string>,
     *   release_raw:string,
     *   release_normalized:string,
     *   visibility:string,
     *   password:string,
     *   meta_description:string,
     *   slug:string,
     *   title:string,
     *   markdown:string
     * }
     */
    public static function validate_document($content)
    {
        $content = str_replace(["\r\n", "\r"], "\n", (string) $content);
        $lines = explode("\n", $content);
        $errors = [];

        if (count($lines) < 11) {
            $errors[] = __('File must have at least 11 lines (release/visibility/meta/slug/title + markdown).', 'markdown-importer');
        }

        $release_raw = '';
        $release_normalized = 'now';
        $visibility_status = 'private';
        $visibility_password = '';
        $meta_description = trim(isset($lines[4]) ? (string) $lines[4] : '');
        $slug_line = trim(isset($lines[6]) ? (string) $lines[6] : '');
        $title = trim(isset($lines[8]) ? (string) $lines[8] : '');
        $markdown = implode("\n", array_slice($lines, 10));

        $line1 = trim(isset($lines[0]) ? (string) $lines[0] : '');
        $release = self::parse_release_line($line1);
        if ($release === null) {
            $errors[] = __('Line 1 (release date) must be [[YYYY_MM_DD::HH_MM]] or [[now]].', 'markdown-importer');
        } else {
            $release_raw = $release['raw'];
            $release_normalized = $release['normalized'];
        }

        $line3 = trim(isset($lines[2]) ? (string) $lines[2] : '');
        $visibility = self::parse_visibility_line($line3);
        if ($visibility === null) {
            $errors[] = __('Line 3 (visibility) must be [[PRIVATE]], [[DRAFT]], [[PUBLIC]], or [[PUBLIC::password]].', 'markdown-importer');
        } else {
            $visibility_status = $visibility['status'];
            $visibility_password = $visibility['password'];
        }

        if ($slug_line === '') {
            $errors[] = __('Line 7 (URL slug) must not be empty.', 'markdown-importer');
        }
        if ($title === '') {
            $errors[] = __('Line 9 (title) must not be empty.', 'markdown-importer');
        }

        $sanitized_slug = sanitize_title($slug_line);
        if ($slug_line !== '' && $sanitized_slug === '') {
            $errors[] = __('Line 7 (URL slug) is invalid.', 'markdown-importer');
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'release_raw' => $release_raw,
            'release_normalized' => $release_normalized,
            'visibility' => $visibility_status,
            'password' => $visibility_password,
            'meta_description' => $meta_description,
            'slug' => $sanitized_slug,
            'title' => $title,
            'markdown' => ltrim($markdown, "\n"),
        ];
    }

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
        $validation = self::validate_document($content);
        if (! $validation['ok']) {
            return [
                'ok' => false,
                'error' => isset($validation['errors'][0]) ? (string) $validation['errors'][0] : __('Invalid markdown file.', 'markdown-importer'),
                'errors' => $validation['errors'],
            ];
        }
        $keyword = $filename_for_keyword !== '' ? self::keyword_from_filename($filename_for_keyword) : '';

        return [
            'ok' => true,
            'release_raw' => $validation['release_raw'],
            'release_normalized' => $validation['release_normalized'],
            'visibility' => $validation['visibility'],
            'password' => $validation['password'],
            'meta_description' => $validation['meta_description'],
            'slug' => $validation['slug'],
            'title' => $validation['title'],
            'markdown' => $validation['markdown'],
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
