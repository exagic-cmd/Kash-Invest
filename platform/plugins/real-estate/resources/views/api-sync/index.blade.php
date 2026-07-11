@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <p class="text-muted">{{ trans('plugins/real-estate::api-sync.description') }}</p>

    <div class="row">
        @foreach ($sources as $source)
            <div class="col-lg-6 mb-3">
                <div class="card h-100" data-source-card="{{ $source['key'] }}" data-last-log-id="{{ $source['last_log']['id'] ?? 0 }}">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="mb-1">{{ $source['label'] }}</h5>
                                <span class="badge bg-{{ $source['enabled'] ? 'success' : 'secondary' }}">
                                    {{ $source['enabled'] ? trans('plugins/real-estate::api-sync.enabled') : trans('plugins/real-estate::api-sync.disabled') }}
                                </span>
                            </div>
                            <a href="{{ $source['projects_url'] }}" class="btn btn-sm btn-outline-secondary">
                                {{ trans('plugins/real-estate::api-sync.view_projects', ['count' => number_format($source['projects_count'])]) }}
                            </a>
                        </div>

                        <ul class="list-unstyled text-muted small mb-3">
                            <li><strong>{{ trans('plugins/real-estate::api-sync.schedule') }}:</strong> {{ $source['schedule'] }}</li>
                            @foreach ($source['meta'] as $label => $value)
                                <li><strong>{{ $label }}:</strong> {{ $value }}</li>
                            @endforeach
                        </ul>

                        <div class="border rounded p-2 mb-3 bg-body-tertiary">
                            <div class="text-muted small mb-1">{{ trans('plugins/real-estate::api-sync.last_run') }}</div>
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
                        <th class="text-center">{{ trans('plugins/real-estate::api-sync.columns.failed') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.duration') }}</th>
                        <th>{{ trans('plugins/real-estate::api-sync.columns.when') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ \Illuminate\Support\Str::headline($log->source) }}</td>
                            <td>
                                @php($color = $log->status === 'success' ? 'success' : ($log->status === 'failed' ? 'danger' : 'warning'))
                                <span class="badge bg-{{ $color }}">{{ ucfirst($log->status) }}</span>
                            </td>
                            <td>{{ ucfirst($log->triggered_by) }}</td>
                            <td class="text-center">{{ $log->created }}</td>
                            <td class="text-center">{{ $log->updated }}</td>
                            <td class="text-center">{{ $log->failed }}</td>
                            <td>{{ $log->duration_label ?: '—' }}</td>
                            <td title="{{ $log->finished_at ?: $log->started_at }}">
                                {{ ($log->finished_at ?: $log->started_at)?->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-3">{{ trans('plugins/real-estate::api-sync.no_history') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
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
            };

            function render(log) {
                if (!log) {
                    return '<span class="text-muted">' + L.never + '</span>';
                }
                if (log.status === 'running') {
                    return '<span class="text-warning"><span class="spinner-border spinner-border-sm me-1"></span>' + L.running + '</span>';
                }
                const color = log.status === 'failed' ? 'danger' : 'success';
                let meta = [];
                if (log.finished_at) meta.push(log.finished_at);
                if (log.duration) meta.push(log.duration);
                if (log.triggered_by) meta.push(log.triggered_by);
                let html = '<span class="text-' + color + ' fw-semibold">' +
                    log.created + ' created, ' + log.updated + ' updated, ' + log.failed + ' failed</span>';
                if (meta.length) html += ' <small class="text-muted">· ' + meta.join(' · ') + '</small>';
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
        })();
    </script>
@endpush
