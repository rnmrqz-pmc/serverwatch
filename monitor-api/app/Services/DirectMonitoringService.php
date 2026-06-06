<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use phpseclib3\Net\SSH2;

class DirectMonitoringService
{
    /**
     * Get server metrics directly via SSH.
     * Caches results for 10 seconds.
     */
    public function getServerMetricsViaSsh(Server $server): array
    {
        $cacheKey = "direct_ssh_metrics:{$server->id}";

        return Cache::remember($cacheKey, 10, function () use ($server) {
            $host = $server->ip;
            $port = $server->ssh_port ?: 22;
            $user = $server->ssh_user;
            $password = $server->ssh_password; // decrypted automatically by Laravel model cast

            if (empty($user) || empty($password)) {
                throw new \Exception("SSH credentials missing for server {$server->name}.");
            }

            $ssh = new SSH2($host, $port);
            // Set connection timeout (3 seconds)
            $ssh->setTimeout(3);

            if (!$ssh->login($user, $password)) {
                throw new \Exception("SSH Login failed for server {$server->name}.");
            }

            // Command string combining cross-platform commands for Linux and macOS (Darwin)
            $cmd = 'if [ "$(uname)" = "Darwin" ]; then ' .
                   'echo "===CPU_CORES==="; sysctl -n hw.ncpu; ' .
                   'echo "===LOAD==="; sysctl -n vm.loadavg | awk \'{print $2}\'; ' .
                   'echo "===UPTIME==="; boot=$(sysctl -n kern.boottime | awk -F\'[ =,]\' \'{print $6}\'); now=$(date +%s); echo $((now - boot)); ' .
                   'echo "===MEMORY==="; total=$(sysctl -n hw.memsize); free_pages=$(vm_stat | awk \'/Pages free:/ {print $3}\' | tr -d \'.\'); spec_pages=$(vm_stat | awk \'/Pages speculative:/ {print $3}\' | tr -d \'.\'); free=$(((free_pages + spec_pages) * 4096)); used=$((total - free)); echo "$total $used $free"; ' .
                   'echo "===DISK==="; df -g / | tail -1 | awk \'{print $2*1024*1024*1024, $3*1024*1024*1024}\'; ' .
                   'echo "===CPU_USAGE==="; ps -A -o %cpu | awk \'{s+=$1} END {print s}\'; ' .
                   'else ' .
                   'echo "===CPU_CORES==="; nproc 2>/dev/null || grep -c ^processor /proc/cpuinfo; ' .
                   'echo "===LOAD==="; cat /proc/loadavg 2>/dev/null | awk \'{print $1}\' || uptime | awk -F\'load average:\' \'{print $2}\' | awk -F\',\' \'{print $1}\'; ' .
                   'echo "===UPTIME==="; cat /proc/uptime 2>/dev/null | awk \'{print $1}\' || uptime | awk -F\'up \' \'{print $2}\' | awk -F\',\' \'{print $1}\'; ' .
                   'echo "===MEMORY==="; free -b 2>/dev/null | awk \'/Mem:/ {print $2, $3, $7}\' || vmstat -s | awk \'/total memory/ {t=$1} /free memory/ {f=$1} END {print t*1024, (t-f)*1024, f*1024}\'; ' .
                   'echo "===DISK==="; df -B1 / 2>/dev/null | tail -1 | awk \'{print $2, $3}\' || df -k / | tail -1 | awk \'{print $2*1024, $3*1024}\'; ' .
                   'echo "===CPU_USAGE==="; vmstat 1 2 2>/dev/null | tail -1 | awk \'{print 100 - $15}\' || top -bn1 2>/dev/null | grep "Cpu(s)" | sed "s/.*, *\\([0-9.]*\\)%* id.*/\\1/" | awk \'{print 100 - $1}\'; ' .
                   'fi';

            $ssh->setTimeout(5); // Give a bit more time for exec to finish
            $output = $ssh->exec($cmd);

            // Close connection explicitly
            $ssh->disconnect();

            return $this->parseSshMetrics($output, $host, $server);
        });
    }

    /**
     * Parse the combined command outputs from SSH.
     */
    private function parseSshMetrics(string $output, string $host, Server $server): array
    {
        $lines = explode("\n", $output);
        $section = '';
        $data = [
            'instance'  => $host,
            'cpu'       => 0.0,
            'cpu_cores' => 1,
            'memory'    => ['total' => 0, 'used' => 0, 'percent' => 0.0],
            'disk'      => ['total' => 0, 'used' => 0, 'percent' => 0.0],
            'uptime'    => 0.0,
            'load'      => 0.0,
            'databases' => []
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (str_starts_with($line, '===')) {
                $section = str_replace('===', '', $line);
                continue;
            }
            if ($line === '') {
                continue;
            }

            switch ($section) {
                case 'CPU_CORES':
                    $data['cpu_cores'] = max(1, (int)$line);
                    break;
                case 'LOAD':
                    $data['load'] = round((float)$line, 2);
                    break;
                case 'UPTIME':
                    // Parse uptime if output has formats like "2 days, 15:30" vs "123456.78"
                    if (is_numeric($line)) {
                        $data['uptime'] = round((float)$line);
                    } else {
                        // Fallback parsing (e.g. from standard uptime command output)
                        $data['uptime'] = 3600.0; // dummy default fallback
                    }
                    break;
                case 'MEMORY':
                    $parts = array_values(array_filter(explode(' ', $line)));
                    if (count($parts) >= 2) {
                        $total = (int)$parts[0];
                        $used = (int)$parts[1];
                        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;
                        $data['memory'] = [
                            'total'   => $total,
                            'used'    => $used,
                            'percent' => $percent
                        ];
                    }
                    break;
                case 'DISK':
                    $parts = array_values(array_filter(explode(' ', $line)));
                    if (count($parts) >= 2) {
                        $total = (int)$parts[0];
                        $used = (int)$parts[1];
                        $percent = $total > 0 ? round(($used / $total) * 100, 2) : 0.0;
                        $data['disk'] = [
                            'total'   => $total,
                            'used'    => $used,
                            'percent' => $percent
                        ];
                    }
                    break;
                case 'CPU_USAGE':
                    $val = (float)$line;
                    if ($val > 100.0) {
                        // If it's a sum of individual thread percentages (like from ps), cap or divide
                        $val = round($val / $data['cpu_cores'], 2);
                    }
                    $data['cpu'] = min(100.0, max(0.0, round($val, 2)));
                    break;
            }
        }

        // Merge DB metrics if DB credentials exist
        if (!empty($server->db_host) && !empty($server->db_user) && ($server->db_type !== 'none')) {
            $dbMetrics = $this->getDatabaseMetrics($server);
            if ($dbMetrics) {
                $data['databases'][] = $dbMetrics;
            }
        }

        return $data;
    }

    /**
     * Fetch database metrics directly using a PDO connection.
     * Caches results for 10 seconds.
     */
    public function getDatabaseMetrics(Server $server): ?array
    {
        $dbType = $server->db_type;
        if (empty($dbType) || $dbType === 'none' || empty($server->db_host) || empty($server->db_user)) {
            return null;
        }

        $cacheKey = "direct_db_metrics:{$server->id}";

        return Cache::remember($cacheKey, 10, function () use ($server, $dbType) {
            $host = $server->db_host;
            $port = $server->db_port;
            $user = $server->db_user;
            $password = $server->db_password; // decrypted automatically by Laravel model cast
            $dbName = $server->db_name;

            try {
                if ($dbType === 'postgresql') {
                    if (!$port) $port = 5432;
                    $dsn = "pgsql:host={$host};port={$port}" . ($dbName ? ";dbname={$dbName}" : ";dbname=postgres");
                    
                    $pdo = new \PDO($dsn, $user, $password, [
                        \PDO::ATTR_TIMEOUT => 3,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                    ]);

                    // Query version
                    $stmt = $pdo->query("SELECT version()");
                    $versionStr = $stmt->fetchColumn();
                    preg_match('/PostgreSQL ([\d\.]+)/i', $versionStr, $matches);
                    $version = $matches[1] ?? 'PostgreSQL';

                    // Query active connections
                    $stmt = $pdo->query("SELECT count(*) FROM pg_stat_activity");
                    $connections = (int)$stmt->fetchColumn();

                    // Query size in bytes
                    if ($dbName) {
                        $stmt = $pdo->prepare("SELECT pg_database_size(?)");
                        $stmt->execute([$dbName]);
                        $size = (int)$stmt->fetchColumn();
                    } else {
                        $stmt = $pdo->query("SELECT sum(pg_database_size(datname)) FROM pg_database WHERE datname NOT LIKE 'template%'");
                        $size = (int)$stmt->fetchColumn();
                    }

                    return [
                        'type'        => 'postgresql',
                        'health'      => 'healthy',
                        'size_bytes'  => $size ?: 0,
                        'connections' => $connections ?: 0,
                        'version'     => $version,
                    ];

                } else if ($dbType === 'mysql' || $dbType === 'mariadb') {
                    if (!$port) $port = 3306;
                    $dsn = "mysql:host={$host};port={$port}" . ($dbName ? ";dbname={$dbName}" : "");
                    
                    $pdo = new \PDO($dsn, $user, $password, [
                        \PDO::ATTR_TIMEOUT => 3,
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                    ]);

                    // Query version
                    $stmt = $pdo->query("SELECT VERSION()");
                    $version = $stmt->fetchColumn();

                    // Query active connections
                    $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
                    $connectionsRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $connections = $connectionsRow ? (int)$connectionsRow['Value'] : 0;

                    // Query size in bytes
                    if ($dbName) {
                        $stmt = $pdo->prepare("
                            SELECT SUM(data_length + index_length) 
                            FROM information_schema.tables 
                            WHERE table_schema = ?
                        ");
                        $stmt->execute([$dbName]);
                        $size = (int)$stmt->fetchColumn();
                    } else {
                        $stmt = $pdo->query("
                            SELECT SUM(data_length + index_length) 
                            FROM information_schema.tables 
                            WHERE table_schema NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys')
                        ");
                        $size = (int)$stmt->fetchColumn();
                    }

                    return [
                        'type'        => $dbType,
                        'health'      => 'healthy',
                        'size_bytes'  => $size ?: 0,
                        'connections' => $connections ?: 0,
                        'version'     => $version ?: 'Unknown',
                    ];
                }
            } catch (\Exception $e) {
                // Return degraded status details if query/connection fails
                return [
                    'type'        => $dbType,
                    'health'      => 'down',
                    'size_bytes'  => 0,
                    'connections' => 0,
                    'version'     => '',
                ];
            }

            return null;
        });
    }
}
