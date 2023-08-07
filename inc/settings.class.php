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
// Date:						2012-01-17
// ----------------------------------------------------------------------


class PluginNediImportSettings{
	
	var $Settings=array();
	
	function __construct(){
		$this->Settings['url']="";
		$this->Settings['user']="";
		$this->Settings['pass']="";
		$this->Settings['auto']="0";
		
		$this->Load();
	}
	
	function Load(){
		global $DB;
		
		$query="SELECT * FROM glpi_plugin_nediimport_settings ORDER BY id";
		$response=$DB->query($query) or die("error reading glpi_plugin_nediimport_settings ". $DB->error());
		while($zeile=$DB->fetch_array($response)){
			$this->Settings["{$zeile['spec']}"]=$zeile['value'];
		}
	}
	
	function Save(){
		global $DB;
		
		foreach($this->Settings as $k => $Val){
			$query="UPDATE glpi_plugin_nediimport_settings SET value='$Val' WHERE spec='$k'";
			$DB->query($query) or die("error writing glpi_plugin_nediimport_settings ". $DB->error());
		}
	}
}