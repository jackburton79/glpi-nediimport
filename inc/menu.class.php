<?php

/**
 * To display an entry for the NediImportRooms in the GLPI menus.
 *
 * @author DUVERGIER Claude
 * @package nediimport
 */
class PluginNediimportMenu extends CommonGLPI
{
    public static $rightname = 'plugin_nediimport';

    public static function getMenuName()
    {
        return __('Nedi Data Import');
    }
    
    /*public static function getAdditionalMenuLinks()
    {
      global $CFG_GLPI;
      $links = array();

      //$PLUGIN_HOOKS['submenu_entry']['nediimport']['options']['optionname']['title'] = "Start";
      //$PLUGIN_HOOKS['submenu_entry']['nediimport']['options']['optionname']['page'] = '/plugins/nediimport/front/start.php';
      $links['config'] = '/plugins/nediimport/index.php';
      #$links["<img  src='".$CFG_GLPI["root_doc"]."/pics/menu_showall.png' title='".__s('Show all')."' alt='".__s('Show all')."'>"] = '/plugins/nediimport/front/start.php';
      #$links[__s('Test link', 'example')] = '/plugins/example/index.php';
      return $links;
    }*/
    public static function getMenuContent()
    {
        $menu = [];
        $menu['title'] = self::getMenuName();
        $menu['page'] = "/".Plugin::getWebDir('nediimport', false).'/front/start.php';
        $menu['links']['search'] = "/".Plugin::getWebDir('nediimport', false).'/front/start.php';
        //if (PluginRoomRoom::canCreate()) {
        //    $menu['links']['add'] = PluginNediImport::getFormURL(false);
        //}
        $menu['icon'] = self::getIcon();
        return $menu;
    }
    
    public static function removeRightsFromSession()
    {
        /*if (isset($_SESSION['glpimenu']['tools']['types']['PluginRoomMenu'])) {
            unset($_SESSION['glpimenu']['tools']['types']['PluginRoomMenu']);
        }
        if (isset($_SESSION['glpimenu']['tools']['content']['pluginroommenu'])) {
            unset($_SESSION['glpimenu']['tools']['content']['pluginroommenu']);
	}*/
    }

    public static function getIcon()
    {
        return 'fas fa-building';
    }
}

