<?php

namespace Extensions\Modules\Marketplace\Http\Requests;

use Extensions\Modules\Marketplace\Actions\MarketplaceResourceActions;
use Extensions\Modules\Marketplace\Enums\TeamRole;
use Extensions\Modules\Marketplace\Models\MarketplaceResource;
use Illuminate\Foundation\Http\FormRequest;

class UploadResourceIconRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resource = $this->route('resource');

        return $resource instanceof MarketplaceResource
            && $resource->userCan($this->user(), TeamRole::Manager);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return MarketplaceResourceActions::iconRules(required: true);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'icon.required' => 'Choose an image to upload.',
            'icon.max' => 'The icon must not be larger than 2 MB.',
        ];
    }
}
