<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MI_Article_Service {
    const META_KEYWORD = '_mi_keyword';
    const META_COMMIT = '_mi_commit';
    const META_MARKDOWN = '_mi_markdown';
    const META_RELEASE = '_mi_release_display';
    const META_PENDING_UPGRADE = '_mi_pending_upgrade';
    const CRON_HOOK_APPLY_UPGRADE = 'mi_apply_scheduled_upgrade';

    /**
    * Statuses shown in Article Overview ( scheduled posts use WP status 'future' ).
    *
    * @return string[]
    */
    public static function overview_statuses() {
        return [ 'publish', 'private', 'draft', 'future' ];
    }

    /**
    * Find another mi_article using the same keyword ( case-insensitive ), optionally excluding one post.
    */
    public static function find_post_id_by_keyword( $keyword, $exclude_post_id = 0 ) {
        global $wpdb;
        $keyword = trim( ( string ) $keyword );
        if ( $keyword === '' ) {
            return 0;
        }
        $kw_lower = strtolower( $keyword );
        $meta_key = self::META_KEYWORD;
        $ptype = MI_Post_Type::POST_TYPE;
        $statuses = self::overview_statuses();
        $in_list = "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";
        $sql = "SELECT p.ID FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = %s
                WHERE p.post_type = %s AND p.post_status IN ($in_list)
                AND LOWER(TRIM(pm.meta_value)) = %s";
        $params = [ $meta_key, $ptype, $kw_lower ];
        if ( ( int ) $exclude_post_id > 0 ) {
            $sql .= ' AND p.ID != %d';
            $params[] = ( int ) $exclude_post_id;
        }
        $sql .= ' LIMIT 1';
        $pid = $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        return $pid ? ( int ) $pid : 0;
    }

    /**
    * Find another mi_article using the same URL slug, optionally excluding one post.
    */
    public static function find_post_id_by_slug( $slug, $exclude_post_id = 0 ) {
        $slug = sanitize_title( ( string ) $slug );
        if ( $slug === '' ) {
            return 0;
        }
        $args = [
            'post_type' => MI_Post_Type::POST_TYPE,
            'post_status' => self::overview_statuses(),
            'name' => $slug,
            'posts_per_page' => 1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ];
        if ( ( int ) $exclude_post_id > 0 ) {
            $args[ 'post__not_in' ] = [ ( int ) $exclude_post_id ];
        }
        $q = new WP_Query( $args );
        if ( $q->have_posts() ) {
            return ( int ) $q->posts[ 0 ];
        }
        return 0;
    }

    /**
    * @return true|WP_Error
    */
    public static function validate_article_uniqueness( $post_id, $keyword, $slug ) {
        $keyword = trim( ( string ) $keyword );
        $slug = sanitize_title( ( string ) $slug );
        if ( $keyword === '' ) {
            return new WP_Error( 'mi_empty_keyword', __( 'Keyword cannot be empty.', 'markdown-importer' ) );
        }
        if ( $slug === '' ) {
            return new WP_Error( 'mi_empty_slug', __( 'URL slug cannot be empty.', 'markdown-importer' ) );
        }
        $exclude = ( int ) $post_id;
        if ( self::find_post_id_by_keyword( $keyword, $exclude ) > 0 ) {
            return new WP_Error( 'mi_dup_keyword', __( 'This keyword is already used by another article.', 'markdown-importer' ) );
        }
        if ( self::find_post_id_by_slug( $slug, $exclude ) > 0 ) {
            return new WP_Error( 'mi_dup_slug', __( 'This URL slug is already used by another article.', 'markdown-importer' ) );
        }
        return true;
    }

    public static function create_article( array $parsed, $release_form_value ) {
        $uniq = self::validate_article_uniqueness( 0, $parsed[ 'keyword' ] ?? '', $parsed[ 'slug' ] ?? '' );
        if ( is_wp_error( $uniq ) ) {
            return $uniq;
        }

        $release = MI_Staging::parse_release_input( $release_form_value );
        $post_date = MI_Staging::post_date_from_release( $release );
        $requested = isset( $parsed[ 'visibility' ] ) ? ( string ) $parsed[ 'visibility' ] : 'private';
        if ( ! in_array( $requested, [ 'publish', 'private', 'draft', 'future' ], true ) ) {
            $requested = 'private';
        }
        $status = self::resolve_wp_post_status( $requested, $release );
        $password = isset( $parsed[ 'password' ] ) ? ( string ) $parsed[ 'password' ] : '';
        if ( $status !== 'publish' && $status !== 'future' ) {
            $password = '';
        }

        $post_id = wp_insert_post(
            [
                'post_type' => MI_Post_Type::POST_TYPE,
                'post_title' => $parsed[ 'title' ],
                'post_name' => $parsed[ 'slug' ],
                'post_status' => $status,
                'post_content' => '',
                'post_excerpt' => $parsed[ 'meta_description' ],
                'post_date' => $post_date,
                'post_date_gmt' => get_gmt_from_date( $post_date ),
                'post_password' => $password,
            ],
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        self::attach_meta( $post_id, $parsed, $release );

        return $post_id;
    }

    /**
    * Sideload image files from a directory and attach map basenames -> attachment IDs.
    *
    * @return array<string, int>
    */
    public static function import_images_for_post( $post_id, $directory, $markdown = '' ) {
        $post_id = ( int ) $post_id;
        if ( $post_id <= 0 || ! is_dir( $directory ) ) {
            return [];
        }
        $wanted = self::image_filenames_from_markdown( ( string ) $markdown );
        $import_all = $wanted === [];
        $map = [];
        $dir = rtrim( $directory, '/\\' );
        $patterns = [ '*.png', '*.jpg', '*.jpeg', '*.gif', '*.webp', '*.svg' ];
        foreach ( $patterns as $pat ) {
            $found = glob( $dir . DIRECTORY_SEPARATOR . $pat );
            if ( ! is_array( $found ) ) {
                continue;
            }
            foreach ( $found as $path ) {
                $base = basename( $path );
                if ( preg_match( '/\.md$/i', $base ) ) {
                    continue;
                }
                if ( ! $import_all && ! isset( $wanted[ $base ] ) ) {
                    continue;
                }
                $id = self::sideload_image_file( $path, $post_id, $base );
                if ( ! is_wp_error( $id ) && ( int ) $id > 0 ) {
                    $map[ $base ] = ( int ) $id;
                }
            }
        }
        return $map;
    }

    /**
    * @return array<string, bool> basenames used in image syntax
    */
    private static function image_filenames_from_markdown( $markdown ) {
        $out = [];
        if ( $markdown === '' ) {
            return $out;
        }
        if ( preg_match_all( '/\[\[image::([^:]+)::([^:]+)::([^\]]+)\]\]/u', $markdown, $m, PREG_SET_ORDER ) ) {
            foreach ( $m as $row ) {
                if ( ! empty( $row[ 3 ] ) ) {
                    $out[ trim( $row[ 3 ] ) ] = true;
                }
            }
        }
        if ( preg_match_all( '/\[\[image::([^:]+):::([^\]]+)\]\]/u', $markdown, $m2, PREG_SET_ORDER ) ) {
            foreach ( $m2 as $row ) {
                if ( ! empty( $row[ 2 ] ) ) {
                    $out[ trim( $row[ 2 ] ) ] = true;
                }
            }
        }
        return $out;
    }

    public static function update_article( $post_id, array $parsed, $release_form_value) {
        $uniq = self::validate_article_uniqueness( ( int ) $post_id, $parsed[ 'keyword' ] ?? '', $parsed[ 'slug' ] ?? '' );
        if ( is_wp_error( $uniq ) ) {
            return $uniq;
        }

        $release = MI_Staging::parse_release_input( $release_form_value );
        $post_date = MI_Staging::post_date_from_release( $release );
        $requested = isset( $parsed[ 'visibility' ] ) ? ( string ) $parsed[ 'visibility' ] : '';
        $password = isset( $parsed[ 'password' ] ) ? ( string ) $parsed[ 'password' ] : '';
        $commit = isset( $parsed[ 'commit' ] ) ? ( string ) $parsed[ 'commit' ] : '';

        $update_data = [
            'ID' => $post_id,
            'post_title' => $parsed[ 'title' ],
            'post_name' => $parsed[ 'slug' ],
            'post_excerpt' => $parsed[ 'meta_description' ],
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date( $post_date ),
        ];
        if ( in_array( $requested, [ 'publish', 'private', 'draft', 'future' ], true ) ) {
            $update_data[ 'post_status' ] = self::resolve_wp_post_status( $requested, $release );
        }
        $status = isset( $update_data[ 'post_status' ] ) ? ( string ) $update_data[ 'post_status' ] : '';
        if ( $status === 'publish' || $status === 'future' ) {
            $update_data[ 'post_password' ] = $password;
        } elseif ( $status === 'private' || $status === 'draft' ) {
            $update_data[ 'post_password' ] = '';
        }

        $updated = wp_update_post( $update_data, true );

        if ( is_wp_error( $updated ) ) {
            return $updated;
        }

        self::attach_meta( $post_id, $parsed, $release );
        return $post_id;
    }

    /**
    * Schedule an article overwrite at release datetime, or apply immediately if due.
    *
    * @return array {
        scheduled:bool, post_id:int, release:string}
        |WP_Error
        */
        public static function schedule_or_apply_upgrade( $post_id, array $parsed, $release_form_value) {
            $post_id = ( int ) $post_id;
            $release = MI_Staging::parse_release_input( $release_form_value );
            $release_ts = self::release_to_timestamp( $release );
            $updated = self::update_article( $post_id, $parsed, $release );
            if ( is_wp_error( $updated ) ) {
                return $updated;
            }
            return [ 'scheduled' => false, 'post_id' => $post_id, 'release' => $release ];
        }

        public static function apply_scheduled_upgrade( $post_id ) {
            $post_id = ( int ) $post_id;
            if ( $post_id <= 0 ) {
                return;
            }
            $pending = get_post_meta( $post_id, self::META_PENDING_UPGRADE, true );
            if ( ! is_array( $pending ) || empty( $pending[ 'parsed' ] ) || ! is_array( $pending[ 'parsed' ] ) ) {
                return;
            }
            $parsed = $pending[ 'parsed' ];
            $release = isset( $pending[ 'release' ] ) ? ( string ) $pending[ 'release' ] : 'now';
            $image_map = isset( $pending[ 'image_map' ] ) && is_array( $pending[ 'image_map' ] ) ? $pending[ 'image_map' ] : [];
            $updated = self::update_article( $post_id, $parsed, $release, $image_map );
            if ( ! is_wp_error( $updated ) ) {
                delete_post_meta( $post_id, self::META_PENDING_UPGRADE );
                self::clear_scheduled_upgrade( $post_id );
            }
        }

        private static function clear_scheduled_upgrade( $post_id ) {
            $post_id = ( int ) $post_id;
            $next = wp_next_scheduled( self::CRON_HOOK_APPLY_UPGRADE, [ $post_id ] );
            while ( $next ) {
                wp_unschedule_event( $next, self::CRON_HOOK_APPLY_UPGRADE, [ $post_id ] );
                $next = wp_next_scheduled( self::CRON_HOOK_APPLY_UPGRADE, [ $post_id ] );
            }
        }

        /**
        * Unix timestamp for a normalized release string ( site timezone ). 'now' → current time.
        */
        public static function release_to_timestamp( $release_normalized ) {
            $release = ( string ) $release_normalized;
            if ( $release === '' || strtolower( $release ) === 'now' ) {
                return time();
            }
            $tz = wp_timezone();
            try {
                if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $release ) ) {
                    $release .= ' 12:00:00';
                } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $release ) ) {
                    $release .= ':00';
                }
                $d = new DateTimeImmutable( $release, $tz );
                return ( int ) $d->getTimestamp();
            } catch ( Exception $e ) {
                return time();
            }
        }

        /**
        * Map requested visibility + release to a WordPress post_status.
        * 'future' ( Scheduled ) becomes post_status future only when release is strictly in the future;
        otherwise publish.
        */
        public static function resolve_wp_post_status( $requested_visibility, $release_normalized ) {
            $requested_visibility = strtolower( trim( ( string ) $requested_visibility ) );
            if ( ! in_array( $requested_visibility, [ 'publish', 'private', 'draft', 'future' ], true ) ) {
                return 'private';
            }
            if ( $requested_visibility !== 'future' ) {
                return $requested_visibility;
            }
            $ts = self::release_to_timestamp( $release_normalized );

            return $ts > time() ? 'future' : 'publish';
        }

        public static function attach_meta( $post_id, array $parsed, $release_normalized ) {
            update_post_meta( $post_id, self::META_KEYWORD, $parsed[ 'keyword' ] );
            update_post_meta( $post_id, self::META_MARKDOWN, $parsed[ 'markdown' ] );
            update_post_meta( $post_id, self::META_COMMIT, $parsed[ 'commit' ] );
            MI_Cta::ensure_from_markdown( isset( $parsed[ 'markdown' ] ) ? ( string ) $parsed[ 'markdown' ] : '' );
            update_post_meta( $post_id, self::META_RELEASE, $release_normalized );
            update_post_meta( $post_id, '_mi_meta_description', $parsed[ 'meta_description' ] );
        }

        /**
        * @return true|WP_Error
        */
        public static function set_visibility( $post_id, $status ) {
            $post_id = ( int ) $post_id;
            $status = ( string ) $status;
            if ( $status === 'future' ) {
                $rel = ( string ) get_post_meta( $post_id, self::META_RELEASE, true );
                $release = MI_Staging::parse_release_input( $rel !== '' ? $rel : 'now' );
                if ( self::release_to_timestamp( $release ) <= time() ) {
                    return new WP_Error(
                        'mi_scheduled_needs_future_release',
                        __( 'Scheduled visibility requires a release date in the future. Update the release date first.', 'markdown-importer' )
                    );
                }
                $post_date = MI_Staging::post_date_from_release( $release );
                $keep_pwd = ( string ) get_post_field( 'post_password', $post_id );
                wp_update_post(
                    [
                        'ID' => $post_id,
                        'post_status' => 'future',
                        'post_date' => $post_date,
                        'post_date_gmt' => get_gmt_from_date( $post_date ),
                        'post_password' => $keep_pwd,
                    ]
                );

                return true;
            }
            if ( ! in_array( $status, [ 'publish', 'private', 'draft' ], true ) ) {
                $status = 'private';
            }
            $update = [
                'ID' => $post_id,
                'post_status' => $status,
            ];
            if ( $status !== 'publish' ) {
                $update[ 'post_password' ] = '';
            }
            wp_update_post( $update );

            return true;
        }

        /**
        * Remove plugin-specific metadata/schedules, then hard-delete the article.
        */
        public static function delete_article( $post_id ) {
            $post_id = ( int ) $post_id;
            if ( $post_id <= 0 ) {
                return;
            }
            self::clear_scheduled_upgrade( $post_id );
            delete_post_meta( $post_id, self::META_KEYWORD );
            delete_post_meta( $post_id, self::META_MARKDOWN );
            delete_post_meta( $post_id, '_mi_image_map' );
            delete_post_meta( $post_id, self::META_RELEASE );
            delete_post_meta( $post_id, self::META_PENDING_UPGRADE );
            delete_post_meta( $post_id, '_mi_meta_description' );
            wp_delete_post( $post_id, true );
        }

        public static function get_article_payload( $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post || $post->post_type !== MI_Post_Type::POST_TYPE ) {
                return null;
            }
            $kw = ( string ) get_post_meta( $post_id, self::META_KEYWORD, true );
            $cmt = ( string ) get_post_meta( $post_id, self::META_COMMIT, true );
            $md = ( string ) get_post_meta( $post_id, self::META_MARKDOWN, true );
            $rel = ( string ) get_post_meta( $post_id, self::META_RELEASE, true );
            $meta = ( string ) get_post_meta( $post_id, '_mi_meta_description', true );
            if ( $meta === '' ) {
                $meta = $post->post_excerpt;
            }

            return [
                'id' => ( int ) $post_id,
                'keyword' => $kw,
                'commit' => $cmt,
                'slug' => $post->post_name,
                'title' => $post->post_title,
                'meta_description' => $meta,
                'markdown' => $md,
                'release_date' => MI_Staging::release_for_form( $rel !== '' ? $rel : 'now' ),
                'visibility' => in_array( $post->post_status, [ 'publish', 'private', 'draft', 'future' ], true ) ? $post->post_status : 'private',
                'password' => ( string ) $post->post_password,
            ];
        }

        /**
        * @return true|WP_Error
        */
        public static function save_article_from_request( $post_id, $title, $keyword, $slug, $meta_description, $commit, $markdown, $release_date, $visibility, $password = '' ) {
            $slug = sanitize_title( $slug );
            $uniq = self::validate_article_uniqueness( ( int ) $post_id, $keyword, $slug );
            if ( is_wp_error( $uniq ) ) {
                return $uniq;
            }
            $commit = trim( ( string ) $commit );
            $keyword = trim( ( string ) $keyword );
            $release = MI_Staging::parse_release_input( $release_date );
            $post_date = MI_Staging::post_date_from_release( $release );
            $requested = in_array( $visibility, [ 'publish', 'private', 'draft', 'future' ], true ) ? $visibility : 'private';
            $status = self::resolve_wp_post_status( $requested, $release );
            $password = ( string ) $password;
            if ( $status !== 'publish' && $status !== 'future' ) {
                $password = '';
            }

            wp_update_post(
                [
                    'ID' => $post_id,
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_excerpt' => $meta_description,
                    'post_status' => $status,
                    'post_password' => $password,
                    'post_date' => $post_date,
                    'post_date_gmt' => get_gmt_from_date( $post_date ),
                ]
            );
            update_post_meta( $post_id, self::META_KEYWORD, $keyword );
            update_post_meta( $post_id, self::META_COMMIT, $commit );
            update_post_meta( $post_id, self::META_MARKDOWN, $markdown );
            MI_Cta::ensure_from_markdown( $markdown );
            update_post_meta( $post_id, self::META_RELEASE, $release );
            update_post_meta( $post_id, '_mi_meta_description', $meta_description );

            return true;
        }

        public static function sideload_image_file( $file_path, $post_id, $filename_hint = '' ) {
            if ( ! file_exists( $file_path ) ) {
                return new WP_Error( 'missing', 'File not found' );
            }
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $name = $filename_hint !== '' ? $filename_hint : basename( $file_path );
            $tmp = wp_tempnam( $name );
            if ( ! $tmp ) {
                return new WP_Error( 'tmp', 'Temp file' );
            }
            copy( $file_path, $tmp );
            $file_array = [
                'name' => $name,
                'tmp_name' => $tmp,
            ];
            $id = media_handle_sideload( $file_array, $post_id );
            if ( is_wp_error( $id ) ) {
                @unlink( $tmp );
                return $id;
            }
            return ( int ) $id;
        }
    }
