(function ($) {
    'use strict';

    function ajax(action, data) {
        return $.post(MIAdmin.ajaxUrl, $.extend({ action: action, nonce: MIAdmin.nonce }, data || {}));
    }

    function esc(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    /* ---------- Upload tab ---------- */
    function uploadTab() {
        var $root = $('#mi-upload-root');
        if (!$root.length) {
            return;
        }

        var $stepLabel = $('#mi-upload-step-label');
        var $step1 = $('#mi-upload-step1');
        var $step2 = $('#mi-upload-step2');
        var $tbody = $('#mi-queue-table tbody');

        function renderQueue(rows) {
            $tbody.empty();
            if (!rows || !rows.length) {
                $step2.addClass('mi-hidden');
                $step1.removeClass('mi-hidden');
                $stepLabel.text('Upload [1/2]');
                return;
            }
            $step1.addClass('mi-hidden');
            $step2.removeClass('mi-hidden');
            $stepLabel.text('Upload [2/2]');
            rows.forEach(function (row, idx) {
                var err = row.error ? '<div class="mi-error">' + esc(row.error) + '</div>' : '';
                var rd = esc(row.release_date || 'now');
                var tr =
                    '<tr data-id="' +
                    esc(row.id) +
                    '">' +
                    '<td>' +
                    (idx + 1) +
                    '</td>' +
                    '<td>' +
                    esc(row.keyword) +
                    err +
                    '</td>' +
                    '<td>' +
                    esc(row.slug) +
                    '</td>' +
                    '<td><input type="text" class="mi-release-input" value="' +
                    rd +
                    '" /></td>' +
                    '<td class="mi-inline-actions">' +
                    '<button type="button" class="button-link-delete mi-q-remove" title="Remove"><span class="dashicons dashicons-trash"></span></button>' +
                    '</td>' +
                    '</tr>';
                $tbody.append(tr);
            });
        }

        function loadQueue() {
            ajax('mi_fetch_import_queue').done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        }

        loadQueue();

        function uploadFiles(files) {
            var fd = new FormData();
            for (var i = 0; i < files.length; i++) {
                fd.append('files[]', files[i]);
            }
            fd.append('action', 'mi_stage_upload');
            fd.append('nonce', MIAdmin.nonce);
            $.ajax({
                url: MIAdmin.ajaxUrl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
            }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                } else {
                    alert(MIAdmin.i18n.error);
                }
            });
        }

        $('#mi-file-picker').on('click', function () {
            $('#mi-file-input').trigger('click');
        });
        $('#mi-file-input').on('change', function () {
            if (this.files && this.files.length) {
                uploadFiles(this.files);
            }
        });

        var $dz = $('#mi-dropzone');
        $dz.on('dragover dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dz.addClass('mi-dragover');
        });
        $dz.on('dragleave dragend drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            $dz.removeClass('mi-dragover');
        });
        $dz.on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length) {
                uploadFiles(files);
            }
        });

        $tbody.on('change', '.mi-release-input', function () {
            var $tr = $(this).closest('tr');
            var id = $tr.data('id');
            var val = $(this).val();
            ajax('mi_patch_queue_item', { id: id, release_date: val }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        });

        $tbody.on('click', '.mi-q-remove', function () {
            var $tr = $(this).closest('tr');
            ajax('mi_remove_queue_item', { id: $tr.data('id') }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        });

        $('#mi-confirm-import').on('click', function () {
            if (!window.confirm(MIAdmin.i18n.confirmImport)) {
                return;
            }
            ajax('mi_confirm_import').done(function (res) {
                if (res.success) {
                    renderQueue([]);
                    var msg = res.data.message || MIAdmin.i18n.saved;
                    if (res.data.failed && res.data.failed.length) {
                        var parts = [msg];
                        res.data.failed.forEach(function (f) {
                            parts.push((f.filename ? f.filename + ': ' : '') + (f.message || ''));
                        });
                        msg = parts.join('\n');
                    }
                    alert(msg);
                } else {
                    alert(MIAdmin.i18n.error);
                }
            });
        });

        $('#mi-cancel-import').on('click', function () {
            if (!window.confirm(MIAdmin.i18n.confirmClear)) {
                return;
            }
            ajax('mi_clear_import_queue').done(function (res) {
                if (res.success) {
                    renderQueue([]);
                }
            });
        });

        $('#mi-queue-search-btn').on('click', function () {
            var q = $('#mi-queue-search').val().toLowerCase();
            $('#mi-queue-table tbody tr').each(function () {
                var t = $(this).text().toLowerCase();
                $(this).toggle(t.indexOf(q) !== -1);
            });
        });
    }

    /* ---------- Articles ---------- */
    function articlesTab() {
        var $root = $('#mi-articles-root');
        if (!$root.length) {
            return;
        }

        var $tbody = $('#mi-articles-table tbody');
        var $editor = $('#mi-article-editor');
        var currentId = null;

        function loadList(term) {
            ajax('mi_list_articles', { search: term || '' }).done(function (res) {
                if (!res.success) {
                    return;
                }
                $tbody.empty();
                (res.data.articles || []).forEach(function (a) {
                    var visPublic = a.visibility !== 'private';
                    var tr =
                        '<tr data-id="' +
                        esc(a.id) +
                        '">' +
                        '<td>' +
                        esc(a.id) +
                        '</td>' +
                        '<td>' +
                        esc(a.keyword) +
                        '</td>' +
                        '<td>' +
                        esc(a.slug) +
                        '</td>' +
                        '<td class="mi-visibility-box">' +
                        '<label><input type="radio" name="vis-' +
                        esc(a.id) +
                        '" class="mi-vis" data-pub="1" ' +
                        (visPublic ? 'checked' : '') +
                        '/> ' +
                        esc('Public') +
                        '</label> ' +
                        '<label><input type="radio" name="vis-' +
                        esc(a.id) +
                        '" class="mi-vis" data-pub="0" ' +
                        (!visPublic ? 'checked' : '') +
                        '/> ' +
                        esc('Private') +
                        '</label></td>' +
                        '<td>' +
                        (a.permalink
                            ? '<a href="' +
                              esc(a.permalink) +
                              '" class="mi-preview-link" target="_blank" rel="noopener noreferrer">' +
                              esc(MIAdmin.previewLabel || 'Preview') +
                              ' <span class="dashicons dashicons-external"></span></a>'
                            : '') +
                        '</td>' +
                        '<td>' +
                        '<button type="button" class="button mi-a-edit" title="Edit"><span class="dashicons dashicons-edit"></span></button> ' +
                        '<button type="button" class="button mi-a-del" title="Delete"><span class="dashicons dashicons-trash"></span></button> ' +
                        '<a class="button" href="' +
                        esc(MIAdmin.upgradeTabUrl) +
                        '" title="Upgrade tab"><span class="dashicons dashicons-media-document"></span></a>' +
                        '</td>' +
                        '</tr>';
                    $tbody.append(tr);
                });
            });
        }

        loadList('');

        $('#mi-articles-search-btn').on('click', function () {
            loadList($('#mi-articles-search').val());
        });

        $tbody.on('change', '.mi-vis', function () {
            var id = $(this).closest('tr').data('id');
            var pub = $(this).attr('data-pub') === '1';
            ajax('mi_set_visibility', { id: id, public: pub ? 1 : 0 });
        });

        $tbody.on('click', '.mi-a-del', function () {
            if (!window.confirm(MIAdmin.i18n.confirmDelete)) {
                return;
            }
            var id = $(this).closest('tr').data('id');
            ajax('mi_delete_article', { id: id }).done(function (res) {
                if (res.success) {
                    if (currentId === id) {
                        $editor.addClass('mi-hidden');
                        currentId = null;
                    }
                    loadList($('#mi-articles-search').val());
                }
            });
        });

        $tbody.on('click', '.mi-a-edit', function () {
            var id = $(this).closest('tr').data('id');
            ajax('mi_get_article', { id: id }).done(function (res) {
                if (!res.success) {
                    return;
                }
                var a = res.data.article;
                currentId = a.id;
                $('#mi-e-title').val(a.title);
                $('#mi-e-keyword').val(a.keyword || '');
                $('#mi-e-slug').val(a.slug);
                $('#mi-e-meta').val(a.meta_description);
                $('#mi-e-md').val(a.markdown);
                $('#mi-e-release').val(a.release_date);
                $('input[name="mi-e-vis"][value="' + (a.visibility === 'private' ? 'private' : 'public') + '"]').prop('checked', true);
                $editor.removeClass('mi-hidden');
            });
        });

        $('#mi-e-save').on('click', function () {
            if (!currentId) {
                return;
            }
            ajax('mi_save_article', {
                id: currentId,
                title: $('#mi-e-title').val(),
                keyword: $('#mi-e-keyword').val(),
                slug: $('#mi-e-slug').val(),
                meta_description: $('#mi-e-meta').val(),
                markdown: $('#mi-e-md').val(),
                release_date: $('#mi-e-release').val(),
                visibility: $('input[name="mi-e-vis"]:checked').val(),
            }).done(function (res) {
                if (res.success) {
                    alert(MIAdmin.i18n.saved);
                    loadList($('#mi-articles-search').val());
                } else if (res.data && res.data.message) {
                    alert(res.data.message);
                } else {
                    alert(MIAdmin.i18n.error);
                }
            });
        });
    }

    /* ---------- CTA ---------- */
    function ctaTab() {
        var $root = $('#mi-cta-root');
        if (!$root.length) {
            return;
        }

        var $list = $('#mi-cta-list');
        var allCt = [];

        function renderList(filter) {
            $list.empty();
            var q = (filter || '').toLowerCase();
            allCt.forEach(function (c) {
                if (q && String(c.name).toLowerCase().indexOf(q) === -1) {
                    return;
                }
                var li =
                    '<li data-name="' +
                    esc(c.name) +
                    '"><span>' +
                    esc(c.display_name) +
                    '</span><span><button type="button" class="button-link mi-cta-pick"><span class="dashicons dashicons-edit"></span></button> ' +
                    '<button type="button" class="button-link-delete mi-cta-del"><span class="dashicons dashicons-trash"></span></button></span></li>';
                $list.append(li);
            });
        }

        function fetchCtas() {
            ajax('mi_list_ctas').done(function (res) {
                if (res.success) {
                    allCt = res.data.ctas || [];
                    renderList($('#mi-cta-search').val());
                }
            });
        }

        fetchCtas();

        var $editor = $('#mi-cta-editor');
        var $hint = $('#mi-cta-hint');
        var previewTimer;

        function updateCtaPreview() {
            var snippet = $('#mi-cta-code').val() || '';
            var frame = document.getElementById('mi-cta-preview-frame');
            if (!frame) {
                return;
            }
            var docHtml =
                '<!DOCTYPE html><html><head><meta charset="utf-8">' +
                '<style>html,body{margin:0;padding:12px;box-sizing:border-box;background:#fff;color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:14px;line-height:1.5;}</style>' +
                '</head><body>' +
                snippet +
                '</body></html>';
            try {
                /* srcdoc works with a strict sandbox; document.write needs contentDocument (blocked without allow-same-origin). */
                frame.srcdoc = docHtml;
            } catch (e) {
                /* ignore */
            }
        }

        function scheduleCtaPreview() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updateCtaPreview, 150);
        }

        $('#mi-cta-code').on('input change', scheduleCtaPreview);
        scheduleCtaPreview();

        function setNewCtaMode(on) {
            if (on) {
                $editor.addClass('mi-cta-editor--new');
                $hint.removeClass('mi-hidden');
                $('#mi-cta-cancel').removeClass('mi-hidden');
            } else {
                $editor.removeClass('mi-cta-editor--new');
                $hint.addClass('mi-hidden');
                $('#mi-cta-cancel').addClass('mi-hidden');
            }
        }

        $('#mi-cta-search-btn').on('click', function () {
            renderList($('#mi-cta-search').val());
        });

        $('#mi-cta-add').on('click', function (e) {
            e.preventDefault();
            setNewCtaMode(true);
            $('#mi-cta-name').val('').trigger('focus');
            $('#mi-cta-code').val('');
            scheduleCtaPreview();
            $list.find('li').removeClass('mi-active');
        });

        $('#mi-cta-cancel').on('click', function (e) {
            e.preventDefault();
            $('#mi-cta-name').val('');
            $('#mi-cta-code').val('');
            scheduleCtaPreview();
            setNewCtaMode(false);
            $list.find('li').removeClass('mi-active');
        });

        $list.on('click', '.mi-cta-pick', function () {
            var name = $(this).closest('li').data('name');
            var c = allCt.find(function (x) {
                return x.name === name;
            });
            if (c) {
                setNewCtaMode(false);
                $('#mi-cta-name').val(c.name);
                $('#mi-cta-code').val(c.code);
                scheduleCtaPreview();
                $list.find('li').removeClass('mi-active');
                $(this).closest('li').addClass('mi-active');
            }
        });

        $list.on('click', '.mi-cta-del', function () {
            var $li = $(this).closest('li');
            var name = $li.data('name');
            var wasActive = $li.hasClass('mi-active');
            ajax('mi_delete_cta', { name: name }).done(function (res) {
                if (res.success) {
                    allCt = res.data.ctas || [];
                    renderList($('#mi-cta-search').val());
                    if (wasActive) {
                        $('#mi-cta-name').val('');
                        $('#mi-cta-code').val('');
                        scheduleCtaPreview();
                        setNewCtaMode(false);
                    }
                }
            });
        });

        $('#mi-cta-save').on('click', function () {
            var saveName = $.trim($('#mi-cta-name').val());
            if (!saveName) {
                alert(MIAdmin.i18n.ctaNameRequired || 'Please enter a name for this CTA.');
                $('#mi-cta-name').trigger('focus');
                return;
            }
            ajax('mi_save_cta', {
                name: $('#mi-cta-name').val(),
                code: $('#mi-cta-code').val(),
            }).done(function (res) {
                if (res.success) {
                    allCt = res.data.ctas || [];
                    renderList($('#mi-cta-search').val());
                    setNewCtaMode(false);
                    $list.find('li').removeClass('mi-active');
                    $list.find('li').each(function () {
                        if ($(this).data('name') === saveName) {
                            $(this).addClass('mi-active');
                        }
                    });
                    alert(MIAdmin.i18n.saved);
                }
            });
        });
    }

    /* ---------- Upgrade ---------- */
    function upgradeTab() {
        var $root = $('#mi-upgrade-root');
        if (!$root.length) {
            return;
        }

        var $artBody = $('#mi-upgrade-articles tbody');
        var $qWrap = $('#mi-upgrade-queue-wrap');
        var $qBody = $('#mi-upgrade-queue-table tbody');

        function loadArticles(term) {
            ajax('mi_list_articles', { search: term || '' }).done(function (res) {
                if (!res.success) {
                    return;
                }
                $artBody.empty();
                (res.data.articles || []).forEach(function (a) {
                    var vis = a.visibility === 'private' ? 'Private' : 'Public';
                    var rd = a.release_date || '';
                    var tr =
                        '<tr>' +
                        '<td>' +
                        esc(a.id) +
                        '</td>' +
                        '<td>' +
                        esc(a.keyword) +
                        '</td>' +
                        '<td>' +
                        esc(a.slug) +
                        '</td>' +
                        '<td>' +
                        esc(vis) +
                        '</td>' +
                        '<td>' +
                        esc(rd) +
                        '</td>' +
                        '</tr>';
                    $artBody.append(tr);
                });
            });
        }

        function renderQueue(rows) {
            $qBody.empty();
            if (!rows || !rows.length) {
                $qWrap.addClass('mi-hidden');
                return;
            }
            $qWrap.removeClass('mi-hidden');
            rows.forEach(function (row) {
                var err = row.error ? '<div class="mi-error">' + esc(row.error) + '</div>' : '';
                var tr =
                    '<tr data-id="' +
                    esc(row.id) +
                    '">' +
                    '<td>' +
                    esc(row.keyword) +
                    err +
                    '</td>' +
                    '<td>' +
                    esc(row.slug) +
                    '</td>' +
                    '<td><input type="text" class="mi-release-input" value="' +
                    esc(row.release_date || 'now') +
                    '" /></td>' +
                    '<td><button type="button" class="button-link-delete mi-uq-remove"><span class="dashicons dashicons-trash"></span></button></td>' +
                    '</tr>';
                $qBody.append(tr);
            });
        }

        function loadQueue() {
            ajax('mi_fetch_upgrade_queue').done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        }

        loadArticles('');
        loadQueue();

        function uploadFiles(files) {
            var fd = new FormData();
            for (var i = 0; i < files.length; i++) {
                fd.append('files[]', files[i]);
            }
            fd.append('action', 'mi_upgrade_upload');
            fd.append('nonce', MIAdmin.nonce);
            $.ajax({
                url: MIAdmin.ajaxUrl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
            }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        }

        $('#mi-upgrade-file-picker').on('click', function () {
            $('#mi-upgrade-file-input').trigger('click');
        });
        $('#mi-upgrade-file-input').on('change', function () {
            if (this.files && this.files.length) {
                uploadFiles(this.files);
            }
        });

        var $udz = $('#mi-upgrade-dropzone');
        $udz.on('dragover dragenter', function (e) {
            e.preventDefault();
            $udz.addClass('mi-dragover');
        });
        $udz.on('dragleave drop', function (e) {
            e.preventDefault();
            $udz.removeClass('mi-dragover');
        });
        $udz.on('drop', function (e) {
            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length) {
                uploadFiles(files);
            }
        });

        $qBody.on('change', '.mi-release-input', function () {
            var $tr = $(this).closest('tr');
            ajax('mi_patch_upgrade_item', { id: $tr.data('id'), release_date: $(this).val() }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        });

        $qBody.on('click', '.mi-uq-remove', function () {
            ajax('mi_remove_upgrade_item', { id: $(this).closest('tr').data('id') }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        });

        $('#mi-confirm-upgrade').on('click', function () {
            if (!window.confirm(MIAdmin.i18n.confirmUpgrade)) {
                return;
            }
            ajax('mi_confirm_upgrade').done(function (res) {
                if (res.success) {
                    renderQueue([]);
                    loadArticles($('#mi-upgrade-search').val());
                    var msg = res.data.message || MIAdmin.i18n.saved;
                    if (res.data.failed && res.data.failed.length) {
                        var parts = [msg];
                        res.data.failed.forEach(function (f) {
                            parts.push((f.filename ? f.filename + ': ' : '') + (f.message || ''));
                        });
                        msg = parts.join('\n');
                    }
                    alert(msg);
                }
            });
        });

        $('#mi-cancel-upgrade').on('click', function () {
            if (!window.confirm(MIAdmin.i18n.confirmClear)) {
                return;
            }
            ajax('mi_clear_upgrade_queue').done(function (res) {
                if (res.success) {
                    renderQueue([]);
                }
            });
        });

        $('#mi-upgrade-search-btn').on('click', function () {
            loadArticles($('#mi-upgrade-search').val());
        });
    }

    $(function () {
        uploadTab();
        articlesTab();
        ctaTab();
        upgradeTab();
    });
})(jQuery);
