<?php

namespace GlpiPlugin\Nediimport;

use Config as GlpiConfig;
use GLPIKey;
use Session;

/**
 * Plugin settings, stored in glpi_configs (context "plugin:nediimport").
 */
final class Config
{
    public const CONTEXT = 'plugin:nediimport';

    private const DEFAULTS = [
        'nedi_host'    => 'localhost',
        'nedi_port'    => '3306',
        'nedi_db'      => 'nedi',
        'nedi_user'    => 'nedi',
        'nedi_pass'    => '',
        'auto_all'     => '0',
        'max_age_days' => '365',
        'entities_id'  => '0',
        'last_run'     => '',
        'last_stats'   => '',
    ];

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function setDefaults(): void
    {
        $current = GlpiConfig::getConfigurationValues(self::CONTEXT);
        $missing = array_diff_key(self::DEFAULTS, $current);
        if ($missing) {
            GlpiConfig::setConfigurationValues(self::CONTEXT, $missing);
        }
    }

    /**
     * @return array<string, string> with the password already decrypted
     */
    public static function get(): array
    {
        $values = array_merge(self::DEFAULTS, GlpiConfig::getConfigurationValues(self::CONTEXT));
        if ($values['nedi_pass'] !== '') {
            $values['nedi_pass'] = (new GLPIKey())->decrypt($values['nedi_pass']);
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $values only known keys are stored
     */
    public static function save(array $values): void
    {
        $values = array_intersect_key($values, self::DEFAULTS);
        if (isset($values['nedi_pass']) && $values['nedi_pass'] !== '') {
            $values['nedi_pass'] = (new GLPIKey())->encrypt((string) $values['nedi_pass']);
        }
        GlpiConfig::setConfigurationValues(self::CONTEXT, array_map('strval', $values));
    }
}
