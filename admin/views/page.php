<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap mi-wrap">
    <h1 class="mi-page-title"><?php echo esc_html(get_admin_page_title()); ?></h1>

    <h2 class="nav-tab-wrapper mi-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=markdown-importer&tab=upload')); ?>" class="nav-tab <?php echo $tab === 'upload' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Upload', 'markdown-importer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=markdown-importer&tab=articles')); ?>" class="nav-tab <?php echo $tab === 'articles' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Article Overview', 'markdown-importer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=markdown-importer&tab=cta')); ?>" class="nav-tab <?php echo $tab === 'cta' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('CTA Buttons', 'markdown-importer'); ?></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=markdown-importer&tab=upgrade')); ?>" class="nav-tab <?php echo $tab === 'upgrade' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Upgrade Articles', 'markdown-importer'); ?></a>
    </h2>

    <div class="mi-tab-body">
        <?php if ($tab === 'upload') : ?>
            <?php require MI_PLUGIN_DIR . 'admin/views/tab-upload.php'; ?>
        <?php elseif ($tab === 'articles') : ?>
            <?php require MI_PLUGIN_DIR . 'admin/views/tab-articles.php'; ?>
        <?php elseif ($tab === 'cta') : ?>
            <?php require MI_PLUGIN_DIR . 'admin/views/tab-cta.php'; ?>
        <?php else : ?>
            <?php require MI_PLUGIN_DIR . 'admin/views/tab-upgrade.php'; ?>
        <?php endif; ?>
    </div>
</div>
