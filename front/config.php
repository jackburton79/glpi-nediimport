<?php
/*
*	$Id: config.php 3 2012-01-24 11:05:35Z seincoray $
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


define('GLPI_ROOT', '../../..');

require '../../../inc/includes.php';
require "../inc/settings.class.php";

commonHeader(__('Nedi Data Import'), $_SERVER['PHP_SELF'],"plugins","nediimport","optionname");

$PluginSettings=new PluginNediImportSettings();

if($_POST['action']){
	//save changes
	if($_POST['url']){
		$PluginSettings->Settings['url']=$_POST['url'];
	}
	if($_POST['user']){
		$PluginSettings->Settings['user']=$_POST['user'];
	}
	if($_POST['pass']){
		$PluginSettings->Settings['pass']=$_POST['pass'];
	}
	if($_POST['auto']){
		$PluginSettings->Settings['auto']="1";
	}
	else{
		$PluginSettings->Settings['auto']="0";
	}
	
	$PluginSettings->Save();
	echo "<div align='center' style='padding: 5px;'>".__('Settings were changed successfully!')."</div>";
}

//load informations

if($PluginSettings->Settings['auto']=="1"){
	$check=" checked='checked' ";
}
else{
	$check=" ";
}

echo "<form action='config.php' method='post'>";

echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";
echo "<tr><th colspan='2'>".__('Change Nedi Import settings')."</th></tr>";
echo "<tr class='tab_bg_1'><td>".__('URL to Nedi')."</td><td><input type='text' name='url' value='{$PluginSettings->Settings['url']}' /></td></tr>";
echo "<tr class='tab_bg_1'><td>".__('Username for Nedi')."</td><td><input type='text' name='user' value='{$PluginSettings->Settings['user']}' /></td></tr>";
echo "<tr class='tab_bg_1'><td>".__('Password for Nedi')."</td><td><input type='password' name='pass' value='{$PluginSettings->Settings['pass']}' /></td></tr>";
echo "<tr class='tab_bg_1'><td>".__('Activate autoimport of switches')."</td><td><input type='checkbox' name='auto' $check /></td></tr>";
echo "<tr class='tab_bg_1'><td>&nbsp;</td><td><input type='submit' class='submit' name='action' value='".__('Submit')."' /></td></tr>";

echo "</table>";
echo "<a href='start.php'>{__('Back')}</a>";
echo "</div></form>";

commonFooter();

