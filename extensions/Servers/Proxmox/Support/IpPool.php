<?php

namespace Extensions\Servers\Proxmox\Support;

use InvalidArgumentException;

class IpPool
{
    /**
     * Expand a pool definition into IPv4 addresses.
     *
     * Accepted lines:
     * - 192.168.1.10
     * - 192.168.1.10-192.168.1.20
     */
    public static function expand(string $raw): array
    {
        $addresses = [];

        foreach (preg_split('/\r\n|\r|\n|,/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '-')) {
                [$start, $end] = array_map('trim', explode('-', $line, 2));

                $addresses = array_merge($addresses, self::range($start, $end));

                continue;
            }

            if (filter_var($line, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                throw new InvalidArgumentException("Invalid IPv4 address [{$line}] in the IP pool.");
            }

            $addresses[] = $line;
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @param  array<int, string>  $used
     */
    public static function nextAvailable(string $raw, array $used = []): ?string
    {
        $used = array_flip($used);

        foreach (self::expand($raw) as $address) {
            if (! isset($used[$address])) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function range(string $start, string $end): array
    {
        if (filter_var($start, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || filter_var($end, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            throw new InvalidArgumentException("Invalid IPv4 range [{$start}-{$end}].");
        }

        $startLong = ip2long($start);
        $endLong = ip2long($end);

        if ($startLong === false || $endLong === false) {
            throw new InvalidArgumentException("Invalid IPv4 range [{$start}-{$end}].");
        }

        if ($startLong > $endLong) {
            [$startLong, $endLong] = [$endLong, $startLong];
        }

        if (($endLong - $startLong) > 1024) {
            throw new InvalidArgumentException('IP ranges cannot contain more than 1024 addresses.');
        }

        $addresses = [];

        for ($current = $startLong; $current <= $endLong; $current++) {
            $addresses[] = long2ip($current);
        }

        return $addresses;
    }
}
