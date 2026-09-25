<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function download(Request $request, int $version): StreamedResponse
    {
        return MarketplaceResourceVersion::actions()->downloadForIntegrated([
            'version_id' => $version,
            'license_key' => $request->string('license_key')->toString() ?: null,
        ]);
    }
}
