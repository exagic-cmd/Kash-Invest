@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('real-estate.landing-pages.index') }}" class="text-muted">
                <i class="ti ti-arrow-left"></i> Back to Featured Projects
            </a>
        </div>
        @php($landingUrl = route('landing.page', $project->getKey()))
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary lp-copy-link" data-url="{{ $landingUrl }}">
                <i class="ti ti-link"></i> Copy Link
            </button>
            <a href="{{ $landingUrl . '?preview=1' }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="ti ti-external-link"></i> Preview landing page
            </a>
        </div>
    </div>

    <div class="alert alert-info">
        Every field is optional. Leave a field blank to fall back to <strong>{{ $project->name }}</strong>'s
        own data (for example, no logo → the project name is shown).
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
