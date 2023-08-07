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


include (GLPI_ROOT . "/plugins/nediimport/inc/settings.class.php");

class PluginNediImportConnection{
	var $con;
	var $err;
	
	function __destruct(){
		curl_close($this->con);
	}
	
	function __construct(){
		$this->Connect();
	}
	
	function Connect(){
		global $LANG;
		
		//Load login settings
		$Settings=new PluginNediImportSettings();
		$Settings->Load();
		//Init cURL
		$this->con=curl_init();
		curl_setopt($this->con,CURLOPT_URL,$Settings->Settings['url']);
		curl_setopt($this->con,CURLOPT_POSTFIELDS,'user='.$Settings->Settings['user'].'&pass='.$Settings->Settings['pass']);
		curl_setopt($this->con,CURLOPT_COOKIEFILE,"cookie.txt");
		curl_setopt($this->con,CURLOPT_COOKIEJAR,"cookie.txt");
		curl_setopt($this->con,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($this->con,CURLOPT_SSL_VERIFYPEER,false);
		
		//Login
		$store=curl_exec($this->con);
		
		if(!$store){
			$this->err="{$LANG['plugin_nediimport']['check_con_fail']}";
			return false;
		}
		else{
			$Info=curl_getinfo($this->con);
			if($Info['http_code']!=200){
				$this->err="{$LANG['plugin_nediimport']['check_http_fail']}";
				return false;
			}
			
			//check for incorrect login
			if(stripos($store,"Incorrect Login")){
				$this->err="{$LANG['plugin_nediimport']['check_login_fail']}";
				return false;
			}
			
			return true;
		}
	}
	
	function LoadSwitches(){
		$Settings=new PluginNediImportSettings();
		$Settings->Load();
		$Conf=new PluginNediImportSwitchConf();
		
		$url=$Settings->Settings['url'].'/Devices-List.php?ina=device&opa=regexp&sta=&cop=&inb=device&opb=regexp&stb=&col[]=device';
		curl_setopt($this->con,CURLOPT_URL,$url);
		$content=curl_exec($this->con);
		
		$Extract=new DOMDocument();
		@$Extract->loadHTML($content);
		$Extract->normalizeDocument();
		
		$AllElements=$Extract->getElementsByTagName("b");
		
		$Nodes=array();
		
		//Start from 2 because first two <b> are empty
		//The last entry contains the number -> ignoring
		for($x=0;$x<$AllElements->length;$x++){
			$Node=$AllElements->item($x);
			$Nodes[$x]=$Node->nodeValue;
		}
		
		if($Settings->Settings['auto']=='0'){
			$TempNodes=array();
			$Index=0;
			
			for($x=0;$x<sizeof($Nodes);$x++){
				$Conf->Insert($Nodes[$x]);
				if($Conf->GetConf($Nodes[$x])==1){
					$TempNodes[$Index]=$Nodes[$x];
					$Index++;
				}
			}
			
			$Nodes=$TempNodes;
		}
		
		return $Nodes;
	}
	
	function GetSwitches(){
		$Settings=new PluginNediImportSettings();
		$Settings->Load();
		$Conf=new PluginNediImportSwitchConf();
		
		$url=$Settings->Settings['url'].'/Devices-List.php?ina=device&opa=regexp&sta=&cop=&inb=device&opb=regexp&stb=&col[]=device';
		curl_setopt($this->con,CURLOPT_URL,$url);
		$content=curl_exec($this->con);
		
		$Extract=new DOMDocument();
		@$Extract->loadHTML($content);
		$Extract->normalizeDocument();
		
		$AllElements=$Extract->getElementsByTagName("b");
		
		$Nodes=array();
		
		//Start from 2 because first two <b> are empty
		//The last entry contains the number -> ignoring
		for($x=0;$x<$AllElements->length;$x++){
			$Node=$AllElements->item($x);
			$Nodes[$x]=$Node->nodeValue;
		}
		
		return $Nodes;
	}
	
	function LoadComputers($Switch){
		$Settings=new PluginNediImportSettings();
		//URL to get information from NEDI
		$url=$Settings->Settings['url'].'/Nodes-List.php?ina=device&opa=%3D&sta='.$Switch.'&cop=&inb=name&opb=regexp&stb=&col[]=name&col[]=nodip&col[]=mac&col[]=firstseen&col[]=lastseen&col[]=ifname&col[]=ifupdate&col[]=vlanid';
		
		curl_setopt($this->con,CURLOPT_URL,$url);
		$content=curl_exec($this->con);
		
		$Extract=new DOMDocument();
		@$Extract->loadHTML($content);
		$Extract->normalizeDocument();
		$Elements=$Extract->getElementsByTagName("td");
		
		//Start from 2 because first two <td> are empty
		//The last entry contains the number -> ignoring
		$DataIndex=-1;
		$Data=array();
		for($x=2;$x<$Elements->length-1;$x++){
			if(($x-2)%8==0){
				$DataIndex++;
			}
			$Node=$Elements->item($x);
			$NodeValue=$Node->nodeValue;
		
			//Delete all '\n' from values
			$NodePart=explode("\n",$NodeValue);
			$NodeValue="";
			for($y=0;$y<sizeof($NodePart);$y++){
				$NodeValue=$NodeValue.$NodePart[$y];
			}
		
			$Data[$DataIndex][($x-2)%8]=$NodeValue;
		}
		return $this->ReadComputersData($Data);
	}
	
	function ReadComputersData($Data){
		global $UnknownNumber;
		
		//Go through the whole data
		$Index=0;
		$NewData=array();
		for($x=0;$x<sizeof($Data);$x++){
			//Ignore if computer last seen is more than a year in past
			if(time()-strtotime($Data[$x][LA])>24*3600*356){
				//TODO: make in config to configure timeout time
			}
		
			//Change name, if host is empty or '-'
			else if($Data[$x][HO]==''||$Data[$x][HO]=='-'||$Data[$x][HO]=='- '||$Data[$x][HO]==' '){
				//TODO: make config rule for unknown or empty computers
				$Data[$x][HO]="unknown".$UnknownNumber;
				$UnknownNumber++;
				$NewData[$Index]=$Data[$x];
				$Index++;
			}
		
			//Ignore if duplicate was found
			else if($this->CheckDouble($Data,$x)){
				//TODO: make config rule for duplicates
				//ignore
			}
			else{
				$NewData[$Index]=$Data[$x];
				$Index++;
			}
		}
		return $NewData;
	}
	
	function CheckDouble($Data,$SP){
		$Last=$SP;
		for($x=$SP+1;$x<sizeof($Data);$x++){
			if($Data[$x][HO]==$Data[$Last][HO]){
				//Put the newer one into the entry which is not the actual tested one
				//The other will be ignored
				if(strtotime($Data[$x][LA])<strtotime($Data[$Last][LA])){
					for($i=0;$i<8;$i++){
						$Data[$x][$i]=$Data[$Last][$i];
					}
					$Last=$x;
				}
				else{
					$Last=$x;
				}
			}
		}
		return !($Last==$SP);
	}
	
	function ReadSwitchVlans($url){
		curl_setopt($this->con,CURLOPT_URL,$url);
		$content=curl_exec($this->con);
		
		$Extract=new DOMDocument();
		@$Extract->loadHTML($content);
		$Extract->normalizeDocument();
		
		//Extracte all parts <td> from the table
		$Elements=$Extract->getElementsByTagName("td");
		$OneSwitch=array();
		
		//Ignoring the first because it is empty
		//Ignoring last because it contents information about the request
		for($z=1,$y=0;$z<$Elements->length-1;$z+=2,$y++){
			$Node=$Elements->item($z);
			$OneSwitch[$y][0]=$Node->nodeValue;
			$Node=$Elements->item($z+1);
			$OneSwitch[$y][1]=$Node->nodeValue;
		}
		return $OneSwitch;
	}
}