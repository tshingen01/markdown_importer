<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-upload-root" class="mi-panel">
    <div class="mi-step-label" id="mi-upload-step-label"><?php esc_html_e('Upload [1/2]', 'markdown-importer'); ?></div>

    <div class="mi-section-header"><?php esc_html_e('Upload new Articles / Images', 'markdown-importer'); ?></div>

    <div id="mi-upload-step1">
        <div class="mi-dropzone" id="mi-dropzone">
            <p class="mi-dropzone-text"><?php esc_html_e('Drop files to upload', 'markdown-importer'); ?></p>
            <p class="mi-or"><?php esc_html_e('or', 'markdown-importer'); ?></p>
            <button type="button" class="button button-secondary" id="mi-file-picker"><?php esc_html_e('Select Files', 'markdown-importer'); ?></button>
            <input type="file" id="mi-file-input" multiple accept=".md,.markdown,.png,.jpg,.jpeg,.gif,.webp,.svg,image/*" class="mi-hidden-input" />
        </div>
    </div>

    <div id="mi-upload-step2" class="mi-hidden">
        <div class="mi-toolbar">
            <input type="search" id="mi-queue-search" class="mi-search" placeholder="<?php esc_attr_e('Search…', 'markdown-importer'); ?>" />
            <button type="button" class="button" id="mi-queue-search-btn" aria-label="<?php esc_attr_e('Search', 'markdown-importer'); ?>"><span class="dashicons dashicons-search"></span></button>
        </div>

        <table class="widefat striped mi-table" id="mi-queue-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('id', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('keyword', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('Url-slug', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('release date', 'markdown-importer'); ?></th>
                    <th><?php esc_html_e('Actions', 'markdown-importer'); ?></th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>

        <p class="mi-actions-row">
            <button type="button" class="button button-primary mi-btn-confirm" id="mi-confirm-import"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Confirm', 'markdown-importer'); ?></button>
            <button type="button" class="button mi-btn-danger" id="mi-cancel-import"><span class="dashicons dashicons-trash"></span> <?php esc_html_e('Cancel', 'markdown-importer'); ?></button>
        </p>
    </div>
</div>
