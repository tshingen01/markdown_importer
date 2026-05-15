<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div id="mi-upgrade-root" class="mi-panel">
    <div class="mi-step-label"><?php esc_html_e('Upgrade Articles [1/1]', 'markdown-importer'); ?></div>

    <div class="mi-section-header">
        <?php esc_html_e('Articles', 'markdown-importer'); ?>
        <div class="mi-actions-row mi-hidden">
            <button type="button" class="button button-primary mi-btn-confirm" id="mi-confirm-upgrade">
                <span class="dashicons dashicons-yes mi-actions-row-icon" aria-hidden="true"></span>
                <span class="mi-actions-row-label"><?php esc_html_e('Confirm', 'markdown-importer'); ?></span>
            </button>
            <button type="button" class="button mi-btn-danger" id="mi-cancel-upgrade">
                <span class="dashicons dashicons-dismiss mi-actions-row-icon" aria-hidden="true"></span>
                <span class="mi-actions-row-label"><?php esc_html_e('Cancel', 'markdown-importer'); ?></span>
            </button>
        </div>
    </div>

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
    <div id="mi-upgrade-queue-wrap" class="mi-hidden">
        <table class="widefat striped mi-table" id="mi-upgrade-queue-table">
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
    </div>
    <h3><?php esc_html_e('Articles', 'markdown-importer'); ?></h3>
    <table class="widefat striped mi-table" id="mi-upgrade-articles">
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

    <div class="mi-modal" id="mi-upgrade-queue-editor-modal">
        <div class="mi-modal-content">
            <span class="mi-modal-close" id="mi-uq-e-modal-close">&times;</span>
            <h3><?php esc_html_e('View / edit staged upgrade article', 'markdown-importer'); ?></h3>
            <div id="mi-u-queue-editor" class="mi-article-editor mi-u-queue-editor">
                <p class="description" id="mi-u-queue-editor-filename-wrap">
                    <?php esc_html_e('File:', 'markdown-importer'); ?>
                    <code id="mi-uq-filename"></code>
                </p>
                <div class="mi-editor-grid">
                    <div class="mi-editor-main">
                        <label class="mi-label"><?php esc_html_e('Comment', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-uq-e-comment" placeholder="<?php esc_attr_e('Optional Comment', 'markdown-importer'); ?>" />
                        
                        <label class="mi-label"><?php esc_html_e('Categories', 'markdown-importer'); ?></label>
                        <select id="mi-uq-e-categories" name="mi-uq-e-categories[]" multiple ></select> 
                        
                        <label class="mi-label"><?php esc_html_e('Title', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-uq-e-title" />

                        <label class="mi-label"><?php esc_html_e('Keyword', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-uq-e-keyword" placeholder="<?php esc_attr_e('Unique per article', 'markdown-importer'); ?>" />

                        <label class="mi-label"><?php esc_html_e('URL Slug', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-uq-e-slug" />

                        <label class="mi-label"><?php esc_html_e('Meta description', 'markdown-importer'); ?></label>
                        <textarea class="widefat" rows="2" id="mi-uq-e-meta"></textarea>

                        <label class="mi-label"><?php esc_html_e('Text Editor', 'markdown-importer'); ?></label>
                        <textarea class="widefat mi-code" rows="16" id="mi-uq-e-md"></textarea>
                    </div>
                    <div class="mi-editor-side">
                        <div class="mi-side-box">
                            <strong><?php esc_html_e('Visibility', 'markdown-importer'); ?></strong>: <br />
                            <label><input type="radio" name="mi-uq-e-vis" value="draft" /> <?php esc_html_e('Draft', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-uq-e-vis" value="private" /> <?php esc_html_e('Private', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-uq-e-vis" value="future" /> <?php esc_html_e('Scheduled', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-uq-e-vis" value="publish" /> <?php esc_html_e('Public', 'markdown-importer'); ?></label><br />
                            <label class="mi-label" for="mi-uq-e-password"><?php esc_html_e('Password when public or scheduled (optional)', 'markdown-importer'); ?></label>
                            <input type="text" class="widefat" id="mi-uq-e-password" />
                        </div>
                        <?php
                            $variant = 'up-queue';
                            require MI_PLUGIN_DIR . 'admin/views/partials/release-scheduler.php';
                        ?>
                        <div class="mi-side-actions">
                            <button type="button" class="button button-primary" id="mi-uq-e-save"><?php esc_html_e('SAVE CHANGES', 'markdown-importer'); ?></button>
                            <button type="button" class="button" id="mi-uq-e-close"><?php esc_html_e('Close', 'markdown-importer'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
    
    <div class="mi-modal" id="mi-upgrade-article-modal">
        <div class="mi-modal-content">
            <span class="mi-modal-close" id="mi-ua-e-modal-close">&times;</span>
            <h3><?php esc_html_e('Edit article', 'markdown-importer'); ?></h3>
            <div id="mi-upgrade-article-editor" class="mi-upgrade-article-editor">
                <div class="mi-editor-grid">
                    <div class="mi-editor-main">
                        <label class="mi-label"><?php esc_html_e('Comment', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-ua-e-comment" placeholder="<?php esc_attr_e('Optional comment', 'markdown-importer'); ?>" />
                        
                        <label class="mi-label"><?php esc_html_e('Categories', 'markdown-importer'); ?></label>
                        <select id="mi-ua-e-categories" name="mi-ua-e-categories[]" multiple ></select>
                        
                        <label class="mi-label"><?php esc_html_e('Title', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-ua-e-title" />
        
                        
                        <label class="mi-label"><?php esc_html_e('Keyword', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-ua-e-keyword" placeholder="<?php esc_attr_e('From filename; must be unique', 'markdown-importer'); ?>" />
        
                        <label class="mi-label"><?php esc_html_e('URL Slug', 'markdown-importer'); ?></label>
                        <input type="text" class="widefat" id="mi-ua-e-slug" />
        
                        <label class="mi-label"><?php esc_html_e('Meta description', 'markdown-importer'); ?></label>
                        <textarea class="widefat" rows="2" id="mi-ua-e-meta"></textarea>
        
                        <label class="mi-label"><?php esc_html_e('Article Content', 'markdown-importer'); ?></label>
                        <textarea class="widefat mi-code" rows="16" id="mi-ua-e-md"></textarea>
                    </div>
                    <div class="mi-editor-side">
                        <div class="mi-side-box">
                            <strong><?php esc_html_e('Visibility', 'markdown-importer'); ?></strong>: <br />
                            <label><input type="radio" name="mi-ua-e-vis" value="draft" /> <?php esc_html_e('Draft', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-ua-e-vis" value="private" /> <?php esc_html_e('Private', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-ua-e-vis" value="future" /> <?php esc_html_e('Scheduled', 'markdown-importer'); ?></label><br />
                            <label><input type="radio" name="mi-ua-e-vis" value="publish" /> <?php esc_html_e('Public', 'markdown-importer'); ?></label><br />
                            <label class="mi-label" for="mi-ua-e-password"><?php esc_html_e('Password when public or scheduled (optional)', 'markdown-importer'); ?></label>
                            <input type="text" class="widefat" id="mi-ua-e-password" />
                        </div>
                        <?php
                        $variant = 'up-article';
                        require MI_PLUGIN_DIR . 'admin/views/partials/release-scheduler.php';
                        ?>
                        <div class="mi-side-actions">
                            <button type="button" class="button button-primary" id="mi-ua-e-save"><?php esc_html_e('SAVE CHANGES', 'markdown-importer'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
    </div>

</div>
