<?php

declare(strict_types=1);

namespace Thehouseofel\Dbsync\Domain\Support;

final class DriverRegistry
{
    public static function engineFor(string $driver): ?string
    {
        $drivers = config('dbsync.drivers', []);

        if (! is_array($drivers)) {
            return null;
        }

        foreach ($drivers as $engine => $aliases) {
            if (! is_string($engine) || ! is_string($aliases)) {
                continue;
            }

            if (in_array($driver, self::parseAliases($aliases), true)) {
                return $engine;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private static function parseAliases(string $aliases): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $aliases)),
            static fn (string $alias): bool => $alias !== ''
        ));
    }
}

