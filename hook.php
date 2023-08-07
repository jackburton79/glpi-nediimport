<?php
/*
*	$Id: hook.php 3 2012-01-24 11:05:35Z seincoray $
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


function plugin_nediimport_install(){
	global $DB;
	
	if (!$DB->tableExists("glpi_plugin_nediimport_settings")){
		$query = "CREATE TABLE `glpi_plugin_nediimport_settings` (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`spec` VARCHAR( 20 ),
					`value` VARCHAR( 200 )
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
		$DB->query($query) or die("error creating glpi_plugin_nediimport_settings ". $DB->error());
		$query="INSERT INTO glpi_plugin_nediimport_settings (id,spec,value) VALUES (NULL,'url',''),(NULL,'user',''),(NULL,'pass',''),(NULL,'auto','0');";
		$DB->query($query) or die("error filling glpi_plugin_nediimport_settings ". $DB->error());
	}
	
	if (!$DB->tableExists("glpi_plugin_nediimport_switch_conf")){
		$query = "CREATE TABLE `glpi_plugin_nediimport_switch_conf` (
					`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
					`name` VARCHAR( 20 ),
					`conf` INT
					) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
		$DB->query($query) or die("error creating glpi_plugin_nediimport_switch_conf ". $DB->error());
	}
	
	if(!$DB->tableExists("glpi_plugin_nediimport_stat")){
		$query="CREATE TABLE `glpi`.`glpi_plugin_nediimport_stat` (
				`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
				`name` VARCHAR( 200 ) NOT NULL ,
				`value` INT NOT NULL
				) ENGINE = MYISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci ";
		$DB->query($query) or die("error creating glpi_plugin_nediimport_switch_stat ". $DB->error());
	}
	
	CronTask::Register('PluginNediimportCron', 'nediimport', 300, array());
	return true;
}

function plugin_nediimport_uninstall(){
	global $DB;
	
	if($DB->tableExists("glpi_plugin_nediimport_settings")){
		$query="DROP TABLE `glpi_plugin_nediimport_settings`";
		$DB->query($query) or die("error drop glpi_plugin_nediimport_settings ". $DB->error());
	}
	
	if($DB->tableExists("glpi_plugin_nediimport_switch_conf")){
		$query="DROP TABLE `glpi_plugin_nediimport_switch_conf`";
		$DB->query($query) or die("error drop glpi_plugin_nediimport_switch_conf ". $DB->error());
	}
	
	if($DB->tableExists("glpi_plugin_nediimport_switch_stat")){
		$query="DROP TABLE `glpi_plugin_nediimport_switch_stat`";
		$DB->query($query) or die("error drop glpi_plugin_nediimport_switch_stat ". $DB->error());
	}
	return true;
}

?>
