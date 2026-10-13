<?php

/**
 * NeDi Import - a plugin for GLPI
 *
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Config;
use GlpiPlugin\Nediimport\Cron;

function plugin_nediimport_install(): bool
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    // Switches known to NeDi, and whether they must be synchronised
    if (!$DB->tableExists('glpi_plugin_nediimport_switches')) {
        $DB->doQuery(
            "CREATE TABLE `glpi_plugin_nediimport_switches` (
                `id` int {$sign} NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `is_synced` tinyint NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;"
        ) or die('error creating glpi_plugin_nediimport_switches: ' . $DB->error());
    }

    Config::setDefaults();

    // Disabled until the administrator has configured the NeDi connection
    CronTask::register(
        Cron::class,
        'Nediimport',
        DAY_TIMESTAMP,
        [
            'state'   => CronTask::STATE_DISABLE,
            'mode'    => CronTask::MODE_EXTERNAL,
            'comment' => 'Import switches, ports, VLANs and nodes from NeDi',
        ]
    );

    return true;
}

function plugin_nediimport_uninstall(): bool
{
    global $DB;

    $DB->doQuery('DROP TABLE IF EXISTS `glpi_plugin_nediimport_switches`');
    $DB->delete('glpi_crontasks', ['itemtype' => Cron::class]);
    $DB->delete('glpi_configs', ['context' => Config::CONTEXT]);

    return true;
}
