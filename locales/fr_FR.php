<?php
/*
*	$Id: fr_FR.php 3 2012-01-24 11:05:35Z seincoray $
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

//TODO: translate french locale file

//strings for main page
$LANG['plugin_nediimport']["title"]="Nedi Data Import";
$LANG['plugin_nediimport']['mainmenu']="Nedi Import Actions";
$LANG['plugin_nediimport']['import_action']="Import data from Nedi";
$LANG['plugin_nediimport']['config_action']="Change Nedi import settings";
$LANG['plugin_nediimport']['check_action']="Check communication with Nedi";
$LANG['plugin_nediimport']['switch_conf_action']="Configure Switch import";

//strings for settings page
$LANG['plugin_nediimport']['settings_title']="Change Nedi Import settings";
$LANG['plugin_nediimport']['config_url']="URL to Nedi";
$LANG['plugin_nediimport']['config_user']="Username for Nedi";
$LANG['plugin_nediimport']['config_pass']="Password for Nedi";
$LANG['plugin_nediimport']['config_success']="Settings were changed successfully!";
$LANG['plugin_nediimport']['config_autoimport']="Activate autoimport of switches";

//strings for checking page
$LANG['plugin_nediimport']['check_title']="Checking communication to Nedi";
$LANG['plugin_nediimport']['check_success']="Connection to Nedi was successfull!";
$LANG['plugin_nediimport']['check_con_fail']="Connection failed!";
$LANG['plugin_nediimport']['check_http_fail']="Login failed (HTTP_ERROR)!";
$LANG['plugin_nediimport']['check_login_fail']="Incorrect Login!";

//strings for switch conf page
$LANG['plugin_nediimport']['switch_conf_title']="Configure Switch import";
$LANG['plugin_nediimport']['switch_conf_select']="Select Switches which should be imported to GLPI";
$LANG['plugin_nediimport']['switch_conf_success']="Changes were saved successfully!";
$LANG['plugin_nediimport']['switch_conf_sync_off']="Switches which will not be synchronized";
$LANG['plugin_nediimport']['switch_conf_sync_on']="Switches which will be synchronized";

//strings for import page
$LANG['plugin_nediimport']['import_title']="Importing...  Please Wait...";
$LANG['plugin_nediimport']['import_http_error']="Import could not finish correctly!";
$LANG['plugin_nediimport']['import_xmlhttp_error']="Your browser does not support XMLHTTP.";
$LANG['plugin_nediimport']['import_stat_switches']="Number of imported switches";
$LANG['plugin_nediimport']['import_stat_ports']="Number of affected ports";

//strings for import_done page
$LANG['plugin_nediimport']['import_done_title']="Import done!";
$LANG['plugin_nediimport']['import_done_vlan_check']="Number of ports checked for VLAN";
$LANG['plugin_nediimport']['import_done_total_computers']="Number of imported computers";

//often used strings
$LANG['plugin_nediimport']['util_error']="Error";
$LANG['plugin_nediimport']['util_back']="Back";
$LANG['plugin_nediimport']['util_submit']="Submit";
$LANG['plugin_nediimport']['util_cron_desc']="Automatic synchronisation from Nedi for selected switches";

?>