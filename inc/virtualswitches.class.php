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


class VirtualSwitches{
	var $list;
	var $info; //information about connection to real switch
	
	function __construct(){
		$this->Reset();
	}
	
	function Reset(){
		$this->list=array();
	}
	
	function Search($l){
		for($x=0;$x<sizeof($l);$x++){
			for($y=$x+1;$y<sizeof($l);$y++){
				if($l[$x][CO]==$l[$y][CO]){
					//virtual switch detected
					$Split=explode(" ",$l[$x][CO]);
					$i=$this->VirtualSwitchExists($Split[0]."-".$Split[1]."-VirtualSwitch");
					if($i==-1){
						//create new virtual switch
						$i=$this->CreateNewVirtualSwitch($l[$x][CO]);
					}
					$this->AddToVirtualSwitch($l[$x][HO],$i);
					$this->AddToVirtualSwitch($l[$y][HO],$i);
				}
			}
		}
	}
	
	function Create($Switches){
		global $DB;
		
		for($x=0;$x<sizeof($this->list);$x++){
			$time=date("Y.m.d H:i:s");
			$RealSwitchName=explode("-",$this->list[$x][1]);
			$RealSwitchPort=$RealSwitchName[1];
			$RealSwitchName=$RealSwitchName[0];
			$RealSwitchInfo=$Switches->GetSwitchInfo($RealSwitchName);
			$RealSwitchLocation=$RealSwitchInfo['locations_id'];
			$query="INSERT INTO glpi_networkequipments (name,date_mod,locations_id,networkequipmenttypes_id) VALUES ('{$this->list[$x][1]}','$time',$RealSwitchLocation,1)";
			$DB->query($query) or die("error insert into glpi_networkequipments ". $DB->error());
			$VirtualSwitchInfo=$Switches->GetSwitchInfo($this->list[$x][1]);
			$VirtualSwitchID=$VirtualSwitchInfo['id'];
			$RealSwitchID=$RealSwitchInfo['id'];
			
			$this->info[$x][0]=$RealSwitchID;
			$this->info[$x][1]=$VirtualSwitchID;
			
			$RealSwitchPortInfo=$Switches->GetPortInfo($RealSwitchID,$RealSwitchPort,1);
			$RealSwitchPortID=$RealSwitchPortInfo['id'];
			
			$query="SELECT * FROM glpi_networkports_vlans WHERE networkports_id=$RealSwitchPortID";
			$response=$DB->query($query) or die("error select from glpi_networkports_vlans ". $DB->error());
			$Result=$DB->fetch_array($response);
			$VlanID=$Result['vlans_id'];
			
			for($y=1;$y<49;$y++){
				$query="INSERT INTO glpi_networkports (items_id,itemtype,logical_number,name) VALUES ($VirtualSwitchID,'NetworkEquipment',$y,'port$y')";
				$DB->query($query) or die("error insert into glpi_networkports ". $DB->error());
				$query="SELECT * FROM glpi_networkports WHERE items_id=$VirtualSwitchID AND itemtype='NetworkEquipment' AND logical_number=$y";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$Result=$DB->fetch_array($response);
				$PortID=$Result['id'];
				$query="INSERT INTO glpi_networkports_vlans (vlans_id,networkports_id) VALUES ('$VlanID','$PortID')";
				$DB->query($query) or die("error insert into glpi_networkports_vlans ". $DB->error());
			}
			
			$VirtualSwitchPortInfo=$Switches->GetPortInfo($VirtualSwitchID,'port48',1);
			$VirtualSwitchPortID=$VirtualSwitchPortInfo['id'];
			
			$query="INSERT INTO glpi_networkports_networkports (networkports_id_1,networkports_id_2) VALUES ('$VirtualSwitchPortID','$RealSwitchPortID')";
			$DB->query($query) or die("error insert into glpi_networkports_networkports ". $DB->error());
		}
	}
	
	function AddToVirtualSwitch($host,$id){
		for($x=2;$x<sizeof($this->list[$id]);$x++){
			if($host==$this->list[$id][$x]){
				return;
			}
		}
		$this->list[$id][sizeof($this->list[$id])]=$host;
	}
	
	function IsInVirtualSwitch($host){
		for($x=0;$x<sizeof($this->list);$x++){
			for($y=2;$y<sizeof($this->list[$x]);$y++){
				if($host==$this->list[$x][$y]){
					return $x;
				}
			}
		}
		return -1;
	}
	
	function GetFreePort($Name){
		global $DB;
		
		for($x=0;$x<sizeof($this->list);$x++){
			if($this->list[$x][1]==$Name){
				$query="SELECT glpi_networkports.* FROM glpi_networkports WHERE glpi_networkports.items_id={$this->info[$x][1]} AND glpi_networkports.id NOT IN (SELECT glpi_networkports_networkports.networkports_id_2 FROM glpi_networkports_networkports WHERE 1 ORDER BY id) ORDER BY id LIMIT 1";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$line=$DB->fetch_array($response);
				return $line['id'];
			}
		}
		return 0;
	}
	
	function CreateNewVirtualSwitch($port){
		$index=sizeof($this->list);
		$Split=explode(" ",$port);
		$RealSwitch=$Split[0];
		$PortName=$Split[1];
		$VirtualSwitchName=$RealSwitch."-".$PortName."-VirtualSwitch";
		$this->list[$index][0]=$PortName;
		$this->list[$index][1]=$VirtualSwitchName;
		return $index;
	}
	
	function VirtualSwitchExists($port){
		for($x=0;$x<sizeof($this->list);$x++){
			if($this->list[$x][1]==$port){
				return $x;
			}
		}
		return -1;
	}
}

?>