<?php

/**
 * NeDi Import - a plugin for GLPI
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Config;
use GlpiPlugin\Nediimport\Menu;

Session::checkRight('config', READ);

Html::header(__('NeDi import', 'nediimport'), $_SERVER['PHP_SELF'], 'tools', Menu::class);

$cfg  = Config::get();
$last = json_decode($cfg['last_stats'] ?: '[]', true) ?: [];

echo "<div class='container-fluid'><div class='card'><div class='card-header'><h3 class='card-title'>"
    . __s('NeDi import', 'nediimport') . "</h3></div><div class='list-group list-group-flush'>";
echo "<a class='list-group-item' href='import.php'><i class='ti ti-download me-2'></i>" . __s('Import data from NeDi', 'nediimport') . '</a>';
echo "<a class='list-group-item' href='config.php'><i class='ti ti-settings me-2'></i>" . __s('NeDi connection and settings', 'nediimport') . '</a>';
if ($cfg['auto_all'] !== '1') {
    echo "<a class='list-group-item' href='switches.php'><i class='ti ti-list-check me-2'></i>" . __s('Choose the switches to import', 'nediimport') . '</a>';
}
echo '</div></div>';

if ($cfg['last_run'] !== '') {
    echo "<p class='mt-3 text-muted'>" . sprintf(__s('Last import: %s', 'nediimport'), htmlspecialchars($cfg['last_run'])) . '</p>';
}
echo '</div>';

Html::footer();
