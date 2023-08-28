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

/*Today composition of array:
[0] => vpc8061.psi.ch
[1] => 129.129.88.177
[2] => 080027abf5ae
[3] => 28.Sep 10 13:26
[4] => 22.Oct 10 15:01
[5] => Twmsa204 Fa0/1
[6] => 22.Oct 10 15:01
[7] => 606
*/

class Computers{
	var $list;
	
	function __construct(){
		$this->Reset();
	}
	
	function Reset(){
		$this->NumImported=0;
		$this->list=array();
		$this->VirtualSwitches=array();
	}
	
	function AddList($l){
		$Index=sizeof($this->list);
		for($x=0;$x<sizeof($l);$x++,$Index++){
			$this->list[$Index]=$l[$x];
		}
	}
	
	function CheckPorts(){
		global $DB;
		
		for($x=0;$x<sizeof($this->list);$x++){
			$Name=explode(".",$this->list[$x][HO]);
			$Name=$Name[0];
			
			$query="SELECT * FROM glpi_computers WHERE name='$Name'";
			$response=$DB->query($query) or die("error select from glpi_computers ". $DB->error());
			$Result=$DB->fetchArray($response);
			if(empty($Result)){
				$query="INSERT INTO glpi_computers (name) VALUES ('$Name');";
				$DB->query($query) or die("error insert into glpi_computers". $DB->error());
				$query="SELECT * FROM glpi_computers WHERE name='$Name'";
				$response=$DB->query($query) or die("error select from glpi_computers ". $DB->error());
				$Result=$DB->fetchArray($response);
			}
			$ComputerID=$Result['id'];
			$this->list[$x][ID]=$ComputerID;
			
			$query="SELECT * FROM glpi_networkports WHERE items_id=$ComputerID AND itemtype='Computer'";
			$response=$DB->query($query) or die("error select from glpi_networkports". $DB->error());
			$Result=$DB->fetchArray($response);
			if(empty($Result)){
				$query="INSERT INTO glpi_networkports (items_id,itemtype,name) VALUES ($ComputerID,'Computer','eth0')";
				$DB->query($query) or die("error insert into glpi_networkports". $DB->error());
				$query="SELECT * FROM glpi_networkports WHERE items_id=$ComputerID AND itemtype='Computer'";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$Result=$DB->fetchArray($response);
			}
			$host=explode(".",$this->list[$x][HO]);
			$this->list[$x][HO]=$host[0];
			$NetworkportID=$Result['id'];
			$this->list[$x][NP]=$NetworkportID;
			$MacAddress=str_split($this->list[$x][MA],2);
			$Mac=implode(":",$MacAddress);
			$this->list[$x][MA]=$Mac;
			$IP=$this->list[$x][IP];
			
			$Comment="IF Update: ".$this->list[$x][IU]."\nFirst seen: ".$this->list[$x][FI]."\nLast seen: ".$this->list[$x][LA];
			$query="UPDATE glpi_computers SET comment='$Comment' WHERE id=$ComputerID";
			$DB->query($query) or die("error update glpi_computers". $DB->error());
			
			$query="UPDATE glpi_networkports SET ip='$IP', mac='$Mac' WHERE id=$NetworkportID";
			$DB->query($query) or die("error update glpi_networkports ". $DB->error());
		}
	}
	
	function CheckComputers($VirtualSwitches,$Switches){
		global $DB;
		
		for($x=0;$x<sizeof($this->list);$x++){
			$Split=explode(" ",$this->list[$x][CO]);
			if($VirtualSwitches->IsInVirtualSwitch($this->list[$x][HO])!=-1){
				$PortID=$VirtualSwitches->GetFreePort($Split[0]."-".$Split[1]."-VirtualSwitch");
				if($PortID==0){
					die("Error finding a free port for {$this->list[$x][HO]}!");
				}
				
				$RealSwitchInfo=$Switches->GetSwitchInfo($Split[0]);
				$RealSwitchID=$RealSwitchInfo['id'];
				$RealPortInfo=$Switches->GetPortInfo($RealSwitchID,$Split[1],1);
				$RealPortID=$RealPortInfo['id'];
				if($RealPortID==0){
					die("Error finding the correct port!");
				}
				$query="SELECT * FROM glpi_networkports WHERE id=$RealPortID";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$Result=$DB->fetchArray($response);
				if($Result['netpoints_id']!=0){
					$query="SELECT * FROM glpi_netpoints WHERE id={$Result['netpoints_id']}";
					$response=$DB->query($query) or die("error select from glpi_netpoints ". $DB->error());
					$Result=$DB->fetchArray($response);
					$Location=$Result['locations_id'];
				}
				else{
					$query="SELECT * FROM glpi_networkequipments WHERE id=$RealSwitchID";
					$response=$DB->query($query) or die("error select from glpi_networkequipments ". $DB->error());
					$Result=$DB->fetchArray($response);
					$Location=$Result['locations_id'];
				}
				$query="UPDATE glpi_computers SET locations_id='$Location' WHERE id={$this->list[$x][ID]}";
				$DB->query($query) or die("error update glpi_computers ". $DB->error());
			}
			else{
				$RealSwitchInfo=$Switches->GetSwitchInfo($Split[0]);
				$RealSwitchID=$RealSwitchInfo['id'];
				$PortInfo=$Switches->GetPortInfo($RealSwitchID,$Split[1],1);
				$PortID=$PortInfo['id'];
				if($PortID==0){
					die("Error finding the correct port!");
				}
				$query="SELECT * FROM glpi_networkports WHERE id=$PortID";
				$response=$DB->query($query) or die("error select from glpi_networkports ". $DB->error());
				$Result=$DB->fetchArray($response);
				if($Result['netpoints_id']!=0){
					$query="SELECT * FROM glpi_netpoints WHERE id={$Result['netpoints_id']}";
					$response=$DB->query($query) or die("error select from glpi_netpoints ". $DB->error());
					$Result=$DB->fetchArray($response);
					$Location=$Result['locations_id'];
				}
				else{
					$query="SELECT * FROM glpi_networkequipments WHERE id=$RealSwitchID";
					$response=$DB->query($query) or die("error select from glpi_networkequipments ". $DB->error());
					$Result=$DB->fetchArray($response);
					$Location=$Result['locations_id'];
				}
				$query="UPDATE glpi_computers SET locations_id='$Location' WHERE id={$this->list[$x][ID]}";
				$DB->query($query) or die("error update glpi_computers ". $DB->error());
			}
			$query="INSERT INTO glpi_networkports_networkports (networkports_id_1,networkports_id_2) VALUES ('{$this->list[$x][NP]}','$PortID')";
			$DB->query($query) or die("error insert into glpi_networkports_networkports ". $DB->error());
		}
	}
}

?>
