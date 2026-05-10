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
     *   release_error:string,
     *   visibility_error:string,
     *   slug_error:string,
     *   release_raw:string,
     *   release_normalized:string,
     *   visibility:string,
     *   slug_raw:string,
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

        /* Compact header: line 1 release, 2 visibility, 3 meta, 4 slug, 5 title; markdown from line 6 (no blank lines between). */
        if (count($lines) < 5) {
            $errors[] = __('File must have at least 5 header lines (release, visibility, meta, slug, title) plus markdown body.', 'markdown-importer');
        }

        $release_raw = '';
        $release_normalized = 'now';
        $release_error = '';
        $visibility_status = 'private';
        $visibility_password = '';
        $visibility_error = '';
        $meta_description = trim(isset($lines[2]) ? (string) $lines[2] : '');
        $slug_line = trim(isset($lines[3]) ? (string) $lines[3] : '');
        $slug_error = '';
        $title = trim(isset($lines[4]) ? (string) $lines[4] : '');
        $markdown = implode("\n", array_slice($lines, 5));

        $line1 = trim(isset($lines[0]) ? (string) $lines[0] : '');
        $release = self::parse_release_line($line1);
        if (! $release['valid']) {
            $release_raw = $release['raw'];
            $release_error = __('Invalid syntax.', 'markdown-importer');
            $errors[] = __('Line 1 (release date) must be [[YYYY_MM_DD::HH_MM]] or [[now]].', 'markdown-importer') . ' ' . $release_error;
        } else {
            $release_raw = $release['raw'];
            $release_normalized = $release['normalized'];
        }

        $line2 = trim(isset($lines[1]) ? (string) $lines[1] : '');
        $visibility = self::parse_visibility_line($line2);
        if (! $visibility['valid']) {
            $visibility_error = __('Invalid syntax.', 'markdown-importer');
            $errors[] = __('Line 2 (visibility) must be [[PRIVATE]], [[DRAFT]], [[PUBLIC]], or [[PUBLIC::password]].', 'markdown-importer') . ' ' . $visibility_error;
        } else {
            $visibility_status = $visibility['status'];
            $visibility_password = $visibility['password'];
        }

        if ($slug_line === '') {
            $slug_error = __('Empty URL slug is not allowed.', 'markdown-importer');
            $errors[] = __('Line 4 (URL slug) cannot be empty.', 'markdown-importer');
        }
        if ($title === '') {
            $errors[] = __('Line 5 (title) cannot be empty.', 'markdown-importer');
        }

        $sanitized_slug = sanitize_title($slug_line);
        if ($slug_line !== '' && $sanitized_slug === '') {
            $slug_error = __('Invalid slug after sanitization.', 'markdown-importer');
            $errors[] = __('Line 4 (URL slug) is invalid.', 'markdown-importer') . ' ' . $slug_error;
        }

        if ($slug_line !== '' && ! preg_match('/^[A-Za-z0-9-]+$/', $slug_line)) {
            $slug_error = __('Invalid syntax.', 'markdown-importer');
            $errors[] = __('Line 4 (URL slug) contains disallowed characters. Only a-z, 0-9, and hyphen (-) are allowed.', 'markdown-importer') . ' ' . $slug_error;
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'release_error' => $release_error,
            'visibility_error' => $visibility_error,
            'slug_error' => $slug_error,
            'release_raw' => $release_raw,
            'release_normalized' => $release_normalized,
            'visibility' => $visibility_status,
            'slug_raw' => $slug_line,
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
     * @return array{valid:bool,raw:string,normalized:string}
     */
    private static function parse_release_line($line)
    {
        $original = trim((string) $line);
        if (! preg_match('/^\[\[(.+)\]\]$/u', $original, $m)) {
            return ['valid' => false, 'raw' => $original, 'normalized' => ''];
        }

        $inner = trim($m[1]);
        $lower = strtolower($inner);
        if ($lower === 'now') {
            return ['valid' => true, 'raw' => $original, 'normalized' => 'now'];
        }

        if (preg_match('/^(\d{4})[ _-](\d{2})[ _-](\d{2})::(\d{2})[: _-](\d{2})$/u', $inner, $d)) {
            $y = (int) $d[1];
            $mo = (int) $d[2];
            $day = (int) $d[3];
            $h = (int) $d[4];
            $mi = (int) $d[5];
            if (! checkdate($mo, $day, $y)) {
                return ['valid' => false, 'raw' => $original, 'normalized' => ''];
            }
            if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) {
                return ['valid' => false, 'raw' => $original, 'normalized' => ''];
            }
            $ymdhm = sprintf('%04d-%02d-%02d %02d:%02d', $y, $mo, $day, $h, $mi);
            return ['valid' => true, 'raw' => $original, 'normalized' => $ymdhm];
        }

        return ['valid' => false, 'raw' => $original, 'normalized' => ''];
    }

    /**
     * @return array{valid:bool,raw:string,status:string,password:string}
     */
    private static function parse_visibility_line($line)
    {
        $original = trim((string) $line);
        if (! preg_match('/^\[\[(.+)\]\]$/u', $original, $m)) {
            return ['valid' => false, 'raw' => $original, 'status' => '', 'password' => ''];
        }
        $inner = trim($m[1]);
        $upper = strtoupper($inner);
        if ($upper === 'PRIVATE') {
            return ['valid' => true, 'raw' => $original, 'status' => 'private', 'password' => ''];
        }
        if ($upper === 'DRAFT') {
            return ['valid' => true, 'raw' => $original, 'status' => 'draft', 'password' => ''];
        }
        if ($upper === 'PUBLIC') {
            return ['valid' => true, 'raw' => $original, 'status' => 'publish', 'password' => ''];
        }
        if (preg_match('/^PUBLIC::(.+)$/iu', $inner, $p)) {
            $password = sanitize_text_field(trim($p[1]));
            return ['valid' => true, 'raw' => $original, 'status' => 'publish', 'password' => $password];
        }
        return ['valid' => false, 'raw' => $original, 'status' => '', 'password' => ''];
    }
}
