(function ($) {
    'use strict';

    function ajax(action, data) {
        return $.post(MIAdmin.ajaxUrl, $.extend({ action: action, nonce: MIAdmin.nonce }, data || {}));
    }

    function esc(s) {
        return $('<div/>').text(s == null ? '' : String(s)).html();
    }

    /** Sync React schedule UI when hidden #mi-e-release / #mi-q-e-release changes from jQuery. */
    var MIReleaseScheduler = {
        sync: function (hiddenId) {
            document.dispatchEvent(
                new CustomEvent('mi-release-scheduler-sync', { detail: { id: hiddenId } })
            );
        },
    };

    function renderEmptyTableRow($tbody, colspan, message) {
        $tbody.html('<tr class="mi-empty-row"><td colspan="' + colspan + '">' + esc(message) + '</td></tr>');
    }

    function csvEscape(value) {
        var s = String(value == null ? '' : value);
        return '"' + s.replace(/"/g, '""') + '"';
    }

    function buildValidationCsv(rows) {
        var out = ['"filename","keyword","keyword_status","release_date","release_status","visibility","visibility_status","url_slug","url_slug_status","errors"'];
        (rows || []).forEach(function (row) {
            out.push(
                [
                    csvEscape(row.filename || ''),
                    csvEscape(row.keyword || ''),
                    csvEscape(row.keyword_status || ''),
                    csvEscape(row.release_date || ''),
                    csvEscape(row.release_status || ''),
                    csvEscape(row.visibility || ''),
                    csvEscape(row.visibility_status || ''),
                    csvEscape(row.slug || ''),
                    csvEscape(row.slug_status || ''),
                    csvEscape((row.errors || []).join(' | ')),
                ].join(',')
            );
        });
        return out.join('\r\n');
    }

    function buildFailedResultCsv(rows) {
        var out = ['"filename","release_date","visibility","url_slug","message"'];
        (rows || []).forEach(function (row) {
            out.push(
                [
                    csvEscape(row.filename || ''),
                    csvEscape(row.release_date || ''),
                    csvEscape(row.visibility || ''),
                    csvEscape(row.slug || ''),
                    csvEscape(row.message || ''),
                ].join(',')
            );
        });
        return out.join('\r\n');
    }

    function downloadValidationCsv(rows) {
        var csv = buildValidationCsv(rows || []);
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        var stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        a.href = url;
        a.download = 'mi-md-validation-' + stamp + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function downloadFailedResultCsv(rows) {
        var csv = buildFailedResultCsv(rows || []);
        var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        var stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
        a.href = url;
        a.download = 'mi-import-failed-' + stamp + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    function showUploadValidationErrors(rows, message, batchUploaded, batchTotal) {
        var parts = [];
        var invalidCount = (rows || []).length;
        if (typeof batchUploaded !== 'undefined' && typeof batchTotal !== 'undefined') {
            parts.push(
                'Upload failed: ' +
                String(invalidCount) +
                ' invalid file(s). ' +
                String(batchUploaded) +
                '/' +
                String(batchTotal) +
                ' uploaded.'
            );
        } else {
            parts.push('Upload failed: ' + String(invalidCount) + ' invalid file(s).');
        }
        parts.push(message || 'Upload blocked because some markdown files are invalid.');
        (rows || []).forEach(function (row) {
            var details = (row.errors || []).join('; ');
            parts.push((row.filename || 'unknown.md') + ': ' + details);
        });
        alert(parts.join('\n'));
        if (rows && rows.length) {
            downloadValidationCsv(rows);
        }
    }

    function releaseToToken(release) {
        var v = $.trim(String(release || 'now'));
        if (!v || v.toLowerCase() === 'now') {
            return '[[now]]';
        }
        var m = v.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?$/);
        if (!m) {
            return '[[now]]';
        }
        var hh = m[4] || '12';
        var mm = m[5] || '00';
        return '[[' + m[1] + '_' + m[2] + '_' + m[3] + '::' + hh + '_' + mm + ']]';
    }

    function visibilityToToken(visibility, password) {
        var vis = String(visibility || 'private').toLowerCase();
        var pwd = $.trim(String(password || ''));
        if (vis === 'publish') {
            return pwd ? '[[PUBLIC::' + pwd + ']]' : '[[PUBLIC]]';
        }
        if (vis === 'draft') {
            return '[[DRAFT]]';
        }
        if (vis === 'future') {
            return pwd ? '[[SCHEDULED::' + pwd + ']]' : '[[SCHEDULED]]';
        }
        return '[[PRIVATE]]';
    }

    function buildStructuredMd(article) {
        var body = String(article.markdown || '').replace(/^\n+/, '');
        return (
            String(article.comment || '') +
            '\n' +
            releaseToToken(article.release_date) +
            '\n' +
            visibilityToToken(article.visibility, article.password) +
            '\n' +
            String(article.meta_description || '') +
            '\n' +
            String(article.slug || '') +
            '\n' +
            String(article.title || '') +
            '\n' +
            body
        );
    }

    function parseReleaseToken(line) {
        var raw = $.trim(line || '');
        var mNow = raw.match(/^\[\[\s*now\s*\]\]$/i);
        if (mNow) {
            return 'now';
        }
        var m = raw.match(/^\[\[(\d{4})[ _-](\d{2})[ _-](\d{2})::(\d{2})[ _-](\d{2})\]\]$/i);
        if (!m) {
            return null;
        }
        return m[1] + '-' + m[2] + '-' + m[3] + ' ' + m[4] + ':' + m[5];
    }

    function parseVisibilityToken(line) {
        var raw = $.trim(line || '');
        var m = raw.match(/^\[\[(.+)\]\]$/);
        if (!m) {
            return null;
        }
        var inner = $.trim(m[1]);
        if (/^PRIVATE$/i.test(inner)) {
            return { visibility: 'private', password: '' };
        }
        if (/^DRAFT$/i.test(inner)) {
            return { visibility: 'draft', password: '' };
        }
        var ps = inner.match(/^SCHEDULED::(.+)$/i);
        if (ps) {
            return { visibility: 'future', password: $.trim(ps[1]) };
        }
        if (/^SCHEDULED$/i.test(inner)) {
            return { visibility: 'future', password: '' };
        }
        if (/^PUBLIC$/i.test(inner)) {
            return { visibility: 'publish', password: '' };
        }
        var p = inner.match(/^PUBLIC::(.+)$/i);
        if (p) {
            return { visibility: 'publish', password: $.trim(p[1]) };
        }
        return null;
    }

    function parseStructuredMd(md) {
        var content = String(md || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var lines = content.split('\n');
        if (lines.length < 6) {
            return { ok: false, message: 'Invalid MD structure. Expect lines 1–6 header (comment, release, visibility, meta, slug, title) then markdown body.' };
        }
        var comment = $.trim(lines[0] || '');
        var releaseDate = parseReleaseToken(lines[1]);
        if (!releaseDate) {
            return { ok: false, message: 'Line 2 must be [[YYYY_MM_DD::HH_MM]] or [[now]].' };
        }
        var vis = parseVisibilityToken(lines[2]);
        if (!vis) {
            return { ok: false, message: 'Line 3 must be [[PRIVATE]], [[DRAFT]], [[SCHEDULED]], [[SCHEDULED::password]], [[PUBLIC]], or [[PUBLIC::password]].' };
        }
        var meta = $.trim(lines[3] || '');
        var slug = $.trim(lines[4] || '');
        var title = $.trim(lines[5] || '');
        if (!slug || !title) {
            return { ok: false, message: 'Line 5 (slug) and line 6 (title) are required.' };
        }
        var markdown = lines.slice(6).join('\n').replace(/^\n+/, '');

        return {
            ok: true,
            release_date: releaseDate,
            visibility: vis.visibility,
            password: vis.password,
            meta_description: meta,
            slug: slug,
            title: title,
            comment: comment,
            markdown: markdown,
        };
    }

    function visibilityLabel(status, password) {
        var s = String(status || '').toLowerCase();
        if (s === 'private') {
            return 'Private';
        }
        if (s === 'draft') {
            return 'Draft';
        }
        if (s === 'publish') {
            return password ? 'Public (password)' : 'Public';
        }
        if (s === 'future') {
            var sched = MIAdmin.i18n && MIAdmin.i18n.visibilityScheduled ? MIAdmin.i18n.visibilityScheduled : 'Scheduled';
            return password ? sched + ' (' + (MIAdmin.i18n.visibilityWithPassword || 'password') + ')' : sched;
        }
        return 'Private';
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
        var $queueEditor = $('#mi-queue-editor');
        var currentQueueId = null;

        function syncQueuePasswordField() {
            var vis = $('input[name="mi-q-e-vis"]:checked').val();
            var $pwd = $('#mi-q-e-password');
            var isPublic = vis === 'publish' || vis === 'future';
            $pwd.prop('disabled', !isPublic);
            if (!isPublic) {
                $pwd.val('');
            }
        }

        function setQueueEditorMode(viewOnly) {
            var ro = !!viewOnly;
            $queueEditor.toggleClass('mi-queue-view-mode', ro);
            $('#mi-q-e-save').toggle(!ro);
            $(
                '#mi-q-e-title, #mi-q-e-keyword, #mi-q-e-slug, #mi-q-e-meta, #mi-q-e-md, #mi-q-e-password'
            )
                .prop('readonly', ro)
                .prop('disabled', false);
            $('input[name="mi-q-e-vis"]').prop('disabled', ro);
            $queueEditor.find('.mi-wp-schedule').attr('data-readonly', ro ? '1' : '');
            if (ro) {
                $('#mi-q-e-password').prop('disabled', false);
            }
        }

        function fillQueueEditorFromItem(it, viewOnly) {
            if (!it) {
                return;
            }
            setQueueEditorMode(viewOnly);
            currentQueueId = it.id;
            $('#mi-q-filename').text(it.filename || '');
            $('#mi-q-e-title').val(it.title || '');
            $('#mi-q-e-keyword').val(it.keyword || '');
            $('#mi-q-e-slug').val(it.slug || '');
            $('#mi-q-e-meta').val(it.meta_description || '');
            $('#mi-q-e-md').val(buildStructuredMd(it));
            $('#mi-q-e-release').val(it.release_date || 'now');
            MIReleaseScheduler.sync('mi-q-e-release');
            $('#mi-q-e-password').val(it.password || '');
            var v = String(it.visibility || 'private').toLowerCase();
            if (v !== 'publish' && v !== 'private' && v !== 'draft' && v !== 'future') {
                v = 'private';
            }
            $('input[name="mi-q-e-vis"][value="' + v + '"]').prop('checked', true);
            if (!viewOnly) {
                syncQueuePasswordField();
            }
        }

        function renderQueue(rows) {
            $tbody.empty();
            if (!rows || !rows.length) {
                currentQueueId = null;
                $queueEditor.addClass('mi-hidden');
                $step2.addClass('mi-hidden');
                $step1.removeClass('mi-hidden');
                $stepLabel.text('Upload [1/2]');
                return;
            }
            $step1.addClass('mi-hidden');
            $step2.removeClass('mi-hidden');
            $stepLabel.text('Upload [2/2]');
            rows.forEach(function (row) {
                var err = row.error ? '<div class="mi-error">' + esc(row.error) + '</div>' : '';
                var rd = esc(row.release_date || 'now');
                var tr =
                    '<tr data-id="' +
                    esc(row.id) +
                    '">' +
                    '<td>' +
                    esc(row.id) +
                    '</td>' +
                    '<td>' +
                    esc(row.keyword) +
                    err +
                    '</td>' +
                    '<td>' +
                    esc(row.slug) +
                    '</td>' +
                    '<td><input type="text" class="mi-release-input" placeholder="now or YYYY-MM-DD HH:MM" value="' +
                    rd +
                    '" /></td>' +
                    '<td class="mi-inline-actions">' +
                    '<div class="mi-queue-actions" role="group" aria-label="' +
                    esc(MIAdmin.i18n.stagedArticleActions || 'Staged article actions') +
                    '">' +
                    '<button type="button" class="button mi-queue-action-btn mi-q-view" title="' +
                    esc(MIAdmin.i18n.viewStaged || 'View') +
                    '" aria-label="' +
                    esc(MIAdmin.i18n.viewStaged || 'View') +
                    '">' +
                    '<span class="dashicons dashicons-visibility" aria-hidden="true"></span>' +
                    '</button>' +
                    '<button type="button" class="button button-primary mi-queue-action-btn mi-q-edit" title="' +
                    esc(MIAdmin.i18n.editStaged || 'Edit') +
                    '" aria-label="' +
                    esc(MIAdmin.i18n.editStaged || 'Edit') +
                    '">' +
                    '<span class="dashicons dashicons-edit" aria-hidden="true"></span>' +
                    '</button>' +
                    '<button type="button" class="button mi-queue-action-btn mi-q-remove" title="' +
                    esc(MIAdmin.i18n.removeFromQueue || 'Remove from queue') +
                    '" aria-label="' +
                    esc(MIAdmin.i18n.removeFromQueue || 'Remove from queue') +
                    '">' +
                    '<span class="dashicons dashicons-trash" aria-hidden="true"></span>' +
                    '</button>' +
                    '</div>' +
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
                    if (typeof res.data.batch_uploaded !== 'undefined' && typeof res.data.batch_total !== 'undefined') {
                        alert(String(res.data.batch_uploaded) + '/' + String(res.data.batch_total) + ' uploaded.');
                    }
                } else if (res.data && res.data.invalid_files && res.data.invalid_files.length) {
                    showUploadValidationErrors(res.data.invalid_files, res.data.message || '', res.data.batch_uploaded, res.data.batch_total);
                } else {
                    alert((res.data && res.data.message) || MIAdmin.i18n.error);
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
            var rid = $tr.data('id');
            if (currentQueueId && String(currentQueueId) === String(rid)) {
                currentQueueId = null;
                $queueEditor.addClass('mi-hidden');
            }
            ajax('mi_remove_queue_item', { id: rid }).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                }
            });
        });

        function openQueueEditor(id, viewOnly) {
            ajax('mi_get_import_queue_item', { id: id }).done(function (res) {
                if (!res.success || !res.data.item) {
                    return;
                }
                fillQueueEditorFromItem(res.data.item, viewOnly);
                $queueEditor.removeClass('mi-hidden');
            });
        }

        $tbody.on('click', '.mi-q-view', function () {
            openQueueEditor($(this).closest('tr').data('id'), true);
        });

        $tbody.on('click', '.mi-q-edit', function () {
            openQueueEditor($(this).closest('tr').data('id'), false);
        });

        $queueEditor.on('change', 'input[name="mi-q-e-vis"]', function () {
            syncQueuePasswordField();
        });

        /**
         * Header lines 1–5 must follow the dedicated inputs (title, slug, meta, release, visibility).
         * Only the markdown body is taken from lines 6+ of the textarea.
         */
        function buildPayloadForImportQueueSave() {
            var cur = parseStructuredMd($('#mi-q-e-md').val());
            if (!cur.ok) {
                return cur;
            }
            var vis = $('input[name="mi-q-e-vis"]:checked').val() || 'private';
            if (vis !== 'publish' && vis !== 'private' && vis !== 'draft' && vis !== 'future') {
                vis = 'private';
            }
            var pwd = vis === 'publish' || vis === 'future' ? String($('#mi-q-e-password').val() || '') : '';
            var releaseVal = String($('#mi-q-e-release').val() || '').trim();
            if (!releaseVal) {
                releaseVal = 'now';
            }
            var slug = String($('#mi-q-e-slug').val() || '').trim();
            var title = String($('#mi-q-e-title').val() || '').trim();
            var meta = String($('#mi-q-e-meta').val() || '');
            var kw = String($('#mi-q-e-keyword').val() || '').trim();
            if (!kw) {
                return { ok: false, message: MIAdmin.i18n.keywordRequired };
            }
            if (!slug || !title) {
                return { ok: false, message: MIAdmin.i18n.slugTitleRequired };
            }
            var mergedArticle = {
                release_date: releaseVal,
                visibility: vis,
                password: pwd,
                meta_description: meta,
                slug: slug,
                title: title,
                comment: cur.comment,
                markdown: cur.markdown,
            };
            $('#mi-q-e-md').val(buildStructuredMd(mergedArticle));
            var parsedMd = parseStructuredMd($('#mi-q-e-md').val());
            if (!parsedMd.ok) {
                return parsedMd;
            }
            return {
                ok: true,
                payload: {
                    id: currentQueueId,
                    title: parsedMd.title,
                    keyword: kw,
                    slug: parsedMd.slug,
                    meta_description: parsedMd.meta_description,
                    comment: parsedMd.comment,
                    markdown: parsedMd.markdown,
                    release_date: parsedMd.release_date,
                    visibility: parsedMd.visibility,
                    password: parsedMd.password,
                },
            };
        }

        $('#mi-q-e-save').on('click', function () {
            if (!currentQueueId) {
                return;
            }
            var built = buildPayloadForImportQueueSave();
            if (!built.ok) {
                alert(built.message || MIAdmin.i18n.error);
                return;
            }
            ajax('mi_save_import_queue_item', built.payload).done(function (res) {
                if (res.success) {
                    renderQueue(res.data.queue || []);
                    var sid = currentQueueId;
                    var wasView = $queueEditor.hasClass('mi-queue-view-mode');
                    ajax('mi_get_import_queue_item', { id: sid })
                        .done(function (r2) {
                            if (r2.success && r2.data.item) {
                                fillQueueEditorFromItem(r2.data.item, wasView);
                            }
                        })
                        .always(function () {
                            alert(MIAdmin.i18n.saved);
                        });
                } else if (res.data && res.data.message) {
                    alert(res.data.message);
                } else {
                    alert(MIAdmin.i18n.error);
                }
            });
        });

        $('#mi-q-e-close').on('click', function () {
            currentQueueId = null;
            $queueEditor.addClass('mi-hidden');
        });

        $('#mi-confirm-import').on('click', function () {
            if (!window.confirm(MIAdmin.i18n.confirmImport)) {
                return;
            }
            ajax('mi_confirm_import').done(function (res) {
                if (res.success) {
                    renderQueue([]);
                    var msg = res.data.message || MIAdmin.i18n.saved;
                    var failedRows = (res.data && res.data.failed) || [];
                    if (res.data.failed && res.data.failed.length) {
                        var parts = ['Import failed: ' + String(failedRows.length) + ' invalid file(s).', msg];
                        msg = parts.join('\n');
                    }
                    alert(msg);
                    if (failedRows.length) {
                        downloadFailedResultCsv(failedRows);
                    }
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

        function syncPasswordField() {
            var vis = $('input[name="mi-e-vis"]:checked').val();
            var $pwd = $('#mi-e-password');
            var isPublic = vis === 'publish' || vis === 'future';
            $pwd.prop('disabled', !isPublic);
            if (!isPublic) {
                $pwd.val('');
            }
        }

        function loadList(term) {
            ajax('mi_list_articles', { search: term || '' }).done(function (res) {
                if (!res.success) {
                    return;
                }
                $tbody.empty();
                var rows = res.data.articles || [];
                if (!rows.length) {
                    renderEmptyTableRow($tbody, 6, 'No articles found.');
                    return;
                }
                rows.forEach(function (a) {
                    var vis = String(a.visibility || 'private').toLowerCase();
                    if (vis !== 'publish' && vis !== 'private' && vis !== 'draft' && vis !== 'future') {
                        vis = 'private';
                    }
                    var visText = visibilityLabel(vis, a.password || '');
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
                        '<select class="mi-vis-select">' +
                        '<option value="draft" ' +
                        (vis === 'draft' ? 'selected' : '') +
                        '>Draft</option>' +
                        '<option value="private" ' +
                        (vis === 'private' ? 'selected' : '') +
                        '>Private</option>' +
                        '<option value="future" ' +
                        (vis === 'future' ? 'selected' : '') +
                        '>' +
                        esc(MIAdmin.i18n.visibilityScheduled || 'Scheduled') +
                        '</option>' +
                        '<option value="publish" ' +
                        (vis === 'publish' ? 'selected' : '') +
                        '>Public</option>' +
                        '</select> ' +
                        '<span class="mi-vis-note">' +
                        esc(visText) +
                        '</span></td>' +
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

        $tbody.on('change', '.mi-vis-select', function () {
            var rowId = $(this).closest('tr').data('id');
            var status = $(this).val();
            function reloadTable() {
                loadList($('#mi-articles-search').val());
            }
            ajax('mi_set_visibility', { id: rowId, status: status }).done(function (res) {
                if (!res.success) {
                    var msg =
                        res.data && res.data.message
                            ? res.data.message
                            : MIAdmin.i18n.error || 'Error';
                    window.alert(msg);
                    reloadTable();
                    return;
                }
                var editingThis =
                    res.success && currentId != null && Number(currentId) === Number(rowId);
                if (editingThis) {
                    ajax('mi_get_article', { id: rowId })
                        .done(function (r2) {
                            if (r2.success && r2.data && r2.data.article) {
                                refillArticleEditorFromPayload(r2.data.article);
                            }
                        })
                        .always(reloadTable);
                } else {
                    reloadTable();
                }
            });
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

        /**
         * Lines 1–5 of structured MD come from the side/top inputs; body from textarea lines 6+.
         */
        function buildPayloadForArticleSave() {
            var cur = parseStructuredMd($('#mi-e-md').val());
            console.log(cur);
            if (!cur.ok) {
                return cur;
            }
            var vis = $('input[name="mi-e-vis"]:checked').val() || 'private';
            if (vis !== 'publish' && vis !== 'private' && vis !== 'draft' && vis !== 'future') {
                vis = 'private';
            }
            var pwd = vis === 'publish' || vis === 'future' ? String($('#mi-e-password').val() || '') : '';
            var releaseVal = String($('#mi-e-release').val() || '').trim();
            if (!releaseVal) {
                releaseVal = 'now';
            }
            var slug = String($('#mi-e-slug').val() || '').trim();
            var title = String($('#mi-e-title').val() || '').trim();
            var meta = String($('#mi-e-meta').val() || '');
            var kw = String($('#mi-e-keyword').val() || '').trim();
            if (!kw) {
                return { ok: false, message: MIAdmin.i18n.keywordRequired };
            }
            if (!slug || !title) {
                return { ok: false, message: MIAdmin.i18n.slugTitleRequired };
            }
            var mergedArticle = {
                release_date: releaseVal,
                visibility: vis,
                password: pwd,
                meta_description: meta,
                slug: slug,
                title: title,
                markdown: cur.markdown,
                comment: cur.comment,
            };
            $('#mi-e-md').val(buildStructuredMd(mergedArticle));
            var parsedMd = parseStructuredMd($('#mi-e-md').val());
            if (!parsedMd.ok) {
                return parsedMd;
            }
            return {
                ok: true,
                payload: {
                    id: currentId,
                    title: parsedMd.title,
                    keyword: kw,
                    slug: parsedMd.slug,
                    meta_description: parsedMd.meta_description,
                    comment: parsedMd.comment,
                    markdown: parsedMd.markdown,
                    release_date: parsedMd.release_date,
                    visibility: parsedMd.visibility,
                    password: parsedMd.password,
                },
            };
        }

        function refillArticleEditorFromPayload(a) {
            if (!a) {
                return;
            }
            $('#mi-e-title').val(a.title || '');
            $('#mi-e-keyword').val(a.keyword || '');
            $('#mi-e-slug').val(a.slug || '');
            $('#mi-e-meta').val(a.meta_description || '');
            $('#mi-e-md').val(buildStructuredMd(a));
            $('#mi-e-release').val(a.release_date || 'now');
            MIReleaseScheduler.sync('mi-e-release');
            $('#mi-e-password').val(a.password || '');
            var v = String(a.visibility || 'private').toLowerCase();
            if (v !== 'publish' && v !== 'private' && v !== 'draft' && v !== 'future') {
                v = 'private';
            }
            $('input[name="mi-e-vis"][value="' + v + '"]').prop('checked', true);
            syncPasswordField();
        }

        $tbody.on('click', '.mi-a-edit', function () {
            var id = $(this).closest('tr').data('id');
            ajax('mi_get_article', { id: id }).done(function (res) {
                if (!res.success) {
                    return;
                }
                var a = res.data.article;
                currentId = a.id;
                refillArticleEditorFromPayload(a);
                $editor.removeClass('mi-hidden');
            });
        });

        $editor.on('change', 'input[name="mi-e-vis"]', function () {
            syncPasswordField();
        });

        $('#mi-e-save').on('click', function () {
            if (!currentId) {
                return;
            }
            var built = buildPayloadForArticleSave();
            if (!built.ok) {
                alert(built.message || MIAdmin.i18n.error);
                return;
            }
            console.log(built.payload);
            ajax('mi_save_article', built.payload).done(function (res) {
                if (res.success) {
                    console.log(res.data.article);
                    refillArticleEditorFromPayload(res.data.article);
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
            var shown = 0;
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
                shown++;
            });
            if (!shown) {
                $list.append('<li class="mi-empty-row"><span>No CTA buttons found.</span></li>');
            }
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
        var ctaCodeEditor = null;

        function ctaCodeGet() {
            if (ctaCodeEditor && ctaCodeEditor.codemirror) {
                return ctaCodeEditor.codemirror.getValue();
            }
            return $('#mi-cta-code').val() || '';
        }

        function ctaCodeSet(value) {
            var nextValue = value || '';
            $('#mi-cta-code').val(nextValue);
            if (ctaCodeEditor && ctaCodeEditor.codemirror) {
                ctaCodeEditor.codemirror.setValue(nextValue);
                ctaCodeEditor.codemirror.refresh();
            }
        }

        function initCtaCodeEditor() {
            if (!window.wp || !wp.codeEditor || !wp.codeEditor.initialize) {
                $('#mi-cta-code').on('input change', scheduleCtaPreview);
                return;
            }
            ctaCodeEditor = wp.codeEditor.initialize('mi-cta-code', {
                codemirror: {
                    mode: 'htmlmixed',
                    lineNumbers: true,
                    lineWrapping: false,
                    matchBrackets: true,
                    autoCloseBrackets: true,
                    styleActiveLine: true,
                    indentUnit: 2,
                    tabSize: 2,
                    indentWithTabs: false,
                },
            });
            if (ctaCodeEditor && ctaCodeEditor.codemirror) {
                ctaCodeEditor.codemirror.on('change', function () {
                    $('#mi-cta-code').val(ctaCodeEditor.codemirror.getValue());
                    scheduleCtaPreview();
                });
            } else {
                $('#mi-cta-code').on('input change', scheduleCtaPreview);
            }
        }

        function updateCtaPreview() {
            var snippet = ctaCodeGet();
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

        initCtaCodeEditor();
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
            ctaCodeSet('');
            scheduleCtaPreview();
            $list.find('li').removeClass('mi-active');
        });

        $('#mi-cta-cancel').on('click', function (e) {
            e.preventDefault();
            $('#mi-cta-name').val('');
            ctaCodeSet('');
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
                ctaCodeSet(c.code);
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
                        ctaCodeSet('');
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
                code: ctaCodeGet(),
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
                var rows = res.data.articles || [];
                if (!rows.length) {
                    renderEmptyTableRow($artBody, 5, 'No articles found.');
                    return;
                }
                rows.forEach(function (a, idx) {
                    var vis = visibilityLabel(a.visibility, a.password || '');
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
                    '<td><input type="text" class="mi-release-input" placeholder="now or YYYY-MM-DD HH:MM" value="' +
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
                    var q = res.data.queue || [];
                    renderQueue(q);
                    if (typeof res.data.batch_uploaded !== 'undefined' && typeof res.data.batch_total !== 'undefined') {
                        alert(String(res.data.batch_uploaded) + '/' + String(res.data.batch_total) + ' uploaded.');
                    }
                    if (!q.length) {
                        alert('No staged updates. Ensure the filename keyword matches an existing article (example: Bitcoin.md -> keyword Bitcoin).');
                    }
                } else if (res.data && res.data.invalid_files && res.data.invalid_files.length) {
                    showUploadValidationErrors(res.data.invalid_files, res.data.message || '', res.data.batch_uploaded, res.data.batch_total);
                } else if (res.data && res.data.message) {
                    alert(res.data.message);
                } else {
                    alert(MIAdmin.i18n.error);
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

        function persistUpgradeReleaseInputs() {
            var requests = [];
            $qBody.find('tr').each(function () {
                var $tr = $(this);
                var id = $tr.data('id');
                var release = $tr.find('.mi-release-input').val();
                if (!id) {
                    return;
                }
                requests.push(ajax('mi_patch_upgrade_item', { id: id, release_date: release }));
            });
            if (!requests.length) {
                return $.Deferred().resolve().promise();
            }
            return $.when.apply($, requests);
        }

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
            persistUpgradeReleaseInputs().always(function () {
                ajax('mi_confirm_upgrade').done(function (res) {
                    if (res.success) {
                        renderQueue([]);
                        loadArticles($('#mi-upgrade-search').val());
                        var msg = res.data.message || MIAdmin.i18n.saved;
                        var failedRows = (res.data && res.data.failed) || [];
                        if (res.data.failed && res.data.failed.length) {
                            var parts = ['Upgrade failed: ' + String(failedRows.length) + ' invalid file(s).', msg];
                            res.data.failed.forEach(function (f) {
                                parts.push((f.filename ? f.filename + ': ' : '') + (f.message || ''));
                            });
                            msg = parts.join('\n');
                        }
                        alert(msg);
                        if (failedRows.length) {
                            downloadFailedResultCsv(failedRows);
                        }
                    }
                });
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
        if (typeof window.MiWpScheduleInit === 'function') {
            window.MiWpScheduleInit();
        }
        uploadTab();
        articlesTab();
        ctaTab();
        upgradeTab();
    });
})(jQuery);
