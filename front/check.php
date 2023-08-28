<?php
/*
*	$Id: check.php 3 2012-01-24 11:05:35Z seincoray $
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
// Date:						2012-01-17
// ----------------------------------------------------------------------

require '../../../inc/includes.php';
include("../inc/connection.class.php");

html::Header(__('Nedi Data Import', 'Checking communication to Nedi'), $_SERVER['PHP_SELF'],"plugins","nediimport","optionname");

echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";
echo "<tr><th>".__('Nedi Data Import', 'Checking communication to Nedi')."</th></tr>";

$Con=new PluginNediImportConnection();

if($Con->Connect()){
	echo "<tr class='tab_bg_1'><td align='center'>".__('Connection to Nedi was successfull!')."<br /></td></tr>";
}
else{
	echo "<tr class='tab_bg_1'><td align='center'>".__('Error').": ".htmlentities($Con->err)."</td></tr>";
}

$Con->__destruct();

echo "<tr class='tab_bg_1'><td align='center'><a href='start.php'>".__('Back')."</a></td></tr>";

echo "</table></div>";

Html::footer();
?>
