<?php

/**
 * NeDi Import - a plugin for GLPI
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Importer;
use GlpiPlugin\Nediimport\ImportResult;
use GlpiPlugin\Nediimport\Menu;

Session::checkRight('config', UPDATE);

Html::header(__('NeDi import', 'nediimport'), $_SERVER['PHP_SELF'], 'tools', Menu::class);

$h = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES);

/** Table with the counters of an import (real or predicted) */
$statsTable = static function (ImportResult $result) use ($h): string {
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
    $out = "<table class='table table-sm w-auto'>";
    foreach ($labels as $key => $label) {
        $out .= "<tr><td>$label</td><td class='text-end'>" . (int) $result->stats[$key] . '</td></tr>';
    }
    return $out . '</table>';
};

$messages = static function (ImportResult $result) use ($h): string {
    if (!$result->warnings) {
        return '';
    }
    $out = '<h4>' . __s('Messages', 'nediimport') . '</h4><ul>';
    foreach (array_slice($result->warnings, 0, 100) as $w) {
        $out .= '<li>' . $h($w) . '</li>';
    }
    return $out . '</ul>';
};

$card = static function (string $title, string $body): void {
    echo "<div class='container-fluid'><div class='card mb-3'><div class='card-header'><h3 class='card-title'>$title</h3></div>"
        . "<div class='card-body'>$body</div></div></div>";
};

$previewButton = static function (): string {
    ob_start();
    echo "<form method='post' action='import.php'><button class='btn btn-outline-primary' name='preview' value='1'>"
        . __s('Preview', 'nediimport') . '</button>';
    Html::closeForm();
    return ob_get_clean();
};

if (isset($_POST['preview'])) {
    // ------------------------------------------------------------------ preview
    $result = (new Importer(null, ['dry_run' => true]))->run();

    $byScope = [];       // switch => ['switch' => [actions], 'nodes' => [mac => [actions]]]
    foreach ($result->actions as $a) {
        if ($a['mac'] === null) {
            $byScope[$a['switch']]['switch'][] = $a;
        } else {
            $byScope[$a['switch']]['nodes'][$a['mac']][] = $a;
        }
    }
    $nodesBySwitch = [];
    foreach ($result->nodes as $n) {
        $nodesBySwitch[$n['switch']][] = $n;
    }

    $card(
        __s('Preview', 'nediimport'),
        '<p>' . __s('Nothing has been written to GLPI. Below is what the import would do, switch by switch: tick what you want and apply it one switch at a time. Run the preview again afterwards: when everything is applied it reports no changes.', 'nediimport') . '</p>'
        . '<h4>' . __s('Expected totals', 'nediimport') . '</h4>' . $statsTable($result) . $messages($result)
    );

    $listActions = static function (array $actions) use ($h): string {
        $collapsed = ['new_port' => __s('Ports to create', 'nediimport'), 'vlan_change' => __s('VLAN changes', 'nediimport')];
        $plain     = [];
        $groups    = [];
        foreach ($actions as $a) {
            if (isset($collapsed[$a['type']])) {
                $groups[$a['type']][] = $a['text'];
            } else {
                $plain[] = $a;
            }
        }
        $out = '<ul class="mb-1">';
        foreach ($plain as $a) {
            $class = $a['type'] === 'warn' ? ' class="text-warning"' : '';
            $out  .= "<li$class>" . $h($a['text']) . '</li>';
        }
        $out .= '</ul>';
        foreach ($groups as $type => $texts) {
            $out .= '<details class="mb-1"><summary>' . $collapsed[$type] . ' (' . count($texts) . ')</summary><ul>';
            foreach ($texts as $t) {
                $out .= '<li>' . $h($t) . '</li>';
            }
            $out .= '</ul></details>';
        }
        return $out;
    };

    $unchangedSwitches = 0;
    $switchNames       = array_unique(array_merge(array_keys($byScope), array_keys($nodesBySwitch)));
    sort($switchNames, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($switchNames as $switch) {
        $switchActions = $byScope[$switch]['switch'] ?? [];
        $nodeActions   = $byScope[$switch]['nodes'] ?? [];
        $changedNodes  = array_filter($nodesBySwitch[$switch] ?? [], static fn($n) => isset($nodeActions[$n['mac']]));
        $unchanged     = count($nodesBySwitch[$switch] ?? []) - count($changedNodes);

        if (!$switchActions && !$changedNodes) {
            $unchangedSwitches++;
            continue;
        }

        ob_start();
        echo "<form method='post' action='import.php'>";
        echo "<input type='hidden' name='apply_switch' value='" . $h($switch) . "'>";
        if ($switchActions) {
            echo "<label class='form-check'><input class='form-check-input' type='checkbox' name='switch_level' value='1' checked>"
                . "<span class='form-check-label'><strong>" . __s('The switch itself: equipment, ports, VLANs, cables to other switches', 'nediimport') . '</strong></span></label>';
            echo $listActions($switchActions);
        } else {
            echo "<input type='hidden' name='switch_level' value='0'>";
        }
        if ($changedNodes) {
            echo '<table class="table table-sm mt-2"><thead><tr><th></th><th>' . __s('Node', 'nediimport') . '</th><th>MAC</th><th>IP</th><th>'
                . __s('Will be connected to', 'nediimport') . '</th><th>' . __s('Changes', 'nediimport') . '</th></tr></thead><tbody>';
            foreach ($changedNodes as $n) {
                echo "<tr><td><input class='form-check-input' type='checkbox' name='nodes[]' value='" . $h($n['mac']) . "' checked></td>"
                    . '<td>' . $h($n['name']) . '</td><td><code>' . $h($n['mac']) . '</code></td><td>' . $h($n['ip']) . '</td>'
                    . '<td>' . $h($n['target']) . '</td><td>' . $listActions($nodeActions[$n['mac']]) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        if ($unchanged > 0) {
            echo '<p class="text-muted">' . sprintf(__s('%d nodes already up to date', 'nediimport'), $unchanged) . '</p>';
        }
        echo "<button class='btn btn-primary' type='submit'>" . sprintf(__s('Apply the selection for %s', 'nediimport'), $h($switch)) . '</button>';
        Html::closeForm();

        $card($h($switch), ob_get_clean());
    }

    if ($unchangedSwitches === count($switchNames)) {
        $card(__s('Nothing to do', 'nediimport'), '<p>' . __s('GLPI is already up to date with NeDi.', 'nediimport') . '</p>');
    } elseif ($unchangedSwitches > 0) {
        echo "<div class='container-fluid text-muted mb-3'>" . sprintf(__s('%d switches already up to date', 'nediimport'), $unchangedSwitches) . '</div>';
    }
} elseif (isset($_POST['apply_switch'])) {
    // -------------------------------------------------------------- apply a selection
    $switch = (string) $_POST['apply_switch'];
    $result = (new Importer(null, [
        'switches'     => [$switch],
        'switch_level' => !empty($_POST['switch_level']),
        'nodes'        => array_map('strval', (array) ($_POST['nodes'] ?? [])),
    ]))->run();

    $card(
        sprintf(__s('Imported: %s', 'nediimport'), $h($switch)),
        $statsTable($result) . $messages($result) . $previewButton()
    );
} elseif (isset($_POST['run'])) {
    // ---------------------------------------------------------------- full import
    $result = (new Importer())->run();
    $card(__s('Import data from NeDi', 'nediimport'), $statsTable($result) . $messages($result));
} else {
    ob_start();
    echo '<p>' . __s('Read switches, ports, VLANs and attached nodes from NeDi and update GLPI. Nothing is deleted. This can take a while.', 'nediimport') . '</p>';
    echo '<p>' . __s('Use the preview first: it shows what would change without writing anything, and lets you apply one switch at a time.', 'nediimport') . '</p>';
    echo "<form method='post' action='import.php' class='d-flex gap-2'>"
        . "<button class='btn btn-primary' name='preview' value='1'>" . __s('Preview', 'nediimport') . '</button>'
        . "<button class='btn btn-outline-secondary' name='run' value='1'>" . __s('Import everything now', 'nediimport') . '</button>';
    Html::closeForm();
    $card(__s('Import data from NeDi', 'nediimport'), ob_get_clean());
}

Html::footer();
