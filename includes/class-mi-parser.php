<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MI_Parser {
    /**
    * Validate required MD structure fields used by bulk upload preflight.
    *
    * @return array {
        *   ok:bool,
        *   errors:array<int, string>,
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
    public static function validate_document( $content ) {
                    
        $content = str_replace( [ "\r\n", "\r" ], "\n", ( string ) $content );
        $lines = explode( "\n", $content );
        $errors = [];
        /* Compact header: line 1 comment, 2 release, 3 visibility, 4 meta, 5 categories, 6 tags, 7 slug, 8 title, 9 settings;
        markdown from line 10 ( no blank lines between ). */
        if ( count( $lines ) < 10 ) {
            $errors[] = __( 'File must have at least 10 header lines (comment, release, visibility, meta, categories, tags, settings, slug, title) plus markdown body.', 'markdown-importer' );
        }

        $release_raw = '';
        $release_normalized = 'now';
        $release_error = '';
        $visibility_status = 'private';
        $visibility_password = '';
        $visibility_error = '';
        $categories_raw = '';
        $categories_normalized = [];
        $categories_error = '';
        $tags_raw = '';
        $tags_normalized = [];
        $tags_error = '';
        $settings_normalized = [];
        $settings_error = '';
        $comment = trim( isset( $lines[ 0 ] ) ? ( string ) $lines[ 0 ] : '' );
        $release_line = trim( isset( $lines[ 1 ] ) ? ( string ) $lines[ 1 ] : '' );
        $release = self::parse_release_line( $release_line );
        if ( ! $release[ 'valid' ] ) {
            $release_raw = $release[ 'raw' ];
            $release_error = __( 'Invalid syntax.', 'markdown-importer' );
            $errors[] = __( 'Line 2 (release date) must be [[YYYY_MM_DD::HH_MM]] or [[now]].', 'markdown-importer' ) . ' ' . $release_error;
        } else {
            $release_raw = $release[ 'raw' ];
            $release_normalized = $release[ 'normalized' ];
        }

        $visibility_line = trim( isset( $lines[ 2 ] ) ? ( string ) $lines[ 2 ] : '' );
        $visibility = self::parse_visibility_line( $visibility_line );
        if ( ! $visibility[ 'valid' ] ) {
            $visibility_error = __( 'Invalid syntax.', 'markdown-importer' );
            $errors[] = __( 'Line 3 (visibility) must be [[PRIVATE]], [[DRAFT]], [[SCHEDULED]], [[SCHEDULED::password]], [[PUBLIC]], or [[PUBLIC::password]].', 'markdown-importer' ) . ' ' . $visibility_error;
        } else {
            $visibility_status = $visibility[ 'status' ];
            $visibility_password = $visibility[ 'password' ];
        }
        $meta_description = trim( isset( $lines[ 3 ] ) ? ( string ) $lines[ 3 ] : '' );
        $categories_line = trim( isset( $lines[ 4 ] ) ? ( string ) $lines[ 4 ] : '' );
        $categories = self::parse_categories_line( $categories_line );
        if ( ! $categories[ 'valid' ] ) {
            $categories_raw = $categories[ 'raw' ];
            $categories_error = __( 'Invalid syntax.', 'markdown-importer' );
            $errors[] = __( 'Line 5 (categories) must be [[Category1::Category2::...]] , [[]], and [["Uncategorized"]]', 'markdown-importer' ) . ' ' . $categories_error;
        } else {
            $categories_normalized = $categories[ 'normalized' ];
        }

        $tags_line = trim( isset( $lines[ 5 ] ) ? ( string ) $lines[ 5 ] : '' );
        $tags = self::parse_tags_line( $tags_line );
        if ( ! $tags[ 'valid' ] ) {
            $tags_raw = $tags[ 'raw' ];
            $tags_error = __( 'Invalid syntax.', 'markdown-importer' );
            $errors[] = __( 'Line 6 (tags) must be [[Tag1::Tag2::...]] or [[]].', 'markdown-importer' ) . ' ' . $tags_error;
        } else {
            $tags_normalized = $tags[ 'normalized' ];
        }
        $settings_line = trim(isset( $lines [ 6 ] ) ? (string) $lines[ 6 ] : '' );
        $settings = self::parse_settings_line($settings_line);
        if(! $settings[ 'valid' ]) { 
            $settings_error = __('Invalid syntax or incorrect key | value', 'markdown-importer');
            $errors[] = __( 'Line 7 (settings) must be [[ALLOW_COMMENTS::ON|OFF]] or [[]].', 'markdown-importer' ) . ' ' . $settings_error;
        } else {
            $settings_normalized = array('mi-allow-comments' => $settings[ 'normalized' ] );
        }
        $slug_line = trim( isset( $lines[ 7 ] ) ? ( string ) $lines[ 7 ] : '' );
        $title = trim( isset( $lines[ 8 ] ) ? ( string ) $lines[ 8 ] : '' );
        $markdown = implode( "\n", array_slice( $lines, 9 ) );

        if ( $slug_line === '' ) {
            $slug_error = __( 'Empty URL slug is not allowed.', 'markdown-importer' );
            $errors[] = __( 'Line 8 (URL slug) cannot be empty.', 'markdown-importer' );
        }
        if ( $title === '' ) {
            $errors[] = __( 'Line 9 (title) cannot be empty.', 'markdown-importer' );
        }

        $sanitized_slug = sanitize_title( $slug_line );
        if ( $slug_line !== '' && $sanitized_slug === '' ) {
            $slug_error = __( 'Invalid slug after sanitization.', 'markdown-importer' );
            $errors[] = __( 'Line 8 (URL slug) is invalid.', 'markdown-importer' ) . ' ' . $slug_error;
        }

        if ( $slug_line !== '' && ! preg_match( '/^[A-Za-z0-9-]+$/', $slug_line ) ) {
            $slug_error = __( 'Invalid syntax.', 'markdown-importer' );
            $errors[] = __( 'Line 8 (URL slug) contains disallowed characters. Only a-z, 0-9, and hyphen (-) are allowed.', 'markdown-importer' ) . ' ' . $slug_error;
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'comment' => $comment,
            'release_error' => $release_error,
            'release_raw' => $release_raw,
            'release_normalized' => $release_normalized,
            'visibility_error' => $visibility_error,
            'visibility' => $visibility_status,
            'password' => $visibility_password,
            'meta_description' => $meta_description,
            'categories_raw' => $categories_raw,
            'categories_error' => $categories_error,
            'categories' => $categories_normalized,
            'tags_raw' => $tags_raw,
            'tags_error' => $tags_error,
            'tags' => $tags_normalized,
            'settings_error' => $settings_error,
            'settings' => $settings_normalized,
            'slug_error' => $slug_error,
            'slug_raw' => $slug_line,
            'slug' => $sanitized_slug,
            'title' => $title,
            'markdown' => ltrim( $markdown, "\n" ),
        ];
    }

    /**
    * Build canonical upload markdown ( lines 1–5 header + body ) from editor fields.
    */
    public static function compose_document( $comment, $release_form, $visibility, $password, $meta_description, $categories, $tags, $settings_line, $slug_line, $title, $markdown_body ) {
        $line1 = trim( ( string ) $comment );
        $line2 = self::release_token_from_form( $release_form );
        $line3 = self::visibility_token_from_status( $visibility, $password );
        $line4 = ( string ) $meta_description;
        $line5 = '[[' . implode( '::', array_map( function ( $c ) { return trim( ( string ) $c ); }, $categories ) ) . ']]';
        $line6 = '[[' . implode( '::', array_map( function ( $t ) { return trim( ( string ) $t ); }, $tags ) ) . ']]';
        $line7 = '[[ALLOW_COMMENTS::' . ( $settings_line['mi-allow-comments'] == 'true' ? 'ON' : 'OFF' ) . ']]';
        $line8 = trim( ( string ) $slug_line );
        $line9 = trim( ( string ) $title );
        $body = ltrim( ( string ) $markdown_body, "\n" );

        return $line1 . "\n" . $line2 . "\n" . $line3 . "\n" . $line4 . "\n" . $line5 . "\n" . $line6 . "\n" . $line7 . "\n" . $line8 . "\n" . $line9 . "\n" . $body;
    }

    /**
    * @param string $release_form Admin form value: 'now', 'YYYY-MM-DD', or 'YYYY-MM-DD HH:MM'
    */
    public static function release_token_from_form( $release_form ) {
        $v = trim( ( string ) $release_form );
        if ( $v === '' || strtolower( $v ) === 'now' ) {
            return '[[now]]';
        }
        if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?$/', $v, $m ) ) {
            $hh = isset( $m[ 4 ] ) && $m[ 4 ] !== '' ? $m[ 4 ] : '12';
            $mm = isset( $m[ 5 ] ) && $m[ 5 ] !== '' ? $m[ 5 ] : '00';

            return '[[' . $m[ 1 ] . '_' . $m[ 2 ] . '_' . $m[ 3 ] . '::' . $hh . '_' . $mm . ']]';
        }

        return '[[now]]';
    }

    /**
    * @param string $visibility publish|private|draft|future
    */
    public static function visibility_token_from_status( $visibility, $password ) {
        $vis = strtolower( trim( ( string ) $visibility ) );
        $pwd = trim( ( string ) $password );
        if ( $vis === 'publish' ) {
            return $pwd !== '' ? '[[PUBLIC::' . $pwd . ']]' : '[[PUBLIC]]';
        }
        if ( $vis === 'draft' ) {
            return '[[DRAFT]]';
        }
        if ( $vis === 'future' ) {
            return $pwd !== '' ? '[[SCHEDULED::' . $pwd . ']]' : '[[SCHEDULED]]';
        }

        return '[[PRIVATE]]';
    }

    /**
    * Keyword from filename: Satoshi-Nakamoto.md -> Satoshi-Nakamoto
    */
    public static function keyword_from_filename( $basename ) {
        $name = preg_replace( '/\.[^.]+$/', '', $basename );
        return $name === '' ? 'article' : $name;
    }

    /**
    * Parse MD body according to owner rules.
    *
    * @return array {
        ok:bool, error?:string, release_raw?:string, release_normalized?:string, meta_description?:string, slug?:string, title?:string, markdown?:string}
    */
    public static function parse_document( $content, $filename_for_keyword = '' ) {
        $validation = self::validate_document( $content );
        if ( ! $validation[ 'ok' ] ) {
            return [
                'ok' => false,
                'error' => isset( $validation[ 'errors' ][ 0 ] ) ? ( string ) $validation[ 'errors' ][ 0 ] : __( 'Invalid markdown file.', 'markdown-importer' ),
                'errors' => $validation[ 'errors' ],
            ];
        }
        $keyword = $filename_for_keyword !== '' ? self::keyword_from_filename( $filename_for_keyword ) : '';

        return [
            'ok' => true,
            'release_raw' => $validation[ 'release_raw' ],
            'release_normalized' => $validation[ 'release_normalized' ],
            'visibility' => $validation[ 'visibility' ],
            'password' => $validation[ 'password' ],
            'meta_description' => $validation[ 'meta_description' ],
            'categories' => $validation[ 'categories' ],
            'tags' => $validation[ 'tags' ],
            'slug' => $validation[ 'slug' ],
            'title' => $validation[ 'title' ],
            'markdown' => $validation[ 'markdown' ],
            'keyword' => $keyword,
        ];
    }

    /**
    * @return array {
        valid:bool, raw:string, normalized:string}
    */
    private static function parse_release_line( $line ) {
        $original = trim( ( string ) $line );
        if ( ! preg_match( '/^\[\[(.+)\]\]$/u', $original, $m ) ) {
            return [ 'valid' => false, 'raw' => $original, 'normalized' => '' ];
        }

        $inner = trim( $m[ 1 ] );
        $lower = strtolower( $inner );
        if ( $lower === 'now' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => 'now' ];
        }

        if ( preg_match( '/^(\d{4})[ _-](\d{2})[ _-](\d{2})::(\d{2})[: _-](\d{2})$/u', $inner, $d ) ) {
            $y = ( int ) $d[ 1 ];
            $mo = ( int ) $d[ 2 ];
            $day = ( int ) $d[ 3 ];
            $h = ( int ) $d[ 4 ];
            $mi = ( int ) $d[ 5 ];
            if ( ! checkdate( $mo, $day, $y ) ) {
                return [ 'valid' => false, 'raw' => $original, 'normalized' => '' ];
            }
            if ( $h < 0 || $h > 23 || $mi < 0 || $mi > 59 ) {
                return [ 'valid' => false, 'raw' => $original, 'normalized' => '' ];
            }
            $ymdhm = sprintf( '%04d-%02d-%02d %02d:%02d', $y, $mo, $day, $h, $mi );
            return [ 'valid' => true, 'raw' => $original, 'normalized' => $ymdhm ];
        }

        return [ 'valid' => false, 'raw' => $original, 'normalized' => '' ];
    }

    /**
    * @return array {
        valid:bool, raw:string, status:string, password:string}
    */
    private static function parse_visibility_line( $line ) {
        $original = trim( ( string ) $line );
        if ( ! preg_match( '/^\[\[(.+)\]\]$/u', $original, $m ) ) {
            return [ 'valid' => false, 'raw' => $original, 'status' => '', 'password' => '' ];
        }
        $inner = trim( $m[ 1 ] );
        $upper = strtoupper( $inner );
        if ( $upper === 'PRIVATE' ) {
            return [ 'valid' => true, 'raw' => $original, 'status' => 'private', 'password' => '' ];
        }
        if ( $upper === 'DRAFT' ) {
            return [ 'valid' => true, 'raw' => $original, 'status' => 'draft', 'password' => '' ];
        }
        if ( preg_match( '/^SCHEDULED::(.+)$/iu', $inner, $p ) ) {
            $password = sanitize_text_field( trim( $p[ 1 ] ) );

            return [ 'valid' => true, 'raw' => $original, 'status' => 'future', 'password' => $password ];
        }
        if ( $upper === 'SCHEDULED' ) {
            return [ 'valid' => true, 'raw' => $original, 'status' => 'future', 'password' => '' ];
        }
        if ( $upper === 'PUBLIC' ) {
            return [ 'valid' => true, 'raw' => $original, 'status' => 'publish', 'password' => '' ];
        }
        if ( preg_match( '/^PUBLIC::(.+)$/iu', $inner, $p ) ) {
            $password = sanitize_text_field( trim( $p[ 1 ] ) );
            return [ 'valid' => true, 'raw' => $original, 'status' => 'publish', 'password' => $password ];
        }
        return [ 'valid' => false, 'raw' => $original, 'status' => '', 'password' => '' ];
    }

    /**
    * @return array {
        valid:bool, raw:string, normalized:string[]}
    */
    private static function parse_categories_line( $line ) {
        $original = trim( ( string ) $line );
        if ( $original === '' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => [ 'Uncategorized' ] ];
        }
        if ( ! preg_match( '/^\[\[(.*)\]\]$/u', $original, $m ) ) {
            return [ 'valid' => false, 'raw' => $original, 'normalized' => [] ];
        }
        $inner = trim( $m[ 1 ] );
        if ( $inner === '' || strtolower( $inner ) === 'uncategorized' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => [ 'Uncategorized' ] ];
        }
        $categories = explode( '::', $inner );
        // Avoid very long category names that could cause issues.
        $normalized = [];
        foreach ( $categories as $cat ) {
            $c = trim( ( string ) $cat );
            if ( $c !== '' && strtolower( $c ) !== 'uncategorized' && strlen( $c ) <= 100 ) {
                $normalized[] = $c;
            }
        }
        return [ 'valid' => true, 'raw' => $original, 'normalized' => $normalized ];
    }

    private static function parse_tags_line( $line ) {
        $original = trim( ( string ) $line );
        if ( $original === '' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => [] ];
        }
        if ( ! preg_match( '/^\[\[(.*)\]\]$/u', $original, $m ) ) {
            return [ 'valid' => false, 'raw' => $original, 'normalized' => [] ];
        }
        $inner = trim( $m[ 1 ] );
        if ( $inner === '' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => [] ];
        }
        $tags = explode( '::', $inner );
        // Avoid very long tag names that could cause issues.
        $normalized = [];
        foreach ( $tags as $tag ) {
            $t = trim( ( string ) $tag );
            if ( $t !== '' && strlen( $t ) <= 30 ) {
                $normalized[] = $t;
            }
        }
        return [ 'valid' => true, 'raw' => $original, 'normalized' => $normalized ];
    }

    private static function parse_settings_line($line) {
        $original = trim( ( string ) $line );
        if ( $original === '' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => false ];
        }
        if ( ! preg_match( '/^\[\[(.*)\]\]$/u', $original, $m ) ) {
            return [ 'valid' => false, 'raw' => $original, 'normalized' => false ];
        }
        $inner = trim( $m[ 1 ] );
        if ( $inner === '' ) {
            return [ 'valid' => true, 'raw' => $original, 'normalized' => false ];
        }
        $settings = explode( '::', $inner );
        // Avoid very long category names that could cause issues.
        $key = $settings[0];
        $value = $settings[1];
        if($key && $key == 'ALLOW_COMMENTS' && $value && ($value == 'OFF' || $value == 'ON')){
            return [ 'valid' => true, 'raw' => $original, 'normalized' => $value == 'ON' ];
        }
        else 
            return [ 'valid' => true, 'raw' => $original, 'normalized' => $normalized ];
    }
}
