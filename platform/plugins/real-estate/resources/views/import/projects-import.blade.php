@extends($importer->getLayout())

@section('content')
    @include('packages/data-synchronize::partials.importer')

    {{-- Download sample template buttons + rules modal trigger (no example table) --}}
    <div class="mt-3 d-flex flex-wrap gap-1 align-items-center">
        <x-core::form method="post" :url="$importer->getDownloadExampleUrl()" class="d-flex gap-1">
            <x-core::button type="submit" icon="ti ti-file-type-csv" name="format" value="csv">
                {{ trans('packages/data-synchronize::data-synchronize.import.example.download', ['type' => 'CSV']) }}
            </x-core::button>
            <x-core::button type="submit" icon="ti ti-file-spreadsheet" name="format" value="xlsx">
                {{ trans('packages/data-synchronize::data-synchronize.import.example.download', ['type' => 'Excel']) }}
            </x-core::button>
        </x-core::form>

        <button
            type="button"
            class="btn btn-info"
            data-bs-toggle="modal"
            data-bs-target="#projects-import-rules-modal"
        >
            <x-core::icon name="ti ti-list-check" />
            {{ trans('packages/data-synchronize::data-synchronize.import.rules.title') }}
        </button>
    </div>

    <div
        class="modal fade"
        id="projects-import-rules-modal"
        tabindex="-1"
        aria-labelledby="projects-import-rules-modal-label"
        aria-hidden="true"
    >
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="projects-import-rules-modal-label">
                        {{ trans('packages/data-synchronize::data-synchronize.import.rules.title') }}
                    </h5>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="{{ trans('core/base::forms.cancel') }}"
                    ></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>{{ trans('packages/data-synchronize::data-synchronize.import.rules.column') }}</th>
                                    <th>{{ trans('packages/data-synchronize::data-synchronize.import.rules.title') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($importer->getColumns() as $column)
                                    <tr>
                                        <td>{{ $column->getLabel() }}</td>
                                        <td>{{ $column->getRulesDescription() ?: Arr::join($column->getRules(), ', ') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop
