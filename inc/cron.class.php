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


class PluginNediimportCron{
	
	static function cronInfo($name) {
		switch ($name) {
			case 'nediimport' :
				return array('description' => __('Automatic synchronisation from Nedi for selected switches', 'nediimport'),
	                         'parameter'   => "params");
		}
		return array();
	}
	
	static function cronNediimport() {
		global $DB,$Switches;
		
		$Cron='plugins/nediimport/';
	
		include("../plugins/nediimport/work/start.php");
	
		return 1;
	}
}

?>
