@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')

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
        /* URL column reads as a path, not a code block */
        .lp-actions code,
        td code {
            background: transparent;
            padding: 0;
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
            A project can have several landing pages — one per ad campaign. Search below to
            configure a new project, or use <strong>Add page</strong> on any project to create
            another campaign page. The <strong>Default</strong> page is the one served at the
            short <code>/landing/&lt;project&gt;</code> URL.
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

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Landing pages ({{ $pages->count() }})</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Landing page</th>
                            <th>Public URL</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- One row per landing page: a project with several campaign
                             pages appears once per page it owns. --}}
                        @forelse ($pages as $page)
                                @php($project = $page->project)
                                @php($projectId = $project->getKey())
                                @php($published = $page->is_published)
                                @php($landingUrl = $page->url)
                                @php($path = ($page->is_primary || empty($page->slug)) ? '/landing/' . $projectId : '/landing/' . $projectId . '/' . $page->slug)
                                <tr>
                                    <td><strong>{{ $project->name }}</strong></td>
                                    <td>
                                        <span class="fw-semibold">{{ $page->name ?: ($page->is_primary ? 'Landing Page 1' : 'Landing Page ' . $loop->iteration) }}</span>
                                        @if ($page->is_primary)
                                            <span title="Primary page served at main URL /landing/{{ $projectId }}">
                                                {!! BaseHelper::renderBadge('Primary Page', 'info') !!}
                                            </span>
                                        @else
                                            <span title="Campaign variant page served at /landing/{{ $projectId }}/{{ $page->slug }}">
                                                {!! BaseHelper::renderBadge('Campaign Variant', 'warning') !!}
                                            </span>
                                        @endif
                                    </td>
                                    <td><code class="text-muted small">{{ $path }}</code></td>
                                    <td>
                                        {!! $published
                                            ? BaseHelper::renderBadge('Published', 'success')
                                            : BaseHelper::renderBadge('Draft', 'secondary') !!}
                                    </td>
                                    <td class="text-end">
                                        {{-- Icon-only square buttons, matching the operations
                                             column on the Projects table. Icons must go through
                                             <x-core::icon>: Botble renders `ti ti-*` as inline
                                             SVG, there is no icon webfont, so a bare
                                             <i class="ti ti-..."> renders nothing at all. --}}
                                        <div class="d-flex align-items-center justify-content-end gap-1 lp-actions">
                                            <a href="{{ $landingUrl . '?preview=1' }}" target="_blank" rel="noopener"
                                               class="btn btn-sm btn-icon btn-outline-secondary"
                                               data-bs-toggle="tooltip" title="Preview this page">
                                                <x-core::icon name="ti ti-eye" />
                                            </a>
                                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary lp-copy-link"
                                                    data-url="{{ $landingUrl }}"
                                                    data-bs-toggle="tooltip" title="Copy public URL">
                                                <x-core::icon name="ti ti-link" />
                                            </button>
                                            <a href="{{ route('real-estate.landing-pages.pages.create', $projectId) }}"
                                               class="btn btn-sm btn-icon btn-outline-secondary"
                                               data-bs-toggle="tooltip" title="Add another campaign page to {{ $project->name }}">
                                                <x-core::icon name="ti ti-plus" />
                                            </a>
                                            <a href="{{ route('real-estate.landing-pages.edit-page', [$projectId, $page->getKey()]) }}"
                                               class="btn btn-sm btn-icon btn-primary"
                                               data-bs-toggle="tooltip" title="Edit content">
                                                <x-core::icon name="ti ti-edit" />
                                            </a>
                                            <button type="button" class="btn btn-sm btn-icon btn-danger lp-delete-page"
                                                    data-url="{{ route('real-estate.landing-pages.pages.destroy', [$projectId, $page->getKey()]) }}"
                                                    data-name="{{ $page->name }} ({{ $project->name }})"
                                                    data-bs-toggle="tooltip" title="Delete this landing page">
                                                <x-core::icon name="ti ti-trash" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    No landing pages yet. Use the search above to select a project.
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

    {{-- Generic submitter for the per-page row actions (duplicate / make default /
         delete). Each button carries its own URL, so one form serves them all. --}}
    <form id="lp-action-form" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="_method" id="lp-action-method" value="POST">
    </form>

    <script>
        (function () {
            var form = document.getElementById('lp-action-form');
            var method = document.getElementById('lp-action-method');

            function submitTo(url, httpMethod) {
                form.action = url;
                method.value = httpMethod;
                form.submit();
            }

            document.querySelectorAll('.lp-post').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    submitTo(btn.getAttribute('data-url'), 'POST');
                });
            });

            // KashConfirm is the shared Kash Invest dialog, injected on every admin
            // page by RealEstateHookServiceProvider.
            document.querySelectorAll('.lp-delete-page').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    KashConfirm.ask({
                        title: 'Delete this landing page?',
                        message: '“' + btn.getAttribute('data-name') + '” will be removed and its public URL will stop working. The project and its other landing pages are not affected.',
                        confirmLabel: 'Delete',
                        type: 'danger',
                    }, function () {
                        submitTo(btn.getAttribute('data-url'), 'DELETE');
                    });
                });
            });
        })();
    </script>

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
                    // Already-configured projects get a direct "Add page" action, so a
                    // new campaign page can be created straight from the search box
                    // without opening the editor first.
                    if (it.assigned) {
                        return '<div class="list-group-item d-flex justify-content-between align-items-center">'
                            + '<span>' + it.name
                            + '<span class="badge bg-success text-success-fg ms-2">Configured</span>'
                            + '</span>'
                            + '<span class="d-flex gap-2">'
                            + '<a class="btn btn-sm btn-outline-primary" href="' + unassignBase + '/' + it.id + '/pages/create">+ Add page</a>'
                            + '<a class="btn btn-sm btn-primary" href="' + unassignBase + '/' + it.id + '/edit">Edit</a>'
                            + '</span>'
                            + '</div>';
                    }

                    return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center" data-id="' + it.id + '">'
                        + '<span>' + it.name + '</span>'
                        + '<span class="text-primary">Configure →</span>'
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
                    KashConfirm.ask({
                        title: 'Remove all landing pages?',
                        message: '“' + btn.getAttribute('data-name') + '” will revert to its standard project page and every landing page it has will be deleted.',
                        confirmLabel: 'Remove',
                        type: 'danger',
                    }, function () {
                        const form = document.getElementById('lp-unassign-form');
                        form.action = unassignBase + '/' + btn.getAttribute('data-id');
                        form.submit();
                    });
                });
            });

            document.querySelectorAll('.lp-copy-link').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const url = btn.getAttribute('data-url');
                    const original = btn.innerHTML;
                    const done = function () {
                        btn.innerHTML = 'Copied';
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
