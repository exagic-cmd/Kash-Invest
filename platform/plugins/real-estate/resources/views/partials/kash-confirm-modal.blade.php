{{--
    Kash Invest confirmation dialog.

    Rendered once on every admin page (see RealEstateHookServiceProvider →
    BASE_FILTER_FOOTER_LAYOUT_TEMPLATE) and driven entirely from JS, so any screen
    can raise a themed confirmation instead of the browser's native confirm().

    Usage:
        KashConfirm.show({
            title: 'Delete this landing page?',
            message: 'Its public URL will stop working.',
            confirmLabel: 'Delete',
            type: 'danger',            // danger | warning | primary | success
        }).then(function (ok) { if (ok) { ... } });

    Or the drop-in replacement for a `confirm()` guard:
        KashConfirm.ask('Delete this?', function () { ... });

    Styling piggybacks on the admin's Bootstrap/Tabler classes, so it inherits the
    theme (including dark mode) rather than hard-coding colours.
--}}
<div class="modal modal-blur fade" id="kash-confirm-modal" tabindex="-1" role="dialog" aria-hidden="true"
     data-bs-backdrop="static" aria-labelledby="kash-confirm-title">
    <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
        <div class="modal-content">
            {{-- coloured status bar, swapped per type --}}
            <div class="modal-status bg-danger" data-kash-status></div>

            <div class="modal-body text-center py-4">
                <div class="mb-2" data-kash-icon>
                    <x-core::icon name="ti ti-alert-triangle" class="text-danger" size="lg" />
                </div>

                <h3 id="kash-confirm-title" data-kash-title>Are you sure?</h3>

                <div class="text-muted text-break" data-kash-message></div>
            </div>

            <div class="modal-footer">
                <div class="w-100">
                    <div class="row">
                        <div class="col">
                            <button type="button" class="w-100 btn btn-danger" data-kash-confirm>
                                Confirm
                            </button>
                        </div>
                        <div class="col">
                            <button type="button" class="w-100 btn" data-bs-dismiss="modal" data-kash-cancel>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function (window, document) {
        'use strict';

        if (window.KashConfirm) {
            return; // already initialised
        }

        var ICONS = {
            danger: 'ti-alert-triangle',
            warning: 'ti-alert-circle',
            success: 'ti-circle-check',
            primary: 'ti-info-circle',
        };

        function el() {
            return document.getElementById('kash-confirm-modal');
        }

        /**
         * Show the dialog. Returns a Promise<boolean> so callers can await the
         * answer — a modal cannot block like window.confirm(), so anything that
         * used `if (!confirm(...)) return;` has to move its work into .then().
         */
        function show(options) {
            options = options || {};

            var modalEl = el();

            // No modal markup (or no Bootstrap) — never silently skip a
            // confirmation, fall back to the native dialog instead.
            if (!modalEl || !window.bootstrap || !window.bootstrap.Modal) {
                return Promise.resolve(
                    window.confirm([options.title, options.message].filter(Boolean).join('\n\n'))
                );
            }

            var type = options.type || 'danger';

            modalEl.querySelector('[data-kash-title]').textContent = options.title || 'Are you sure?';
            modalEl.querySelector('[data-kash-message]').textContent = options.message || '';

            var confirmBtn = modalEl.querySelector('[data-kash-confirm]');
            confirmBtn.textContent = options.confirmLabel || 'Confirm';
            confirmBtn.className = 'w-100 btn btn-' + type;

            modalEl.querySelector('[data-kash-cancel]').textContent = options.cancelLabel || 'Cancel';
            modalEl.querySelector('[data-kash-status]').className = 'modal-status bg-' + type;

            // Recolour + reshape the icon to match the type.
            var svg = modalEl.querySelector('[data-kash-icon] svg');
            if (svg) {
                svg.setAttribute('class', String(svg.getAttribute('class') || '')
                    .replace(/text-\w+/g, '')
                    .replace(/svg-icon-ti-ti-[\w-]+/g, 'svg-icon-ti-' + (ICONS[type] || ICONS.danger))
                    .trim() + ' text-' + type);
            }

            var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);

            return new Promise(function (resolve) {
                var settled = false;

                function finish(result) {
                    if (settled) return;
                    settled = true;
                    confirmBtn.removeEventListener('click', onConfirm);
                    modalEl.removeEventListener('hidden.bs.modal', onDismiss);
                    resolve(result);
                }

                function onConfirm() {
                    finish(true);
                    modal.hide();
                }

                function onDismiss() {
                    finish(false);
                }

                confirmBtn.addEventListener('click', onConfirm);
                modalEl.addEventListener('hidden.bs.modal', onDismiss);

                modal.show();
            });
        }

        /**
         * Terse form for the common "confirm then do it" case.
         *   KashConfirm.ask('Delete this?', function () { ... })
         */
        function ask(titleOrOptions, onConfirm) {
            var options = typeof titleOrOptions === 'string'
                ? { title: titleOrOptions }
                : titleOrOptions;

            return show(options).then(function (ok) {
                if (ok && typeof onConfirm === 'function') {
                    onConfirm();
                }
                return ok;
            });
        }

        window.KashConfirm = { show: show, ask: ask };
    })(window, document);
</script>
