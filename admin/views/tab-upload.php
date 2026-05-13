<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-upload-root" class="mi-panel">
    <div class="mi-step-label" id="mi-upload-step-label"><?php esc_html_e('Upload [1/2]', 'markdown-importer'); ?></div>

    <div class="mi-section-header"><?php esc_html_e('Upload new Articles', 'markdown-importer'); ?></div>

    <div id="mi-upload-step1">
        <div class="mi-dropzone" id="mi-dropzone">
            <p class="mi-dropzone-text"><?php esc_html_e('Drop files to upload', 'markdown-importer'); ?></p>
            <p class="mi-or"><?php esc_html_e('or', 'markdown-importer'); ?></p>
            <button type="button" class="button button-secondary" id="mi-file-picker"><?php esc_html_e('Select Files', 'markdown-importer'); ?></button>
            <input type="file" id="mi-file-input" multiple accept=".md,.markdown" class="mi-hidden-input" />
        </div>

        <div class="mi-md-template">
            <h3><?php esc_html_e('Markdown template (copy/paste)', 'markdown-importer'); ?></h3>
            <textarea class="mi-md-template-code" readonly spellcheck="false">
Commit
[[2026_05_21::18_55]]
[[PRIVATE]]
This article will explain to you how to invest in Bitcoin
invest-in-bitcoin-today
How to Invest in Bitcoin
# How to Invest in Bitcoin
Intro text...
[[image::ALT-ATTRIBUTE::xxx.png]]
[[Bitcoin::Bitcoin::self]]
[[https://google.com::Google::blank]]
[[CTA::START]]
            </textarea>
            <p class="description"><?php esc_html_e('Lines 1–5: release, visibility, meta description, URL slug, title (no blank lines between). Line 6 onward: markdown body. Visibility line: [[PRIVATE]], [[DRAFT]], [[SCHEDULED]] or [[SCHEDULED::password]] (public at line 1 date when that date is in the future), [[PUBLIC]], or [[PUBLIC::password]].', 'markdown-importer'); ?></p>
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

        <div id="mi-queue-editor" class="mi-article-editor mi-queue-editor mi-hidden">
            <hr />
            <h3><?php esc_html_e('View / edit staged article', 'markdown-importer'); ?></h3>
            <p class="description" id="mi-queue-editor-filename-wrap">
                <?php esc_html_e('File:', 'markdown-importer'); ?>
                <code id="mi-q-filename"></code>
            </p>
            <div class="mi-editor-grid">
                <div class="mi-editor-main">
                    <label class="mi-label"><?php esc_html_e('Title', 'markdown-importer'); ?></label>
                    <input type="text" class="widefat" id="mi-q-e-title" />

                    <label class="mi-label"><?php esc_html_e('Keyword', 'markdown-importer'); ?></label>
                    <input type="text" class="widefat" id="mi-q-e-keyword" placeholder="<?php esc_attr_e('Unique per article', 'markdown-importer'); ?>" />

                    <label class="mi-label"><?php esc_html_e('URL Slug', 'markdown-importer'); ?></label>
                    <input type="text" class="widefat" id="mi-q-e-slug" />

                    <label class="mi-label"><?php esc_html_e('Meta description', 'markdown-importer'); ?></label>
                    <textarea class="widefat" rows="2" id="mi-q-e-meta"></textarea>

                    <label class="mi-label"><?php esc_html_e('Text Editor', 'markdown-importer'); ?></label>
                    <textarea class="widefat mi-code" rows="16" id="mi-q-e-md"></textarea>
                </div>
                <div class="mi-editor-side">
                    <div class="mi-side-box">
                        <strong><?php esc_html_e('Visibility', 'markdown-importer'); ?></strong>: <br />
                        <label><input type="radio" name="mi-q-e-vis" value="draft" /> <?php esc_html_e('Draft', 'markdown-importer'); ?></label><br />
                        <label><input type="radio" name="mi-q-e-vis" value="private" /> <?php esc_html_e('Private', 'markdown-importer'); ?></label><br />
                        <label><input type="radio" name="mi-q-e-vis" value="future" /> <?php esc_html_e('Scheduled', 'markdown-importer'); ?></label><br />
                        <label><input type="radio" name="mi-q-e-vis" value="publish" /> <?php esc_html_e('Public', 'markdown-importer'); ?></label><br />
                        <label class="mi-label" for="mi-q-e-password"><?php esc_html_e('Password when public or scheduled (optional)', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-q-e-password" />
                    </div>
                    <?php
                    $variant = 'queue';
                    require MI_PLUGIN_DIR . 'admin/views/partials/release-scheduler.php';
                    ?>
                    <div class="mi-side-actions">
                        <button type="button" class="button button-primary" id="mi-q-e-save"><?php esc_html_e('SAVE CHANGES', 'markdown-importer'); ?></button>
                        <button type="button" class="button" id="mi-q-e-close"><?php esc_html_e('Close', 'markdown-importer'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mi-actions-row">
            <button type="button" class="button button-primary mi-btn-confirm" id="mi-confirm-import">
                <span class="dashicons dashicons-yes mi-actions-row-icon" aria-hidden="true"></span>
                <span class="mi-actions-row-label"><?php esc_html_e('Confirm', 'markdown-importer'); ?></span>
            </button>
            <button type="button" class="button mi-btn-danger" id="mi-cancel-import">
                <span class="dashicons dashicons-dismiss mi-actions-row-icon" aria-hidden="true"></span>
                <span class="mi-actions-row-label"><?php esc_html_e('Discard', 'markdown-importer'); ?></span>
            </button>
        </div>
    </div>
</div>
