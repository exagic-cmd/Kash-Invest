@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="max-width-1200 mx-auto">
        <p class="text-muted">
            Assign a preconstruction landing page to a project, then edit every section like a CMS.
            Search a project below to assign it; assigned projects appear in the list.
        </p>

        {{-- Search + assign --}}
        <div class="card mb-4">
            <div class="card-body">
                <label class="form-label fw-bold">Search a project to assign</label>
                <div class="position-relative">
                    <input type="text" id="lp-search" class="form-control" autocomplete="off"
                           placeholder="Start typing a project name…">
                    <div id="lp-results" class="list-group position-absolute w-100 shadow-sm"
                         style="z-index:20; max-height:320px; overflow:auto; display:none;"></div>
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
                                <td><span class="badge bg-secondary">Light</span></td>
                                <td>
                                    <span class="badge bg-{{ $published ? 'success' : 'secondary' }}">
                                        {{ $published ? 'Published' : 'Hidden' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @if ($project->url)
                                        <a href="{{ $project->url }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-secondary">Preview</a>
                                    @endif
                                    <a href="{{ route('real-estate.landing-pages.edit', $project->getKey()) }}"
                                       class="btn btn-sm btn-primary">Edit</a>
                                    <button type="button" class="btn btn-sm btn-outline-danger lp-unassign"
                                            data-id="{{ $project->getKey() }}" data-name="{{ $project->name }}">
                                        Unassign
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No landing pages assigned yet. Use the search above to assign one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Hidden forms for POST/DELETE actions --}}
    <form id="lp-assign-form" action="{{ route('real-estate.landing-pages.assign') }}" method="POST" class="d-none">
        @csrf
        <input type="hidden" name="project_id" id="lp-assign-id">
    </form>
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
            const assignForm = document.getElementById('lp-assign-form');
            const assignId = document.getElementById('lp-assign-id');
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
                        + '<span class="text-primary">' + (it.assigned ? 'Edit →' : 'Assign →') + '</span>'
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
                const item = (btn.textContent || '').indexOf('Edit') !== -1;
                if (item) {
                    window.location = unassignBase + '/' + id + '/edit';
                } else {
                    assignId.value = id;
                    assignForm.submit();
                }
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
        })();
    </script>
@endsection
