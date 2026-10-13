<?php

namespace GlpiPlugin\Nediimport;

use Computer;
use IPAddress;
use NetworkEquipment;
use NetworkName;
use NetworkPort;
use NetworkPort_NetworkPort;
use NetworkPort_Vlan;
use Throwable;
use Vlan;

/**
 * Copies switches, ports, VLANs, switch uplinks and attached nodes from NeDi into GLPI.
 *
 * The import is incremental: existing GLPI objects are matched and updated, never
 * deleted. A node that moved to another port is simply re-wired.
 *
 * Options:
 *  - dry_run      (bool)         write nothing, only record what would be done (ImportResult::$actions)
 *  - switches     (list|null)    only process these NeDi devices
 *  - switch_level (bool)         create/update the switch, its ports, VLANs and cables (default true);
 *                                when false only the nodes are processed
 *  - nodes        (list|null)    only import the nodes with these MAC addresses
 *
 * In dry-run mode every write goes through the same code path as the real import:
 * objects which do not exist yet get a negative placeholder id, and planned cables
 * are kept in an in-memory overlay, so later steps see the effect of earlier ones.
 */
class Importer
{
    private ImportResult $result;

    private ?NediSource $source;

    /** @var array<string, string> */
    private array $cfg;

    private int $entity;

    /** @var list<int> entity and all its children, used to look objects up */
    private array $entities;

    private bool $dryRun;

    /** @var list<string>|null */
    private ?array $onlySwitches;

    /** @var list<string>|null */
    private ?array $onlyMacs;

    private bool $switchLevel;

    /** @var array<int, int> VLAN tag => vlans_id */
    private array $vlans = [];

    /** @var array<string, int> switch name => networkequipments_id */
    private array $switchIds = [];

    // ---- dry-run state
    private int $fakeCounter = 0;

    /** @var array<string, int> object key => placeholder id */
    private array $fakeIndex = [];

    /** @var array<int, string> placeholder id => display label */
    private array $labels = [];

    /** @var array<string, string> "itemtype:id" or "port:id" => display label of a real object */
    private array $realLabels = [];

    /** @var array<string, list<array{0: int, 1: string}>> "itemtype:id" => placeholder ports [id, name] */
    private array $fakePorts = [];

    /** @var array<int, int> port id => opposite port id (0 = unplugged), overrides the database */
    private array $wireOverlay = [];

    /** @var array{switch: ?string, mac: ?string} */
    private array $scope = ['switch' => null, 'mac' => null];

    /**
     * @param array{dry_run?: bool, switches?: ?list<string>, switch_level?: bool, nodes?: ?list<string>} $options
     */
    public function __construct(?NediSource $source = null, array $options = [])
    {
        $this->cfg          = Config::get();
        $this->source       = $source;
        $this->entity       = (int) $this->cfg['entities_id'];
        $this->entities     = array_map('intval', array_values(getSonsOf('glpi_entities', $this->entity)));
        $this->result       = new ImportResult();
        $this->dryRun       = (bool) ($options['dry_run'] ?? false);
        $this->onlySwitches = $options['switches'] ?? null;
        $this->switchLevel  = (bool) ($options['switch_level'] ?? true);
        $this->onlyMacs     = isset($options['nodes'])
            ? array_values(array_filter(array_map([NediSource::class, 'normalizeMac'], $options['nodes'])))
            : null;
    }

    public function run(): ImportResult
    {
        @set_time_limit(0);

        try {
            $this->source ??= NediSource::fromConfig($this->cfg);
            $devices = array_column($this->source->devices(), 'name');
        } catch (Throwable $e) {
            $this->result->error(sprintf(__('Cannot read from NeDi: %s', 'nediimport'), $e->getMessage()));
            return $this->finish();
        }

        if (!$this->dryRun) {
            SwitchSelection::register($devices);
        }
        $selection = SwitchSelection::all();
        $switches  = $this->cfg['auto_all'] === '1'
            ? $devices
            : array_values(array_filter($devices, static fn($d) => $selection[$d] ?? false));
        if ($this->onlySwitches !== null) {
            $switches = array_values(array_intersect($devices, $this->onlySwitches));
        }

        // First pass: equipment, ports and VLANs, so that every link target exists
        foreach ($switches as $switch) {
            $this->scope = ['switch' => $switch, 'mac' => null];
            $this->guard("switch $switch", fn() => $this->importSwitch($switch));
        }
        // Second pass: uplinks and attached nodes
        foreach ($switches as $switch) {
            $this->scope = ['switch' => $switch, 'mac' => null];
            if (!isset($this->switchIds[$switch])) {
                continue;
            }
            if ($this->switchLevel) {
                $this->guard("links of $switch", fn() => $this->importLinks($switch));
            }
            $this->guard("nodes of $switch", fn() => $this->importNodes($switch));
        }

        return $this->finish();
    }

    private function finish(): ImportResult
    {
        if (!$this->dryRun) {
            Config::save([
                'last_run'   => date('Y-m-d H:i:s'),
                'last_stats' => json_encode(['stats' => $this->result->stats, 'warnings' => array_slice($this->result->warnings, 0, 100)]),
            ]);
        }
        return $this->result;
    }

    private function guard(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            $this->result->error("$what: " . $e->getMessage());
        }
    }

    // ------------------------------------------------------------ dry-run support

    private function record(string $type, string $text): void
    {
        $this->result->actions[] = [
            'switch' => (string) $this->scope['switch'],
            'mac'    => $this->scope['mac'],
            'type'   => $type,
            'text'   => $text,
        ];
    }

    /** Placeholder id for an object which would be created; the same key always gives the same id. */
    private function fakeNew(string $key, string $label, string $type, string $text, string $stat): int
    {
        if (isset($this->fakeIndex[$key])) {
            return $this->fakeIndex[$key];
        }
        $id = --$this->fakeCounter;
        $this->fakeIndex[$key] = $id;
        $this->labels[$id]     = $label;
        $this->record($type, $text);
        $this->result->inc($stat);
        return $id;
    }

    private function itemLabel(string $itemtype, int $id): string
    {
        if ($id < 0) {
            return $this->labels[$id] ?? "#$id";
        }
        $key = "$itemtype:$id";
        if (!isset($this->realLabels[$key])) {
            $table = $itemtype === 'Computer' ? 'glpi_computers' : 'glpi_networkequipments';
            $row   = $this->findOne($table, ['id' => $id]);
            $this->realLabels[$key] = (string) ($row['name'] ?? "#$id");
        }
        return $this->realLabels[$key];
    }

    private function portLabel(int $portId): string
    {
        if ($portId < 0) {
            return $this->labels[$portId] ?? "#$portId";
        }
        if (!isset($this->realLabels["port:$portId"])) {
            $row = $this->findOne('glpi_networkports', ['id' => $portId]);
            $this->realLabels["port:$portId"] = $row
                ? $this->itemLabel($row['itemtype'], (int) $row['items_id']) . ':' . $row['name']
                : "#$portId";
        }
        return $this->realLabels["port:$portId"];
    }

    /**
     * Ports of an item, existing and (in dry-run) planned, as [id, name].
     *
     * @return list<array{0: int, 1: string}>
     */
    private function portsOf(string $itemtype, int $itemId): array
    {
        global $DB;

        $out = [];
        if ($itemId > 0) {
            foreach ($DB->request(['FROM' => 'glpi_networkports', 'WHERE' => ['itemtype' => $itemtype, 'items_id' => $itemId, 'is_deleted' => 0], 'ORDER' => 'id']) as $row) {
                $out[] = [(int) $row['id'], $row['name']];
            }
        }
        return array_merge($out, $this->fakePorts["$itemtype:$itemId"] ?? []);
    }

    // ------------------------------------------------------------------ switches

    private function importSwitch(string $name): void
    {
        if (!$this->switchLevel) {
            $id = $this->knownSwitchId($name);
            if ($id) {
                $this->switchIds[$name] = $id;
            } else {
                $this->result->warn(sprintf(__('%s does not exist in GLPI: import the switch itself first', 'nediimport'), $name));
            }
            return;
        }

        $id = $this->ensureEquipment($name);
        $this->switchIds[$name] = $id;
        $this->result->inc('switches');

        foreach ($this->source->interfaces($name) as $if) {
            $portId = $this->ensurePort('NetworkEquipment', $id, $if['ifname'], $if['alias']);
            if ($if['pvid'] > 0) {
                $this->setUntaggedVlan($portId, $if['pvid']);
            }
        }
    }

    /** Id of an existing (or, in dry-run, planned) switch; 0 if there is none. Never creates anything. */
    private function knownSwitchId(string $name): int
    {
        if (isset($this->switchIds[$name])) {
            return $this->switchIds[$name];
        }
        $row = $this->findOne('glpi_networkequipments', [
            'name'        => $name,
            'is_deleted'  => 0,
            'is_template' => 0,
            'entities_id' => $this->entities,
        ]);
        return $row ? (int) $row['id'] : ($this->fakeIndex["eq:$name"] ?? 0);
    }

    private function ensureEquipment(string $name, array $extra = []): int
    {
        $row = $this->findOne('glpi_networkequipments', [
            'name'        => $name,
            'is_deleted'  => 0,
            'is_template' => 0,
            'entities_id' => $this->entities,
        ]);
        if ($row) {
            return (int) $row['id'];
        }
        if ($this->dryRun) {
            return $this->fakeIndex["eq:$name"]
                ?? $this->fakeNew("eq:$name", $name, 'new_switch', sprintf(__('Create network equipment %s', 'nediimport'), $name), 'switches_created');
        }
        $id = (new NetworkEquipment())->add($extra + [
            'name'        => $name,
            'entities_id' => $this->entity,
            'comment'     => 'Created by NeDi import',
        ]);
        if (!$id) {
            throw new \RuntimeException("cannot create network equipment $name");
        }
        $this->result->inc('switches_created');
        return (int) $id;
    }

    private function ensurePort(string $itemtype, int $itemId, string $name, string $comment = '', string $mac = ''): int
    {
        $row = $itemId > 0 ? $this->findOne('glpi_networkports', [
            'itemtype'   => $itemtype,
            'items_id'   => $itemId,
            'name'       => $name,
            'is_deleted' => 0,
        ]) : null;
        if ($row) {
            if ($mac !== '' && $row['mac'] !== $mac) {
                if ($this->dryRun) {
                    $this->record('port_mac', sprintf(__('Set MAC %1$s on %2$s', 'nediimport'), $mac, $this->portLabel((int) $row['id'])));
                } else {
                    (new NetworkPort())->update(['id' => $row['id'], 'mac' => $mac, 'name' => $name]);
                }
            }
            return (int) $row['id'];
        }

        if ($this->dryRun) {
            $key = "port:$itemtype:$itemId:$name";
            if (isset($this->fakeIndex[$key])) {
                return $this->fakeIndex[$key];
            }
            $label = $this->itemLabel($itemtype, $itemId) . ':' . $name;
            $id    = $this->fakeNew($key, $label, 'new_port', sprintf(__('Create port %s', 'nediimport'), $label), 'ports_created');
            $this->fakePorts["$itemtype:$itemId"][] = [$id, $name];
            return $id;
        }

        $input = [
            'itemtype'           => $itemtype,
            'items_id'           => $itemId,
            'name'               => $name,
            'comment'            => $comment,
            'entities_id'        => $this->entity,
            'instantiation_type' => 'NetworkPortEthernet',
            'logical_number'     => 0,
        ];
        if ($mac !== '') {
            $input['mac'] = $mac;
        }
        $id = (new NetworkPort())->add($input);
        if (!$id) {
            throw new \RuntimeException("cannot create port $name on $itemtype #$itemId");
        }
        $this->result->inc('ports_created');
        return (int) $id;
    }

    private function ensureVlan(int $tag): int
    {
        if (isset($this->vlans[$tag])) {
            return $this->vlans[$tag];
        }
        $row = $this->findOne('glpi_vlans', ['tag' => $tag, 'entities_id' => $this->entities]);
        if ($row) {
            return $this->vlans[$tag] = (int) $row['id'];
        }
        if ($this->dryRun) {
            return $this->vlans[$tag] = $this->fakeNew("vlan:$tag", "VLAN $tag", 'new_vlan', sprintf(__('Create VLAN %d', 'nediimport'), $tag), 'vlans_created');
        }
        $id = (new Vlan())->add(['name' => "VLAN $tag", 'tag' => $tag, 'entities_id' => $this->entity]);
        if (!$id) {
            throw new \RuntimeException("cannot create VLAN $tag");
        }
        $this->result->inc('vlans_created');
        return $this->vlans[$tag] = (int) $id;
    }

    /** Make $tag the only untagged VLAN of the port */
    private function setUntaggedVlan(int $portId, int $tag): void
    {
        global $DB;

        $vlanId = $this->ensureVlan($tag);

        if ($this->dryRun) {
            $current = [];
            if ($portId > 0) {
                foreach ($DB->request([
                    'SELECT'     => 'glpi_vlans.tag',
                    'FROM'       => 'glpi_networkports_vlans',
                    'INNER JOIN' => ['glpi_vlans' => ['ON' => ['glpi_networkports_vlans' => 'vlans_id', 'glpi_vlans' => 'id']]],
                    'WHERE'      => ['glpi_networkports_vlans.networkports_id' => $portId, 'glpi_networkports_vlans.tagged' => 0],
                ]) as $row) {
                    $current[] = (int) $row['tag'];
                }
            }
            if ($current !== [$tag]) {
                $this->record('vlan_change', sprintf(
                    __('VLAN on %1$s: %2$s → %3$d', 'nediimport'),
                    $this->portLabel($portId),
                    $current ? implode(',', $current) : '-',
                    $tag
                ));
            }
            return;
        }

        $present = false;
        foreach ($DB->request(['FROM' => 'glpi_networkports_vlans', 'WHERE' => ['networkports_id' => $portId, 'tagged' => 0]]) as $row) {
            if ((int) $row['vlans_id'] === $vlanId) {
                $present = true;
            } else {
                (new NetworkPort_Vlan())->delete(['id' => $row['id']]);
            }
        }
        if (!$present) {
            (new NetworkPort_Vlan())->add(['networkports_id' => $portId, 'vlans_id' => $vlanId, 'tagged' => 0]);
        }
    }

    // --------------------------------------------------------------------- wiring

    private function oppositePort(int $portId): int
    {
        if (array_key_exists($portId, $this->wireOverlay)) {
            return $this->wireOverlay[$portId];
        }
        if ($portId <= 0) {
            return 0;
        }
        $row = $this->findOne('glpi_networkports_networkports', [
            'OR' => ['networkports_id_1' => $portId, 'networkports_id_2' => $portId],
        ]);
        if (!$row) {
            return 0;
        }
        return (int) ($row['networkports_id_1'] == $portId ? $row['networkports_id_2'] : $row['networkports_id_1']);
    }

    private function unplug(int $portId): void
    {
        global $DB;

        foreach ($DB->request([
            'FROM'  => 'glpi_networkports_networkports',
            'WHERE' => ['OR' => ['networkports_id_1' => $portId, 'networkports_id_2' => $portId]],
        ]) as $row) {
            (new NetworkPort_NetworkPort())->delete(['id' => $row['id']]);
        }
    }

    /** @return bool true if a new cable was created */
    private function wire(int $a, int $b): bool
    {
        $oldA = $this->oppositePort($a);
        if ($oldA === $b) {
            return false;
        }

        if ($this->dryRun) {
            $oldB     = $this->oppositePort($b);
            $replaced = [];
            if ($oldA) {
                $replaced[] = $this->portLabel($a) . ' ↔ ' . $this->portLabel($oldA);
            }
            if ($oldB) {
                $replaced[] = $this->portLabel($b) . ' ↔ ' . $this->portLabel($oldB);
            }
            $text = $this->portLabel($a) . ' ↔ ' . $this->portLabel($b);
            if ($replaced) {
                $text .= ' (' . sprintf(__('replaces %s', 'nediimport'), implode(', ', $replaced)) . ')';
            }
            $this->record('wire', $text);
            foreach ([$oldA, $oldB] as $old) {
                if ($old) {
                    $this->wireOverlay[$old] = 0;
                }
            }
            $this->wireOverlay[$a] = $b;
            $this->wireOverlay[$b] = $a;
            $this->result->inc('links');
            return true;
        }

        $this->unplug($a);
        $this->unplug($b);
        (new NetworkPort_NetworkPort())->add(['networkports_id_1' => $a, 'networkports_id_2' => $b]);
        $this->result->inc('links');
        return true;
    }

    private function importLinks(string $switch): void
    {
        foreach ($this->source->links($switch) as $link) {
            $peerId = $this->knownSwitchId($link['neighbor']);
            if ($link['ifname'] === '' || $link['nbrifname'] === '' || !$peerId) {
                continue;
            }
            $local  = $this->ensurePort('NetworkEquipment', $this->switchIds[$switch], $link['ifname']);
            $remote = $this->ensurePort('NetworkEquipment', $peerId, $link['nbrifname']);
            $this->wire($local, $remote);
        }
    }

    // ---------------------------------------------------------------------- nodes

    private function importNodes(string $switch): void
    {
        $switchId = $this->switchIds[$switch];
        $maxAge   = max(1, (int) $this->cfg['max_age_days']) * DAY_TIMESTAMP;

        // Ports which lead to another switch only carry transit MAC addresses
        $uplinks = [];
        foreach ($this->source->links($switch) as $link) {
            $uplinks[$link['ifname']] = true;
        }

        $byPort = [];
        foreach ($this->source->nodes($switch) as $node) {
            if ($node['ifname'] === '' || isset($uplinks[$node['ifname']])) {
                continue;
            }
            if ($node['lastseen'] > 0 && time() - $node['lastseen'] > $maxAge) {
                continue;
            }
            if ($node['mac'] === '') {
                continue;
            }
            $byPort[$node['ifname']][$node['mac']] = $node;   // one entry per MAC
        }

        foreach ($byPort as $ifname => $nodes) {
            // Whether a port needs a virtual switch depends on every node behind it,
            // not only on the selected ones.
            $shared   = count($nodes) > 1;
            $selected = $this->onlyMacs === null
                ? $nodes
                : array_intersect_key($nodes, array_flip($this->onlyMacs));
            if (!$selected) {
                continue;
            }

            $this->guard("port $ifname of $switch", function () use ($switch, $switchId, $ifname, $selected, $shared) {
                $realPort = $this->ensurePort('NetworkEquipment', $switchId, $ifname);
                $location = $this->portLocation($realPort, $switchId);

                if (!$shared) {
                    $node = reset($selected);
                    $this->importAndWire($switch, $node, $location, fn() => $realPort);
                    return;
                }

                // Several MAC addresses behind one port: an unmanaged switch or hub
                $first       = reset($selected);
                $this->scope['mac'] = $first['mac'];
                $virtualName = "$switch-$ifname-VirtualSwitch";
                $virtualId   = $this->ensureEquipment($virtualName, ['locations_id' => $location]);
                $uplink      = $this->ensurePort('NetworkEquipment', $virtualId, 'uplink');
                if ($this->wire($uplink, $realPort)) {
                    $this->result->inc('virtual_switches');
                }
                foreach ($selected as $node) {
                    $this->importAndWire($switch, $node, $location, fn($computerPort) => $this->freeVirtualPort($virtualId, $computerPort));
                }
            });
        }
        $this->scope['mac'] = null;
    }

    /**
     * Import one node and cable it to the port returned by $targetPort(computerPort).
     */
    private function importAndWire(string $switch, array $node, int $location, callable $targetPort): void
    {
        $this->scope['mac'] = $node['mac'];
        $this->result->inc('nodes');

        $computerPort = $this->importNode($node, $location);
        $this->wire($computerPort, $targetPort($computerPort));

        if ($this->dryRun) {
            $this->result->nodes[] = [
                'switch' => $switch,
                'mac'    => $node['mac'],
                'name'   => $this->hostname($node),
                'ip'     => $node['ip'],
                'target' => $this->portLabel($this->oppositePort($computerPort)),
            ];
        }
        $this->scope['mac'] = null;
    }

    /** The virtual port already wired to $computerPort, or the first free one (created if needed). */
    private function freeVirtualPort(int $virtualId, int $computerPort): int
    {
        $current = $this->oppositePort($computerPort);
        $ports   = [];
        foreach ($this->portsOf('NetworkEquipment', $virtualId) as [$id, $name]) {
            if (!str_starts_with($name, 'port ')) {
                continue;
            }
            if ($id === $current) {
                return $current;
            }
            $ports[] = $id;
        }
        foreach ($ports as $id) {
            if (!$this->oppositePort($id)) {
                return $id;
            }
        }
        return $this->ensurePort('NetworkEquipment', $virtualId, 'port ' . (count($ports) + 1));
    }

    /**
     * Create or update the Computer and its network port for a NeDi node.
     *
     * @return int the computer's networkports_id
     */
    private function importNode(array $node, int $location): int
    {
        global $DB;

        $mac      = $node['mac'];
        $hostname = $this->hostname($node);
        $computer = null;
        $portId   = 0;

        // 1. Same MAC address on a computer's port
        foreach ($DB->request(['FROM' => 'glpi_networkports', 'WHERE' => ['mac' => $mac, 'itemtype' => 'Computer', 'is_deleted' => 0]]) as $port) {
            $row = $this->findOne('glpi_computers', ['id' => $port['items_id'], 'is_deleted' => 0]);
            if ($row) {
                $computer = $row;
                $portId   = (int) $port['id'];
                if ($this->dryRun && $row['name'] !== $hostname && $node['name'] !== '') {
                    $this->record('warn', sprintf(__('MAC %1$s belongs to computer "%2$s" in GLPI, NeDi calls it "%3$s"', 'nediimport'), $mac, $row['name'], $hostname));
                }
                break;
            }
        }

        // 2. Same host name
        if (!$computer) {
            $computer = $this->findOne('glpi_computers', [
                'name'        => $hostname,
                'is_deleted'  => 0,
                'is_template' => 0,
                'entities_id' => $this->entities,
            ]);
        }

        // 3. New computer
        if (!$computer) {
            if ($this->dryRun) {
                $id = $this->fakeIndex["comp:$hostname"] ?? $this->fakeNew(
                    "comp:$hostname",
                    $hostname,
                    'new_computer',
                    sprintf(__('Create computer %s', 'nediimport'), $hostname),
                    'computers_created'
                );
                if ($node['name'] === '' || $node['name'] === '-') {
                    $this->record('warn', sprintf(__('%1$s has no name in NeDi: it will be called %2$s', 'nediimport'), $mac, $hostname));
                }
                $computer = ['id' => $id, 'name' => $hostname, 'comment' => null, 'locations_id' => 0];
            } else {
                $id = (new Computer())->add(['name' => $hostname, 'entities_id' => $this->entity]);
                if (!$id) {
                    throw new \RuntimeException("cannot create computer $hostname");
                }
                $this->result->inc('computers_created');
                $computer = $this->findOne('glpi_computers', ['id' => $id]);
            }
        }

        $computerId = (int) $computer['id'];
        if (!$portId) {
            $portId = $this->ensurePort('Computer', $computerId, 'eth' . count($this->portsOf('Computer', $computerId)), '', $mac);
        }

        if ($node['ip'] !== '') {
            $this->setIp($portId, $node['ip']);
        }

        // The first/last seen comment changes on every run: it is written by the real
        // import but deliberately not reported as a change in the preview.
        $update = [];
        $seen   = "NeDi import\nFirst seen: " . $this->date($node['firstseen']) . "\nLast seen: " . $this->date($node['lastseen']);
        if ($computer['comment'] === null || $computer['comment'] === '' || str_starts_with($computer['comment'], 'NeDi import')) {
            if ($computer['comment'] !== $seen) {
                $update['comment'] = $seen;
            }
        }
        if ($location && !$computer['locations_id']) {
            $update['locations_id'] = $location;
            if ($this->dryRun) {
                $this->record('location', sprintf(__('Set the location of %s from its switch', 'nediimport'), $computer['name']));
            }
        }
        if ($update && !$this->dryRun) {
            (new Computer())->update(['id' => $computerId] + $update);
        }

        return $portId;
    }

    private function setIp(int $portId, string $ip): void
    {
        global $DB;

        $name   = $portId > 0 ? $this->findOne('glpi_networknames', ['itemtype' => 'NetworkPort', 'items_id' => $portId, 'is_deleted' => 0]) : null;
        $nameId = $name ? (int) $name['id'] : 0;

        $current = [];
        if ($nameId) {
            foreach ($DB->request(['FROM' => 'glpi_ipaddresses', 'WHERE' => ['itemtype' => 'NetworkName', 'items_id' => $nameId, 'is_deleted' => 0]]) as $row) {
                $current[(int) $row['id']] = $row['name'];
            }
        }

        if ($this->dryRun) {
            if ($current !== [] && array_values($current) === [$ip]) {
                return;
            }
            $this->record('ip', sprintf(
                __('IP of %1$s: %2$s → %3$s', 'nediimport'),
                $this->portLabel($portId),
                $current ? implode(',', $current) : '-',
                $ip
            ));
            return;
        }

        if (!$nameId) {
            $nameId = (int) (new NetworkName())->add(['itemtype' => 'NetworkPort', 'items_id' => $portId, 'entities_id' => $this->entity]);
            if (!$nameId) {
                throw new \RuntimeException("cannot create network name for port #$portId");
            }
        }
        $found = false;
        foreach ($current as $id => $address) {
            if ($address === $ip) {
                $found = true;
            } else {
                (new IPAddress())->delete(['id' => $id], true);
            }
        }
        if (!$found) {
            (new IPAddress())->add(['itemtype' => 'NetworkName', 'items_id' => $nameId, 'name' => $ip, 'entities_id' => $this->entity]);
        }
    }

    /** Location of the netpoint the port is attached to, else the one of the switch */
    private function portLocation(int $portId, int $switchId): int
    {
        $eth = $this->findOne('glpi_networkportethernets', ['networkports_id' => $portId]);
        if ($eth && !empty($eth['netpoints_id'])) {
            $netpoint = $this->findOne('glpi_netpoints', ['id' => $eth['netpoints_id']]);
            if ($netpoint && $netpoint['locations_id']) {
                return (int) $netpoint['locations_id'];
            }
        }
        $switch = $this->findOne('glpi_networkequipments', ['id' => $switchId]);
        return (int) ($switch['locations_id'] ?? 0);
    }

    // -------------------------------------------------------------------- helpers

    /** Short host name; "unknown-<mac>" when NeDi has none (stable across runs) */
    private function hostname(array $node): string
    {
        $name = trim($node['name']);
        if ($name === '' || $name === '-' || filter_var($name, FILTER_VALIDATE_IP)) {
            return 'unknown-' . str_replace(':', '', $node['mac']);
        }
        return explode('.', $name)[0];
    }

    private function date(int $ts): string
    {
        return $ts > 0 ? date('Y-m-d H:i:s', $ts) : '-';
    }

    /** @return array<string, mixed>|null */
    private function findOne(string $table, array $where): ?array
    {
        global $DB;

        foreach ($DB->request(['FROM' => $table, 'WHERE' => $where, 'LIMIT' => 1]) as $row) {
            return $row;
        }
        return null;
    }
}
