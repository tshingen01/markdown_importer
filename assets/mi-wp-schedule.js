/**
 * WordPress @wordpress/components Dropdown + DateTimePicker (same stack as editor-post-schedule).
 */
(function () {
    'use strict';

    window.MiWpScheduleInit = function () {
        if (typeof wp === 'undefined' || !wp.element || !wp.components) {
            return;
        }

        var el = wp.element.createElement;
        var Fragment = wp.element.Fragment;
        var useState = wp.element.useState;
        var useEffect = wp.element.useEffect;
        var render = wp.element.render;
        var Dropdown = wp.components.Dropdown;
        var Button = wp.components.Button;
        var DateTimePicker = wp.components.DateTimePicker;

        function pad2(n) {
            var x = parseInt(n, 10);
            if (isNaN(x)) {
                x = 0;
            }
            return (x < 10 ? '0' : '') + x;
        }

        function parseHidden(val) {
            var v = String(val || '').trim();
            if (!v || v.toLowerCase() === 'now') {
                return null;
            }
            var m = v.match(/^(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}):(\d{2}))?$/);
            if (!m) {
                return null;
            }
            return new Date(
                parseInt(m[1], 10),
                parseInt(m[2], 10) - 1,
                parseInt(m[3], 10),
                m[4] ? parseInt(m[4], 10) : 12,
                m[5] ? parseInt(m[5], 10) : 0,
                0,
                0
            );
        }

        function formatHidden(d) {
            return (
                d.getFullYear() +
                '-' +
                pad2(d.getMonth() + 1) +
                '-' +
                pad2(d.getDate()) +
                ' ' +
                pad2(d.getHours()) +
                ':' +
                pad2(d.getMinutes())
            );
        }

        /** Local wall-clock string for DateTimePicker (avoid UTC shift from toISOString). */
        function wpPickerDateString(d) {
            return (
                d.getFullYear() +
                '-' +
                pad2(d.getMonth() + 1) +
                '-' +
                pad2(d.getDate()) +
                'T' +
                pad2(d.getHours()) +
                ':' +
                pad2(d.getMinutes()) +
                ':00'
            );
        }

        function summaryLine(hiddenEl) {
            var v = String(hiddenEl.value || '').trim();
            var imm =
                typeof MIAdmin !== 'undefined' && MIAdmin.i18n && MIAdmin.i18n.publishImmediately
                    ? MIAdmin.i18n.publishImmediately
                    : 'Immediately';
            if (!v || v.toLowerCase() === 'now') {
                return imm;
            }
            var parsed = parseHidden(v);
            if (!parsed || isNaN(parsed.getTime())) {
                return v;
            }
            try {
                return parsed.toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
            } catch (e) {
                return v;
            }
        }

        function MiScheduleDropdown(props) {
            var hiddenId = props.hiddenId;
            var disabled = props.disabled;
            var hiddenEl = document.getElementById(hiddenId);

            var dateInit = hiddenEl ? parseHidden(hiddenEl.value) || new Date() : new Date();
            var dateState = useState(dateInit);
            var date = dateState[0];
            var setDate = dateState[1];

            var summaryState = useState(hiddenEl ? summaryLine(hiddenEl) : '');
            var summary = summaryState[0];
            var setSummary = summaryState[1];

            useEffect(
                function () {
                    function onSync(ev) {
                        var id = ev.detail && ev.detail.id;
                        if (id !== hiddenId) {
                            return;
                        }
                        var h = document.getElementById(hiddenId);
                        if (!h) {
                            return;
                        }
                        setSummary(summaryLine(h));
                        setDate(parseHidden(h.value) || new Date());
                    }
                    document.addEventListener('mi-release-scheduler-sync', onSync);
                    return function () {
                        document.removeEventListener('mi-release-scheduler-sync', onSync);
                    };
                },
                [hiddenId]
            );

            if (!hiddenEl) {
                return null;
            }

            var i18n = typeof MIAdmin !== 'undefined' && MIAdmin.i18n ? MIAdmin.i18n : {};

            return el(Dropdown, {
                className: 'editor-post-schedule__dialog-toggle mi-editor-post-schedule-dropdown',
                contentClassName: 'editor-post-schedule__dialog-content mi-editor-post-schedule-dropdown-content',
                popoverProps: {
                    placement: 'bottom-start',
                    offset: 8,
                    className: 'mi-schedule-popover components-popover',
                    focusOnMount: 'firstElement',
                },
                renderToggle: function (_ref) {
                    var onToggle = _ref.onToggle;
                    var isOpen = _ref.isOpen;
                    return el(
                        Button,
                        {
                            className:
                                'editor-post-schedule__dialog-toggle components-button is-compact is-secondary',
                            variant: 'secondary',
                            onClick: function () {
                                if (!isOpen) {
                                    var h = document.getElementById(hiddenId);
                                    if (h) {
                                        setDate(parseHidden(h.value) || new Date());
                                        setSummary(summaryLine(h));
                                    }
                                }
                                onToggle();
                            },
                            'aria-expanded': isOpen,
                            disabled: disabled,
                        },
                        el(
                            Fragment,
                            {},
                            el(
                                'span',
                                { className: 'editor-post-schedule__dialog-toggle-label' },
                                i18n.publishToggleLabel || 'Publish on:'
                            ),
                            el(
                                'span',
                                {
                                    className:
                                        'editor-post-schedule__dialog-toggle-schedule mi-schedule-summary',
                                },
                                summary
                            ),
                            el('span', {
                                className: 'dashicons dashicons-arrow-down-alt2 mi-schedule-toggle-chevron',
                                'aria-hidden': true,
                            })
                        )
                    );
                },
                renderContent: function (_ref2) {
                    var onClose = _ref2.onClose;
                    var pickerProps = {
                        currentDate: wpPickerDateString(date),
                        onChange: function (newVal) {
                            var d = newVal instanceof Date ? newVal : new Date(newVal);
                            if (!isNaN(d.getTime())) {
                                setDate(d);
                            }
                        },
                        is12Hour: true,
                    };
                    return el(
                        'div',
                        { className: 'mi-wp-datetime-picker-wrap' },
                        DateTimePicker
                            ? el(DateTimePicker, pickerProps)
                            : el(
                                  'p',
                                  { className: 'mi-schedule-fallback' },
                                  i18n.schedulePickerUnavailable || 'Date picker could not load.'
                              ),
                        el(
                            'div',
                            { className: 'mi-schedule-dropdown-actions' },
                            el(
                                Button,
                                {
                                    variant: 'tertiary',
                                    onClick: function () {
                                        hiddenEl.value = 'now';
                                        setSummary(summaryLine(hiddenEl));
                                        document.dispatchEvent(
                                            new CustomEvent('mi-release-scheduler-sync', {
                                                detail: { id: hiddenId },
                                            })
                                        );
                                        onClose();
                                    },
                                },
                                i18n.scheduleNowBtn || 'Now'
                            ),
                            el(
                                Button,
                                {
                                    variant: 'primary',
                                    onClick: function () {
                                        hiddenEl.value = formatHidden(date);
                                        setSummary(summaryLine(hiddenEl));
                                        document.dispatchEvent(
                                            new CustomEvent('mi-release-scheduler-sync', {
                                                detail: { id: hiddenId },
                                            })
                                        );
                                        onClose();
                                    },
                                },
                                i18n.scheduleConfirmBtn || 'Schedule'
                            )
                        )
                    );
                },
            });
        }

        function MiScheduleRoot(props) {
            var rootEl = props.rootEl;
            var hiddenId = props.hiddenId;

            var roState = useState(rootEl.getAttribute('data-readonly') === '1');
            var readonly = roState[0];
            var setReadonly = roState[1];

            useEffect(
                function () {
                    var mo = new MutationObserver(function () {
                        setReadonly(rootEl.getAttribute('data-readonly') === '1');
                    });
                    mo.observe(rootEl, {
                        attributes: true,
                        attributeFilter: ['data-readonly'],
                    });
                    return function () {
                        mo.disconnect();
                    };
                },
                [rootEl]
            );

            return el(MiScheduleDropdown, { hiddenId: hiddenId, disabled: readonly });
        }

        document.querySelectorAll('.mi-wp-schedule-root').forEach(function (mountEl) {
            var hiddenId = mountEl.getAttribute('data-hidden-id');
            if (!hiddenId) {
                return;
            }
            var wrap = mountEl.closest('.mi-wp-schedule');
            if (!wrap) {
                return;
            }
            render(el(MiScheduleRoot, { rootEl: wrap, hiddenId: hiddenId }), mountEl);
        });
    };
})();
