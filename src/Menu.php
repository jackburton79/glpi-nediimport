<?php

namespace GlpiPlugin\Nediimport;

use CommonGLPI;
use Plugin;

class Menu extends CommonGLPI
{
    public static $rightname = 'config';

    public static function getTypeName($nb = 0)
    {
        return __s('NeDi import', 'nediimport');
    }

    public static function getMenuName()
    {
        return __s('NeDi import', 'nediimport');
    }

    public static function getIcon()
    {
        return 'ti ti-network';
    }

    public static function getWebDir(): string
    {
        return '/' . ltrim(Plugin::getWebDir('nediimport', false), '/');
    }

    public static function getMenuContent()
    {
        $base = self::getWebDir() . '/front';

        return [
            'title' => self::getMenuName(),
            'page'  => $base . '/start.php',
            'icon'  => self::getIcon(),
        ];
    }
}
