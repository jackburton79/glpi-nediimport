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

if(!isset($Cron)){
	define('GLPI_ROOT', '../../..');
	include (GLPI_ROOT . "/inc/includes.php");
}

//definition of order in computer info array from nedi
define("HO",0);
define("IP",1);
define("MA",2);
define("FI",3);
define("LA",4);
define("CO",5);
define("IU",6);
define("VL",7);

//definition for additional information related with glpi
define("ID",8); //ComputerID
define("NP",9); //NetworkportID

if(isset($Cron)){
	include("../{$Cron}inc/switch_list.class.php");
	include("../{$Cron}work/functions.php");
	include("../{$Cron}inc/computers.class.php");
	include("../{$Cron}inc/virtualswitches.class.php");
}
else{
	include("../inc/switch_list.class.php");
	include("../work/functions.php");
	include("../inc/computers.class.php");
	include("../inc/virtualswitches.class.php");
}

$Stat=new Stat(true);

$Connection=new PluginNediImportConnection();
if(!$Connection->Connect()){
	header("HTTP/1.0 202 Could not establish connection to Nedi");
	die($LANG['plugin_nediimport']['util_error'].": ".$Connection->err);
}

DeleteOldValues();

$Switches=new Switches($Connection->LoadSwitches());
$Computers=new Computers();
$VirtualSwitches=new VirtualSwitches();
$Stat->WriteStat($LANG['plugin_nediimport']['import_stat_switches'],sizeof($Switches->list));

$Switches->CheckNewSwitches();
$PortNum=$Switches->CheckPorts($Connection);
$Stat->WriteStat($LANG['plugin_nediimport']['import_stat_ports'], $PortNum);
$Switches->CheckVlans($Connection);

$UnknownNumber=1;
for($x=0;$x<sizeof($Switches->list);$x++){
	$Computers->AddList($Connection->LoadComputers($Switches->list[$x]));
}
$Computers->CheckPorts();

$VirtualSwitches->Search($Computers->list);
$VirtualSwitches->Create($Switches);

$Computers->CheckComputers($VirtualSwitches,$Switches);

$Stat->PrintStat();

echo "finished!";

?>