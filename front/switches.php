<?php

/**
 * NeDi Import - a plugin for GLPI
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Config;
use GlpiPlugin\Nediimport\Menu;
use GlpiPlugin\Nediimport\NediSource;
use GlpiPlugin\Nediimport\SwitchSelection;

Session::checkRight('config', UPDATE);

$error = '';
try {
    SwitchSelection::register(array_column(NediSource::fromConfig(Config::get())->devices(), 'name'));
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (isset($_POST['save'])) {
    SwitchSelection::save(array_map('strval', (array) ($_POST['synced'] ?? [])));
    Session::addMessageAfterRedirect(__('Settings saved', 'nediimport'), false, INFO);
    Html::back();
}

Html::header(__('NeDi import', 'nediimport'), $_SERVER['PHP_SELF'], 'tools', Menu::class);

echo "<form method='post' action='switches.php' class='container-fluid'><div class='card'>";
echo "<div class='card-header'><h3 class='card-title'>" . __s('Choose the switches to import', 'nediimport') . '</h3></div>';
echo "<div class='card-body'>";
if ($error !== '') {
    echo "<div class='alert alert-danger'>" . htmlspecialchars(sprintf(__('Cannot read from NeDi: %s', 'nediimport'), $error)) . '</div>';
}
foreach (SwitchSelection::all() as $name => $synced) {
    $n = htmlspecialchars($name, ENT_QUOTES);
    echo "<label class='form-check'><input class='form-check-input' type='checkbox' name='synced[]' value='$n'"
        . ($synced ? ' checked' : '') . "><span class='form-check-label'>$n</span></label>";
}
echo "</div><div class='card-footer'><button class='btn btn-primary' name='save' value='1'>" . _sx('button', 'Save') . '</button></div></div>';
Html::closeForm();

Html::footer();
