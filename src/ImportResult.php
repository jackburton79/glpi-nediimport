<?php

namespace GlpiPlugin\Nediimport;

final class ImportResult
{
    /** @var array<string, int> */
    public array $stats = [
        'switches'         => 0,
        'switches_created' => 0,
        'ports_created'    => 0,
        'vlans_created'    => 0,
        'nodes'            => 0,
        'computers_created' => 0,
        'virtual_switches' => 0,
        'links'            => 0,
        'errors'           => 0,
    ];

    /** @var list<string> */
    public array $warnings = [];

    public function inc(string $stat, int $by = 1): void
    {
        $this->stats[$stat] += $by;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function error(string $message): void
    {
        $this->stats['errors']++;
        $this->warnings[] = $message;
    }
}
