<?php
/*
*	$Id: switch_conf.php 3 2012-01-24 11:05:35Z seincoray $
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


require '../../../inc/includes.php';
include("../inc/switch_list.class.php");

Html::header(__('Nedi Data Import', 'nediimport'), $_SERVER['PHP_SELF'],"plugins","nediimport","optionname");

$Connection=new PluginNediImportConnection();
if(!$Connection){
	die("Error connecting to Nedi: ".$Connection->err);
}
$List=new Switches($Connection->GetSwitches());
$List->Load();

if (isset($_POST['on']) || isset($_POST['off'])) {
	//save changes
	$List->Execute();
	
	echo "<div align='center' style='padding: 5px;'>".__('Changes were saved successfully!', 'nediimport')."</div>";
}

echo "<form action='switch_conf.php' method='post'>";
echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";
echo "<tr><th colspan='4'>".__('Configure Switch import', 'nediimport')."</th></tr>";
echo "<tr class='tab_bg_1'><td>".__('Select Switches which should be imported to GLPI', 'nediimport')."</td>";

$List->Draw();

echo "</tr>";
echo "</table>";
echo "<a href='start.php'>".__('Back', 'nediimport')."</a>";
echo "</div></form>";

Html::footer();
