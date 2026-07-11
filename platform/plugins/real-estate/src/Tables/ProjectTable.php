<?php

namespace Botble\RealEstate\Tables;

use Botble\Base\Facades\BaseHelper;
use Botble\RealEstate\Enums\ProjectStatusEnum;
use Botble\RealEstate\Models\Investor;
use Botble\RealEstate\Models\Project;
use Botble\Table\Abstracts\TableAbstract;
use Botble\Table\Actions\DeleteAction;
use Botble\Table\Actions\EditAction;
use Botble\Table\BulkActions\DeleteBulkAction;
use Botble\Table\BulkChanges\NameBulkChange;
use Botble\Table\BulkChanges\SelectBulkChange;
use Botble\Table\BulkChanges\StatusBulkChange;
use Botble\Table\Columns\CreatedAtColumn;
use Botble\Table\Columns\FormattedColumn;
use Botble\Table\Columns\IdColumn;
use Botble\Table\Columns\ImageColumn;
use Botble\Table\Columns\NameColumn;
use Botble\Table\Columns\StatusColumn;
use Botble\Table\HeaderActions\CreateHeaderAction;
use Botble\Table\HeaderActions\HeaderAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProjectTable extends TableAbstract
{
    public function setup(): void
    {
        $this->defaultSortColumnName = 'created_at';

        $this
            ->model(Project::class)
            ->addHeaderAction(CreateHeaderAction::make()->route('project.create'))
            ->when($this->hasPermission('tools.data-synchronize.import.projects.index'), function ($table) {
                return $table->addHeaderAction(
                    HeaderAction::make('import')
                        ->route('tools.data-synchronize.import.projects.index')
                        ->icon('ti ti-upload')
                        ->label(trans('plugins/real-estate::project.import_projects'))
                        ->color('btn-info')
                );
            })
            ->when($this->hasPermission('tools.data-synchronize.export.projects.index'), function ($table) {
                return $table->addHeaderAction(
                    HeaderAction::make('export')
                        ->route('tools.data-synchronize.export.projects.index')
                        ->icon('ti ti-download')
                        ->label(trans('plugins/real-estate::project.export_projects'))
                        ->color('btn-success')
                );
            })
            ->addActions([
                EditAction::make()->route('project.edit'),
                DeleteAction::make()->route('project.destroy'),
            ])
            ->addColumns([
                IdColumn::make(),
                ImageColumn::make()
                    ->searchable(false)
                    ->orderable(false),
                NameColumn::make()->route('project.edit'),
                FormattedColumn::make('properties_count')
                    ->title(trans('plugins/real-estate::project.properties'))
                    ->width(100)
                    ->alignCenter()
                    ->orderable(false)
                    ->searchable(false)
                    ->getValueUsing(function (FormattedColumn $column) {
                        $propertiesCount = $column->getItem()->properties_count;

                        if (! $propertiesCount) {
                            return 0;
                        }

                        return number_format($propertiesCount);
                    }),
                FormattedColumn::make('views')
                    ->title(trans('plugins/real-estate::project.views'))
                    ->getValueUsing(function (FormattedColumn $column) {
                        $views = $column->getItem()->views;

                        if (! $views) {
                            return 0;
                        }

                        return number_format($views);
                    }),
                FormattedColumn::make('unique_id')
                    ->title(trans('plugins/real-estate::project.unique_id'))
                    ->getValueUsing(function (FormattedColumn $column) {
                        return BaseHelper::clean($column->getItem()->unique_id ?: '&mdash;');
                    }),
                FormattedColumn::make('source')
                    ->title(trans('plugins/real-estate::project.source'))
                    ->width(120)
                    ->orderable(false)
                    ->searchable(false)
                    ->getValueUsing(function (FormattedColumn $column) {
                        $source = $column->getItem()->source;

                        if (! $source) {
                            return '&mdash;';
                        }

                        $color = match ($source) {
                            'buildify' => 'info',
                            'excel' => 'success',
                            'manual' => 'secondary',
                            default => 'primary',
                        };

                        return BaseHelper::renderBadge(Str::headline($source), $color);
                    }),
                CreatedAtColumn::make(),
                StatusColumn::make(),
            ])
            ->addBulkActions([
                DeleteBulkAction::make()->permission('project.destroy'),
            ])
            ->addBulkChanges([
                NameBulkChange::make(),
                StatusBulkChange::make()
                    ->choices(ProjectStatusEnum::labels()),
                SelectBulkChange::make()
                    ->name('investor_id')
                    ->title(trans('plugins/real-estate::project.form.investor'))
                    ->searchable()
                    ->choices(fn () => Investor::query()->pluck('name', 'id')->all()),
                SelectBulkChange::make()
                    ->name('source')
                    ->title(trans('plugins/real-estate::project.source'))
                    ->choices(fn () => $this->sourceChoices()),
            ])
            ->queryUsing(function (Builder $query) {
                return $query
                    ->select([
                        'id',
                        'name',
                        'images',
                        'views',
                        'status',
                        'created_at',
                        'unique_id',
                        'source',
                        'location',
                        'zip_code',
                    ])
                    ->withCount('properties');
            })
            ->onAjax(function (self $table) {
                return $table->toJson(
                    $table
                        ->table
                        ->eloquent($table->query())
                        ->filter(function ($query) {
                            if ($keyword = $this->request->input('search.value')) {
                                $keyword = '%' . $keyword . '%';

                                return $query
                                    ->where('name', 'LIKE', $keyword)
                                    ->orWhere('unique_id', 'LIKE', $keyword)
                                    ->orWhere('location', 'LIKE', $keyword)
                                    ->orWhere('zip_code', 'LIKE', $keyword)
                                    ->orWhereHas('city', function ($query) use ($keyword): void {
                                        $query->where('name', 'LIKE', $keyword);
                                    })
                                    ->orWhereHas('state', function ($query) use ($keyword): void {
                                        $query->where('name', 'LIKE', $keyword);
                                    })
                                    ->orWhereHas('country', function ($query) use ($keyword): void {
                                        $query->where('name', 'LIKE', $keyword);
                                    });
                            }

                            return $query;
                        })
                );
            });
    }

    /**
     * Filter options built from the distinct `source` values actually present in
     * the database. New ingestion sources (e.g. another API) appear here on their
     * own — no code change needed — as long as they stamp their own `source`.
     *
     * @return array<string, string>
     */
    protected function sourceChoices(): array
    {
        return Project::query()
            ->whereNotNull('source')
            ->where('source', '!=', '')
            ->distinct()
            ->orderBy('source')
            ->pluck('source')
            ->mapWithKeys(fn ($source) => [$source => Str::headline($source)])
            ->all();
    }
}
