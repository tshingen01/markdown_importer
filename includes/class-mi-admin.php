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
        wp_enqueue_style(
            'mi-admin',
            MI_PLUGIN_URL . 'assets/admin.css',
            [],
            MI_VERSION
        );
        wp_enqueue_script(
            'mi-admin',
            MI_PLUGIN_URL . 'assets/admin.js',
            ['jquery'],
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
                    'ctaNameRequired' => __('Enter a CTA name before saving (e.g. CTA_START).', 'markdown-importer'),
                    'confirmImport' => __('Import articles now?', 'markdown-importer'),
                    'confirmUpgrade' => __('Update articles now?', 'markdown-importer'),
                    'confirmDelete' => __('Delete this article permanently?', 'markdown-importer'),
                    'confirmClear' => __('Discard staged files?', 'markdown-importer'),
                    'saved' => __('Saved.', 'markdown-importer'),
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
