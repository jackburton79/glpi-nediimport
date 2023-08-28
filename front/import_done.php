<?php 
/*
*	$Id: import_done.php 3 2012-01-24 11:05:35Z seincoray $
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


require "../../../inc/includes.php";

include("../inc/switch_list.class.php");
include("../work/functions.php");

Html::header(__('Nedi Data Import','Import done!'), '', 'tools', 'pluginnediimport');

echo "<form action='switch_conf.php' method='post', name='import_done'>";
echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";

echo "<tr><th colspan='2'>".__("Import done!", "nediimport")."</th></tr>";

$Stat=new NediImport\Stat(false);
$Stat->PrintStat();

echo "</table>";
echo "<a href='start.php'>".__("Back", "nediimport")."</a>";
echo "</div>";

Html::footer();
