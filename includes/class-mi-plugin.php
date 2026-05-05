<?php

if (! defined('ABSPATH')) {
    exit;
}

class MI_Plugin
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
        // Must run on init — $wp_rewrite is null before init (add_rewrite_tag() fatal).
        add_action('init', [MI_Post_Type::class, 'register'], 0);
        add_action(
            'init',
            static function () {
                if (get_option('mi_flush_rewrite_rules_flag')) {
                    flush_rewrite_rules();
                    delete_option('mi_flush_rewrite_rules_flag');
                }
            },
            99
        );
        add_action('template_redirect', [MI_Renderer::class, 'template_redirect_private_article'], 5);
        add_action('wp_head', [MI_Renderer::class, 'head_meta'], 1);
        add_filter('the_content', [MI_Renderer::class, 'filter_content'], 8);
        add_action(MI_Article_Service::CRON_HOOK_APPLY_UPGRADE, [MI_Article_Service::class, 'apply_scheduled_upgrade'], 10, 1);

        if (is_admin()) {
            MI_Admin::instance();
            MI_Ajax::register();
        }
    }

    public static function activate()
    {
        update_option('mi_flush_rewrite_rules_flag', true);
    }
}
