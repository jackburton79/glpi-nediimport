<?php

/**
 * NeDi Import - a plugin for GLPI
 *
 * Originally written by Sein Coray (2012), ported to GLPI 11.
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Config;
use GlpiPlugin\Nediimport\Menu;

define('PLUGIN_NEDIIMPORT_VERSION', '1.0.0');
define('PLUGIN_NEDIIMPORT_MIN_GLPI', '11.0.0');
define('PLUGIN_NEDIIMPORT_MAX_GLPI', '11.99.99');

function plugin_init_nediimport(): void
{
    global $PLUGIN_HOOKS;

    if (Session::getLoginUserID() && Config::canView()) {
        $PLUGIN_HOOKS['menu_toadd']['nediimport'] = ['tools' => Menu::class];
        $PLUGIN_HOOKS['config_page']['nediimport'] = 'front/config.php';
    }
}

function plugin_version_nediimport(): array
{
    return [
        'name'         => 'NeDi Import',
        'version'      => PLUGIN_NEDIIMPORT_VERSION,
        'author'       => 'Sein Coray, Stefano Ceccherini',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/jackburton79/glpi-nediimport',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_NEDIIMPORT_MIN_GLPI,
                'max' => PLUGIN_NEDIIMPORT_MAX_GLPI,
            ],
            'php' => ['exts' => ['pdo_mysql' => ['required' => true]]],
        ],
    ];
}

function plugin_nediimport_check_prerequisites(): bool
{
    return true;
}

function plugin_nediimport_check_config($verbose = false): bool
{
    return true;
}
