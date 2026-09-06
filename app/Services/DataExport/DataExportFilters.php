<?php

namespace App\Services\DataExport;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Throwable;

class DataExportFilters
{
    public function __construct(
        public readonly ?Carbon $from = null,
        public readonly ?Carbon $to = null,
        public readonly ?int $limit = null,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            from: self::parseDate($input['from'] ?? null)?->startOfDay(),
            to: self::parseDate($input['to'] ?? null)?->endOfDay(),
            limit: self::parseLimit($input['limit'] ?? null),
        );
    }

    public function limitedTo(int $rows): self
    {
        return new self($this->from, $this->to, $rows);
    }

    public function hasDateRange(): bool
    {
        return $this->from !== null || $this->to !== null;
    }

    public function applyDateRange(Builder $query, ?string $column): void
    {
        if ($column === null) {
            return;
        }

        if ($this->from !== null) {
            $query->where($column, '>=', $this->from);
        }

        if ($this->to !== null) {
            $query->where($column, '<=', $this->to);
        }
    }

    /**
     * @return array<string, string>
     */
    public function toQueryParameters(): array
    {
        return array_filter([
            'from' => $this->from?->toDateString(),
            'to' => $this->to?->toDateString(),
            'limit' => $this->limit !== null ? (string) $this->limit : null,
        ]);
    }

    protected static function parseDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    protected static function parseLimit(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max(1, (int) $value);
    }
}
