<?php
/**
 * Release field: hidden value + WordPress components DateTimePicker (editor-post-schedule pattern).
 *
 * @var string $variant 'article' or 'queue'
 */
if (! defined('ABSPATH')) {
    exit;
}

$variant = isset($variant) ? (string) $variant : 'article';
$is_queue = $variant === 'queue';

$hidden_id = $is_queue ? 'mi-q-e-release' : 'mi-e-release';
?>
<div class="mi-wp-schedule" data-readonly="">
    <span class="mi-label"><?php esc_html_e('Release date', 'markdown-importer'); ?></span>
    <input type="hidden" id="<?php echo esc_attr($hidden_id); ?>" value="now" autocomplete="off" />
    <div class="mi-wp-schedule-root" data-hidden-id="<?php echo esc_attr($hidden_id); ?>"></div>
</div>
