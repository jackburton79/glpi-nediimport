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


#define('GLPI_ROOT', '../../..');

require '../../../inc/includes.php';

include("../inc/settings.class.php");

$Settings=new PluginNediImportSettings();

Html::header(__('Nedi Data Import', 'nediimport'), $_SERVER["PHP_SELF"], 'tools', "pluginnediimportmenu", "nediimport");

echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";
echo "<tr><th>".__('Nedi Import Actions')."</th></tr>";
echo "<tr class='tab_bg_1'><td><a href='import.php'>".__('Import data from Nedi')."</a></td></tr>";
echo "<tr class='tab_bg_1'><td><a href='config.php'>".__('Change Nedi import settings')."</a></td></tr>";
if($Settings->Settings['auto']=="0"){
	echo "<tr class='tab_bg_1'><td><a href='switch_conf.php'>".__('Configure Switch import')."</a></td></tr>";
}
echo "<tr class='tab_bg_1'><td><a href='check.php'>".__('Check communication with Nedi')."</a></td></tr>";

echo "</table>";
echo "</div>";

Html::footer();

