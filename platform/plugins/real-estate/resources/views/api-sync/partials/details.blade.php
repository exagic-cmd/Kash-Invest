@php
    /** @var \Botble\RealEstate\Models\ProjectSyncLog $log */
    $groups = $log->items->groupBy('action');
    // Show the most actionable groups first.
    $sections = ['failed' => '#d63939', 'created' => '#2fb344', 'updated' => '#4299e1'];
@endphp

{{-- Explicit colours rather than bg-* utilities: on the dark admin theme
     `bg-secondary` resolves to a pale grey and the white label inside it became
     invisible. These four tones read on both the light and dark themes. --}}
@php
    $chip = 'display:inline-block;padding:.35rem .6rem;border-radius:6px;font-size:.75rem;font-weight:600;color:#fff;';
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    <span style="{{ $chip }}background-color:#2fb344;">{{ trans('plugins/real-estate::api-sync.columns.created') }}: {{ $log->created }}</span>
    <span style="{{ $chip }}background-color:#4299e1;">{{ trans('plugins/real-estate::api-sync.columns.updated') }}: {{ $log->updated }}</span>
    <span style="{{ $chip }}background-color:#64748b;">{{ trans('plugins/real-estate::api-sync.columns.unchanged') }}: {{ $log->unchanged }}</span>
    <span style="{{ $chip }}background-color:#d63939;">{{ trans('plugins/real-estate::api-sync.columns.failed') }}: {{ $log->failed }}</span>
</div>

@if ($log->items->isEmpty())
    <p class="text-muted mb-0">{{ trans('plugins/real-estate::api-sync.details_empty') }}</p>
@else
    @foreach ($sections as $action => $color)
        @php($group = $groups->get($action))
        @if ($group && $group->count())
            <h5 class="mt-3 mb-2">
                <span style="{{ $chip }}background-color:{{ $color }};">{{ \Illuminate\Support\Str::headline($action) }}</span>
                <span class="small">({{ $group->count() }})</span>
            </h5>
            <div class="table-responsive mb-2">
                <table class="table table-sm table-vcenter">
                    <thead>
                        <tr>
                            <th style="width: 34%">{{ trans('plugins/real-estate::api-sync.details_project') }}</th>
                            <th>{{ trans('plugins/real-estate::api-sync.details_changes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group as $item)
                            <tr>
                                <td>
                                    @if ($item->project_id && Route::has('project.edit'))
                                        <a href="{{ route('project.edit', $item->project_id) }}" target="_blank" rel="noopener">
                                            {{ $item->name ?: '#' . $item->project_id }}
                                        </a>
                                    @else
                                        {{ $item->name ?: '—' }}
                                    @endif
                                    @if ($item->external_id)
                                        <div class="small">{{ $item->external_id }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($action === 'failed')
                                        <span class="text-danger">{{ $item->error ?: '—' }}</span>
                                    @elseif ($action === 'created')
                                        <span class="text-muted">{{ trans('plugins/real-estate::api-sync.details_new_project') }}</span>
                                    @else
                                        @php($fields = $item->field_changes)
                                        @if (count($fields))
                                            <ul class="list-unstyled mb-0">
                                                @foreach ($fields as $change)
                                                    <li class="mb-1">
                                                        <strong>{{ $change['field'] }}:</strong>
                                                        <span class="text-muted text-decoration-line-through">{{ $change['from'] ?? '—' }}</span>
                                                        <i class="ti ti-arrow-right mx-1"></i>
                                                        <span class="fw-medium">{{ $change['to'] ?? '—' }}</span>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach
@endif
