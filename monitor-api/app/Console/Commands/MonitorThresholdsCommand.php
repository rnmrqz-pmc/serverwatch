<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Server;
use App\Models\User;
use App\Services\PrometheusService;
use App\Services\DirectMonitoringService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

class MonitorThresholdsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:thresholds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check server resource usage against configured CPU, RAM, and Disk thresholds and alert if necessary.';

    /**
     * Execute the console command.
     */
    public function handle(PrometheusService $prometheus, DirectMonitoringService $directMonitor)
    {
        $servers = Server::all();
        $adminUsers = User::all();
        $activeIncidents = [];

        if ($servers->isEmpty()) {
            $this->info('No servers configured for monitoring.');
            Cache::forever('active_threshold_breaches', []);
            return Command::SUCCESS;
        }

        foreach ($servers as $server) {
            $instance = $server->ip;
            $prometheusInstance = str_contains($instance, ':') ? $instance : "{$instance}:9100";

            // Determine if server is up
            $isUp = false;
            try {
                $upQuery = $prometheus->query("up{instance=\"{$prometheusInstance}\"}");
                $isUp = ((float)($upQuery[0]['value'][1] ?? 0)) === 1.0;
            } catch (\Exception $e) {
                $isUp = false;
            }

            // Fetch metrics
            $metrics = [];
            $fallbackUsed = false;

            if ($isUp) {
                try {
                    $metrics = $prometheus->getServerMetrics($instance);
                } catch (\Exception $e) {
                    // Failed to load metrics but target is up
                }
            }

            // Direct SSH fallback
            if (!$isUp && !empty($server->ssh_user) && !empty($server->ssh_password)) {
                try {
                    $metrics = $directMonitor->getServerMetricsViaSsh($server);
                    $isUp = true;
                    $fallbackUsed = true;
                } catch (\Exception $e) {
                    // Direct SSH fallback failed
                }
            }

            if (!$isUp || empty($metrics)) {
                $this->warn("Server {$server->name} ({$server->ip}) is offline or metrics are unavailable. Skipping threshold checks.");
                continue;
            }

            // Extract values
            $cpuUsage = (float) ($metrics['cpu'] ?? 0);
            $ramUsage = (float) ($metrics['memory']['percent'] ?? 0);
            $diskUsage = (float) ($metrics['disk']['percent'] ?? 0);

            // CPU thresholds
            $cpuInfo = (int) $server->cpu_threshold_info;
            $cpuWarning = (int) $server->cpu_threshold_warning;
            $cpuCritical = (int) $server->cpu_threshold_critical;

            // RAM thresholds
            $ramInfo = (int) $server->ram_threshold_info;
            $ramWarning = (int) $server->ram_threshold_warning;
            $ramCritical = (int) $server->ram_threshold_critical;

            // Disk thresholds
            $diskInfo = (int) $server->disk_threshold_info;
            $diskWarning = (int) $server->disk_threshold_warning;
            $diskCritical = (int) $server->disk_threshold_critical;

            // Evaluate CPU
            $cpuSeverity = $this->evaluateBreach($cpuUsage, $cpuInfo, $cpuWarning, $cpuCritical);
            $this->processMetricAlert($server, 'CPU', $cpuUsage, $cpuSeverity, $adminUsers, $activeIncidents);

            // Evaluate RAM
            $ramSeverity = $this->evaluateBreach($ramUsage, $ramInfo, $ramWarning, $ramCritical);
            $this->processMetricAlert($server, 'RAM', $ramUsage, $ramSeverity, $adminUsers, $activeIncidents);

            // Evaluate Disk
            $diskSeverity = $this->evaluateBreach($diskUsage, $diskInfo, $diskWarning, $diskCritical);
            $this->processMetricAlert($server, 'Disk', $diskUsage, $diskSeverity, $adminUsers, $activeIncidents);
        }

        // Save active incidents in cache
        Cache::forever('active_threshold_breaches', $activeIncidents);

        $this->info('Server thresholds checked successfully. Firing incidents count: ' . count($activeIncidents));
        return Command::SUCCESS;
    }

    private function evaluateBreach(float $value, int $info, int $warning, int $critical): string
    {
        if ($value >= $critical) {
            return 'critical';
        }
        if ($value >= $warning) {
            return 'warning';
        }
        if ($value >= $info) {
            return 'info';
        }
        return 'ok';
    }

    private function processMetricAlert(Server $server, string $metric, float $value, string $currentSeverity, $adminUsers, &$activeIncidents)
    {
        $cacheKey = "server_{$server->id}_{$metric}_alert_level";
        $lastSeverity = Cache::get($cacheKey, 'ok');

        // If state changed
        if ($currentSeverity !== $lastSeverity) {
            if ($currentSeverity !== 'ok') {
                // Determine threshold value breached
                $thresholdField = strtolower($metric) . '_threshold_' . ($currentSeverity === 'warning' ? 'warning' : $currentSeverity);
                // Fallback if field name doesn't match completely (e.g. ram_threshold_warning)
                $thresholdVal = $server->{$thresholdField} ?? 0;

                // Send email alert
                $this->sendEmailAlert($server, $metric, $value, $currentSeverity, $thresholdVal, $adminUsers);
            } else {
                // Resolved
                $this->sendEmailResolution($server, $metric, $value, $adminUsers);
            }

            // Update cached state
            Cache::forever($cacheKey, $currentSeverity);
        }

        // If it is active, add to activeIncidents list
        if ($currentSeverity !== 'ok') {
            $thresholdField = strtolower($metric) . '_threshold_' . ($currentSeverity === 'warning' ? 'warning' : $currentSeverity);
            $thresholdVal = $server->{$thresholdField} ?? 0;

            $activeIncidents[] = [
                'id'          => md5("threshold_{$server->id}_{$metric}"),
                'name'        => "High" . $metric . "Usage",
                'instance'    => $server->ip,
                'severity'    => $currentSeverity, // info, warning, critical
                'state'       => 'firing',
                'summary'     => "{$metric} utilization is at {$value}% (above {$thresholdVal}% threshold) on {$server->name}",
                'started_at'  => Carbon::now()->toIso8601String(),
                'resolved_at' => null,
            ];
        }
    }

    private function sendEmailAlert(Server $server, string $metric, float $value, string $severity, int $thresholdVal, $adminUsers)
    {
        $severityUpper = strtoupper($severity);
        $metricUpper = strtoupper($metric);
        $subject = "[{$severityUpper}] ServerWatcher: {$metricUpper} Threshold Breach on {$server->name}";

        $body = "ServerWatcher Alert Details:\n";
        $body .= "----------------------------\n";
        $body .= "Server: {$server->name} ({$server->ip})\n";
        $body .= "Metric: {$metricUpper}\n";
        $body .= "Current Value: {$value}%\n";
        $body .= "Threshold Value: {$thresholdVal}%\n";
        $body .= "Severity: {$severityUpper}\n";
        $body .= "Time: " . Carbon::now()->toDateTimeString() . "\n\n";
        $body .= "Please check the server dashboard for more details.";

        foreach ($adminUsers as $user) {
            try {
                Mail::raw($body, function ($message) use ($user, $subject) {
                    $message->to($user->email)->subject($subject);
                });
            } catch (\Exception $e) {
                // Log/ignore individual mail delivery errors to prevent blocking command execution
                $this->error("Failed to send alert email to {$user->email}: " . $e->getMessage());
            }
        }
    }

    private function sendEmailResolution(Server $server, string $metric, float $value, $adminUsers)
    {
        $metricUpper = strtoupper($metric);
        $subject = "[RESOLVED] ServerWatcher: {$metricUpper} usage normal on {$server->name}";

        $body = "ServerWatcher Resolution Details:\n";
        $body .= "----------------------------\n";
        $body .= "Server: {$server->name} ({$server->ip})\n";
        $body .= "Metric: {$metricUpper} has returned to normal.\n";
        $body .= "Current Value: {$value}%\n";
        $body .= "Time: " . Carbon::now()->toDateTimeString() . "\n\n";
        $body .= "The alert threshold is no longer breached.";

        foreach ($adminUsers as $user) {
            try {
                Mail::raw($body, function ($message) use ($user, $subject) {
                    $message->to($user->email)->subject($subject);
                });
            } catch (\Exception $e) {
                $this->error("Failed to send resolution email to {$user->email}: " . $e->getMessage());
            }
        }
    }
}
