<?php

namespace App\Services\DataExport;

use App\Models\User;
use App\Services\DataExport\Datasets\CatalogDatasets;
use App\Services\DataExport\Datasets\CustomerDatasets;
use App\Services\DataExport\Datasets\OperationsDatasets;
use App\Services\DataExport\Datasets\RevenueDatasets;
use App\Services\DataExport\Datasets\ServiceDatasets;

class DataExportRegistry
{
    /** @var list<class-string<DataExportDatasetProvider>> */
    protected array $providers = [
        RevenueDatasets::class,
        CustomerDatasets::class,
        ServiceDatasets::class,
        CatalogDatasets::class,
        OperationsDatasets::class,
    ];

    /** @var array<string, DataExportDefinition>|null */
    protected ?array $definitions = null;

    /**
     * Every dataset whose underlying tables exist on this installation.
     *
     * @return array<string, DataExportDefinition>
     */
    public function all(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = [];

        foreach ($this->providers as $provider) {
            foreach ((new $provider)->definitions() as $definition) {
                if ($definition->isAvailable()) {
                    $definitions[$definition->key] = $definition;
                }
            }
        }

        return $this->definitions = $definitions;
    }

    public function find(string $key): ?DataExportDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Datasets the given staff member is allowed to export, keyed by dataset key.
     *
     * @return array<string, DataExportDefinition>
     */
    public function availableTo(User $user): array
    {
        return array_filter(
            $this->all(),
            fn (DataExportDefinition $definition): bool => $user->hasPermission($definition->permission),
        );
    }

    /**
     * @return array<string, array<string, DataExportDefinition>>
     */
    public function groupedFor(User $user): array
    {
        $grouped = [];

        foreach ($this->availableTo($user) as $key => $definition) {
            $grouped[$definition->group][$key] = $definition;
        }

        return $grouped;
    }
}
