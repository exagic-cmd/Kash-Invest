@extends(BaseHelper::getAdminMasterLayoutTemplate())

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <a href="{{ route('real-estate.landing-pages.index') }}" class="text-muted">
                <i class="ti ti-arrow-left"></i> Back to landing pages
            </a>
        </div>
        @if ($project->url)
            <a href="{{ $project->url }}" target="_blank" rel="noopener" class="btn btn-outline-secondary">
                <i class="ti ti-external-link"></i> Preview live page
            </a>
        @endif
    </div>

    <div class="alert alert-info">
        Every field is optional. Leave a field blank to fall back to <strong>{{ $project->name }}</strong>'s
        own data (for example, no logo → the project name is shown).
    </div>

    {!! $form->renderForm() !!}
@endsection
