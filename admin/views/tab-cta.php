<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-cta-root" class="mi-panel">
    <div class="mi-section-header"><?php esc_html_e('Edit/add CTA Buttons', 'markdown-importer'); ?></div>
    <div class="mi-cta-layout">
        <div class="mi-cta-editor" id="mi-cta-editor">
            <p class="description mi-cta-hint mi-hidden" id="mi-cta-hint"><?php esc_html_e('New CTA: choose a unique name (used as [[CTA::name]]), add HTML/CSS, then click Save.', 'markdown-importer'); ?></p>
            <label class="mi-label"><?php esc_html_e('Name', 'markdown-importer'); ?></label>
            <input type="text" class="widefat" id="mi-cta-name" placeholder="CTA_START" />

            <label class="mi-label"><?php esc_html_e('HTML / CSS', 'markdown-importer'); ?></label>
            <textarea class="widefat mi-code" rows="18" id="mi-cta-code"></textarea>

            <p><button type="button" class="button button-primary" id="mi-cta-save"><?php esc_html_e('save', 'markdown-importer'); ?></button></p>
        </div>
        <div class="mi-cta-list-wrap">
            <div class="mi-toolbar">
                <input type="search" id="mi-cta-search" class="mi-search" placeholder="<?php esc_attr_e('Search…', 'markdown-importer'); ?>" />
                <button type="button" class="button" id="mi-cta-search-btn"><span class="dashicons dashicons-search"></span></button>
            </div>
            <ul id="mi-cta-list" class="mi-cta-list"></ul>
            <p class="mi-cta-add-row"><button type="button" class="button button-primary" id="mi-cta-add">+ <?php esc_html_e('ADD', 'markdown-importer'); ?></button></p>
        </div>
    </div>
</div>
