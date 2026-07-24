@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    @php($isSaved = ($project->landing_template === 'light') && isset($project->landingPage) && $project->landingPage->exists)
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('real-estate.landing-pages.index') }}" class="text-muted">
                <i class="ti ti-arrow-left"></i> Back to Landing Pages
            </a>
        </div>
        @if ($isSaved)
            @php($landingUrl = route('landing.page', $project->getKey()))
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary lp-copy-link" data-url="{{ $landingUrl }}">
                    <i class="ti ti-link"></i> Copy Link
                </button>
                <a href="{{ $landingUrl . '?preview=1' }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                    <i class="ti ti-external-link"></i> Preview landing page
                </a>
            </div>
        @endif
    </div>

    <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
        <i class="ti ti-info-circle fs-2 text-info flex-shrink-0"></i>
        <div>
            <div class="fw-bold mb-1">Customize Landing Page Content for {{ $project->name }}</div>
            <div class="text-muted small">
                All fields below are optional. Any field left blank will automatically fall back to the project's standard details (e.g., if no custom logo is uploaded, the main project name will be displayed).
            </div>
        </div>
    </div>

    {!! $form->renderForm() !!}
@endsection

@push('footer')
    <script>
        (function () {
            document.querySelectorAll('.lp-copy-link').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var url = btn.getAttribute('data-url');
                    var original = btn.innerHTML;
                    var done = function () {
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
@endpush
