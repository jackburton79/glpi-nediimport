<?php
/*
*	$Id: de_DE.php 3 2012-01-24 11:05:35Z seincoray $
*
* 	LICENSE
*
* 	This file is part of Nedi Import.
*   Copyright (C) 2012  Sein Coray
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   (at your option) any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
*/
// ----------------------------------------------------------------------
// Nedi Import  - a plugin for GLPI
// Copyright 2012 by Sein Coray
// http://sourceforge.net/p/nediimport
// ----------------------------------------------------------------------
// Original Author of file: 	Sein Coray
// Date:						2012-01-24
// ----------------------------------------------------------------------

//TODO: update german local file

//strings for main page
$LANG['plugin_nediimport']["title"]="Nedi Data Import";
$LANG['plugin_nediimport']['mainmenu']="Nedi Import Aktionen";
$LANG['plugin_nediimport']['import_action']="Daten von Nedi importieren";
$LANG['plugin_nediimport']['config_action']="Importeinstellungen von Nedi &auml;ndern";
$LANG['plugin_nediimport']['check_action']="Verbindung zu Nedi testen";
$LANG['plugin_nediimport']['switch_conf_action']="Importeinstellungen f&uuml;r Switches";

//strings for settings page
$LANG['plugin_nediimport']['settings_title']="Importeinstellungen von Nedi";
$LANG['plugin_nediimport']['config_url']="URL zu Nedi";
$LANG['plugin_nediimport']['config_user']="Username f&uuml;r Nedi";
$LANG['plugin_nediimport']['config_pass']="Password f&uuml;r Nedi";
$LANG['plugin_nediimport']['config_success']="Einstellungen wurden erfolgreich gespeichert!";
$LANG['plugin_nediimport']['config_autoimport']="Automatische Synchronisation von Switches";

//strings for checking page
$LANG['plugin_nediimport']['check_title']="Verbindung zu Nedi testen";
$LANG['plugin_nediimport']['check_success']="Verbindung konnte erfolgreich hergestellt werden!";
$LANG['plugin_nediimport']['check_con_fail']="Verbindung konnte nicht hergestellt werden!";
$LANG['plugin_nediimport']['check_http_fail']="Login war nicht erfolgreich (HTTP_ERROR)!";
$LANG['plugin_nediimport']['check_login_fail']="Falsche Login-Daten!";

//strings for switch conf page
$LANG['plugin_nediimport']['switch_conf_title']="Importeinstellungen f&uuml;r Switches";
$LANG['plugin_nediimport']['switch_conf_select']="W&auml;hlen Sie Switches, die mit GLPI synchronisiert werden sollen";
$LANG['plugin_nediimport']['switch_conf_success']="Einstellungen wurden erfolgreich gespeichert!";
$LANG['plugin_nediimport']['switch_conf_sync_off']="Switches, die nicht synchronisiert werden";
$LANG['plugin_nediimport']['switch_conf_sync_on']="Switches, die synchronisiert werden";

//strings for import page
$LANG['plugin_nediimport']['import_title']="Importieren...  Bitte warten...";
$LANG['plugin_nediimport']['import_http_error']="Importieren konnte nicht korrekt abgeschlossen werden!";
$LANG['plugin_nediimport']['import_xmlhttp_error']="Ihr Browser unterst&uuml;tzt XMLHTTP nicht.";

//strings for import_done page
$LANG['plugin_nediimport']['import_done_title']="Importieren beendet!";
$LANG['plugin_nediimport']['import_done_vlan_check']="Anzahl Ports, die auf ihr VLAN gepr&uuml;ft wurden";
$LANG['plugin_nediimport']['import_done_total_computers']="Anzahl importierter und synchronisierter Computer";

//often used strings
$LANG['plugin_nediimport']['util_error']="Fehler";
$LANG['plugin_nediimport']['util_back']="Zur&uuml;ck";
$LANG['plugin_nediimport']['util_submit']="&Uuml;bernehmen";
$LANG['plugin_nediimport']['util_cron_desc']="Automatische Synchronisation von Nedi f&uuml;r ausgew&auml;hlte Switches";

?>