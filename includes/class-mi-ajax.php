<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MI_Ajax {
    const NONCE = 'mi-admin-v1';

    public static function register() {
        $actions = [
            'mi_fetch_import_queue',
            'mi_stage_upload',
            'mi_patch_queue_item',
            'mi_remove_queue_item',
            'mi_confirm_import',
            'mi_clear_import_queue',
            'mi_get_import_queue_item',
            'mi_save_import_queue_item',
            'mi_list_articles',
            'mi_get_article',
            'mi_save_article',
            'mi_delete_article',
            'mi_set_visibility',
            'mi_list_ctas',
            'mi_save_cta',
            'mi_delete_cta',
            'mi_fetch_upgrade_queue',
            'mi_upgrade_upload',
            'mi_get_upgrade_queue_item',
            'mi_save_upgrade_queue_item',
            'mi_patch_upgrade_item',
            'mi_remove_upgrade_item',
            'mi_confirm_upgrade',
            'mi_clear_upgrade_queue',
        ];
        foreach ( $actions as $action ) {
            add_action( 'wp_ajax_' . $action, [ self::class, str_replace( "mi_", "", $action ) ] );
        }
    }

    private static function auth() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied.', 'markdown-importer' ) ] );
        }
        check_ajax_referer( self::NONCE, 'nonce' );
    }

    public static function fetch_import_queue() {
        self::auth();
        $uid = get_current_user_id();
        wp_send_json_success( [ 'queue' => self::decorate_queue( MI_Staging::get_queue( $uid ) ) ] );
    }

    public static function fetch_upgrade_queue() {
        self::auth();
        $uid = get_current_user_id();
        wp_send_json_success( [ 'queue' => self::decorate_upgrade_queue( self::read_upgrade_queue( $uid ) ) ] );
    }

    public static function stage_upload() {
        self::auth();
        $uid = get_current_user_id();
        if ( empty( $_FILES[ 'files' ] ) ) {
            wp_send_json_error( [ 'message' => __( 'No files uploaded.', 'markdown-importer' ) ] );
        }

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir[ 'error' ] ) ) {
            wp_send_json_error( [ 'message' => $upload_dir[ 'error' ] ] );
        }

        $batch = wp_generate_password( 10, false, false );
        $dir = trailingslashit( $upload_dir[ 'basedir' ] ) . 'mi-staging/' . $uid . '/' . $batch;
        if ( ! wp_mkdir_p( $dir ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not create staging directory.', 'markdown-importer' ) ] );
        }

        $files = $_FILES[ 'files' ];
        $count = is_array( $files[ 'name' ] ) ? count( $files[ 'name' ] ) : 0;
        $total_md_files = 0;
        $valid_items = [];
        $invalid_files = [];

        for ( $i = 0; $i < $count; $i++ ) {
            if ( ! empty( $files[ 'error' ][ $i ] ) && ( int ) $files[ 'error' ][ $i ] !== UPLOAD_ERR_OK ) {
                $invalid_files[] = [
                    'filename' => isset( $files[ 'name' ][ $i ] ) ? sanitize_file_name( ( string ) $files[ 'name' ][ $i ] ) : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Upload error for this file.', 'markdown-importer' ) ],
                ];
                continue;
            }
            $name = sanitize_file_name( $files[ 'name' ][ $i ] );
            if ( preg_match( '/\.md$/i', $name ) ) {
                $total_md_files++;
            }
            $tmp = $files[ 'tmp_name' ][ $i ];
            if ( ! is_uploaded_file( $tmp ) ) {
                $invalid_files[] = [
                    'filename' => $name !== '' ? $name : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Temporary upload file is invalid.', 'markdown-importer' ) ],
                ];
                continue;
            }
            $dest = $dir . '/' . $name;
            if ( ! move_uploaded_file( $tmp, $dest ) ) {
                $invalid_files[] = [
                    'filename' => $name !== '' ? $name : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Could not move uploaded file to staging.', 'markdown-importer' ) ],
                ];
                continue;
            }

            if ( ! preg_match( '/\.md$/i', $name ) ) {
                continue;
            }
            $content = file_get_contents( $dest );
            if ( $content === false ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'keyword' => '',
                    'keyword_status' => '',
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'slug_status' => '',
                    'errors' => [ __( 'Could not read file.', 'markdown-importer' ) ],
                ];
                continue;
            }
            $validation = MI_Parser::validate_document( $content );
            $keyword = MI_Parser::keyword_from_filename( $name );
            if ( ! $validation[ 'ok' ] ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'keyword' => $keyword,
                    'keyword_status' => '',
                    'release_date' => isset( $validation[ 'release_raw' ] ) && ( string ) $validation[ 'release_raw' ] !== '' ? ( string ) $validation[ 'release_raw' ] : ( isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : '' ),
                    'release_status' => isset( $validation[ 'release_error' ] ) ? ( string ) $validation[ 'release_error' ] : '',
                    'visibility' => isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : '',
                    'visibility_status' => isset( $validation[ 'visibility_error' ] ) ? ( string ) $validation[ 'visibility_error' ] : '',
                    'categories' => isset( $validation['categories_raw'] ) ? ( string ) $validation['categories_raw'] : '',
                    'categories_status' => isset( $validation['categories_error'] ) ? ( string ) $validation['categories_error'] : '',
                    'tags' => isset( $validation['tags_raw'] ) ? ( string ) $validation['tags_raw'] : '',
                    'tags_status' => isset( $validation['tags_error'] ) ? ( string ) $validation['tags_error'] : '',
                    'settings_status' => isset( $validation['settings_error'] ) ? ( string ) $validation['settings_error'] : '',
                    'slug' => isset( $validation[ 'slug_raw' ] ) && ( string ) $validation[ 'slug_raw' ] !== '' ? ( string ) $validation[ 'slug_raw' ] : ( isset( $validation[ 'slug' ] ) ? ( string ) $validation[ 'slug' ] : '' ),
                    'slug_status' => isset( $validation[ 'slug_error' ] ) ? ( string ) $validation[ 'slug_error' ] : '',
                    'errors' => isset( $validation[ 'errors' ] ) && is_array( $validation[ 'errors' ] ) ? array_values( array_map( 'strval', $validation[ 'errors' ] ) ) : [ __( 'Invalid markdown file.', 'markdown-importer' ) ],
                ];
                continue;
            }

            $slug = isset( $validation[ 'slug' ] ) ? ( string ) $validation[ 'slug' ] : '';
            $keyword_status = __( 'OK', 'markdown-importer' );
            $slug_status = __( 'OK', 'markdown-importer' );
            $errors = [];

            $keyword_post_id = MI_Article_Service::find_post_id_by_keyword( $keyword, 0 );
            if ( $keyword_post_id > 0 ) {
                $keyword_status = sprintf(
                    /* translators: %d: post ID */
                    __( 'Already exists (post ID: %d).', 'markdown-importer' ),
                    ( int ) $keyword_post_id
                );
                $errors[] = __( 'This keyword is already used by another article.', 'markdown-importer' );
            }

            $slug_post_id = MI_Article_Service::find_post_id_by_slug( $slug, 0 );
            if ( $slug_post_id > 0 ) {
                $slug_status = sprintf(
                    /* translators: %d: post ID */
                    __( 'Already exists (post ID: %d).', 'markdown-importer' ),
                    ( int ) $slug_post_id
                );
                $errors[] = __( 'This URL slug is already used by another article.', 'markdown-importer' );
            }

            if ( $errors !== [] ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'keyword' => $keyword,
                    'keyword_status' => $keyword_status,
                    'release_date' => isset( $validation[ 'release_raw' ] ) && ( string ) $validation[ 'release_raw' ] !== '' ? ( string ) $validation[ 'release_raw' ] : ( isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : '' ),
                    'release_status' => isset( $validation[ 'release_error' ] ) ? ( string ) $validation[ 'release_error' ] : '',
                    'visibility' => isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : '',
                    'visibility_status' => isset( $validation[ 'visibility_error' ] ) ? ( string ) $validation[ 'visibility_error' ] : '',
                    'categories' => isset( $validation[ 'categories_raw' ] ) ? ( string ) $validation[ 'categories_raw' ] : '',     
                    'categories_status' => isset( $validation['categories_error'] ) ? ( string ) $validation['categories_error'] : '',
                    'tags' => isset( $validation['tags_raw'] ) ? ( string ) $validation['tags_raw'] : '',
                    'tags_status' => isset( $validation['tags_error'] ) ? ( string ) $validation['tags_error'] : '',
                    'settings' => isset( $validation['settings_raw'] ) ? ( string ) $validation['settings_raw'] : '',
                    'settings_status' => isset( $validation['settings_error'] ) ? ( string ) $validation['settings_error'] : '',
                    'slug' => isset( $validation[ 'slug_raw' ] ) && ( string ) $validation[ 'slug_raw' ] !== '' ? ( string ) $validation[ 'slug_raw' ] : $slug,
                    'slug_status' => $slug_status,
                    'errors' => $errors,
                ];
                continue;
            }

            $rel = isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : 'now';
            $categories = isset( $validation[ 'categories' ] ) && is_array( $validation[ 'categories' ] ) ? array_values( array_map( 'strval', $validation[ 'categories' ] ) ) : [];
            $tags = isset( $validation[ 'tags' ] ) && is_array( $validation[ 'tags' ] ) ? array_values( array_map( 'strval', $validation[ 'tags' ] ) ) : [];
            $visibility = isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : 'private';
            $password = isset( $validation[ 'password' ] ) ? ( string ) $validation[ 'password' ] : '';
            $valid_items[] = [
                'id' => MI_Staging::make_item_id(),
                'batch' => $batch,
                'filename' => $name,
                'files_dir' => $dir,
                'keyword' => $keyword,
                'comment' => $validation[ 'comment' ],
                'release_date' => MI_Staging::release_for_form( $rel ),
                'visibility' => $visibility,
                'password' => $password,
                'meta_description' => $validation[ 'meta_description' ],
                'categories' => $categories,
                'tags' => $tags,
                'post_settings' => $validation[ 'settings' ],
                'slug' => $slug,
                'title' => $validation[ 'title' ],
                'markdown' => $validation[ 'markdown' ],
                'error' => '',
            ];
        }

        if ( $invalid_files !== [] ) {
            self::recursive_rmdir( $dir );
            wp_send_json_error(
                [
                    'message' => sprintf(
                        /* translators: %d: number of invalid markdown files */
                        __( 'Upload blocked. %d markdown file(s) failed syntax validation.', 'markdown-importer' ),
                        count( $invalid_files )
                    ),
                    'invalid_files' => $invalid_files,
                    'batch_uploaded' => count( $valid_items ),
                    'batch_total' => $total_md_files,
                ]
            );
        }

        if ( $valid_items === [] ) {
            self::recursive_rmdir( $dir );
            wp_send_json_error( [ 'message' => __( 'No valid markdown files were uploaded.', 'markdown-importer' ) ] );
        }

        $queue = MI_Staging::get_queue( $uid );
        foreach ( $valid_items as $item ) {
            $queue[] = $item;
        }
        MI_Staging::save_queue( $uid, $queue );
        wp_send_json_success(
            [
                'queue' => self::decorate_queue( $queue ),
                'batch_uploaded' => count( $valid_items ),
                'batch_total' => $total_md_files,
            ]
        );
    }

    public static function patch_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $release = isset( $_POST[ 'release_date' ] ) ? wp_unslash( $_POST[ 'release_date' ] ) : 'now';
        $queue = MI_Staging::get_queue( $uid );
        $found = false;
        foreach ( $queue as &$item ) {
            if ( $item[ 'id' ] === $id ) {
                $item[ 'release_date' ] = MI_Staging::parse_release_input( $release );
                $found = true;
                break;
            }
        }
        unset( $item );
        if ( ! $found ) {
            wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
        }
        MI_Staging::save_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_queue( $queue ) ] );
    }

    public static function get_import_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $queue = MI_Staging::get_queue( $uid );
        foreach ( $queue as $item ) {
            if ( isset( $item[ 'id' ] ) && $item[ 'id' ] === $id ) {
                $wp_categories = get_categories( [
                    'hide_empty' => false,
                ] );
                $categories = [];
                foreach ( $wp_categories as $cat ) {
                    $categories[] = $cat->name;
                }
                $rel = isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : 'now';
                wp_send_json_success(
                    [
                        'item' => [
                            'id' => ( string ) $item[ 'id' ],
                            'filename' => isset( $item[ 'filename' ] ) ? ( string ) $item[ 'filename' ] : '',
                            'keyword' => isset( $item[ 'keyword' ] ) ? ( string ) $item[ 'keyword' ] : '',
                            'comment' => isset( $item[ 'comment' ] ) ? ( string ) $item[ 'comment' ] : '',
                            'release_date' => MI_Staging::release_for_form( $rel ),
                            'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : 'private',
                            'password' => isset( $item[ 'password' ] ) ? ( string ) $item[ 'password' ] : '',
                            'meta_description' => isset( $item[ 'meta_description' ] ) ? ( string ) $item[ 'meta_description' ] : '',
                            'categories' => isset( $item[ 'categories' ] ) && is_array( $item[ 'categories' ] ) ? array_values( array_map( 'strval', $item[ 'categories' ] ) ) : [],
                            'tags' => isset( $item[ 'tags' ] ) && is_array( $item[ 'tags' ] ) ? array_values( array_map( 'strval', $item[ 'tags' ] ) ) : [],
                            'post_settings' => isset( $item[ 'post_settings'] ) ? ( array ) $item['post_settings'] : [],
                            'slug' => isset( $item[ 'slug' ] ) ? ( string ) $item[ 'slug' ] : '',
                            'title' => isset( $item[ 'title' ] ) ? ( string ) $item[ 'title' ] : '',
                            'markdown' => isset( $item[ 'markdown' ] ) ? ( string ) $item[ 'markdown' ] : '',
                            'error' => isset( $item[ 'error' ] ) ? ( string ) $item[ 'error' ] : '',
                        ],
                        'categories_all' => $categories,
                    ]
                );
                return;
            }
        }
        wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
    }

    public static function save_import_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $keyword = isset( $_POST[ 'keyword' ] ) ? trim( wp_unslash( $_POST[ 'keyword' ] ) ) : '';
        $cmt = isset( $_POST[ 'comment' ] ) ? wp_unslash( $_POST[ 'comment' ] ) : '';
        $title = isset( $_POST[ 'title' ] ) ? wp_unslash( $_POST[ 'title' ] ) : '';
        $slug_in = isset( $_POST[ 'slug' ] ) ? wp_unslash( $_POST[ 'slug' ] ) : '';
        $meta = isset( $_POST[ 'meta_description' ] ) ? wp_unslash( $_POST[ 'meta_description' ] ) : '';
        $ctg = isset( $_POST[ 'categories' ] ) && is_array( $_POST[ 'categories' ] ) ? array_map( function( $c ) { return trim( wp_unslash( $c ) ); }, $_POST[ 'categories' ] ) : [];
        $tags = isset( $_POST[ 'tags' ] ) && is_array( $_POST[ 'tags' ] ) ? array_map( function( $t ) { return trim( wp_unslash( $t ) ); }, $_POST[ 'tags' ] ) : [];
        $md = isset( $_POST[ 'markdown' ] ) ? wp_unslash( $_POST[ 'markdown' ] ) : '';
        $release = isset( $_POST[ 'release_date' ] ) ? wp_unslash( $_POST[ 'release_date' ] ) : 'now';
        $vis = isset( $_POST[ 'visibility' ] ) ? sanitize_key( wp_unslash( $_POST[ 'visibility' ] ) ) : 'private';
        if ( ! in_array( $vis, [ 'publish', 'private', 'draft', 'future' ], true ) ) {
            $vis = 'private';
        }
        $pwd = isset( $_POST[ 'password' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'password' ] ) ) : '';
        if ( $vis !== 'publish' && $vis !== 'future' ) {
            $pwd = '';
        }

        if ( $id === '' ) {
            wp_send_json_error( [ 'message' => __( 'Missing queue item id.', 'markdown-importer' ) ] );
        }
        if ( $keyword === '' ) {
            wp_send_json_error( [ 'message' => __( 'Keyword cannot be empty.', 'markdown-importer' ) ] );
        }
        $post_settings = isset( $_POST[ 'post_settings' ] ) && is_array( $_POST[ 'post_settings' ] ) ? array_map( function( $ps ) { return $ps == "true"; }, $_POST[ 'post_settings' ] ) : [];
        $queue = MI_Staging::get_queue( $uid );
        $idx = null;
        $item = null;
        foreach ( $queue as $i => $row ) {
            if ( isset( $row[ 'id' ] ) && $row[ 'id' ] === $id ) {
                $idx = $i;
                $item = $row;
                break;
            }
        }
        if ( $item === null || $idx === null ) {
            wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
        }

        $composed = MI_Parser::compose_document($cmt, $release, $vis, $pwd, $meta, $ctg, $tags, $post_settings, $slug_in, $title, $md );
        $validation = MI_Parser::validate_document( $composed );
        if ( ! $validation[ 'ok' ] ) {
            $msg = isset( $validation[ 'errors' ][ 0 ] ) ? ( string ) $validation[ 'errors' ][ 0 ] : __( 'Invalid markdown structure.', 'markdown-importer' );
            wp_send_json_error( [ 'message' => $msg ] );
        }

        $kw_lower = strtolower( $keyword );
        $dup_kw = self::import_queue_keyword_conflict( $queue, $id, $kw_lower );
        if ( $dup_kw !== '' ) {
            wp_send_json_error( [ 'message' => $dup_kw ] );
        }

        $slug_s = isset( $validation[ 'slug' ] ) ? ( string ) $validation[ 'slug' ] : '';
        $dup_slug = self::import_queue_slug_conflict( $queue, $id, $slug_s );
        if ( $dup_slug !== '' ) {
            wp_send_json_error( [ 'message' => $dup_slug ] );
        }

        $keyword_post_id = MI_Article_Service::find_post_id_by_keyword( $keyword, 0 );
        if ( $keyword_post_id > 0 ) {
            wp_send_json_error(
                [
                    'message' => sprintf(
                        /* translators: %d: post ID */
                        __( 'This keyword is already used by another article (post ID: %d).', 'markdown-importer' ),
                        ( int ) $keyword_post_id
                    ),
                ]
            );
        }

        $slug_post_id = MI_Article_Service::find_post_id_by_slug( $slug_s, 0 );
        if ( $slug_post_id > 0 ) {
            wp_send_json_error(
                [
                    'message' => sprintf(
                        /* translators: %d: post ID */
                        __( 'This URL slug is already used by another article (post ID: %d).', 'markdown-importer' ),
                        ( int ) $slug_post_id
                    ),
                ]
            );
        }

        $rel_norm = isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : 'now';
        $queue[ $idx ][ 'keyword' ] = $keyword;
        $queue[ $idx ][ 'comment' ] = isset( $validation[ 'comment' ] ) ? ( string ) $validation[ 'comment' ] : '';
        $queue[ $idx ][ 'release_date' ] = MI_Staging::release_for_form( $rel_norm );
        $queue[ $idx ][ 'visibility' ] = isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : 'private';
        $queue[ $idx ][ 'password' ] = isset( $validation[ 'password' ] ) ? ( string ) $validation[ 'password' ] : '';
        $queue[ $idx ][ 'meta_description' ] = isset( $validation[ 'meta_description' ] ) ? ( string ) $validation[ 'meta_description' ] : '';
        $queue[ $idx ][ 'markdown' ] = isset( $validation[ 'markdown' ] ) ? ( string ) $validation[ 'markdown' ] : '';
        $queue[ $idx ][ 'categories' ] = array_values( array_map( 'strval', $validation[ 'categories' ] ) );
        $queue[ $idx ][ 'tags' ] = array_values( array_map( 'strval', $validation[ 'tags' ] ) );
        $queue[ $idx ][ 'slug' ] = $slug_s;
        $queue[ $idx ][ 'title' ] = isset( $validation[ 'title' ] ) ? ( string ) $validation[ 'title' ] : '';
        $queue[ $idx ][ 'post_settings'] = $post_settings;
        $queue[ $idx ][ 'error' ] = '';

        if ( ! empty( $queue[ $idx ][ 'files_dir' ] ) && ! empty( $queue[ $idx ][ 'filename' ] ) && is_dir( ( string ) $queue[ $idx ][ 'files_dir' ] ) ) {
            $path = trailingslashit( ( string ) $queue[ $idx ][ 'files_dir' ] ) . ( string ) $queue[ $idx ][ 'filename' ];
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            file_put_contents( $path, $composed );
        }

        MI_Cta::ensure_from_markdown( $queue[ $idx ][ 'markdown' ] );

        MI_Staging::save_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_queue( $queue ) ] );
    }

    /**
    * @return string Empty if no conflict, else error message.
    */
    private static function import_queue_keyword_conflict( array $queue, $exclude_id, $keyword_lower ) {
        foreach ( $queue as $other ) {
            if ( ! isset( $other[ 'id' ] ) || $other[ 'id' ] === $exclude_id ) {
                continue;
            }
            if ( strtolower( trim( ( string ) ( $other[ 'keyword' ] ?? '' ) ) ) === $keyword_lower ) {
                return __( 'This keyword is already used by another file in the import queue.', 'markdown-importer' );
            }
        }

        return '';
    }

    /**
    * @return string Empty if no conflict, else error message.
    */
    private static function import_queue_slug_conflict( array $queue, $exclude_id, $slug_canonical ) {
        if ( $slug_canonical === '' ) {
            return '';
        }
        foreach ( $queue as $other ) {
            if ( ! isset( $other[ 'id' ] ) || $other[ 'id' ] === $exclude_id ) {
                continue;
            }
            $o = isset( $other[ 'slug' ] ) ? sanitize_title( ( string ) $other[ 'slug' ] ) : '';
            if ( $o !== '' && $o === $slug_canonical ) {
                return __( 'This URL slug is already used by another file in the import queue.', 'markdown-importer' );
            }
        }

        return '';
    }

    public static function remove_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $queue = array_values(
            array_filter(
                MI_Staging::get_queue( $uid ),

                function ( $item ) use ( $id ) {
                    return $item[ 'id' ] !== $id;
                }
            )
        );
        MI_Staging::save_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_queue( $queue ) ] );
    }

    public static function confirm_import() {
        self::auth();
        $uid = get_current_user_id();
        $queue = MI_Staging::get_queue( $uid );
        if ( $queue === [] ) {
            wp_send_json_success(
                [
                    'created' => [],
                    'failed' => [],
                    'message' => __( 'Nothing to import — upload Markdown files first.', 'markdown-importer' ),
                ]
            );
            return;
        }
        $created = [];
        $failed = [];
        foreach ( $queue as $item ) {
            if ( ! empty( $item[ 'error' ] ) ) {
                $failed[] = [
                    'filename' => isset( $item[ 'filename' ] ) ? ( string ) $item[ 'filename' ] : '',
                    'release_date' => isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : '',
                    'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : '',
                    'slug' => isset( $item[ 'slug' ] ) ? ( string ) $item[ 'slug' ] : '',
                    'message' => ( string ) $item[ 'error' ],
                ];
                continue;
            }
            $parsed = [
                'keyword' => $item[ 'keyword' ],
                'comment' => $item[ 'comment' ],
                'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : 'private',
                'password' => isset( $item[ 'password' ] ) ? ( string ) $item[ 'password' ] : '',
                'meta_description' => $item[ 'meta_description' ],
                'categories' => $item[ 'categories' ],
                'tags' => $item[ 'tags' ],
                'slug' => $item[ 'slug' ],
                'title' => $item[ 'title' ],
                'markdown' => $item[ 'markdown' ],
                'post_settings' => $item[ 'post_settings' ]
            ];
            $post_id = MI_Article_Service::create_article( $parsed, $item[ 'release_date' ] );
            if ( is_wp_error( $post_id ) ) {
                $failed[] = [
                    'filename' => isset( $item[ 'filename' ] ) ? ( string ) $item[ 'filename' ] : '',
                    'release_date' => isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : '',
                    'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : '',
                    'slug' => isset( $item[ 'slug' ] ) ? ( string ) $item[ 'slug' ] : '',
                    'message' => $post_id->get_error_message(),
                ];
                continue;
            }
            if ( ! empty( $item[ 'files_dir' ] ) && is_dir( $item[ 'files_dir' ] ) ) {
                MI_Article_Service::import_images_for_post( $post_id, $item[ 'files_dir' ], isset( $item[ 'markdown' ] ) ? ( string ) $item[ 'markdown' ] : '' );
            }
            $created[] = $post_id;
        }
        self::cleanup_staging_dirs( $queue );
        MI_Staging::clear_queue( $uid );
        $msg = __( 'Import completed.', 'markdown-importer' );
        if ( $created === [] && $failed !== [] ) {
            $msg = __( 'No articles were imported. Check row errors below.', 'markdown-importer' );
        } elseif ( $failed !== [] ) {
            $msg = __( 'Import finished with some errors.', 'markdown-importer' );
        }
        wp_send_json_success(
            [
                'created' => $created,
                'failed' => $failed,
                'message' => $msg,
            ]
        );
    }

    public static function clear_import_queue() {
        self::auth();
        $uid = get_current_user_id();
        $queue = MI_Staging::get_queue( $uid );
        self::cleanup_staging_dirs( $queue );
        MI_Staging::clear_queue( $uid );
        wp_send_json_success( [ 'queue' => [] ] );
    }

    public static function list_articles() {
        self::auth();
        $search = isset( $_POST[ 'search' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'search' ] ) ) : '';
        $search = trim( ( string ) $search );

        $ptype = MI_Post_Type::POST_TYPE;
        $statuses = MI_Article_Service::overview_statuses();
        $base = [
            'post_type' => $ptype,
            'post_status' => $statuses,
            'posts_per_page' => 200,
            'orderby' => 'ID',
            'order' => 'DESC',
        ];

        if ( $search === '' ) {
            $q = new WP_Query( $base );
        } else {
            global $wpdb;
            $in_status = "'" . implode( "','", array_map( 'esc_sql', $statuses ) ) . "'";

            $text_q = new WP_Query(
                array_merge(
                    $base,
                    [
                        's' => $search,
                        'fields' => 'ids',
                        'no_found_rows' => true,
                    ]
                )
            );
            $text_ids = is_array( $text_q->posts ) ? array_map( 'intval', $text_q->posts ) : [];

            $like = '%' . $wpdb->esc_like( $search ) . '%';
            $meta_key = MI_Article_Service::META_KEYWORD;

            $kw_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = %s AND pm.meta_value LIKE %s
                    AND p.post_type = %s AND p.post_status IN ($in_status)",
                    $meta_key,
                    $like,
                    $ptype
                )
            );

            $slug_ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts}
                    WHERE post_type = %s AND post_status IN ($in_status) AND post_name LIKE %s",
                    $ptype,
                    $like
                )
            );

            $ids = array_unique(
                array_merge(
                    $text_ids,
                    array_map( 'intval', ( array ) $kw_ids ),
                    array_map( 'intval', ( array ) $slug_ids )
                )
            );
            rsort( $ids, SORT_NUMERIC );
            $ids = array_slice( $ids, 0, 200 );

            if ( $ids === [] ) {
                wp_send_json_success( [ 'articles' => [] ] );
                return;
            }

            $q = new WP_Query(
                [
                    'post_type' => $ptype,
                    'post_status' => $statuses,
                    'post__in' => $ids,
                    'posts_per_page' => 200,
                    'orderby' => 'post__in',
                    'no_found_rows' => true,
                ]
            );
        }

        $rows = [];
        while ( $q->have_posts() ) {
            $q->the_post();
            $pid = get_the_ID();
            $rows[] = [
                'id' => $pid,
                'keyword' => ( string ) get_post_meta( $pid, MI_Article_Service::META_KEYWORD, true ),
                'slug' => get_post_field( 'post_name', $pid ),
                'permalink' => get_permalink( $pid ),
                'visibility' => in_array( get_post_status( $pid ), [ 'publish', 'private', 'draft', 'future' ], true ) ? get_post_status( $pid ) : 'private',
                'password' => ( string ) get_post_field( 'post_password', $pid ),
                'release_date' => MI_Staging::release_for_form( ( string ) get_post_meta( $pid, MI_Article_Service::META_RELEASE, true ) ),
            ];
        }
        wp_reset_postdata();
        wp_send_json_success( [ 'articles' => $rows ] );
    }

    public static function get_article() {
        self::auth();
        $id = isset( $_POST[ 'id' ] ) ? absint( $_POST[ 'id' ] ) : 0;
        $wp_categories = get_categories( [
            'hide_empty' => false,
        ] );
        $categories = [];
        foreach ( $wp_categories as $cat ) {
            $categories[] = $cat->name;
        }

        $payload = MI_Article_Service::get_article_payload( $id );
        if ( $payload === null ) {
            wp_send_json_error( [ 'message' => __( 'Article not found.', 'markdown-importer' ) ] );
        }
        wp_send_json_success( [ 'article' => $payload, 'categories' => $categories ] );
    }

    public static function save_article() {
        self::auth();
        $id = isset( $_POST[ 'id' ] ) ? absint( $_POST[ 'id' ] ) : 0;
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== MI_Post_Type::POST_TYPE ) {
            wp_send_json_error( [ 'message' => __( 'Article not found.', 'markdown-importer' ) ] );
        }
        $keyword = isset( $_POST[ 'keyword' ] ) ? wp_unslash( $_POST[ 'keyword' ] ) : '';
        $cmt = isset( $_POST[ 'comment' ] ) ? wp_unslash( $_POST[ 'comment' ] ) : '';
        $release = isset( $_POST[ 'release_date' ] ) ? wp_unslash( $_POST[ 'release_date' ] ) : 'now';
        $vis = isset( $_POST[ 'visibility' ] ) ? sanitize_key( wp_unslash( $_POST[ 'visibility' ] ) ) : 'private';
        $pwd = isset( $_POST[ 'password' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'password' ] ) ) : '';
        $meta = isset( $_POST[ 'meta_description' ] ) ? wp_unslash( $_POST[ 'meta_description' ] ) : '';
        $ctg = isset( $_POST[ 'categories' ] ) && is_array( $_POST[ 'categories' ] ) ? array_map( function( $c ) { return trim( wp_unslash( $c ) ); }, $_POST[ 'categories' ] ) : [];
        $tags = isset( $_POST[ 'tags' ] ) && is_array( $_POST[ 'tags' ] ) ? array_map( function( $t ) { return trim( wp_unslash( $t ) ); }, $_POST[ 'tags' ] ) : [];
        $slug = isset( $_POST[ 'slug' ] ) ? wp_unslash( $_POST[ 'slug' ] ) : '';
        $title = isset( $_POST[ 'title' ] ) ? wp_unslash( $_POST[ 'title' ] ) : '';
        $md = isset( $_POST[ 'markdown' ] ) ? wp_unslash( $_POST[ 'markdown' ] ) : '';
        $post_settings = isset($_POST[ 'post_settings' ]) ? $_POST['post_settings'] : [];
        $saved = MI_Article_Service::save_article_from_request( $id, $keyword, $cmt, $release, $vis, $pwd, $meta, $ctg, $tags, $slug, $title, $md, $post_settings);
        if ( is_wp_error( $saved ) ) {
            wp_send_json_error( [ 'message' => $saved->get_error_message() ] );
        }
        wp_send_json_success( [ 'article' => MI_Article_Service::get_article_payload( $id ) ] );
    }

    public static function delete_article() {
        self::auth();
        $id = isset( $_POST[ 'id' ] ) ? absint( $_POST[ 'id' ] ) : 0;
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== MI_Post_Type::POST_TYPE ) {
            wp_send_json_error( [ 'message' => __( 'Article not found.', 'markdown-importer' ) ] );
        }
        MI_Article_Service::delete_article( $id );
        wp_send_json_success( [ 'deleted' => $id ] );
    }

    public static function set_visibility() {
        self::auth();
        $id = isset( $_POST[ 'id' ] ) ? absint( $_POST[ 'id' ] ) : 0;
        $status = isset( $_POST[ 'status' ] ) ? sanitize_key( wp_unslash( $_POST[ 'status' ] ) ) : 'private';
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== MI_Post_Type::POST_TYPE ) {
            wp_send_json_error( [ 'message' => __( 'Article not found.', 'markdown-importer' ) ] );
        }
        $r = MI_Article_Service::set_visibility( $id, $status );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        }
        $post = get_post( $id );
        $out_vis = $post ? ( string ) $post->post_status : $status;
        wp_send_json_success( [ 'id' => $id, 'visibility' => $out_vis ] );
    }

    public static function list_ctas() {
        self::auth();
        wp_send_json_success( [ 'ctas' => MI_Cta::list_for_admin() ] );
    }

    public static function save_cta() {
        self::auth();
        $name = isset( $_POST[ 'name' ] ) ? wp_unslash( $_POST[ 'name' ] ) : '';
        $code = isset( $_POST[ 'code' ] ) ? wp_unslash( $_POST[ 'code' ] ) : '';
        $r = MI_Cta::save( $name, $code );
        if ( is_wp_error( $r ) ) {
            wp_send_json_error( [ 'message' => $r->get_error_message() ] );
        }
        wp_send_json_success( [ 'ctas' => MI_Cta::list_for_admin() ] );
    }

    public static function delete_cta() {
        self::auth();
        $name = isset( $_POST[ 'name' ] ) ? wp_unslash( $_POST[ 'name' ] ) : '';
        MI_Cta::delete( $name );
        wp_send_json_success( [ 'ctas' => MI_Cta::list_for_admin() ] );
    }

    public static function upgrade_upload() {
        self::auth();
        $uid = get_current_user_id();
        if ( empty( $_FILES[ 'files' ] ) ) {
            wp_send_json_error( [ 'message' => __( 'No files uploaded.', 'markdown-importer' ) ] );
        }

        $upload_dir = wp_upload_dir();
        if ( ! empty( $upload_dir[ 'error' ] ) ) {
            wp_send_json_error( [ 'message' => $upload_dir[ 'error' ] ] );
        }

        $batch = wp_generate_password( 10, false, false );
        $dir = trailingslashit( $upload_dir[ 'basedir' ] ) . 'mi-staging/' . $uid . '/up-' . $batch;
        if ( ! wp_mkdir_p( $dir ) ) {
            wp_send_json_error( [ 'message' => __( 'Could not create staging directory.', 'markdown-importer' ) ] );
        }

        $files = $_FILES[ 'files' ];
        $count = is_array( $files[ 'name' ] ) ? count( $files[ 'name' ] ) : 0;
        $total_md_files = 0;
        $valid_items = [];
        $invalid_files = [];

        for ( $i = 0; $i < $count; $i++ ) {
            if ( ! empty( $files[ 'error' ][ $i ] ) && ( int ) $files[ 'error' ][ $i ] !== UPLOAD_ERR_OK ) {
                $invalid_files[] = [
                    'filename' => isset( $files[ 'name' ][ $i ] ) ? sanitize_file_name( ( string ) $files[ 'name' ][ $i ] ) : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Upload error for this file.', 'markdown-importer' ) ],
                ];
                continue;
            }
            $name = sanitize_file_name( $files[ 'name' ][ $i ] );
            if ( preg_match( '/\.md$/i', $name ) ) {
                $total_md_files++;
            }
            $tmp = $files[ 'tmp_name' ][ $i ];
            if ( ! is_uploaded_file( $tmp ) ) {
                $invalid_files[] = [
                    'filename' => $name !== '' ? $name : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Temporary upload file is invalid.', 'markdown-importer' ) ],
                ];
                continue;
            }
            $dest = $dir . '/' . $name;
            if ( ! move_uploaded_file( $tmp, $dest ) ) {
                $invalid_files[] = [
                    'filename' => $name !== '' ? $name : __( 'unknown', 'markdown-importer' ),
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Could not move uploaded file to staging.', 'markdown-importer' ) ],
                ];
                continue;
            }
            if ( ! preg_match( '/\.md$/i', $name ) ) {
                continue;
            }

            $content = file_get_contents( $dest );
            if ( $content === false ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'release_date' => '',
                    'visibility' => '',
                    'slug' => '',
                    'errors' => [ __( 'Could not read file.', 'markdown-importer' ) ],
                ];
                continue;
            }

            $keyword = MI_Parser::keyword_from_filename( $name );
            $validation = MI_Parser::validate_document( $content );
            if ( ! $validation[ 'ok' ] ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'release_date' => isset( $validation[ 'release_raw' ] ) && ( string ) $validation[ 'release_raw' ] !== '' ? ( string ) $validation[ 'release_raw' ] : ( isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : '' ),
                    'release_status' => isset( $validation[ 'release_error' ] ) ? ( string ) $validation[ 'release_error' ] : '',
                    'visibility' => isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : '',
                    'visibility_status' => isset( $validation[ 'visibility_error' ] ) ? ( string ) $validation[ 'visibility_error' ] : '',
                    'categories' => isset( $validation[ 'categories_raw' ] ) ? array_values( array_map( 'strval', ( array ) $validation[ 'categories_raw' ] ) ) : [],
                    'categories_status' => isset( $validation[ 'categories_error' ] ) ? ( string ) $validation[ 'categories_error' ] : '',
                    'tags' => isset( $validation[ 'tags_raw' ] ) ? ( string ) $validation[ 'tags_raw' ] : '',
                    'tags_status' => isset( $validation[ 'tags_error' ] ) ? ( string ) $validation[ 'tags_error' ] : '',
                    'settings_status' => isset( $validation[ 'settings_error' ] ) ? ( string ) $validation[ 'settings_error' ] : '',
                    'slug' => isset( $validation[ 'slug_raw' ] ) && ( string ) $validation[ 'slug_raw' ] !== '' ? ( string ) $validation[ 'slug_raw' ] : ( isset( $validation[ 'slug' ] ) ? ( string ) $validation[ 'slug' ] : '' ),
                    'slug_status' => isset( $validation[ 'slug_error' ] ) ? ( string ) $validation[ 'slug_error' ] : '',
                    'errors' => isset( $validation[ 'errors' ] ) && is_array( $validation[ 'errors' ] ) ? array_values( array_map( 'strval', $validation[ 'errors' ] ) ) : [ __( 'Invalid markdown file.', 'markdown-importer' ) ],
                ];
                continue;
            }

            $target = MI_Article_Service::find_post_id_by_keyword( $keyword );
            $target_post = $target > 0 ? get_post( $target ) : null;
            if ( $target <= 0 || ! $target_post ) {
                $invalid_files[] = [
                    'filename' => $name,
                    'release_date' => isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : '',
                    'visibility' => isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : '',
                    'slug' => isset( $validation[ 'slug' ] ) ? ( string ) $validation[ 'slug' ] : '',
                    'errors' => [ __( 'No article matches this filename keyword.', 'markdown-importer' ) ],
                ];
                continue;
            }

            $rel = isset( $validation[ 'release_normalized' ] ) ? ( string ) $validation[ 'release_normalized' ] : 'now';
            if($rel !== 'now') {
                if(strtotime($rel) < time()) {
                    $rel = 'now';
                }
            }
            $valid_items[] = [
                'id' => MI_Staging::make_item_id(),
                'batch' => $batch,
                'filename' => $name,
                'files_dir' => $dir,
                'target_post_id' => $target,
                'keyword' => $keyword,
                'comment' => isset( $validation[ 'comment' ] ) ? ( string ) $validation[ 'comment' ] : '',
                'release_date' => MI_Staging::release_for_form( $rel ),
                'visibility' => isset( $validation[ 'visibility' ] ) ? ( string ) $validation[ 'visibility' ] : 'private',
                'password' => isset( $validation[ 'password' ] ) ? ( string ) $validation[ 'password' ] : '',
                'meta_description' => $validation[ 'meta_description' ],
                'categories' => array_values( array_map( 'strval', $validation[ 'categories' ] ) ),
                'tags' => array_values( array_map( 'strval', $validation[ 'tags' ] ) ),
                'post_settings' => isset( $validation[ 'settings' ] ) ? ( array ) $validation[ 'settings' ] : [],
                'slug' => $validation[ 'slug' ],
                'title' => $validation[ 'title' ],
                'markdown' => $validation[ 'markdown' ],
                'error' => '',
            ];
        }

        if ( $invalid_files !== [] ) {
            self::recursive_rmdir( $dir );
            wp_send_json_error(
                [
                    'message' => sprintf(
                        /* translators: %d: number of invalid markdown files */
                        __( 'Upgrade upload blocked. %d markdown file(s) failed validation.', 'markdown-importer' ),
                        count( $invalid_files )
                    ),
                    'invalid_files' => $invalid_files,
                    'batch_uploaded' => count( $valid_items ),
                    'batch_total' => $total_md_files,
                ]
            );
        }

        if ( $valid_items === [] ) {
            self::recursive_rmdir( $dir );
            wp_send_json_error( [ 'message' => __( 'No valid markdown files were uploaded.', 'markdown-importer' ) ] );
        }

        $queue = self::read_upgrade_queue( $uid );
        foreach ( $valid_items as $item ) {
            $queue[] = $item;
        }
        self::write_upgrade_queue( $uid, $queue );
        wp_send_json_success(
            [
                'queue' => self::decorate_upgrade_queue( $queue ),
                'batch_uploaded' => count( $valid_items ),
                'batch_total' => $total_md_files,
            ]
        );
    }

    public static function patch_upgrade_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $release = isset( $_POST[ 'release_date' ] ) ? wp_unslash( $_POST[ 'release_date' ] ) : 'now';
        $queue = self::read_upgrade_queue( $uid );
        $found = false;
        foreach ( $queue as &$item ) {
            if ( $item[ 'id' ] === $id ) {
                $item[ 'release_date' ] = MI_Staging::parse_release_input( $release );
                $found = true;
                break;
            }
        }
        unset( $item );
        if ( ! $found ) {
            wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
        }
        self::write_upgrade_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_upgrade_queue( $queue ) ] );
    }

    public static function get_upgrade_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $queue = self::read_upgrade_queue( $uid );
        foreach ( $queue as $item ) {
            if ( $item[ 'id' ] === $id ) {
                $wp_categories = get_categories( [
                    'hide_empty' => false,
                ] );
                $categories = [];
                foreach ( $wp_categories as $cat ) {
                    $categories[] = $cat->name;
                }
                wp_send_json_success( [ 'item' => $item, 'categories' => $categories ] );
                return;
            }
        }
        wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
    }

    public static function save_upgrade_queue_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $keyword = isset( $_POST[ 'keyword' ] ) ? wp_unslash( $_POST[ 'keyword' ] ) : '';
        $comment = isset( $_POST[ 'comment' ] ) ? wp_unslash( $_POST[ 'comment' ] ) : '';
        $release = isset( $_POST[ 'release_date' ] ) ? wp_unslash( $_POST[ 'release_date' ] ) : 'now';
        $visibility = isset( $_POST[ 'visibility' ] ) ? sanitize_key( wp_unslash( $_POST[ 'visibility' ] ) ) : 'private';
        $password = isset( $_POST[ 'password' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'password' ] ) ) : '';
        $meta_description = isset( $_POST[ 'meta_description' ] ) ? wp_unslash( $_POST[ 'meta_description' ] ) : '';
        $markdown = isset( $_POST[ 'markdown' ] ) ? wp_unslash( $_POST[ 'markdown' ] ) : '';
        $categories = isset( $_POST[ 'categories' ] ) && is_array( $_POST[ 'categories' ] ) ? array_map( function( $c ) { return trim( wp_unslash( $c ) ); }, $_POST[ 'categories' ] ) : [];
        $tags = isset( $_POST[ 'tags' ] ) && is_array( $_POST[ 'tags' ] ) ? array_map( function( $t ) { return trim( wp_unslash( $t ) ); }, $_POST[ 'tags' ] ) : [];
        $slug = isset( $_POST[ 'slug' ] ) ? wp_unslash( $_POST[ 'slug' ] ) : '';
        $title = isset( $_POST[ 'title' ] ) ? wp_unslash( $_POST[ 'title' ] ) : '';
        $post_settings = isset( $_POST[ 'post_settings' ] ) && is_array( $_POST[ 'post_settings' ] ) ? array_map( function( $ps ) { return $ps == "true"; }, $_POST[ 'post_settings' ] ) : [];
        
        $queue = self::read_upgrade_queue( $uid );
        $found = false;
        foreach ( $queue as &$item ) {
            if ( $item[ 'id' ] === $id ) {
                // Update fields
                $item[ 'keyword' ] = $keyword;
                $item[ 'comment' ] = $comment;
                $item[ 'release_date' ] = MI_Staging::parse_release_input( $release );
                $item[ 'visibility' ] = $visibility;
                $item[ 'password' ] = $password;
                $item[ 'meta_description' ] = $meta_description;
                $item[ 'markdown' ] = $markdown;
                $item[ 'categories' ] = $categories;
                $item[ 'tags' ] = $tags;
                $item[ 'slug' ] = $slug;
                $item[ 'title' ] = $title;
                $item[ 'post_settings' ] = $post_settings;
                $found = true;
                break;
            }
        }

        unset( $item );
        if ( ! $found ) {
            wp_send_json_error( [ 'message' => __( 'Queue item not found.', 'markdown-importer' ) ] );
        }
        self::write_upgrade_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_upgrade_queue( $queue ) ] );
        die();
    }

    public static function remove_upgrade_item() {
        self::auth();
        $uid = get_current_user_id();
        $id = isset( $_POST[ 'id' ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'id' ] ) ) : '';
        $queue = array_values(
            array_filter(
                self::read_upgrade_queue( $uid ),

                function ( $item ) use ( $id ) {
                    return $item[ 'id' ] !== $id;
                }
            )
        );
        self::write_upgrade_queue( $uid, $queue );
        wp_send_json_success( [ 'queue' => self::decorate_upgrade_queue( $queue ) ] );
    }

    public static function confirm_upgrade() {
        self::auth();
        $uid = get_current_user_id();
        $queue = self::read_upgrade_queue( $uid );
        $failed = [];
        $updated_now = [];
        $scheduled = [];
        foreach ( $queue as $item ) {
            if ( ! empty( $item[ 'error' ] ) || empty( $item[ 'target_post_id' ] ) ) {
                continue;
                }
            $parsed = [
                'keyword' => $item[ 'keyword' ],
                'comment' => $item[ 'comment' ],
                'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : '',
                'password' => isset( $item[ 'password' ] ) ? ( string ) $item[ 'password' ] : '',
                'meta_description' => $item[ 'meta_description' ],
                'categories' => $item['categories'],
                'tags' => $item['tags'],
                'slug' => $item[ 'slug' ],
                'title' => $item[ 'title' ],
                'markdown' => $item[ 'markdown' ],
                'post_settings' => $item[ 'post_settings' ],
            ];
            $result = MI_Article_Service::schedule_or_apply_upgrade(
                ( int ) $item[ 'target_post_id' ],
                $parsed,
                isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : 'now',
                ! empty( $item[ 'files_dir' ] ) ? ( string ) $item[ 'files_dir' ] : ''
            );
            if ( is_wp_error( $result ) ) {
                $failed[] = [
                    'filename' => isset( $item[ 'filename' ] ) ? ( string ) $item[ 'filename' ] : '',
                    'release_date' => isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : '',
                    'visibility' => isset( $item[ 'visibility' ] ) ? ( string ) $item[ 'visibility' ] : '',
                    'slug' => isset( $item[ 'slug' ] ) ? ( string ) $item[ 'slug' ] : '',
                    'tags' => isset( $item[ 'tags' ] ) ? ( array ) $item[ 'tags' ] : [],
                    'post_settings' => isset( $item[ 'post_settings' ] ) ? ( array ) $item[ 'post_settings' ] : [],
                    'message' => $result->get_error_message(),
                ];
                continue;
            }
            if ( ! empty( $result[ 'scheduled' ] ) ) {
                $scheduled[] = [
                    'post_id' => ( int ) $result[ 'post_id' ],
                    'release' => ( string ) $result[ 'release' ],
                ];
            } else {
                $updated_now[] = ( int ) $result[ 'post_id' ];
            }
        }
        self::cleanup_staging_dirs( $queue );
        self::clear_upgrade_queue_user( $uid );
        if ( $updated_now !== [] && $scheduled !== [] ) {
            $msg = __( 'Some articles were updated now and others were scheduled for their release date.', 'markdown-importer' );
        } elseif ( $scheduled !== [] ) {
            $msg = __( 'Articles scheduled. They will overwrite existing content at the release date/time.', 'markdown-importer' );
        } else {
            $msg = __( 'Articles updated.', 'markdown-importer' );
        }
        if ( $failed !== [] && $updated_now === [] && $scheduled === [] ) {
            $msg = __( 'No upgrades were applied. Please check errors.', 'markdown-importer' );
        } elseif ( $failed !== [] ) {
            $msg = __( 'Some upgrades could not be saved. Please check errors.', 'markdown-importer' );
        }
        wp_send_json_success(
            [
                'failed' => $failed,
                'updated_now' => $updated_now,
                'scheduled' => $scheduled,
                'message' => $msg,
            ]
        );
    }

    public static function clear_upgrade_queue() {
        self::auth();
        $uid = get_current_user_id();
        $queue = self::read_upgrade_queue( $uid );
        self::cleanup_staging_dirs( $queue );
        self::clear_upgrade_queue_user( $uid );
        wp_send_json_success( [ 'queue' => [] ] );
    }

    private static function decorate_queue( array $queue ) {
        $out = [];
        $n = 1;
        foreach ( $queue as $item ) {
            $row = $item;
            $row[ 'row_num' ] = $n++;
            $row[ 'release_date' ] = isset( $item[ 'release_date' ] ) ? ( string ) $item[ 'release_date' ] : 'now';
            $out[] = $row;
        }
        return $out;
    }

    private static function decorate_upgrade_queue( array $queue ) {
        return self::decorate_queue( $queue );
    }

    private static function cleanup_staging_dirs( array $queue ) {
        $seen = [];
        foreach ( $queue as $item ) {
            if ( empty( $item[ 'files_dir' ] ) ) {
                continue;
            }
            $d = rtrim( ( string ) $item[ 'files_dir' ], '/\\' );
            if ( isset( $seen[ $d ] ) ) {
                continue;
            }
            $seen[ $d ] = true;
            if ( is_dir( $d ) && strpos( $d, 'mi-staging' ) !== false ) {
                self::recursive_rmdir( $d );
            }
        }
    }

    private static function recursive_rmdir( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return;
        }
        $items = scandir( $dir );
        if ( $items === false ) {
            return;
        }
        foreach ( $items as $item ) {
            if ( $item === '.' || $item === '..' ) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if ( is_dir( $path ) ) {
                self::recursive_rmdir( $path );
            } else {
                @unlink( $path );
            }
        }
        @rmdir( $dir );
    }

    private static function read_upgrade_queue( $uid ) {
        $q = get_user_meta( $uid, 'mi_upgrade_queue', true );
        return is_array( $q ) ? $q : [];
    }

    private static function write_upgrade_queue( $uid, array $queue ) {
        update_user_meta( $uid, 'mi_upgrade_queue', $queue );
    }

    private static function clear_upgrade_queue_user( $uid ) {
        delete_user_meta( $uid, 'mi_upgrade_queue' );
    }
}
