<?php

if (! defined('ABSPATH')) {
    exit;
}

spl_autoload_register(
    function ($class) {
        if (strpos($class, 'MI_') !== 0) {
            return;
        }
        $file = MI_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
        if (is_readable($file)) {
            require_once $file;
        }
    }
);
