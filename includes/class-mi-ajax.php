<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Ajax
{
    const NONCE = 'mi-admin-v1';

    public static function register()
    {
        $actions = [
            'mi_fetch_import_queue',
            'mi_stage_upload',
            'mi_patch_queue_item',
            'mi_remove_queue_item',
            'mi_confirm_import',
            'mi_clear_import_queue',
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
            'mi_patch_upgrade_item',
            'mi_remove_upgrade_item',
            'mi_confirm_upgrade',
            'mi_clear_upgrade_queue',
        ];
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, [self::class, str_replace('mi_', '', $action)]);
        }
    }

    private static function auth()
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'markdown-importer')]);
        }
        check_ajax_referer(self::NONCE, 'nonce');
    }

    public static function fetch_import_queue()
    {
        self::auth();
        $uid = get_current_user_id();
        wp_send_json_success(['queue' => self::decorate_queue(MI_Staging::get_queue($uid))]);
    }

    public static function fetch_upgrade_queue()
    {
        self::auth();
        $uid = get_current_user_id();
        wp_send_json_success(['queue' => self::decorate_upgrade_queue(self::read_upgrade_queue($uid))]);
    }

    public static function stage_upload()
    {
        self::auth();
        $uid = get_current_user_id();
        if (empty($_FILES['files'])) {
            wp_send_json_error(['message' => __('No files uploaded.', 'markdown-importer')]);
        }

        $upload_dir = wp_upload_dir();
        if (! empty($upload_dir['error'])) {
            wp_send_json_error(['message' => $upload_dir['error']]);
        }

        $batch = wp_generate_password(10, false, false);
        $dir = trailingslashit($upload_dir['basedir']) . 'mi-staging/' . $uid . '/' . $batch;
        if (! wp_mkdir_p($dir)) {
            wp_send_json_error(['message' => __('Could not create staging directory.', 'markdown-importer')]);
        }

        $queue = MI_Staging::get_queue($uid);
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            if (! empty($files['error'][$i]) && (int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $name = sanitize_file_name($files['name'][$i]);
            $tmp = $files['tmp_name'][$i];
            if (! is_uploaded_file($tmp)) {
                continue;
            }
            $dest = $dir . '/' . $name;
            if (! move_uploaded_file($tmp, $dest)) {
                continue;
            }

            if (! preg_match('/\.md$/i', $name)) {
                continue;
            }

            $content = file_get_contents($dest);
            if ($content === false) {
                $queue[] = [
                    'id' => MI_Staging::make_item_id(),
                    'batch' => $batch,
                    'filename' => $name,
                    'files_dir' => $dir,
                    'keyword' => '',
                    'slug' => '',
                    'title' => '',
                    'meta_description' => '',
                    'markdown' => '',
                    'release_date' => 'now',
                    'error' => __('Could not read file.', 'markdown-importer'),
                ];
                continue;
            }

            $parsed = MI_Parser::parse_document($content, $name);
            if (! $parsed['ok']) {
                $queue[] = [
                    'id' => MI_Staging::make_item_id(),
                    'batch' => $batch,
                    'filename' => $name,
                    'files_dir' => $dir,
                    'keyword' => MI_Parser::keyword_from_filename($name),
                    'slug' => '',
                    'title' => '',
                    'meta_description' => '',
                    'markdown' => '',
                    'release_date' => 'now',
                    'error' => isset($parsed['error']) ? (string) $parsed['error'] : __('Invalid markdown file.', 'markdown-importer'),
                ];
                continue;
            }

            $rel = isset($parsed['release_normalized']) ? (string) $parsed['release_normalized'] : 'now';
            $queue[] = [
                'id' => MI_Staging::make_item_id(),
                'batch' => $batch,
                'filename' => $name,
                'files_dir' => $dir,
                'keyword' => $parsed['keyword'],
                'slug' => $parsed['slug'],
                'title' => $parsed['title'],
                'meta_description' => $parsed['meta_description'],
                'markdown' => $parsed['markdown'],
                'release_date' => MI_Staging::release_for_form($rel),
                'error' => '',
            ];
        }

        MI_Staging::save_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_queue($queue)]);
    }

    public static function patch_queue_item()
    {
        self::auth();
        $uid = get_current_user_id();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $release = isset($_POST['release_date']) ? wp_unslash($_POST['release_date']) : 'now';
        $queue = MI_Staging::get_queue($uid);
        $found = false;
        foreach ($queue as &$item) {
            if ($item['id'] === $id) {
                $item['release_date'] = MI_Staging::parse_release_input($release);
                $found = true;
                break;
            }
        }
        unset($item);
        if (! $found) {
            wp_send_json_error(['message' => __('Queue item not found.', 'markdown-importer')]);
        }
        MI_Staging::save_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_queue($queue)]);
    }

    public static function remove_queue_item()
    {
        self::auth();
        $uid = get_current_user_id();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $queue = array_values(
            array_filter(
                MI_Staging::get_queue($uid),
                function ($item) use ($id) {
                    return $item['id'] !== $id;
                }
            )
        );
        MI_Staging::save_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_queue($queue)]);
    }

    public static function confirm_import()
    {
        self::auth();
        $uid = get_current_user_id();
        $queue = MI_Staging::get_queue($uid);
        $created = [];
        foreach ($queue as $item) {
            if (! empty($item['error'])) {
                continue;
            }
            $parsed = [
                'title' => $item['title'],
                'slug' => $item['slug'],
                'meta_description' => $item['meta_description'],
                'markdown' => $item['markdown'],
                'keyword' => $item['keyword'],
            ];
            $post_id = MI_Article_Service::create_article($parsed, $item['release_date'], []);
            if (is_wp_error($post_id)) {
                continue;
            }
            if (! empty($item['files_dir']) && is_dir($item['files_dir'])) {
                MI_Article_Service::import_images_for_post($post_id, $item['files_dir'], isset($item['markdown']) ? (string) $item['markdown'] : '');
            }
            $created[] = $post_id;
        }
        self::cleanup_staging_dirs($queue);
        MI_Staging::clear_queue($uid);
        wp_send_json_success(['created' => $created, 'message' => __('Import completed.', 'markdown-importer')]);
    }

    public static function clear_import_queue()
    {
        self::auth();
        $uid = get_current_user_id();
        $queue = MI_Staging::get_queue($uid);
        self::cleanup_staging_dirs($queue);
        MI_Staging::clear_queue($uid);
        wp_send_json_success(['queue' => []]);
    }

    public static function list_articles()
    {
        self::auth();
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $q = new WP_Query(
            [
                'post_type' => MI_Post_Type::POST_TYPE,
                'post_status' => ['publish', 'private', 'draft'],
                'posts_per_page' => 200,
                'orderby' => 'ID',
                'order' => 'DESC',
                's' => $search,
            ]
        );
        $rows = [];
        while ($q->have_posts()) {
            $q->the_post();
            $pid = get_the_ID();
            $rows[] = [
                'id' => $pid,
                'keyword' => (string) get_post_meta($pid, MI_Article_Service::META_KEYWORD, true),
                'slug' => get_post_field('post_name', $pid),
                'visibility' => get_post_status($pid) === 'private' ? 'private' : 'public',
                'release_date' => MI_Staging::release_for_form((string) get_post_meta($pid, MI_Article_Service::META_RELEASE, true)),
            ];
        }
        wp_reset_postdata();
        wp_send_json_success(['articles' => $rows]);
    }

    public static function get_article()
    {
        self::auth();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $payload = MI_Article_Service::get_article_payload($id);
        if ($payload === null) {
            wp_send_json_error(['message' => __('Article not found.', 'markdown-importer')]);
        }
        wp_send_json_success(['article' => $payload]);
    }

    public static function save_article()
    {
        self::auth();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $post = get_post($id);
        if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
            wp_send_json_error(['message' => __('Article not found.', 'markdown-importer')]);
        }
        $title = isset($_POST['title']) ? wp_unslash($_POST['title']) : '';
        $slug = isset($_POST['slug']) ? wp_unslash($_POST['slug']) : '';
        $meta = isset($_POST['meta_description']) ? wp_unslash($_POST['meta_description']) : '';
        $md = isset($_POST['markdown']) ? wp_unslash($_POST['markdown']) : '';
        $release = isset($_POST['release_date']) ? wp_unslash($_POST['release_date']) : 'now';
        $vis = isset($_POST['visibility']) && $_POST['visibility'] === 'private' ? 'private' : 'public';

        MI_Article_Service::save_article_from_request($id, $title, $slug, $meta, $md, $release, $vis);
        wp_send_json_success(['article' => MI_Article_Service::get_article_payload($id)]);
    }

    public static function delete_article()
    {
        self::auth();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $post = get_post($id);
        if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
            wp_send_json_error(['message' => __('Article not found.', 'markdown-importer')]);
        }
        wp_delete_post($id, true);
        wp_send_json_success(['deleted' => $id]);
    }

    public static function set_visibility()
    {
        self::auth();
        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $public = isset($_POST['public']) ? (bool) $_POST['public'] : true;
        $post = get_post($id);
        if (! $post || $post->post_type !== MI_Post_Type::POST_TYPE) {
            wp_send_json_error(['message' => __('Article not found.', 'markdown-importer')]);
        }
        MI_Article_Service::set_visibility($id, $public);
        wp_send_json_success(['id' => $id, 'visibility' => $public ? 'public' : 'private']);
    }

    public static function list_ctas()
    {
        self::auth();
        wp_send_json_success(['ctas' => MI_Cta::list_for_admin()]);
    }

    public static function save_cta()
    {
        self::auth();
        $name = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        $code = isset($_POST['code']) ? wp_unslash($_POST['code']) : '';
        $r = MI_Cta::save($name, $code);
        if (is_wp_error($r)) {
            wp_send_json_error(['message' => $r->get_error_message()]);
        }
        wp_send_json_success(['ctas' => MI_Cta::list_for_admin()]);
    }

    public static function delete_cta()
    {
        self::auth();
        $name = isset($_POST['name']) ? wp_unslash($_POST['name']) : '';
        MI_Cta::delete($name);
        wp_send_json_success(['ctas' => MI_Cta::list_for_admin()]);
    }

    public static function upgrade_upload()
    {
        self::auth();
        $uid = get_current_user_id();
        if (empty($_FILES['files'])) {
            wp_send_json_error(['message' => __('No files uploaded.', 'markdown-importer')]);
        }

        $upload_dir = wp_upload_dir();
        if (! empty($upload_dir['error'])) {
            wp_send_json_error(['message' => $upload_dir['error']]);
        }

        $batch = wp_generate_password(10, false, false);
        $dir = trailingslashit($upload_dir['basedir']) . 'mi-staging/' . $uid . '/up-' . $batch;
        if (! wp_mkdir_p($dir)) {
            wp_send_json_error(['message' => __('Could not create staging directory.', 'markdown-importer')]);
        }

        $queue = self::read_upgrade_queue($uid);
        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            if (! empty($files['error'][$i]) && (int) $files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            $name = sanitize_file_name($files['name'][$i]);
            $tmp = $files['tmp_name'][$i];
            if (! is_uploaded_file($tmp)) {
                continue;
            }
            $dest = $dir . '/' . $name;
            if (! move_uploaded_file($tmp, $dest)) {
                continue;
            }
            if (! preg_match('/\.md$/i', $name)) {
                continue;
            }

            $content = file_get_contents($dest);
            if ($content === false) {
                continue;
            }

            $parsed = MI_Parser::parse_document($content, $name);
            $keyword = MI_Parser::keyword_from_filename($name);
            if (! $parsed['ok']) {
                $queue[] = [
                    'id' => MI_Staging::make_item_id(),
                    'batch' => $batch,
                    'filename' => $name,
                    'files_dir' => $dir,
                    'keyword' => $keyword,
                    'target_post_id' => 0,
                    'slug' => '',
                    'title' => '',
                    'meta_description' => '',
                    'markdown' => '',
                    'release_date' => 'now',
                    'error' => isset($parsed['error']) ? (string) $parsed['error'] : __('Invalid markdown file.', 'markdown-importer'),
                ];
                continue;
            }

            $target = MI_Article_Service::find_post_id_by_keyword($parsed['keyword']);
            if ($target <= 0) {
                $queue[] = [
                    'id' => MI_Staging::make_item_id(),
                    'batch' => $batch,
                    'filename' => $name,
                    'files_dir' => $dir,
                    'keyword' => $parsed['keyword'],
                    'target_post_id' => 0,
                    'slug' => $parsed['slug'],
                    'title' => $parsed['title'],
                    'meta_description' => $parsed['meta_description'],
                    'markdown' => $parsed['markdown'],
                    'release_date' => MI_Staging::release_for_form((string) $parsed['release_normalized']),
                    'error' => __('No existing article matches this keyword (filename base).', 'markdown-importer'),
                ];
                continue;
            }

            $rel = isset($parsed['release_normalized']) ? (string) $parsed['release_normalized'] : 'now';
            $queue[] = [
                'id' => MI_Staging::make_item_id(),
                'batch' => $batch,
                'filename' => $name,
                'files_dir' => $dir,
                'keyword' => $parsed['keyword'],
                'target_post_id' => $target,
                'slug' => $parsed['slug'],
                'title' => $parsed['title'],
                'meta_description' => $parsed['meta_description'],
                'markdown' => $parsed['markdown'],
                'release_date' => MI_Staging::release_for_form($rel),
                'error' => '',
            ];
        }

        self::write_upgrade_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_upgrade_queue($queue)]);
    }

    public static function patch_upgrade_item()
    {
        self::auth();
        $uid = get_current_user_id();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $release = isset($_POST['release_date']) ? wp_unslash($_POST['release_date']) : 'now';
        $queue = self::read_upgrade_queue($uid);
        $found = false;
        foreach ($queue as &$item) {
            if ($item['id'] === $id) {
                $item['release_date'] = MI_Staging::parse_release_input($release);
                $found = true;
                break;
            }
        }
        unset($item);
        if (! $found) {
            wp_send_json_error(['message' => __('Queue item not found.', 'markdown-importer')]);
        }
        self::write_upgrade_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_upgrade_queue($queue)]);
    }

    public static function remove_upgrade_item()
    {
        self::auth();
        $uid = get_current_user_id();
        $id = isset($_POST['id']) ? sanitize_text_field(wp_unslash($_POST['id'])) : '';
        $queue = array_values(
            array_filter(
                self::read_upgrade_queue($uid),
                function ($item) use ($id) {
                    return $item['id'] !== $id;
                }
            )
        );
        self::write_upgrade_queue($uid, $queue);
        wp_send_json_success(['queue' => self::decorate_upgrade_queue($queue)]);
    }

    public static function confirm_upgrade()
    {
        self::auth();
        $uid = get_current_user_id();
        $queue = self::read_upgrade_queue($uid);
        foreach ($queue as $item) {
            if (! empty($item['error']) || empty($item['target_post_id'])) {
                continue;
            }
            $parsed = [
                'title' => $item['title'],
                'slug' => $item['slug'],
                'meta_description' => $item['meta_description'],
                'markdown' => $item['markdown'],
                'keyword' => $item['keyword'],
            ];
            $post_id = MI_Article_Service::update_article((int) $item['target_post_id'], $parsed, $item['release_date'], []);
            if (! is_wp_error($post_id) && ! empty($item['files_dir']) && is_dir($item['files_dir'])) {
                MI_Article_Service::import_images_for_post((int) $item['target_post_id'], $item['files_dir'], isset($item['markdown']) ? (string) $item['markdown'] : '');
            }
        }
        self::cleanup_staging_dirs($queue);
        self::clear_upgrade_queue_user($uid);
        wp_send_json_success(['message' => __('Articles updated.', 'markdown-importer')]);
    }

    public static function clear_upgrade_queue()
    {
        self::auth();
        $uid = get_current_user_id();
        $queue = self::read_upgrade_queue($uid);
        self::cleanup_staging_dirs($queue);
        self::clear_upgrade_queue_user($uid);
        wp_send_json_success(['queue' => []]);
    }

    private static function decorate_queue(array $queue)
    {
        $out = [];
        $n = 1;
        foreach ($queue as $item) {
            $row = $item;
            $row['row_num'] = $n++;
            $row['release_date'] = isset($item['release_date']) ? (string) $item['release_date'] : 'now';
            $out[] = $row;
        }
        return $out;
    }

    private static function decorate_upgrade_queue(array $queue)
    {
        return self::decorate_queue($queue);
    }

    private static function cleanup_staging_dirs(array $queue)
    {
        $seen = [];
        foreach ($queue as $item) {
            if (empty($item['files_dir'])) {
                continue;
            }
            $d = rtrim((string) $item['files_dir'], '/\\');
            if (isset($seen[$d])) {
                continue;
            }
            $seen[$d] = true;
            if (is_dir($d) && strpos($d, 'mi-staging') !== false) {
                self::recursive_rmdir($d);
            }
        }
    }

    private static function recursive_rmdir($dir)
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::recursive_rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    private static function read_upgrade_queue($uid)
    {
        $q = get_user_meta($uid, 'mi_upgrade_queue', true);
        return is_array($q) ? $q : [];
    }

    private static function write_upgrade_queue($uid, array $queue)
    {
        update_user_meta($uid, 'mi_upgrade_queue', $queue);
    }

    private static function clear_upgrade_queue_user($uid)
    {
        delete_user_meta($uid, 'mi_upgrade_queue');
    }
}
