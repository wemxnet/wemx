<?php

namespace App\Services\DataExport;

use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Describes one exportable dataset: what it is called, who may export it, the
 * columns it produces and the query that produces them.
 */
class DataExportDefinition
{
    public string $description = '';

    public string $icon = 'table';

    public string $permission = 'admin.data-export';

    /** @var array<string, string> Column key => spreadsheet header. */
    public array $columns = [];

    public ?string $dateColumn = null;

    public string $dateLabel = '';

    protected bool $aggregated = false;

    protected ?Closure $queryResolver = null;

    protected ?Closure $rowMapper = null;

    protected ?Closure $availabilityResolver = null;

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $group,
    ) {}

    public static function make(string $key, string $label, string $group): self
    {
        return new self($key, $label, $group);
    }

    public function describedAs(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function withIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function requiring(string $permission): self
    {
        $this->permission = $permission;

        return $this;
    }

    /**
     * @param  array<string, string>  $columns
     */
    public function withColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function filteredByDate(string $column, string $label): self
    {
        $this->dateColumn = $column;
        $this->dateLabel = $label;

        return $this;
    }

    /**
     * @param  Closure(): Builder  $query
     * @param  Closure(object): array<string, string>  $map
     */
    public function sourcedFrom(Closure $query, Closure $map): self
    {
        $this->queryResolver = $query;
        $this->rowMapper = $map;

        return $this;
    }

    /**
     * Marks the query as grouped so row counts are taken from a wrapping subquery.
     */
    public function aggregated(): self
    {
        $this->aggregated = true;

        return $this;
    }

    /**
     * @param  Closure(): bool  $callback
     */
    public function availableWhen(Closure $callback): self
    {
        $this->availabilityResolver = $callback;

        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->availabilityResolver === null || (bool) ($this->availabilityResolver)();
    }

    public function supportsDateRange(): bool
    {
        return $this->dateColumn !== null;
    }

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return array_values($this->columns);
    }

    /**
     * @return list<string>
     */
    public function columnKeys(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return LazyCollection<int, array<string, string>>
     */
    public function rows(DataExportFilters $filters): LazyCollection
    {
        $query = $this->newQuery($filters);

        if ($filters->limit !== null) {
            $query->limit($filters->limit);
        }

        $mapper = $this->rowMapper;

        return $query->cursor()->map(fn (object $row): array => $mapper($row));
    }

    public function count(DataExportFilters $filters): int
    {
        $query = $this->newQuery($filters);

        if ($this->aggregated) {
            return DB::query()->fromSub($query, 'data_export_rows')->count();
        }

        return $query->count();
    }

    public function filename(string $extension): string
    {
        return 'wemx-'.Str::slug(str_replace('_', '-', $this->key)).'-'.now()->format('Y-m-d-His').'.'.$extension;
    }

    protected function newQuery(DataExportFilters $filters): Builder
    {
        if ($this->queryResolver === null || $this->rowMapper === null) {
            throw new RuntimeException("Data export [{$this->key}] does not define a data source.");
        }

        $query = ($this->queryResolver)();

        $filters->applyDateRange($query, $this->dateColumn);

        return $query;
    }
}
