<?php

namespace GlpiPlugin\Nediimport;

/**
 * Which NeDi devices are synchronised (glpi_plugin_nediimport_switches).
 */
final class SwitchSelection
{
    private const TABLE = 'glpi_plugin_nediimport_switches';

    /** Remember devices seen in NeDi; new ones start as "not synchronised". */
    public static function register(array $names): void
    {
        global $DB;

        $known = self::all();
        foreach ($names as $name) {
            if (!isset($known[$name])) {
                $DB->insert(self::TABLE, ['name' => $name, 'is_synced' => 0]);
            }
        }
    }

    /** @return array<string, bool> name => is_synced */
    public static function all(): array
    {
        global $DB;

        $out = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'ORDER' => 'name']) as $row) {
            $out[$row['name']] = (bool) $row['is_synced'];
        }
        return $out;
    }

    /** @param list<string> $synced names to synchronise; every other known device is switched off */
    public static function save(array $synced): void
    {
        global $DB;

        foreach (array_keys(self::all()) as $name) {
            $DB->update(self::TABLE, ['is_synced' => in_array($name, $synced, true) ? 1 : 0], ['name' => $name]);
        }
    }
}
