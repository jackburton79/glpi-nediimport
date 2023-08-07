<?php
/*
*	$Id: import.php 3 2012-01-24 11:05:35Z seincoray $
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


define('GLPI_ROOT', '../../..');
include (GLPI_ROOT . "/inc/includes.php");
include("../inc/switch_list.class.php");

commonHeader($LANG['plugin_nediimport']['title'], $_SERVER['PHP_SELF'],"plugins","nediimport","optionname");

echo "<form action='switch_conf.php' method='post'>";
echo "<div align='center'><table class='tab_cadre' cellpadding='5' width='70%'>";

echo "<tr><th>".$LANG['plugin_nediimport']['import_title']."</th></tr>";

echo "<tr class='tab_bg_1'><td align='center'>";

?>

<img src='../pics/waiting.gif' width='32px' />

<script type='text/javascript'>
var xmlhttp;
var done=false;

Wait();

function Wait(){
	loadXMLDoc('../work/start.php');
	Loop();
}

function Loop(){
	window.setTimeout(function(){Loop()},1000);
	if(done==true){
		window.location='import_done.php';
	}
}

function loadXMLDoc(url){
	xmlhttp=null;
	if (window.XMLHttpRequest){
		// code for all new browsers
		xmlhttp=new XMLHttpRequest();
	}
	else if (window.ActiveXObject){
		// code for IE5 and IE6
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	if (xmlhttp!=null){
		xmlhttp.onreadystatechange=state_Change;
		xmlhttp.open("GET",url,true);
		xmlhttp.send(null);
	}
	else{
		alert("<?php echo $LANG['plugin_nediimport']['import_xmlhttp_error']?>");
		window.location='start.php';
	}
}

function state_Change(){
	if (xmlhttp.readyState==4){
		// 4 = "loaded"
		if (xmlhttp.status==200){
			done=true;
		}
		else{
			alert("<?php echo $LANG['plugin_nediimport']['import_http_error']?>: \n"+xmlhttp.response);
			window.location='start.php';
		}
	}
}
</script>

<?php 

echo "</td></tr></table></div>";

commonFooter();
?>
