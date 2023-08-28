<?php
/*
*	$Id: setup.php 3 2012-01-24 11:05:35Z seincoray $
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

use Glpi\Plugin\Hooks;

function plugin_init_nediimport()
{
	global $PLUGIN_HOOKS,$CFG_GLPI;
	
	Plugin::registerClass('PluginNediimportCron');
	#Plugin::registerClass(Config::class, ['addtabon' => 'Config']);

	if (Session::getLoginUserID()) {
		$PLUGIN_HOOKS['menu_toadd']['nediimport'] = [
			'tools' => 'PluginNediimportMenu',
		];
	}

	$PLUGIN_HOOKS['csrf_compliant']['nediimport'] = true;
}

function plugin_version_nediimport()
{
	return array('name'           => 'Nedi Import',
	             'version'        => '0.5.0',
	             'author'         => 'Sein Coray',
	             'homepage'       => 'http://nediimport.kanajan.ch',
	             'minGlpiVersion' => '0.80');
}

function plugin_nediimport_check_prerequisites()
{
  if (version_compare(GLPI_VERSION, '9.5', '>=') && version_compare(GLPI_VERSION, '10.1', '<=')) {
    return true;
  } else {
    if (method_exists('Plugin', 'messageIncompatible')) {
      echo Plugin::messageIncompatible('core', '9.5', '10.1');
    } else {
      echo 'This plugin requires GLPI >= 9.5 && <= 10.1';
      return false;
    }
  }
}

function plugin_nediimport_check_config()
{
	return true;
}

