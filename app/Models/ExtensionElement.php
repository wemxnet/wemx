<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionElement extends Model
{
    protected $table = 'extension_elements';

    protected $fillable = [
        'extension_identifier',
        'element',
        'view',
        'permission',
        'attributes',
        'sort_order',
    ];

    protected $casts = [
        'attributes' => 'array',
    ];

    public function extension()
    {
        return $this->belongsTo(Extension::class, 'extension_identifier', 'identifier');
    }

    public static function activeElements(string|array $elementTypes): array
    {
        if (is_string($elementTypes)) {
            $elementTypes = [$elementTypes];
        }

        $elements = self::whereIn('element', $elementTypes)->get();

        return $elements->map(function ($element) {
            return [
                'element' => $element->element,
                'view' => $element->view,
                'permission' => $element->permission,
                'attributes' => $element['attributes'] ?? [], // note for self, don't use $element->attributes directly, as it will use laravels method
            ];
        })->toArray();
    }
}
