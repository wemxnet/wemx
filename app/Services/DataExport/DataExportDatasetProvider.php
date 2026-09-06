<?php

namespace App\Services\DataExport;

interface DataExportDatasetProvider
{
    /**
     * @return list<DataExportDefinition>
     */
    public function definitions(): array;
}
