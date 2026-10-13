<?php

namespace GlpiPlugin\Nediimport;

use CommonGLPI;
use CronTask;

class Cron extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __s('NeDi import', 'nediimport');
    }

    public static function cronInfo($name)
    {
        if ($name === 'Nediimport') {
            return ['description' => __s('Synchronise switches, VLANs and nodes from NeDi', 'nediimport')];
        }
        return [];
    }

    public static function cronNediimport(CronTask $task)
    {
        $result = (new Importer())->run();
        $task->addVolume($result->stats['nodes']);
        foreach (array_slice($result->warnings, 0, 20) as $warning) {
            $task->log($warning);
        }
        return $result->stats['errors'] > 0 ? -1 : 1;
    }
}
