<?php

namespace Extensions\Servers\Proxmox\Support;

class OsTemplates
{
    /**
     * Parse admin-configured OS templates.
     *
     * Accepted lines:
     * - id|Label|vmid
     * - Label|vmid
     * - vmid
     *
     * @return array<int, array{id: string, label: string, vmid: int}>
     */
    public static function parse(string $raw): array
    {
        $templates = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));

            if (count($parts) >= 3 && is_numeric($parts[2])) {
                $templates[] = [
                    'id' => $parts[0],
                    'label' => $parts[1] !== '' ? $parts[1] : $parts[0],
                    'vmid' => (int) $parts[2],
                ];

                continue;
            }

            if (count($parts) === 2 && is_numeric($parts[1])) {
                $id = str($parts[0])->slug()->toString() ?: $parts[1];

                $templates[] = [
                    'id' => $id,
                    'label' => $parts[0],
                    'vmid' => (int) $parts[1],
                ];

                continue;
            }

            if (count($parts) === 1 && is_numeric($parts[0])) {
                $templates[] = [
                    'id' => $parts[0],
                    'label' => 'Template '.$parts[0],
                    'vmid' => (int) $parts[0],
                ];
            }
        }

        return $templates;
    }

    /**
     * @return array<string, string>
     */
    public static function options(string $raw): array
    {
        $options = [];

        foreach (self::parse($raw) as $template) {
            $options[$template['id']] = $template['label'].' (VM '.$template['vmid'].')';
        }

        return $options;
    }

    /**
     * @return array{id: string, label: string, vmid: int}|null
     */
    public static function find(string $raw, string|int|null $idOrVmid): ?array
    {
        if ($idOrVmid === null || $idOrVmid === '') {
            return null;
        }

        foreach (self::parse($raw) as $template) {
            if ((string) $template['id'] === (string) $idOrVmid || (string) $template['vmid'] === (string) $idOrVmid) {
                return $template;
            }
        }

        if (is_numeric($idOrVmid)) {
            return [
                'id' => (string) $idOrVmid,
                'label' => 'Template '.$idOrVmid,
                'vmid' => (int) $idOrVmid,
            ];
        }

        return null;
    }
}
