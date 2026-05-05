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

        if (count($lines) < 7) {
            return ['ok' => false, 'error' => __('File must have at least 7 lines (release/meta/slug/title + markdown).', 'markdown-importer')];
        }

        $line1 = trim($lines[0]);
        $release = self::parse_release_line($line1);
        if ($release === null) {
            return ['ok' => false, 'error' => __('Line 1 must be [[YYYY MM DD]] (or underscores/hyphens) or [[now]]. Do not use the word now without double brackets.', 'markdown-importer')];
        }

        $meta_description = trim($lines[2]);
        $slug = trim($lines[4]);
        $title = trim($lines[6]);

        if ($slug === '' || $title === '') {
            return ['ok' => false, 'error' => __('Line 5 (slug) and line 7 (title) must not be empty.', 'markdown-importer')];
        }

        $slug = sanitize_title($slug);
        if ($slug === '') {
            return ['ok' => false, 'error' => __('Invalid URL slug on line 5.', 'markdown-importer')];
        }

        $markdown = implode("\n", array_slice($lines, 7));
        $keyword = $filename_for_keyword !== '' ? self::keyword_from_filename($filename_for_keyword) : '';

        return [
            'ok' => true,
            'release_raw' => $release['raw'],
            'release_normalized' => $release['normalized'],
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

        if (preg_match('/^(\d{4})[ _-](\d{2})[ _-](\d{2})$/u', $inner, $d)) {
            $y = (int) $d[1];
            $mo = (int) $d[2];
            $day = (int) $d[3];
            if (! checkdate($mo, $day, $y)) {
                return null;
            }
            $ymd = sprintf('%04d-%02d-%02d', $y, $mo, $day);
            return ['raw' => $line, 'normalized' => $ymd];
        }

        return null;
    }
}
