@php
    $documents = $project->documents()->get();
    $activeDocs = $documents->where('is_historical', false);
    $historicalDocs = $documents->where('is_historical', true);
@endphp

<div class="project-documents-admin-box">
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <div>
            <span class="badge bg-primary text-white me-2">{{ $activeDocs->count() }} {{ __('Active Documents') }}</span>
            @if ($historicalDocs->isNotEmpty())
                <span class="badge bg-secondary text-white">{{ $historicalDocs->count() }} {{ __('Archived / Past Versions') }}</span>
            @endif
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#admin-docs-active" role="tab">
                {{ __('Current & Active (:count)', ['count' => $activeDocs->count()]) }}
            </a>
        </li>
        @if ($historicalDocs->isNotEmpty())
            <li class="nav-item">
                <a class="nav-link" data-bs-toggle="tab" href="#admin-docs-history" role="tab">
                    {{ __('Archive & History (:count)', ['count' => $historicalDocs->count()]) }}
                </a>
            </li>
        @endif
    </ul>

    <div class="tab-content border border-top-0 p-3" style="max-height: 420px; overflow-y: auto;">
        <div class="tab-pane fade show active" id="admin-docs-active" role="tabpanel">
            @if ($activeDocs->isNotEmpty())
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-vcenter">
                        <thead>
                            <tr>
                                <th>{{ __('Document Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activeDocs as $doc)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if (str_contains(strtolower($doc->document_type), 'pdf') || str_ends_with(strtolower($doc->name), '.pdf'))
                                                <span class="badge bg-danger-lt text-danger">PDF</span>
                                            @elseif (str_contains(strtolower($doc->document_type), 'folder'))
                                                <span class="badge bg-warning-lt text-warning">Folder</span>
                                            @else
                                                <span class="badge bg-info-lt text-info">Doc</span>
                                            @endif
                                            <span class="fw-semibold text-dark">{{ $doc->name }}</span>
                                        </div>
                                    </td>
                                    <td><small class="text-muted">{{ $doc->document_type ?: 'application/pdf' }}</small></td>
                                    <td class="text-end">
                                        @if ($doc->url)
                                            <a href="{{ $doc->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary">
                                                <i class="ti ti-external-link"></i> {{ __('Open') }}
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted mb-0">{{ __('No active documents found.') }}</p>
            @endif
        </div>

        @if ($historicalDocs->isNotEmpty())
            <div class="tab-pane fade" id="admin-docs-history" role="tabpanel">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-vcenter">
                        <thead>
                            <tr>
                                <th>{{ __('Document Name') }}</th>
                                <th>{{ __('Date') }}</th>
                                <th class="text-end">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($historicalDocs as $doc)
                                <tr>
                                    <td>
                                        <span class="text-secondary">{{ $doc->name }}</span>
                                        <span class="badge bg-light text-muted ms-1">{{ __('Archived') }}</span>
                                    </td>
                                    <td><small class="text-muted">{{ $doc->created_at ? $doc->created_at->format('M Y') : '—' }}</small></td>
                                    <td class="text-end">
                                        @if ($doc->url)
                                            <a href="{{ $doc->url }}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-secondary">
                                                <i class="ti ti-external-link"></i> {{ __('View') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>
