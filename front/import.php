<?php

/**
 * NeDi Import - a plugin for GLPI
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Importer;
use GlpiPlugin\Nediimport\Menu;

Session::checkRight('config', UPDATE);

Html::header(__('NeDi import', 'nediimport'), $_SERVER['PHP_SELF'], 'tools', Menu::class);

echo "<div class='container-fluid'><div class='card'><div class='card-header'><h3 class='card-title'>"
    . __s('Import data from NeDi', 'nediimport') . "</h3></div><div class='card-body'>";

if (isset($_POST['run'])) {
    $result = (new Importer())->run();

    $labels = [
        'switches'          => __s('Switches synchronised', 'nediimport'),
        'switches_created'  => __s('Switches created', 'nediimport'),
        'ports_created'     => __s('Ports created', 'nediimport'),
        'vlans_created'     => __s('VLANs created', 'nediimport'),
        'nodes'             => __s('Nodes processed', 'nediimport'),
        'computers_created' => __s('Computers created', 'nediimport'),
        'virtual_switches'  => __s('Virtual switches created', 'nediimport'),
        'links'             => __s('Cables created or changed', 'nediimport'),
        'errors'            => __s('Errors', 'nediimport'),
    ];
    echo "<table class='table table-sm w-auto'>";
    foreach ($labels as $key => $label) {
        echo "<tr><td>$label</td><td class='text-end'>" . (int) $result->stats[$key] . '</td></tr>';
    }
    echo '</table>';
    if ($result->warnings) {
        echo "<h4>" . __s('Messages', 'nediimport') . "</h4><ul>";
        foreach (array_slice($result->warnings, 0, 100) as $w) {
            echo '<li>' . htmlspecialchars($w) . '</li>';
        }
        echo '</ul>';
    }
} else {
    echo '<p>' . __s('Read switches, ports, VLANs and attached nodes from NeDi and update GLPI. Nothing is deleted. This can take a while.', 'nediimport') . '</p>';
    echo "<form method='post' action='import.php'><button class='btn btn-primary' name='run' value='1'>"
        . __s('Start import', 'nediimport') . '</button>';
    Html::closeForm();
}

echo '</div></div></div>';
Html::footer();
