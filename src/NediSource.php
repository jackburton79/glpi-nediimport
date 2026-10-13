<?php

namespace GlpiPlugin\Nediimport;

use PDO;
use RuntimeException;

/**
 * Read-only access to the NeDi database (tables devices, interfaces, links, nodes).
 *
 * Rows are fetched with SELECT * and mapped by column name, so NeDi versions
 * which add or drop optional columns keep working.
 */
class NediSource
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array<string, string> $cfg plugin settings (see Config::get())
     */
    public static function fromConfig(array $cfg): self
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['nedi_host'],
            (int) $cfg['nedi_port'],
            $cfg['nedi_db']
        );
        try {
            $pdo = new PDO($dsn, $cfg['nedi_user'], $cfg['nedi_pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => 10,
            ]);
        } catch (\PDOException $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
        return new self($pdo);
    }

    /**
     * @return list<array{name: string, ip: string, location: string}>
     */
    public function devices(): array
    {
        $out = [];
        foreach ($this->query('SELECT * FROM devices') as $row) {
            $name = trim((string) ($row['device'] ?? $row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name'     => $name,
                'ip'       => self::ipToString($row['devip'] ?? $row['ip'] ?? null),
                'location' => trim((string) ($row['location'] ?? '')),
            ];
        }
        usort($out, static fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    /**
     * @return list<array{ifname: string, alias: string, pvid: int}>
     */
    public function interfaces(string $device): array
    {
        $out = [];
        foreach ($this->query('SELECT * FROM interfaces WHERE device = ? ORDER BY ifidx', [$device]) as $row) {
            $ifname = trim((string) ($row['ifname'] ?? ''));
            if ($ifname === '') {
                continue;
            }
            $out[] = [
                'ifname' => $ifname,
                'alias'  => trim((string) ($row['alias'] ?? '')),
                'pvid'   => (int) ($row['pvid'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Device to device links discovered by NeDi (the table may not exist).
     *
     * @return list<array{ifname: string, neighbor: string, nbrifname: string}>
     */
    public function links(string $device): array
    {
        try {
            $rows = $this->query('SELECT * FROM links WHERE device = ?', [$device]);
        } catch (\PDOException $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'ifname'    => trim((string) ($row['ifname'] ?? '')),
                'neighbor'  => trim((string) ($row['neighbor'] ?? '')),
                'nbrifname' => trim((string) ($row['nbrifname'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * Nodes seen on a device. NeDi 2.x keeps host name and IP addresses in other
     * tables: nodarp (IPv4, DNS name) and nodnd (IPv6, AAAA name), joined by MAC.
     * Older versions had them directly in nodes; both layouts are handled.
     *
     * @return list<array{name: string, ip: string, mac: string, ifname: string, vlanid: int, firstseen: int, lastseen: int}>
     */
    public function nodes(string $device): array
    {
        $rows = $this->query('SELECT * FROM nodes WHERE device = ?', [$device]);
        $arp  = $this->latestByMac('nodarp', 'ipupdate', $device);
        $nd   = $this->latestByMac('nodnd', 'ip6update', $device);

        $out = [];
        foreach ($rows as $row) {
            $mac = self::normalizeMac($row['mac'] ?? $row['nodmac'] ?? '');
            $a   = $arp[$mac] ?? [];
            $n   = $nd[$mac] ?? [];

            $ip = self::ipToString($row['nodip'] ?? $row['ip'] ?? $a['nodip'] ?? null);
            if ($ip === '') {
                $ip = self::ipToString($n['nodip6'] ?? null);
            }
            $name = trim((string) ($row['name'] ?? $a['aname'] ?? $n['aaaaname'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($n['aaaaname'] ?? ''));
            }

            $out[] = [
                'name'      => $name,
                'ip'        => $ip,
                'mac'       => $mac,
                'ifname'    => trim((string) ($row['ifname'] ?? '')),
                'vlanid'    => (int) ($row['vlanid'] ?? 0),
                'firstseen' => self::toTimestamp($row['firstseen'] ?? 0),
                'lastseen'  => self::toTimestamp($row['lastseen'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * Most recent row per MAC for the nodes of a device, from a table keyed by
     * MAC address (empty if the table does not exist).
     *
     * @return array<string, array<string, mixed>>
     */
    private function latestByMac(string $table, string $timeColumn, string $device): array
    {
        try {
            $rows = $this->query(
                "SELECT t.* FROM `$table` t WHERE t.mac IN (SELECT mac FROM nodes WHERE device = ?)",
                [$device]
            );
        } catch (\PDOException $e) {
            return [];
        }
        $latest = [];
        foreach ($rows as $row) {
            $mac = self::normalizeMac($row['mac'] ?? '');
            if ($mac === '') {
                continue;
            }
            if (!isset($latest[$mac]) || (int) ($row[$timeColumn] ?? 0) >= (int) ($latest[$mac][$timeColumn] ?? 0)) {
                $latest[$mac] = $row;
            }
        }
        return $latest;
    }

    /**
     * @param list<mixed> $params
     * @return list<array<string, mixed>>
     */
    private function query(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** "00:1A:2b-3c.4d5e" => "00:1a:2b:3c:4d:5e", or "" if not a MAC */
    public static function normalizeMac($mac): string
    {
        $hex = strtolower((string) preg_replace('/[^0-9a-fA-F]/', '', (string) $mac));
        if (strlen($hex) !== 12) {
            return '';
        }
        return implode(':', str_split($hex, 2));
    }

    /**
     * NeDi stores IPv4 addresses as unsigned integers; newer versions may also
     * use dotted strings or packed binary (4 or 16 bytes).
     */
    public static function ipToString($ip): string
    {
        if ($ip === null || $ip === '' || $ip === 0 || $ip === '0') {
            return '';
        }
        if (is_int($ip) || (is_string($ip) && ctype_digit($ip))) {
            $n = (int) $ip;
            return $n > 0 && $n <= 0xFFFFFFFF ? long2ip($n) : '';
        }
        $ip = (string) $ip;
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        if (strlen($ip) === 4 || strlen($ip) === 16) {
            $text = @inet_ntop($ip);
            if ($text !== false && $text !== '0.0.0.0' && $text !== '::') {
                return $text;
            }
        }
        return '';
    }

    /** Unix timestamps, or date strings, to a Unix timestamp (0 if unknown) */
    public static function toTimestamp($value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        $ts = strtotime((string) $value);
        return $ts === false ? 0 : $ts;
    }
}
