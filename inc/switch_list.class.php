<?php
/*
*	$Id: $
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


include (GLPI_ROOT . "/plugins/nediimport/inc/connection.class.php");
include (GLPI_ROOT . "/plugins/nediimport/inc/switch_conf.class.php");

class Switches{
	var $list;
	var $conf;
	
	function __construct($l){
		$this->list=$l;
	}
	
	function Load(){
		$this->conf=new PluginNediImportSwitchConf();
		for($x=0;$x<sizeof($this->list);$x++){
			$this->conf->Insert($this->list[$x]);
		}
	}
	
	function CheckPorts($Connection){
		global $DB;
		
		$Num=0;
		foreach($this->list as $Switch){
			$SwitchInfo=$this->GetSwitchInfo($Switch);
			$SwitchID=$SwitchInfo['id'];
				
			$List=$Connection->LoadComputers($Switch);
			for($x=0;$x<sizeof($List);$x++){
				$Split=explode(" ",$List[$x][CO]);
				$Split=$Split[1];
				$query="SELECT * FROM glpi_networkports WHERE items_id=$SwitchID AND name='$Split' AND itemtype='NetworkEquipment'";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$Result=$DB->fetchArray($response);
				if(empty($Result)){
					$query="INSERT INTO glpi_networkports (items_id,itemtype,name) VALUES ('$SwitchID','NetworkEquipment','$Split')";
					$DB->query($query) or die("error insert into glpi_networkports ". $DB->error());
				}
				$Num++;
			}
		}
		return $Num;
	}
	
	function CheckNewSwitches(){
		global $DB;
	
		foreach($this->list as $Switch){
			$query="SELECT name FROM glpi_networkequipments WHERE name='$Switch'";
			$response=$DB->query($query) or die("error select from glpi_networkequipments ". $DB->error());
			$Result=$DB->fetchArray($response);
			if(empty($Result)){
				$this->CreateNewSwitch($Switch);
			}
		}
	}
	
	function CreateNewSwitch($Switch){
		global $DB;
	
		$time=date("Y.m.d H:i:s");
		$query="INSERT INTO glpi_networkequipments (id,name,networkequipmenttypes_id,date_mod)VALUES (NULL,'$Switch','1','$time')";
		$response=$DB->query($query) or die("error insert into glpi_networkequipments ". $DB->error());
	}
	
	function GetSwitchInfo($Name){
		global $DB;
		
		$query="SELECT * FROM glpi_networkequipments WHERE  name='$Name' limit 1";
		$response=$DB->query($query) or die("error select from glpi_networkequipments ". $DB->error());
		$Result=$DB->fetchArray($response);
		return $Result;
	}
	
	function CheckVlans($Connection){
		global $DB;
		
		$Settings=new PluginNediImportSettings();
		foreach($this->list as $Switch){
			$url=$Settings->Settings['url'].'/Devices-Interfaces.php?ina=device&opa=regexp&sta='.$Switch.'&cop=&inb=ifname&opb=regexp&stb=&col[]=pvid';
			$Vlans=$Connection->ReadSwitchVlans($url);
			
			//Get Switch ID
			$SwitchInfo=$this->GetSwitchInfo($Switch);
			$SwitchID=$SwitchInfo['id'];
			
			for($x=0;$x<sizeof($Vlans);$x++){
				$PortID=$this->GetPortInfo($SwitchID,$Vlans[$x][0],1);
				$PortID=$PortID['id'];
				
				if($PortID==0){
					continue;
				}
				
				if($Vlans[$x][1]==0){
					$Vlans[$x][1]="null";
				}
				$VlanID=$this->GetVlanID($Vlans[$x][1]);
				
				$query="INSERT INTO glpi_networkports_vlans (vlans_id,networkports_id) VALUES ('$VlanID','$PortID')";
				$DB->query($query) or die("error insert into glpi_networkports_vlans ". $DB->error());
			}
		}
	}
	
	function GetPortInfo($Switch,$Port,$Level){
		global $DB;
	
		//Prevent looping on errors
		if($Level>6){
			return 0;
		}
	
		//Get information about the port
		$query="SELECT * FROM glpi_networkports WHERE  items_id='$Switch' AND itemtype='NetworkEquipment' AND name='$Port'";
		$response=$DB->query($query) or die("error selecting id from glpi_networkports ". $DB->error());
		$line=$DB->fetchArray($response);
	
		//Test if the information is correct
		if(empty($line)){
			//Check if the problem is the compatibility with the old system and its
			//numerating method
			//MARK: Does not work for all Ports correctly - Disable feature in release
			$Number=explode("/", $Port);
			if((int)end($Number) < 10){
				if(sizeof($Number)<2){
					return 0;
				}
				$Portold=$Number[0];
				for($x=1;$x<sizeof($Number)-1;$x++){
					$Portold.="/".$Number[$x];
				}
				$Portold=$Portold."/0".end($Number);
			}
			else{
				//If it is a port which is not valid to be in the database
				return 0;
			}
	
			//Load inforamtion on port with changed name
			return $this->GetPortInfo($Switch,$Portold,$Level+1);
		}
		return $line;
	}
	
	function GetVlanID($Name){
		global $DB;
	
		//Prevents looping if some errors happen
		if($Level>6){
			return 0;
		}
	
		//Load info about the VLAN
		$query="SELECT * FROM glpi_vlans WHERE name='$Name'";
		$response=$DB->query($query) or die("error select from glpi_vlans ". $DB->error());
		$line=$DB->fetchArray($response);
	
		//Test if there is correct information
		if(empty($line)){
			//VLAN does not exist -> create new one
			$query="INSERT INTO glpi_vlans (name) VALUES ('$Name')";
			$DB->query($query) or die("error insert into glpi_vlans ". $DB->error());
	
			//Search for its ID again
			return $this->SearchVLAN($Name);
		}
		else{
			//Return the found ID
			return $line['id'];
		}
	}
	
	function Execute(){
		if($_POST['add']){
			for($x=0;$x<sizeof($_POST['off']);$x++){
				$this->conf->SetConf($this->list[$_POST['off'][$x]],1);
			}
		}
		else if($_POST['del']){
			for($x=0;$x<sizeof($_POST['on']);$x++){
				$this->conf->SetConf($this->list[$_POST['on'][$x]],0);
			}
		}
		$this->conf->Save();
	}
	
	function Draw(){
	
		echo "<td align='center'>".__('Switches which will not be synchronized', 'nediimport').":<br /><br /><input type='hidden' name='num' value='".sizeof($this->list)."' />";
		echo "<select name='off[]' size='".sizeof($this->list)."' style='width: 100px' multiple>";
		for($x=0;$x<sizeof($this->list);$x++){
			if($this->conf->GetConf($this->list[$x])==0){
				//switch is not selected
				echo "<option value='$x'>{$this->list[$x]}</option>";
			}
		}
		echo "</select></td>";
			echo "<td align='center'>";
			echo "<input type='submit' class='submit' name='add' value='-->' /><br /><br />";
			echo "<input type='submit' class='submit' name='del' value='<--' />";
			echo "</td>";
			echo "<td align='center'>".__("Switches which will be synchronized", "nediimport").":<br /><br /><select name='on[]' size='".sizeof($this->list)."' style='width: 100px' multiple>";
			for($x=0;$x<sizeof($this->list);$x++){
				if($this->conf->GetConf($this->list[$x])==1){
					//switch is selected
					echo "<option value='$x'>{$this->list[$x]}</option>";
				}
			}
			echo "</select></td>";
	}
}

?>
