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

$hidden_id = 'mi-e-release';
if($variant === 'article') {
    $hidden_id = 'mi-e-release';
}
if($variant === 'up-article') {
    $hidden_id = 'mi-ua-e-release';
}
if($variant === 'queue') {
    $hidden_id = 'mi-q-e-release';
} 
if ($variant === 'up-queue') {
    $hidden_id = 'mi-uq-e-release';
}

?>
<div class="mi-wp-schedule" data-readonly="">
    <span class="mi-label"><?php esc_html_e('Release date', 'markdown-importer'); ?></span>
    <input type="hidden" id="<?php echo esc_attr($hidden_id); ?>" value="now" autocomplete="off" />
    <div class="mi-wp-schedule-root" data-hidden-id="<?php echo esc_attr($hidden_id); ?>"></div>
</div>
