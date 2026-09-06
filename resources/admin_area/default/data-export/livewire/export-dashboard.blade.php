<?php

use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportFilters;
use App\Services\DataExport\DataExportRegistry;
use App\Services\DataExport\DataExportStreamer;
use Livewire\Volt\Component;

new class extends Component
{
    public const PREVIEW_ROWS = 8;

    public string $dataset = '';

    public string $format = 'csv';

    public string $search = '';

    public ?string $from = null;

    public ?string $to = null;

    public ?string $limit = null;

    public function mount(): void
    {
        $this->dataset = (string) array_key_first($this->available());
    }

    public function selectDataset(string $dataset): void
    {
        if (! array_key_exists($dataset, $this->available())) {
            return;
        }

        $this->dataset = $dataset;
    }

    public function applyPreset(string $preset): void
    {
        [$from, $to] = match ($preset) {
            'this_month' => [now()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'last_30_days' => [now()->subDays(30), now()],
            'this_quarter' => [now()->startOfQuarter(), now()->endOfQuarter()],
            'this_year' => [now()->startOfYear(), now()->endOfYear()],
            'last_year' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            default => [null, null],
        };

        $this->from = $from?->toDateString();
        $this->to = $to?->toDateString();
    }

    /**
     * @return array<string, string>
     */
    public function formats(): array
    {
        return DataExportStreamer::FORMATS;
    }

    /**
     * Datasets the current staff member may export, grouped for the sidebar list.
     *
     * @return array<string, array<string, DataExportDefinition>>
     */
    public function groups(): array
    {
        $search = trim(mb_strtolower($this->search));

        if ($search === '') {
            return app(DataExportRegistry::class)->groupedFor(auth()->user());
        }

        $groups = [];

        foreach ($this->available() as $key => $definition) {
            $haystack = mb_strtolower($definition->label.' '.$definition->group.' '.$definition->description);

            if (str_contains($haystack, $search)) {
                $groups[$definition->group][$key] = $definition;
            }
        }

        return $groups;
    }

    public function definition(): ?DataExportDefinition
    {
        return $this->available()[$this->dataset] ?? null;
    }

    /**
     * Row count and a short preview for the selected dataset.
     *
     * @return array{count: int, rows: list<array<string, string>>, error: string|null}
     */
    public function summary(): array
    {
        $definition = $this->definition();

        if ($definition === null) {
            return ['count' => 0, 'rows' => [], 'error' => null];
        }

        $filters = $this->filters();

        try {
            return [
                'count' => $definition->count($filters),
                'rows' => $definition->rows($filters->limitedTo(self::PREVIEW_ROWS))->all(),
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return ['count' => 0, 'rows' => [], 'error' => $exception->getMessage()];
        }
    }

    public function downloadUrl(): ?string
    {
        $definition = $this->definition();

        if ($definition === null) {
            return null;
        }

        return route('admin.data-export.download', array_merge(
            ['dataset' => $definition->key, 'format' => $this->format],
            $this->filters()->toQueryParameters(),
        ));
    }

    /**
     * @return array<string, DataExportDefinition>
     */
    protected function available(): array
    {
        return app(DataExportRegistry::class)->availableTo(auth()->user());
    }

    protected function filters(): DataExportFilters
    {
        $supportsDates = $this->definition()?->supportsDateRange() ?? false;

        return DataExportFilters::fromArray([
            'from' => $supportsDates ? $this->from : null,
            'to' => $supportsDates ? $this->to : null,
            'limit' => $this->limit,
        ]);
    }
}

?>

<div class="row row-cards">
    @php
        $groups = $this->groups();
        $definition = $this->definition();
        $totalDatasets = count($this->available());
    @endphp

    <div class="col-12 col-lg-4">
        <div class="card">
            <div class="card-header d-block">
                <h3 class="card-title mb-1">{{ __('messages.data_export') }}</h3>
                <div class="text-secondary small">
                    {{ trans_choice('[0,1] :count dataset available|[2,*] :count datasets available', $totalDatasets, ['count' => $totalDatasets]) }}
                </div>
            </div>
            <div class="card-body border-bottom py-3">
                <div class="input-icon">
                    <span class="input-icon-addon">
                        <x-admin::icon icon="search" outline/>
                    </span>
                    <input type="search" class="form-control" placeholder="Search datasets..."
                           wire:model.live.debounce.300ms="search"/>
                </div>
            </div>

            @if(empty($groups))
                <div class="card-body">
                    <p class="text-secondary mb-0">No datasets match your search.</p>
                </div>
            @else
                <div class="list-group list-group-flush">
                    @foreach($groups as $group => $definitions)
                        <div class="list-group-item bg-body-tertiary py-2">
                            <span class="text-uppercase text-secondary small fw-bold">{{ $group }}</span>
                        </div>
                        @foreach($definitions as $key => $option)
                            <button type="button"
                                    wire:key="dataset-{{ $key }}"
                                    wire:click="selectDataset('{{ $key }}')"
                                    @class([
                                        'list-group-item list-group-item-action text-start d-flex align-items-center gap-2',
                                        'active' => $this->dataset === $key,
                                    ])>
                                <x-admin::icon :icon="$option->icon" outline/>
                                <span>{{ $option->label }}</span>
                            </button>
                        @endforeach
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="col-12 col-lg-8">
        @if($definition === null)
            <div class="card">
                <div class="empty">
                    <div class="empty-icon">
                        <x-admin::icon icon="database-off" class="icon icon-lg" outline/>
                    </div>
                    <p class="empty-title">Nothing to export</p>
                    <p class="empty-subtitle text-secondary">
                        You do not have permission to export any data yet. Ask a primary administrator to grant you
                        access to the areas you need to report on.
                    </p>
                </div>
            </div>
        @else
            @php($summary = $this->summary())
            @php($matched = $summary['count'])
            @php($limit = $this->limit !== null && $this->limit !== '' ? max(1, (int) $this->limit) : null)
            @php($exported = $limit !== null ? min($matched, $limit) : $matched)

            <div class="card">
                <div class="card-header d-block">
                    <h3 class="card-title mb-1 d-flex align-items-center gap-2">
                        <x-admin::icon :icon="$definition->icon" outline/>
                        {{ $definition->label }}
                    </h3>
                    <div class="text-secondary">{{ $definition->description }}</div>
                </div>

                <div class="card-body border-bottom">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <x-admin::form.label for="export-format">Format</x-admin::form.label>
                            <select class="form-select" id="export-format" wire:model.live="format">
                                @foreach($this->formats() as $value => $formatLabel)
                                    <option value="{{ $value }}">{{ $formatLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        @if($definition->supportsDateRange())
                            <div class="col-6 col-md-4">
                                <x-admin::form.label for="export-from">{{ $definition->dateLabel }} from</x-admin::form.label>
                                <input type="date" class="form-control" id="export-from"
                                       wire:model.live.debounce.500ms="from"/>
                            </div>
                            <div class="col-6 col-md-4">
                                <x-admin::form.label for="export-to">{{ $definition->dateLabel }} to</x-admin::form.label>
                                <input type="date" class="form-control" id="export-to"
                                       wire:model.live.debounce.500ms="to"/>
                            </div>
                        @else
                            <div class="col-12 col-md-8 d-flex align-items-end">
                                <x-admin::form.description>
                                    This dataset is configuration rather than history, so it is always exported in full.
                                </x-admin::form.description>
                            </div>
                        @endif
                    </div>

                    @if($definition->supportsDateRange())
                        <div class="mt-3 d-flex flex-wrap gap-2">
                            @foreach([
                                'this_month' => 'This month',
                                'last_month' => 'Last month',
                                'last_30_days' => 'Last 30 days',
                                'this_quarter' => 'This quarter',
                                'this_year' => 'This year',
                                'last_year' => 'Last year',
                                'all_time' => 'All time',
                            ] as $preset => $presetLabel)
                                <button type="button" class="btn btn-sm"
                                        wire:click="applyPreset('{{ $preset }}')">{{ $presetLabel }}</button>
                            @endforeach
                        </div>
                    @endif

                    <div class="row g-3 mt-1">
                        <div class="col-12 col-md-4">
                            <x-admin::form.label for="export-limit">Row limit (optional)</x-admin::form.label>
                            <input type="number" min="1" class="form-control" id="export-limit" placeholder="No limit"
                                   wire:model.live.debounce.500ms="limit"/>
                        </div>
                        <div class="col-12 col-md-8 d-flex align-items-end">
                            <x-admin::form.description>
                                Exports stream directly to the file, so large datasets download without a row limit.
                            </x-admin::form.description>
                        </div>
                    </div>
                </div>

                <div class="card-body border-bottom">
                    @if($summary['error'] !== null)
                        <div class="alert alert-danger mb-0" role="alert">
                            <h4 class="alert-title">This dataset could not be read</h4>
                            <div class="text-secondary">{{ $summary['error'] }}</div>
                        </div>
                    @else
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div wire:loading.remove wire:target="from,to,limit,format,search,selectDataset,applyPreset">
                                <span class="h1 mb-0">{{ number_format($exported) }}</span>
                                <span class="text-secondary ms-1">
                                    {{ trans_choice('[0,1] row|[2,*] rows', $exported) }} will be exported
                                </span>
                                @if($limit !== null && $matched > $limit)
                                    <span class="badge bg-yellow-lt ms-2">
                                        limited from {{ number_format($matched) }}
                                    </span>
                                @endif
                            </div>
                            <div wire:loading wire:target="from,to,limit,format,search,selectDataset,applyPreset"
                                 class="text-secondary">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Counting rows...
                            </div>
                            <div class="ms-auto">
                                @if($exported > 0)
                                    <a href="{{ $this->downloadUrl() }}" class="btn btn-primary">
                                        <x-admin::icon icon="download" outline/>
                                        <span class="ms-2">Download {{ strtoupper($this->format) }}</span>
                                    </a>
                                @else
                                    <button type="button" class="btn btn-primary disabled" disabled>
                                        <x-admin::icon icon="download" outline/>
                                        <span class="ms-2">Nothing to download</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                @if($summary['error'] === null)
                    <div class="card-body border-bottom">
                        <h4 class="mb-3">
                            Preview
                            <span class="text-secondary fw-normal small">
                                first {{ count($summary['rows']) }} of {{ number_format($exported) }}
                            </span>
                        </h4>

                        @if(empty($summary['rows']))
                            <p class="text-secondary mb-0">
                                No rows match the selected filters. Try widening the date range.
                            </p>
                        @else
                            <div class="table-responsive">
                                <table class="table table-vcenter table-sm card-table">
                                    <thead>
                                    <tr>
                                        @foreach($definition->columns as $columnKey => $header)
                                            <th class="text-nowrap">{{ $header }}</th>
                                        @endforeach
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($summary['rows'] as $index => $row)
                                        <tr wire:key="preview-{{ $this->dataset }}-{{ $index }}">
                                            @foreach($definition->columnKeys() as $columnKey)
                                                <td class="text-nowrap">
                                                    {{ Str::limit($row[$columnKey] ?? '', 40) }}
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="card-footer">
                    <div class="text-secondary small mb-2">
                        {{ count($definition->columns) }} columns included
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($definition->headers() as $header)
                            <span class="badge bg-secondary-lt">{{ $header }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
