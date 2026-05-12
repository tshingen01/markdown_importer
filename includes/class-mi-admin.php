<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Admin
{
    /** @var self|null */
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
    }

    public function menu()
    {
        add_menu_page(
            __('Markdown Importer', 'markdown-importer'),
            __('Markdown Importer', 'markdown-importer'),
            'manage_options',
            'markdown-importer',
            [$this, 'render_page'],
            'dashicons-shield',
            null
        );
    }

    public function enqueue($hook)
    {
        if ($hook !== 'toplevel_page_markdown-importer') {
            return;
        }
        wp_enqueue_style('dashicons');
        wp_enqueue_code_editor(['type' => 'text/html']);
        wp_enqueue_style(
            'mi-admin',
            MI_PLUGIN_URL . 'assets/admin.css',
            [],
            MI_VERSION
        );
        wp_register_script(
            'mi-wp-schedule',
            MI_PLUGIN_URL . 'assets/mi-wp-schedule.js',
            ['wp-element', 'wp-components', 'wp-date', 'wp-i18n'],
            MI_VERSION,
            true
        );
        wp_enqueue_script('mi-wp-schedule');
        if (wp_style_is('wp-components', 'registered')) {
            wp_enqueue_style('wp-components');
        }
        if (wp_style_is('wp-edit-post', 'registered')) {
            wp_enqueue_style('wp-edit-post');
        }
        wp_enqueue_script(
            'mi-admin',
            MI_PLUGIN_URL . 'assets/admin.js',
            ['jquery', 'code-editor', 'mi-wp-schedule'],
            MI_VERSION,
            true
        );

        wp_localize_script(
            'mi-admin',
            'MIAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce(MI_Ajax::NONCE),
                'upgradeTabUrl' => admin_url('admin.php?page=markdown-importer&tab=upgrade'),
                'previewLabel' => __('Preview', 'markdown-importer'),
                'i18n' => [
                    'viewStaged' => __('View', 'markdown-importer'),
                    'editStaged' => __('Edit', 'markdown-importer'),
                    'removeFromQueue' => __('Remove from queue', 'markdown-importer'),
                    'stagedArticleActions' => __('Staged article actions', 'markdown-importer'),
                    'ctaNameRequired' => __('Enter a CTA name before saving (e.g. START).', 'markdown-importer'),
                    'confirmImport' => __('Import articles now?', 'markdown-importer'),
                    'confirmUpgrade' => __('Update articles now?', 'markdown-importer'),
                    'confirmDelete' => __('Delete this article permanently?', 'markdown-importer'),
                    'confirmClear' => __('Discard staged files?', 'markdown-importer'),
                    'saved' => __('Saved.', 'markdown-importer'),
                    'keywordRequired' => __('Keyword is required.', 'markdown-importer'),
                    'slugTitleRequired' => __('URL slug and title are required.', 'markdown-importer'),
                    'publishImmediately' => __('Immediately', 'markdown-importer'),
                    'publishToggleLabel' => __('Publish on:', 'markdown-importer'),
                    'scheduleNowBtn' => __('Now', 'markdown-importer'),
                    'scheduleConfirmBtn' => __('Schedule', 'markdown-importer'),
                    'schedulePickerUnavailable' => __('Date picker could not load.', 'markdown-importer'),
                    'visibilityScheduled' => __('Scheduled', 'markdown-importer'),
                    'visibilityWithPassword' => __('password', 'markdown-importer'),
                    'error' => __('Something went wrong.', 'markdown-importer'),
                ],
            ]
        );
    }

    public function render_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'upload';
        $allowed = ['upload', 'articles', 'cta', 'upgrade'];
        if (! in_array($tab, $allowed, true)) {
            $tab = 'upload';
        }
        require MI_PLUGIN_DIR . 'admin/views/page.php';
    }
}
