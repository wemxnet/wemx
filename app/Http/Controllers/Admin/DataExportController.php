<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\DataExport\DataExportDefinition;
use App\Services\DataExport\DataExportFilters;
use App\Services\DataExport\DataExportRegistry;
use App\Services\DataExport\DataExportStreamer;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportController extends Controller
{
    public function __construct(
        protected DataExportRegistry $registry,
        protected DataExportStreamer $streamer,
    ) {}

    public function index()
    {
        return view('admin::data-export.index');
    }

    public function download(Request $request, string $dataset): StreamedResponse
    {
        $definition = $this->registry->find($dataset);

        abort_if($definition === null, 404);
        abort_unless(auth()->user()->hasPermission($definition->permission), 403);

        $validated = $request->validate([
            'format' => ['nullable', Rule::in(array_keys(DataExportStreamer::FORMATS))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);

        $format = $validated['format'] ?? 'csv';
        $filters = DataExportFilters::fromArray($validated);

        $this->recordExport($definition, $filters, $format);

        return $this->streamer->download($definition, $filters, $format);
    }

    protected function recordExport(DataExportDefinition $definition, DataExportFilters $filters, string $format): void
    {
        ActivityLog::create([
            'user_id' => auth()->id(),
            'event' => 'data_export',
            'tag' => 'admin',
            'description' => 'Exported '.$definition->label.' as '.strtoupper($format),
            'properties' => [
                'dataset' => $definition->key,
                'format' => $format,
                'from' => $filters->from?->toDateTimeString(),
                'to' => $filters->to?->toDateTimeString(),
                'limit' => $filters->limit,
            ],
        ]);
    }
}
