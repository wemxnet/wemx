<?php

namespace App\Services\DataExport;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Driver specific SQL fragments used by the aggregate exports.
 */
class DataExportGrammar
{
    /**
     * Returns a raw expression that renders a timestamp column as `YYYY-MM`.
     */
    public static function yearMonth(string $column): string
    {
        if (preg_match('/^[A-Za-z0-9_]+\.[A-Za-z0-9_]+$/', $column) !== 1) {
            throw new InvalidArgumentException("Column [{$column}] cannot be grouped by month.");
        }

        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$column}, 'yyyy-MM')",
            default => "STRFTIME('%Y-%m', {$column})",
        };
    }
}
