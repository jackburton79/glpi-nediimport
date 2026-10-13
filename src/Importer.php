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

    /** @var array<int, int> VLAN tag => vlans_id */
    private array $vlans = [];

    /** @var array<string, int> switch name => networkequipments_id */
    private array $switchIds = [];

    public function __construct(?NediSource $source = null)
    {
        $this->cfg      = Config::get();
        $this->source   = $source;
        $this->entity   = (int) $this->cfg['entities_id'];
        $this->entities = array_map('intval', array_values(getSonsOf('glpi_entities', $this->entity)));
        $this->result   = new ImportResult();
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

        SwitchSelection::register($devices);
        $selection = SwitchSelection::all();
        $switches  = $this->cfg['auto_all'] === '1'
            ? $devices
            : array_values(array_filter($devices, static fn($d) => $selection[$d] ?? false));

        // First pass: equipment, ports and VLANs, so that every link target exists
        foreach ($switches as $switch) {
            $this->guard("switch $switch", fn() => $this->importSwitch($switch));
        }
        // Second pass: uplinks and attached nodes
        foreach ($switches as $switch) {
            if (!isset($this->switchIds[$switch])) {
                continue;
            }
            $this->guard("links of $switch", fn() => $this->importLinks($switch));
            $this->guard("nodes of $switch", fn() => $this->importNodes($switch));
        }

        return $this->finish();
    }

    private function finish(): ImportResult
    {
        Config::save([
            'last_run'   => date('Y-m-d H:i:s'),
            'last_stats' => json_encode(['stats' => $this->result->stats, 'warnings' => array_slice($this->result->warnings, 0, 100)]),
        ]);
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

    // ------------------------------------------------------------------ switches

    private function importSwitch(string $name): void
    {
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
        $row = $this->findOne('glpi_networkports', [
            'itemtype'   => $itemtype,
            'items_id'   => $itemId,
            'name'       => $name,
            'is_deleted' => 0,
        ]);
        if ($row) {
            if ($mac !== '' && $row['mac'] !== $mac) {
                (new NetworkPort())->update(['id' => $row['id'], 'mac' => $mac, 'name' => $name]);
            }
            return (int) $row['id'];
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

        $vlanId  = $this->ensureVlan($tag);
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
        if ($this->oppositePort($a) === $b) {
            return false;
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
            $peer = $link['neighbor'];
            if ($link['ifname'] === '' || $link['nbrifname'] === '' || !isset($this->switchIds[$peer])) {
                continue;
            }
            $local  = $this->ensurePort('NetworkEquipment', $this->switchIds[$switch], $link['ifname']);
            $remote = $this->ensurePort('NetworkEquipment', $this->switchIds[$peer], $link['nbrifname']);
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
            $this->guard("port $ifname of $switch", function () use ($switch, $switchId, $ifname, $nodes) {
                $realPort = $this->ensurePort('NetworkEquipment', $switchId, $ifname);
                $location = $this->portLocation($realPort, $switchId);

                if (count($nodes) === 1) {
                    $node = reset($nodes);
                    $this->result->inc('nodes');
                    $computerPort = $this->importNode($node, $location);
                    $this->wire($computerPort, $realPort);
                    return;
                }

                // Several MAC addresses behind one port: an unmanaged switch or hub
                $virtualName = "$switch-$ifname-VirtualSwitch";
                $virtualId   = $this->ensureEquipment($virtualName, ['locations_id' => $location]);
                $uplink      = $this->ensurePort('NetworkEquipment', $virtualId, 'uplink');
                if ($this->wire($uplink, $realPort)) {
                    $this->result->inc('virtual_switches');
                }
                foreach ($nodes as $node) {
                    $this->result->inc('nodes');
                    $computerPort = $this->importNode($node, $location);
                    $this->wire($computerPort, $this->freeVirtualPort($virtualId, $computerPort));
                }
            });
        }
    }

    /** The virtual port already wired to $computerPort, or the first free one (created if needed). */
    private function freeVirtualPort(int $virtualId, int $computerPort): int
    {
        global $DB;

        $current = $this->oppositePort($computerPort);
        $ports   = [];
        foreach ($DB->request([
            'FROM'  => 'glpi_networkports',
            'WHERE' => ['itemtype' => 'NetworkEquipment', 'items_id' => $virtualId, 'is_deleted' => 0, 'name' => ['LIKE', 'port %']],
            'ORDER' => 'id',
        ]) as $port) {
            if ((int) $port['id'] === $current) {
                return $current;
            }
            $ports[] = (int) $port['id'];
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

        $mac = $node['mac'];
        $computer = null;
        $portId   = 0;

        // 1. Same MAC address on a computer's port
        foreach ($DB->request(['FROM' => 'glpi_networkports', 'WHERE' => ['mac' => $mac, 'itemtype' => 'Computer', 'is_deleted' => 0]]) as $port) {
            $row = $this->findOne('glpi_computers', ['id' => $port['items_id'], 'is_deleted' => 0]);
            if ($row) {
                $computer = $row;
                $portId   = (int) $port['id'];
                break;
            }
        }

        // 2. Same host name
        $hostname = $this->hostname($node);
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
            $id = (new Computer())->add(['name' => $hostname, 'entities_id' => $this->entity]);
            if (!$id) {
                throw new \RuntimeException("cannot create computer $hostname");
            }
            $this->result->inc('computers_created');
            $computer = $this->findOne('glpi_computers', ['id' => $id]);
        }

        $computerId = (int) $computer['id'];
        if (!$portId) {
            $existing = iterator_count($DB->request(['FROM' => 'glpi_networkports', 'WHERE' => ['itemtype' => 'Computer', 'items_id' => $computerId, 'is_deleted' => 0]]));
            $portId = $this->ensurePort('Computer', $computerId, 'eth' . $existing, '', $mac);
        }

        if ($node['ip'] !== '') {
            $this->setIp($portId, $node['ip']);
        }

        $update = [];
        $seen   = "NeDi import\nFirst seen: " . $this->date($node['firstseen']) . "\nLast seen: " . $this->date($node['lastseen']);
        if ($computer['comment'] === null || $computer['comment'] === '' || str_starts_with($computer['comment'], 'NeDi import')) {
            if ($computer['comment'] !== $seen) {
                $update['comment'] = $seen;
            }
        }
        if ($location && !$computer['locations_id']) {
            $update['locations_id'] = $location;
        }
        if ($update) {
            (new Computer())->update(['id' => $computerId] + $update);
        }

        return $portId;
    }

    private function setIp(int $portId, string $ip): void
    {
        global $DB;

        $name = $this->findOne('glpi_networknames', ['itemtype' => 'NetworkPort', 'items_id' => $portId, 'is_deleted' => 0]);
        if ($name) {
            $nameId = (int) $name['id'];
        } else {
            $nameId = (int) (new NetworkName())->add(['itemtype' => 'NetworkPort', 'items_id' => $portId, 'entities_id' => $this->entity]);
            if (!$nameId) {
                throw new \RuntimeException("cannot create network name for port #$portId");
            }
        }

        $found = false;
        foreach ($DB->request(['FROM' => 'glpi_ipaddresses', 'WHERE' => ['itemtype' => 'NetworkName', 'items_id' => $nameId, 'is_deleted' => 0]]) as $row) {
            if ($row['name'] === $ip) {
                $found = true;
            } else {
                (new IPAddress())->delete(['id' => $row['id']], true);
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
