<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-upgrade-root" class="mi-panel">
    <div class="mi-step-label"><?php esc_html_e('Upgrade Articles [1/1]', 'markdown-importer'); ?></div>

    <div class="mi-section-header"><?php esc_html_e('Articles', 'markdown-importer'); ?></div>

    <div class="mi-dropzone" id="mi-upgrade-dropzone">
        <p class="mi-dropzone-text"><?php esc_html_e('Drop files to upload', 'markdown-importer'); ?></p>
        <p class="mi-or"><?php esc_html_e('or', 'markdown-importer'); ?></p>
        <button type="button" class="button button-secondary" id="mi-upgrade-file-picker"><?php esc_html_e('Select Files', 'markdown-importer'); ?></button>
        <input type="file" id="mi-upgrade-file-input" multiple accept=".md,.markdown" class="mi-hidden-input" />
    </div>

    <div class="mi-toolbar">
        <input type="search" id="mi-upgrade-search" class="mi-search" placeholder="<?php esc_attr_e('Search…', 'markdown-importer'); ?>" />
        <button type="button" class="button" id="mi-upgrade-search-btn"><span class="dashicons dashicons-search"></span></button>
    </div>

    <table class="widefat striped mi-table" id="mi-upgrade-articles">
        <thead>
            <tr>
                <th><?php esc_html_e('id', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('keyword', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Url-slug', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Visibility', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Release Date', 'markdown-importer'); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div id="mi-upgrade-queue-wrap" class="mi-hidden">
        <h3><?php esc_html_e('Staged updates', 'markdown-importer'); ?></h3>
        <table class="widefat striped mi-table" id="mi-upgrade-queue-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('keyword', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('Url-slug', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('release date', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('Actions', 'markdown-importer'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <p class="mi-actions-row">
            <button type="button" class="button button-primary" id="mi-confirm-upgrade"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Confirm', 'markdown-importer'); ?></button>
            <button type="button" class="button mi-btn-danger" id="mi-cancel-upgrade"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Cancel', 'markdown-importer'); ?></button>
        </p>
    </div>
</div>
