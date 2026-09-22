@php
    use Botble\RealEstate\Services\Redbricks\RedbricksProjectSyncer;

    $payload = $project->raw_payload ?: [];
    $fields = \Illuminate\Support\Arr::get($payload, 'project', []);
    $floorPlans = \Illuminate\Support\Arr::get($payload, 'floor_plans', []);
    $documents = \Illuminate\Support\Arr::get($payload, 'documents', []);

    ksort($fields);

    $map = RedbricksProjectSyncer::FIELD_MAP;

    $stored = array_filter($fields, fn ($key) => isset($map[$key]), ARRAY_FILTER_USE_KEY);
    $notStored = array_diff_key($fields, $stored);

    $render = function ($value): string {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string) $value;
    };
@endphp

<div class="form-group mb-3">
    <label class="form-label">Redbricks API payload</label>
    <p class="text-muted small mb-2">
        Read-only. Exactly what the API returned for this project on the last sync —
        {{ count($fields) }} fields, of which {{ count($stored) }} are written to their own column.
        The rest are kept in <code>raw_payload</code> and are available to build on.
        Editing a project here does not change what the next sync pulls; Redbricks overwrites mapped columns each run.
    </p>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#rb-stored" role="tab">
                Saved to columns ({{ count($stored) }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#rb-extra" role="tab">
                Received, not stored ({{ count($notStored) }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#rb-plans" role="tab">
                Floor plans ({{ count($floorPlans) }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#rb-docs" role="tab">
                Documents ({{ count($documents) }})
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#rb-json" role="tab">Raw JSON</a>
        </li>
    </ul>

    <div class="tab-content border border-top-0 p-3" style="max-height: 460px; overflow: auto;">
        <div class="tab-pane fade show active" id="rb-stored" role="tabpanel">
            <table class="table table-sm table-vcenter">
                <thead>
                    <tr>
                        <th style="width: 28%">API field</th>
                        <th style="width: 28%">Saved to column</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($stored as $key => $value)
                        <tr>
                            <td><code>{{ $key }}</code></td>
                            {{-- text-white is explicit: Tabler's default badge foreground
                                 is dark, which is unreadable on the green fill. --}}
                            <td><span class="badge bg-success text-white">{{ $map[$key] }}</span></td>
                            <td><small>{{ \Illuminate\Support\Str::limit($render($value), 180) }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="rb-extra" role="tabpanel">
            <p class="text-muted small">
                Returned by the API and kept in <code>raw_payload</code>, but with no dedicated column yet.
            </p>
            <table class="table table-sm table-vcenter">
                <thead>
                    <tr>
                        <th style="width: 30%">API field</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($notStored as $key => $value)
                        <tr>
                            <td><code>{{ $key }}</code></td>
                            <td><small>{{ \Illuminate\Support\Str::limit($render($value), 180) }}</small></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="tab-pane fade" id="rb-plans" role="tabpanel">
            @if ($floorPlans)
                <table class="table table-sm table-vcenter">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Beds</th>
                            <th>Baths</th>
                            <th>Size</th>
                            <th>Exposure</th>
                            <th>Availability</th>
                            <th>Price</th>
                            <th>PSF</th>
                            <th>Price history</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($floorPlans as $plan)
                            <tr>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'name', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'bedrooms', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'bathrooms', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'size', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'exposure', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'availability', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'current_price', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($plan, 'current_psf', '—') }}</td>
                                <td><small>{{ count((array) \Illuminate\Support\Arr::get($plan, 'price_history', [])) }} entries</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted mb-0">No floor plans returned for this project.</p>
            @endif
        </div>

        <div class="tab-pane fade" id="rb-docs" role="tabpanel">
            @if ($documents)
                <table class="table table-sm table-vcenter">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>File</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($documents as $document)
                            <tr>
                                <td>{{ \Illuminate\Support\Arr::get($document, 'name', '—') }}</td>
                                <td>{{ \Illuminate\Support\Arr::get($document, 'document_type') ?: \Illuminate\Support\Arr::get($document, 'type', '—') }}</td>
                                <td>
                                    @if ($url = \Illuminate\Support\Arr::get($document, 'file_url'))
                                        <a href="{{ $url }}" target="_blank" rel="noopener">Open</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted mb-0">No documents returned for this project.</p>
            @endif
        </div>

        <div class="tab-pane fade" id="rb-json" role="tabpanel">
            <pre style="white-space: pre-wrap; word-break: break-word; font-size: 11px; margin: 0;">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    </div>
</div>
