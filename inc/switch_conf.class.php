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


class PluginNediImportSwitchConf {
	var $conf;
	
	function __construct(){
		//nothing
		$this->Load();
	}
	
	function Load(){
		global $DB;
		
		$conf=array();
		
		$query="SELECT * FROM glpi_plugin_nediimport_switch_conf ORDER BY id";
		$response=$DB->query($query) or die("error reading glpi_plugin_nediimport_switch_conf ". $DB->error());
		$index=0;
		while($line=$DB->fetch_array($response)){
			$this->conf[$index]['name']=$line['name'];
			$this->conf[$index]['conf']=$line['conf'];
			$index++;
		}
	}
	
	function Save(){
		global $DB;
		
		for($x=0;$x<sizeof($this->conf);$x++){
			$query="UPDATE glpi_plugin_nediimport_switch_conf SET conf=".$this->conf[$x]['conf']." WHERE name='".$this->conf[$x]['name']."';";
			$response=$DB->query($query) or die("error updating glpi_plugin_nediimport_switch_conf ". $DB->error());
		}
	}
	
	function Check($Switch){
		for($x=0;$x<sizeof($this->conf);$x++){
			if($this->conf[$x]['name']==$Switch){
				return true;
			}
		}
		return false;
	}
	
	function Insert($Switch){
		global $DB;
		
		if($this->Check($Switch)){
			return;
		}
		$index=sizeof($this->conf);
		$this->conf[$index]['name']=$Switch;
		$this->conf[$index]['conf']=0;
		$query="INSERT INTO glpi_plugin_nediimport_switch_conf (id,conf,name) VALUES (NULL,'".$this->conf[$index]['conf']."','".$this->conf[$index]['name']."');";
		$response=$DB->query($query) or die("error inserting glpi_plugin_nediimport_switch_conf ". $DB->error());
		$this->Load();
	}
	
	function GetConf($Switch){
		for($x=0;$x<sizeof($this->conf);$x++){
			if($this->conf[$x]['name']==$Switch){
				return $this->conf[$x]['conf'];
			}
		}
		return false;
	}
	
	function SetConf($Switch,$Value){
		for($x=0;$x<sizeof($this->conf);$x++){
			if($this->conf[$x]['name']==$Switch){
				$this->conf[$x]['conf']=$Value;
				return;
			}
		}
	}
}
