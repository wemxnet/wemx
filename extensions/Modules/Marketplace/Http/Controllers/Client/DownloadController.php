<?php

namespace Extensions\Modules\Marketplace\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Extensions\Modules\Marketplace\Models\MarketplaceResourceVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function download(MarketplaceResourceVersion $version): StreamedResponse
    {
        return MarketplaceResourceVersion::actions()->downloadForUser([
            'version_id' => $version->id,
            'user_id' => auth()->id(),
            'source' => 'marketplace',
        ]);
    }
}
