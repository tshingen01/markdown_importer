<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-articles-root" class="mi-panel">
    <div class="mi-section-header"><?php esc_html_e('Articles', 'markdown-importer'); ?></div>

    <div class="mi-toolbar">
        <input type="search" id="mi-articles-search" class="mi-search" placeholder="<?php esc_attr_e('Search…', 'markdown-importer'); ?>" />
        <button type="button" class="button" id="mi-articles-search-btn" aria-label="<?php esc_attr_e('Search', 'markdown-importer'); ?>"><span class="dashicons dashicons-search"></span></button>
    </div>

    <table class="widefat striped mi-table" id="mi-articles-table">
        <thead>
            <tr>
                <th><?php esc_html_e('id', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('keyword', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Url-slug', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Visibility', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Preview', 'markdown-importer'); ?></th>
                <th><?php esc_html_e('Actions', 'markdown-importer'); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>

    <div id="mi-article-editor" class="mi-article-editor mi-hidden">
        <hr />
        <h3><?php esc_html_e('Edit article', 'markdown-importer'); ?></h3>
        <div class="mi-editor-grid">
            <div class="mi-editor-main">
                <label class="mi-label"><?php esc_html_e('Title', 'markdown-importer'); ?></label>
                <input type="text" class="widefat" id="mi-e-title" />

                <label class="mi-label"><?php esc_html_e('Keyword', 'markdown-importer'); ?></label>
                <input type="text" class="widefat" id="mi-e-keyword" placeholder="<?php esc_attr_e('From filename; must be unique', 'markdown-importer'); ?>" />

                <label class="mi-label"><?php esc_html_e('URL Slug', 'markdown-importer'); ?></label>
                <input type="text" class="widefat" id="mi-e-slug" />

                <label class="mi-label"><?php esc_html_e('Meta description', 'markdown-importer'); ?></label>
                <textarea class="widefat" rows="2" id="mi-e-meta"></textarea>

                <label class="mi-label"><?php esc_html_e('Text Editor', 'markdown-importer'); ?></label>
                <textarea class="widefat mi-code" rows="16" id="mi-e-md"></textarea>
            </div>
            <div class="mi-editor-side">
                <div class="mi-side-box">
                    <strong><?php esc_html_e('Visibility', 'markdown-importer'); ?></strong>: <br />
                    <label><input type="radio" name="mi-e-vis" value="draft" /> <?php esc_html_e('Draft', 'markdown-importer'); ?></label><br />
                    <label><input type="radio" name="mi-e-vis" value="private" /> <?php esc_html_e('Private', 'markdown-importer'); ?></label><br />
                    <label><input type="radio" name="mi-e-vis" value="publish" /> <?php esc_html_e('Public', 'markdown-importer'); ?></label><br />
                    <label class="mi-label" for="mi-e-password"><?php esc_html_e('Public password (optional)', 'markdown-importer'); ?></label>
                    <input type="text" class="widefat" id="mi-e-password" />
                </div>
                <?php
                $variant = 'article';
                require MI_PLUGIN_DIR . 'admin/views/partials/release-scheduler.php';
                ?>
                <div class="mi-side-actions">
                    <button type="button" class="button button-primary" id="mi-e-save"><?php esc_html_e('SAVE CHANGES', 'markdown-importer'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
