<?php

namespace App\Models;

use App\Actions\EmailTemplateActions;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    protected $fillable = [
        'identifier',
        'subject',
        'body',
        'button_text',
        'enabled',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function actions(): EmailTemplateActions
    {
        return new EmailTemplateActions;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return config('email_events', []);
    }

    public static function definitionExists(string $identifier): bool
    {
        return array_key_exists($identifier, self::definitions());
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function definition(string $identifier): ?array
    {
        $definition = self::definitions()[$identifier] ?? null;

        if (! is_array($definition)) {
            return null;
        }

        return array_merge($definition, ['identifier' => $identifier]);
    }

    /**
     * Merge the default definition with any admin override.
     *
     * @return array<string, mixed>|null
     */
    public static function resolved(string $identifier): ?array
    {
        $definition = self::definition($identifier);

        if ($definition === null) {
            return null;
        }

        $override = self::query()->where('identifier', $identifier)->first();

        if ($override === null) {
            return [
                'identifier' => $identifier,
                'name' => $definition['name'] ?? $identifier,
                'group' => $definition['group'] ?? 'Other',
                'description' => $definition['description'] ?? '',
                'subject' => $definition['subject'],
                'body' => $definition['body'],
                'button_text' => $definition['button_text'] ?? null,
                'placeholders' => $definition['placeholders'] ?? [],
                'enabled' => true,
                'customized' => false,
            ];
        }

        return [
            'identifier' => $identifier,
            'name' => $definition['name'] ?? $identifier,
            'group' => $definition['group'] ?? 'Other',
            'description' => $definition['description'] ?? '',
            'subject' => $override->subject,
            'body' => $override->body,
            'button_text' => $override->button_text,
            'placeholders' => $definition['placeholders'] ?? [],
            'enabled' => $override->enabled,
            'customized' => true,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function catalog(): array
    {
        $overrides = self::query()->get()->keyBy('identifier');
        $catalog = [];

        foreach (self::definitions() as $identifier => $definition) {
            $override = $overrides->get($identifier);

            $catalog[] = [
                'identifier' => $identifier,
                'name' => $definition['name'] ?? $identifier,
                'group' => $definition['group'] ?? 'Other',
                'description' => $definition['description'] ?? '',
                'subject' => $override->subject ?? $definition['subject'],
                'enabled' => $override !== null ? $override->enabled : true,
                'customized' => $override !== null,
            ];
        }

        return $catalog;
    }

    /**
     * Fill subject, lines, and button text from the matching template.
     * Returns null when the template is disabled.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function compose(array $payload, ?User $user = null): ?array
    {
        $identifier = $payload['template'] ?? $payload['identifier'] ?? null;

        if (! is_string($identifier) || ! self::definitionExists($identifier)) {
            return $payload;
        }

        $resolved = self::resolved($identifier);

        if ($resolved === null || $resolved['enabled'] === false) {
            return null;
        }

        $variables = array_merge(
            [
                'app_name' => settings('app_name', 'My Application'),
                'user_name' => $user?->first_name ?: ($user?->username ?? ''),
                'user_username' => $user?->username ?? '',
                'user_email' => $user?->email ?? '',
            ],
            $payload['variables'] ?? []
        );

        $payload['subject'] = self::replacePlaceholders($resolved['subject'], $variables);
        $payload['lines'] = self::bodyToLines(self::replacePlaceholders($resolved['body'], $variables));

        $buttonText = self::replacePlaceholders((string) ($resolved['button_text'] ?? ''), $variables);

        if (! empty($payload['button_url'])) {
            $payload['button_text'] = $buttonText !== '' ? $buttonText : null;
        }

        unset($payload['variables'], $payload['template']);

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public static function replacePlaceholders(string $text, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            function (array $matches) use ($variables): string {
                $value = $variables[$matches[1]] ?? '';

                if (is_bool($value)) {
                    return $value ? '1' : '0';
                }

                return (string) $value;
            },
            $text
        );
    }

    /**
     * @return array<int, string>
     */
    public static function bodyToLines(string $body): array
    {
        $body = str_replace(["\r\n", "\r"], "\n", trim($body));

        if ($body === '') {
            return [];
        }

        return explode("\n", $body);
    }
}
