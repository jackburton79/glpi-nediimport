<?php

/**
 * NeDi Import - a plugin for GLPI
 * Licensed under the GNU GPL v3 or later.
 */

use GlpiPlugin\Nediimport\Config;
use GlpiPlugin\Nediimport\Menu;
use GlpiPlugin\Nediimport\NediSource;

Session::checkRight('config', UPDATE);
Plugin::load('nediimport');

$message = '';
if (isset($_POST['save']) || isset($_POST['test'])) {
    $new = [
        'nedi_host'    => trim((string) $_POST['nedi_host']),
        'nedi_port'    => (string) max(1, (int) $_POST['nedi_port']),
        'nedi_db'      => trim((string) $_POST['nedi_db']),
        'nedi_user'    => trim((string) $_POST['nedi_user']),
        'auto_all'     => !empty($_POST['auto_all']) ? '1' : '0',
        'max_age_days' => (string) max(1, (int) $_POST['max_age_days']),
        'entities_id'  => (string) (int) $_POST['entities_id'],
    ];
    // An empty password field keeps the stored one
    if ((string) $_POST['nedi_pass'] !== '') {
        $new['nedi_pass'] = (string) $_POST['nedi_pass'];
    }
    Config::save($new);

    if (isset($_POST['test'])) {
        try {
            $devices = NediSource::fromConfig(Config::get())->devices();
            Session::addMessageAfterRedirect(sprintf(__('Connection to NeDi OK: %d devices found', 'nediimport'), count($devices)), false, INFO);
        } catch (Throwable $e) {
            Session::addMessageAfterRedirect(sprintf(__('Cannot connect to NeDi: %s', 'nediimport'), $e->getMessage()), false, ERROR);
        }
    } else {
        Session::addMessageAfterRedirect(__('Settings saved', 'nediimport'), false, INFO);
    }
    Html::back();
}

Html::header(__('NeDi import', 'nediimport'), $_SERVER['PHP_SELF'], 'tools', Menu::class);

$cfg = Config::get();
$h   = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES);

echo "<form method='post' action='config.php' class='container-fluid'><div class='card'>";
echo "<div class='card-header'><h3 class='card-title'>" . __s('NeDi connection and settings', 'nediimport') . '</h3></div>';
echo "<div class='card-body'><p class='text-muted'>"
    . __s('The plugin reads directly from the NeDi database. A read-only MySQL/MariaDB account is enough.', 'nediimport')
    . '</p>';

$row = static function (string $label, string $field) {
    echo "<div class='mb-3 row'><label class='col-sm-3 col-form-label'>$label</label><div class='col-sm-6'>$field</div></div>";
};
$row(__s('NeDi database host', 'nediimport'), "<input class='form-control' name='nedi_host' value='" . $h($cfg['nedi_host']) . "'>");
$row(__s('Port', 'nediimport'), "<input class='form-control' type='number' name='nedi_port' value='" . $h($cfg['nedi_port']) . "'>");
$row(__s('Database name', 'nediimport'), "<input class='form-control' name='nedi_db' value='" . $h($cfg['nedi_db']) . "'>");
$row(__s('User', 'nediimport'), "<input class='form-control' name='nedi_user' value='" . $h($cfg['nedi_user']) . "'>");
$row(__s('Password', 'nediimport'), "<input class='form-control' type='password' name='nedi_pass' autocomplete='new-password' placeholder='"
    . ($cfg['nedi_pass'] !== '' ? __s('(unchanged)', 'nediimport') : '') . "'>");
$row(__s('Import every device found in NeDi', 'nediimport'), "<input class='form-check-input' type='checkbox' name='auto_all' value='1'" . ($cfg['auto_all'] === '1' ? ' checked' : '') . '>');
$row(__s('Ignore nodes not seen for (days)', 'nediimport'), "<input class='form-control' type='number' min='1' name='max_age_days' value='" . $h($cfg['max_age_days']) . "'>");

ob_start();
Entity::dropdown(['name' => 'entities_id', 'value' => (int) $cfg['entities_id']]);
$row(__s('Entity for created items', 'nediimport'), ob_get_clean());

echo "</div><div class='card-footer'>";
echo "<button class='btn btn-primary me-2' name='save' value='1'>" . _sx('button', 'Save') . '</button>';
echo "<button class='btn btn-outline-secondary' name='test' value='1'>" . __s('Save and test connection', 'nediimport') . '</button>';
echo '</div></div>';
Html::closeForm();

Html::footer();
