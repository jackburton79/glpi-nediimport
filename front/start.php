<?php
/*
*	$Id: start.php 3 2012-01-24 11:05:35Z seincoray $
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


define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");
include("../inc/settings.class.php");

$Settings=new PluginNediImportSettings();

commonHeader($LANG['plugin_nediimport']['title'], $_SERVER['PHP_SELF'],"plugins","nediimport","optionname");

echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";
echo "<tr><th>".$LANG['plugin_nediimport']['mainmenu']."</th></tr>";
echo "<tr class='tab_bg_1'><td><a href='import.php'>".$LANG['plugin_nediimport']['import_action']."</a></td></tr>";
echo "<tr class='tab_bg_1'><td><a href='config.php'>".$LANG['plugin_nediimport']['config_action']."</a></td></tr>";
if($Settings->Settings['auto']=="0"){
	echo "<tr class='tab_bg_1'><td><a href='switch_conf.php'>".$LANG['plugin_nediimport']['switch_conf_action']."</a></td></tr>";
}
echo "<tr class='tab_bg_1'><td><a href='check.php'>".$LANG['plugin_nediimport']['check_action']."</a></td></tr>";

echo "</table>";
echo "</div>";

commonFooter();
?>