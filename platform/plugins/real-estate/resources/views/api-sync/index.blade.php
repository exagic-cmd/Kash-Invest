@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    {{-- Same chip style as the Featured Projects table: Bootstrap's `badge bg-*`
         utilities render washed-out/low-contrast on the dark admin theme, so the
         status pills use explicit colours with white text instead. --}}
    @php($chip = 'display:inline-block;padding:.35rem .6rem;border-radius:6px;font-size:.75rem;font-weight:600;color:#fff;line-height:1;')

    {{-- Mobile only (<=767px). Desktop untouched. The source cards are already
         full-width below lg and the history table scrolls inside
         .table-responsive; these tweaks just stop the card header crowding and
         make the primary buttons comfortable tap targets on a phone. --}}
    <style>
        @media (max-width: 767.98px) {
            .sync-card-head {
                flex-direction: column;
                align-items: flex-start !important;
                gap: .5rem;
            }
            .sync-card-head > a.btn { align-self: stretch; text-align: center; }
            [data-run-sync] { width: 100%; }
        }
    </style>

    <p>{{ trans('plugins/real-estate::api-sync.description') }}</p>

    <div class="row">
        @foreach ($sources as $source)
            <div class="col-lg-6 mb-3">
                <div class="card h-100" data-source-card="{{ $source['key'] }}" data-last-log-id="{{ $source['last_log']['id'] ?? 0 }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2 sync-card-head">
                            <div>
                                <h5 class="mb-1">{{ $source['label'] }}</h5>
                                <span style="{{ $chip }}background-color:{{ $source['enabled'] ? '#2fb344' : '#64748b' }};">
                                    {{ $source['enabled'] ? trans('plugins/real-estate::api-sync.enabled') : trans('plugins/real-estate::api-sync.disabled') }}
                                </span>
                            </div>
                            <a href="{{ $source['projects_url'] }}" class="btn btn-sm btn-outline-secondary">
                                {{ trans('plugins/real-estate::api-sync.view_projects', ['count' => number_format($source['projects_count'])]) }}
                            </a>
                        </div>

                        {{-- `text-muted` is far too low-contrast on the dark admin
                             theme, so these inherit the normal body colour instead.
                             `small` still marks them as secondary. --}}
                        <ul class="list-unstyled small mb-3">
                            <li><strong>{{ trans('plugins/real-estate::api-sync.schedule') }}:</strong> {{ $source['schedule'] }}</li>
                            @foreach ($source['meta'] as $label => $value)
                                <li><strong>{{ $label }}:</strong> {{ $value }}</li>
                            @endforeach
                        </ul>

                        <div class="border rounded p-2 mb-3 bg-body-tertiary">
                            <div class="small mb-1 fw-semibold">{{ trans('plugins/real-estate::api-sync.last_run') }}</div>
                            <div data-last-run>@include('plugins/real-estate::api-sync.partials.last-run', ['log' => $source['last_log']])</div>
                        </div>

                        <button type="button" class="btn btn-primary" data-run-sync="{{ $source['key'] }}" @disabled(! $source['enabled'])>
                            <i class="ti ti-refresh me-1"></i>{{ trans('plugins/real-estate::api-sync.run_now') }}
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mt-2">
        <div class="card-header">
            <h5 class="card-title mb-0">{{ trans('plugins/real-estate::api-sync.history') }}</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table mb-0">
                <thead>
                    <tr>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.source') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.status') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.trigger') }}</th>
                        <th class="text-center">{{ trans('plugins/real-estate::api-sync.columns.created') }}</th>
                        <th class="text-center">{{ trans('plugins/real-estate::api-sync.columns.updated') }}</th>
                        <th class="text-center">{{ trans('plugins/real-estate::api-sync.columns.unchanged') }}</th>
                        <th class="text-center">{{ trans('plugins/real-estate::api-sync.columns.failed') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.duration') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.when') }}</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::headline($log->source) }}</td>
                            <td>
                                @php($color = $log->status === 'success' ? '#2fb344' : ($log->status === 'failed' ? '#d63939' : '#f76707'))
                                <span style="{{ $chip }}background-color:{{ $color }};">{{ ucfirst($log->status) }}</span>
                            </td>
                            <td>{{ ucfirst($log->triggered_by) }}</td>
                            <td class="text-center">{{ $log->created }}</td>
                            <td class="text-center">{{ $log->updated }}</td>
                            {{-- was text-muted: rendered noticeably dimmer than the
                                 neighbouring counts and was hard to read on dark --}}
                            <td class="text-center">{{ $log->unchanged }}</td>
                            <td class="text-center">{{ $log->failed }}</td>
                            <td>{{ $log->duration_label ?: '—' }}</td>
                            <td title="{{ $log->finished_at ?: $log->started_at }}">
                                {{ ($log->finished_at ?: $log->started_at)?->diffForHumans() }}
                            </td>
                            {{-- Every row carries the same control so the column
                                 doesn't look half-empty; runs with nothing to show
                                 render it disabled rather than omitting it. --}}
                            <td class="text-end">
                                @if (($log->items_count ?? 0) > 0)
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-details-url="{{ route('real-estate.api-sync.details', $log->id) }}">
                                        <i class="ti ti-list-details me-1"></i>{{ trans('plugins/real-estate::api-sync.details') }}
                                    </button>
                                @else
                                    <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                                            title="{{ trans('plugins/real-estate::api-sync.no_changes_recorded') }}">
                                        <i class="ti ti-list-details me-1"></i>{{ trans('plugins/real-estate::api-sync.details') }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-3">{{ trans('plugins/real-estate::api-sync.no_history') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal modal-blur fade" id="sync-details-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ trans('plugins/real-estate::api-sync.details_title') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" data-details-body>
                    <div class="text-center text-muted py-4">
                        <span class="spinner-border spinner-border-sm me-1"></span>{{ trans('plugins/real-estate::api-sync.loading') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('footer')
    <script>
        (function () {
            const runUrl = @json(route('real-estate.api-sync.run'));
            const statusUrl = @json(route('real-estate.api-sync.status'));
            const token = @json(csrf_token());
            const L = {
                never: @json(trans('plugins/real-estate::api-sync.never_run')),
                running: @json(trans('plugins/real-estate::api-sync.running')),
                loading: @json(trans('plugins/real-estate::api-sync.loading')),
                loadError: @json(trans('plugins/real-estate::api-sync.load_error')),
            };

            function render(log) {
                if (!log) {
                    return '<span class="text-muted">' + L.never + '</span>';
                }
                if (log.status === 'running') {
                    return '<span class="text-warning"><span class="spinner-border spinner-border-sm me-1"></span>' + L.running + '</span>';
                }
                // keep in sync with partials/last-run.blade.php — white reads on the
                // dark card where the success green did not
                const color = log.status === 'failed' ? 'danger' : 'white';
                let meta = [];
                if (log.finished_at) meta.push(log.finished_at);
                if (log.duration) meta.push(log.duration);
                if (log.triggered_by) meta.push(log.triggered_by);
                const unchanged = (typeof log.unchanged === 'number') ? (', ' + log.unchanged + ' unchanged') : '';
                let html = '<span class="text-' + color + ' fw-semibold">' +
                    log.created + ' created, ' + log.updated + ' updated' + unchanged + ', ' + log.failed + ' failed</span>';
                if (meta.length) html += ' <small>· ' + meta.join(' · ') + '</small>';
                if (log.message) html += '<div class="text-danger small mt-1">' + log.message + '</div>';
                return html;
            }

            document.querySelectorAll('[data-run-sync]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const key = btn.getAttribute('data-run-sync');
                    const card = document.querySelector('[data-source-card="' + key + '"]');
                    const box = card.querySelector('[data-last-run]');
                    const beforeId = parseInt(card.getAttribute('data-last-log-id') || '0', 10);

                    btn.disabled = true;
                    box.innerHTML = render({ status: 'running' });

                    // Fire the run; the UI is driven by polling the sync log, not this response.
                    fetch(runUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': token,
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({ source: key }),
                    }).catch(function () {});

                    let tries = 0;
                    (function poll() {
                        fetch(statusUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                const log = res && res.data ? res.data[key] : null;
                                if (log && log.id > beforeId && log.status !== 'running') {
                                    box.innerHTML = render(log);
                                    card.setAttribute('data-last-log-id', log.id);
                                    btn.disabled = false;
                                    setTimeout(function () { window.location.reload(); }, 1500);
                                    return;
                                }
                                box.innerHTML = render(log && log.status === 'running' ? log : { status: 'running' });
                                if (++tries < 240) setTimeout(poll, 3000);
                                else btn.disabled = false;
                            })
                            .catch(function () {
                                if (++tries < 240) setTimeout(poll, 3000);
                                else btn.disabled = false;
                            });
                    })();
                });
            });

            // "Details" modal — load a run's field-level breakdown on demand.
            const modalEl = document.getElementById('sync-details-modal');
            const modalBody = modalEl ? modalEl.querySelector('[data-details-body]') : null;
            const modal = (modalEl && window.bootstrap && window.bootstrap.Modal)
                ? new window.bootstrap.Modal(modalEl)
                : null;
            const spinnerHtml = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-1"></span>' + L.loading + '</div>';

            document.querySelectorAll('[data-details-url]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const url = btn.getAttribute('data-details-url');
                    if (!modal || !modalBody) {
                        window.open(url, '_blank');
                        return;
                    }
                    modalBody.innerHTML = spinnerHtml;
                    modal.show();
                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            modalBody.innerHTML = (res && res.data && res.data.html)
                                ? res.data.html
                                : '<p class="text-danger mb-0">' + L.loadError + '</p>';
                        })
                        .catch(function () {
                            modalBody.innerHTML = '<p class="text-danger mb-0">' + L.loadError + '</p>';
                        });
                });
            });
        })();
    </script>
@endpush
