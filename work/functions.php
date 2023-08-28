<?php
/*
*	$Id: functions.php 3 2012-01-24 11:05:35Z seincoray $
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
// Date:						2012-01-23
// ----------------------------------------------------------------------


class Stat{
	function __construct($Del){
		global $DB;
		
		if($Del){
			$query="TRUNCATE TABLE glpi_plugin_nediimport_stat";
			$DB->query($query) or die("error truncate glpi_plugin_nediimport_switch_stat ". $DB->error());
		}
	}
	
	function WriteStat($Stat,$Num){
		global $DB;
	
		$query="INSERT INTO glpi_plugin_nediimport_stat (id,name,value) VALUES (NULL,'$Stat','$Num');";
		$DB->query($query) or die("error insert glpi_plugin_nediimport_switch_stat ". $DB->error());
	}
	
	function PrintStat(){
		global $DB;
		
		$query="SELECT * FROM glpi_plugin_nediimport_stat ORDER BY id";
		$response=$DB->query($query) or die("error select glpi_plugin_nediimport_switch_stat ". $DB->error());
		
		while($line=$DB->fetchArray($response)){
			echo "<tr class='tab_bg_1'><td>".$line['name']."</td><td>".$line['value']."</td></tr>";
		}
	}
}

function DeleteOldValues(){
	global $DB;
	
	$query="DELETE glpi_networkports.* FROM glpi_networkports,glpi_networkequipments WHERE glpi_networkequipments.id=glpi_networkports.items_id AND glpi_networkports.itemtype='NetworkEquipment' and glpi_networkequipments.name like '%VirtualSwitch%'";
	$DB->query($query) or die("error deleting from glpi_networkports ". $DB->error());
	
	$query='DELETE FROM glpi_networkequipments WHERE name like \'%VirtualSwitch%\'';
	$DB->query($query) or die("error deleting from glpi_networkequipments". $DB->error());
	
	$query='DELETE FROM glpi_networkports_networkports';
	$DB->query($query) or die("error deleting from glpi_networkports_networkports". $DB->error());
	
	$query="DELETE FROM glpi_networkports_vlans";
	$DB->query($query) or die("error deleting from glpi_networkports_vlans". $DB->error());
}

