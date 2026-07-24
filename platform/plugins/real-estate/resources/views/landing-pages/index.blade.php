@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    @php($chip = 'display:inline-block;padding:.35rem .6rem;border-radius:6px;font-size:.75rem;font-weight:600;color:#fff;')

    {{-- Mobile only (<=767px). Desktop is untouched: the table already scrolls
         inside .table-responsive, but the per-row action buttons are the widest
         thing, so on phones we let them wrap and go full-width instead of forcing
         a long sideways scroll. --}}
    <style>
        .lp-actions .btn {
            border-radius: var(--tblr-btn-border-radius, 4px) !important;
        }
        #lp-results {
            z-index: 1050 !important;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            background-color: var(--tblr-bg-surface, #1e293b) !important;
            border: 1px solid var(--tblr-border-color, #334155) !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
            border-radius: 6px;
        }
        #lp-results .list-group-item {
            background-color: var(--tblr-bg-surface, #1e293b) !important;
            color: var(--tblr-body-color, #f8fafc) !important;
            border-color: var(--tblr-border-color, #334155) !important;
        }
        #lp-results .list-group-item:hover,
        #lp-results .list-group-item:focus {
            background-color: var(--tblr-bg-surface-secondary, #334155) !important;
        }
        @media (max-width: 767.98px) {
            .lp-actions {
                flex-wrap: wrap;
            }
            .lp-actions > .btn {
                flex: 1 1 auto;
            }
            #lp-results { max-height: 240px; }
        }
    </style>

    <div class="max-width-1200 mx-auto">
        <p class="text-muted">
            Assign a preconstruction landing page to a project, then edit every section like a CMS.
            Search a project below to create or edit its landing page.
        </p>

        {{-- Search + assign --}}
        <div class="card mb-4">
            <div class="card-body">
                <label class="form-label fw-bold">Search a project to configure</label>
                <div class="position-relative">
                    <input type="text" id="lp-search" class="form-control" autocomplete="off"
                           placeholder="Start typing a project name…">
                    <div id="lp-results" class="list-group position-absolute w-100 shadow-sm"></div>
                </div>
            </div>
        </div>

        {{-- Currently assigned --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Assigned landing pages ({{ $assigned->count() }})</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Template</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assigned as $project)
                            @php($published = $project->landingPage?->is_published ?? true)
                            <tr>
                                <td>{{ $project->name }}</td>
                                {{-- Explicit colours: bg-secondary renders as a pale
                                     grey pill on the dark theme and swallowed its label. --}}
                                <td>
                                    <span style="{{ $chip }}background-color:#64748b;">Light</span>
                                </td>
                                <td>
                                    <span style="{{ $chip }}background-color:{{ $published ? '#2fb344' : '#64748b' }};">
                                        {{ $published ? 'Published' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @php($landingUrl = route('landing.page', $project->getKey()))
                                    <div class="d-flex align-items-center justify-content-end gap-1 lp-actions">
                                        <a href="{{ $landingUrl . '?preview=1' }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-secondary" title="Open the landing page in a new tab">
                                            <i class="ti ti-external-link"></i> Preview
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary lp-copy-link"
                                                data-url="{{ $landingUrl }}" title="Copy the public landing page URL">
                                            <i class="ti ti-link"></i> Copy Link
                                        </button>
                                        <a href="{{ route('real-estate.landing-pages.edit', $project->getKey()) }}"
                                           class="btn btn-sm btn-primary">
                                            <i class="ti ti-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger lp-unassign"
                                                data-id="{{ $project->getKey() }}" data-name="{{ $project->name }}">
                                            <i class="ti ti-trash"></i> Unassign
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No landing pages assigned yet. Use the search above to select a project.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Hidden forms for POST/DELETE actions --}}
    <form id="lp-unassign-form" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>

    <script>
        (function () {
            const searchUrl = @json(route('real-estate.landing-pages.search'));
            const unassignBase = @json(url(BaseHelper::getAdminPrefix() . '/real-estate/landing-pages'));
            const input = document.getElementById('lp-search');
            const results = document.getElementById('lp-results');
            let timer = null;

            function hideResults() {
                results.style.display = 'none';
                results.innerHTML = '';
            }

            function render(items) {
                if (!items.length) {
                    results.innerHTML = '<div class="list-group-item text-muted">No projects found</div>';
                    results.style.display = 'block';
                    return;
                }
                results.innerHTML = items.map(function (it) {
                    const badge = it.assigned
                        ? '<span class="badge bg-success ms-2">Assigned</span>'
                        : '';
                    return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-id="' + it.id + '">'
                        + '<span>' + it.name + badge + '</span>'
                        + '<span class="text-primary">' + (it.assigned ? 'Edit →' : 'Configure →') + '</span>'
                        + '</button>';
                }).join('');
                results.style.display = 'block';
            }

            input.addEventListener('input', function () {
                clearTimeout(timer);
                const q = input.value.trim();
                if (!q) { hideResults(); return; }
                timer = setTimeout(function () {
                    fetch(searchUrl + '?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (res) { render(res.data || []); })
                        .catch(hideResults);
                }, 250);
            });

            results.addEventListener('click', function (e) {
                const btn = e.target.closest('[data-id]');
                if (!btn) return;
                const id = btn.getAttribute('data-id');
                window.location = unassignBase + '/' + id + '/edit';
            });

            document.addEventListener('click', function (e) {
                if (!results.contains(e.target) && e.target !== input) hideResults();
            });

            document.querySelectorAll('.lp-unassign').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (!confirm('Unassign the landing page from "' + btn.getAttribute('data-name') + '"? The project reverts to its standard page.')) {
                        return;
                    }
                    const form = document.getElementById('lp-unassign-form');
                    form.action = unassignBase + '/' + btn.getAttribute('data-id');
                    form.submit();
                });
            });

            document.querySelectorAll('.lp-copy-link').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const url = btn.getAttribute('data-url');
                    const original = btn.innerHTML;
                    const done = function () {
                        btn.innerHTML = '<i class="ti ti-check"></i> Copied';
                        setTimeout(function () { btn.innerHTML = original; }, 1500);
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(url).then(done).catch(function () {
                            window.prompt('Copy this URL:', url);
                        });
                    } else {
                        window.prompt('Copy this URL:', url);
                    }
                });
            });
        })();
    </script>
@endsection
