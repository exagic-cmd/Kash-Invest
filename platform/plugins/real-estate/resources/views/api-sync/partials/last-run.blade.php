@php($log = $log ?? null)
@if (! $log)
    <span class="text-muted">{{ trans('plugins/real-estate::api-sync.never_run') }}</span>
@elseif ($log['status'] === 'running')
    <span class="text-warning"><span class="spinner-border spinner-border-sm me-1"></span>{{ trans('plugins/real-estate::api-sync.running') }}</span>
@else
    {{-- White rather than green: the success green is too low-contrast to read
         on the dark card. Failures stay red so the signal isn't lost. --}}
    @php($color = $log['status'] === 'failed' ? 'danger' : 'white')
    <span class="text-{{ $color }} fw-semibold">{{ trans('plugins/real-estate::api-sync.result_summary', ['created' => $log['created'], 'updated' => $log['updated'], 'failed' => $log['failed']]) }}</span>
    @php($meta = array_filter([$log['finished_at'] ?? null, $log['duration'] ?? null, $log['triggered_by'] ?? null]))
    @if (! empty($meta))
        <small>· {{ implode(' · ', $meta) }}</small>
    @endif
    @if (! empty($log['message']))
        <div class="text-danger small mt-1">{{ $log['message'] }}</div>
    @endif
@endif
