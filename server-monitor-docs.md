ServerWatch — Infrastructure Monitoring Platform
Full Project Documentation


Project: ServerWatch
Stack: Vue 3 + TypeScript (frontend) · Laravel (backend API) · Prometheus · Grafana · Uptime Kuma
Target: Multi-server Ubuntu infrastructure monitoring with centralized dashboard
Version: 1.0.0



Table of Contents
Project Overview
Architecture
Repository Structure
Prerequisites
Central Monitoring Server Setup
Target Server Agent Setup
Prometheus Configuration
Alertmanager Configuration
Grafana Setup
Uptime Kuma Setup
Laravel Backend API
Vue 3 Frontend Dashboard
Uptime View
Nginx Reverse Proxy
Docker Compose (Full Stack)
Firewall & Security
Alert Rules Reference
API Reference
Environment Variables
Deployment Guide
Troubleshooting
Maintenance & Runbooks



1. Project Overview
ServerWatch is a self-hosted, centralized infrastructure monitoring platform built for teams running multiple Ubuntu servers with Vue 3 + Laravel projects. It provides:

Real-time system health — CPU, RAM, disk, network per server
Uptime history — 7 / 30 / 90-day bar view per server
Application health — Laravel queue, DB, Redis, SSL cert expiry
Alerting — Email, Slack, Telegram, webhook notifications
Log aggregation — Centralized log view via Loki (optional)
Public status page — Shareable uptime page via Uptime Kuma
Goals
Goal
Tool
Scrape & store system metrics
Prometheus + Node Exporter
Visualize dashboards
Grafana
Track uptime history
Uptime Kuma
Application-level health
spatie/laravel-health
Custom dashboard UI
Vue 3 + TypeScript
REST API layer
Laravel
Alerts & notifications
Alertmanager




2. Architecture
┌─────────────────────────────────────────────────────┐
│              Target Servers (each)                  │
│  ┌─────────────────┐   ┌────────────────────────┐   │
│  │  Node Exporter  │   │  Laravel /health       │   │
│  │  :9100/metrics  │   │  spatie/laravel-health  │   │
│  └────────┬────────┘   └───────────┬────────────┘   │
└───────────┼───────────────────────┼────────────────┘
            │  scrape (every 15s)   │  HTTP probe
            ▼                       ▼
┌─────────────────────────────────────────────────────┐
│          Central Monitoring Server                  │
│                                                     │
│  ┌──────────────┐   ┌──────────────┐               │
│  │  Prometheus  │──▶│ Alertmanager │               │
│  │  :9090       │   │  :9093       │               │
│  └──────┬───────┘   └──────┬───────┘               │
│         │                  │ email/slack/webhook    │
│         ▼                  ▼                        │
│  ┌──────────────┐   ┌──────────────┐               │
│  │   Grafana    │   │  Uptime Kuma │               │
│  │   :3000      │   │  :3001       │               │
│  └──────────────┘   └──────────────┘               │
│                                                     │
│  ┌──────────────────────────────────────────────┐  │
│  │         Laravel API  :8000                   │  │
│  │  Queries Prometheus HTTP API                 │  │
│  │  Serves metrics to Vue dashboard             │  │
│  └──────────────────────────────────────────────┘  │
│                                                     │
│  ┌──────────────────────────────────────────────┐  │
│  │         Vue 3 Dashboard  :5173 / :80         │  │
│  │  ServerWatch UI — served via Nginx           │  │
│  └──────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
Data flow
Node Exporter runs on each target server, exposing raw system metrics at :9100/metrics
Prometheus (on the central server) scrapes all targets every 15 seconds and stores time-series data
Alertmanager receives firing alerts from Prometheus and routes them to configured notification channels
Laravel API queries the Prometheus HTTP API and the Uptime Kuma API, then serves structured JSON to the Vue dashboard
Vue 3 frontend renders the dashboard, uptime view, and alert history



3. Repository Structure
serverwatch/
├── monitor-api/               # Laravel backend
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/
│   │   │       ├── ServerController.php
│   │   │       ├── MetricsController.php
│   │   │       ├── UptimeController.php
│   │   │       └── AlertController.php
│   │   └── Services/
│   │       ├── PrometheusService.php
│   │       └── UptimeKumaService.php
│   ├── routes/api.php
│   └── .env.example
│
├── monitor-ui/                # Vue 3 + TypeScript frontend
│   ├── src/
│   │   ├── components/
│   │   │   ├── ServerCard.vue
│   │   │   ├── UptimeBar.vue
│   │   │   ├── MetricGauge.vue
│   │   │   ├── AlertBadge.vue
│   │   │   └── ServiceStatus.vue
│   │   ├── views/
│   │   │   ├── Dashboard.vue
│   │   │   ├── ServerDetail.vue
│   │   │   ├── UptimeView.vue
│   │   │   └── Alerts.vue
│   │   ├── stores/
│   │   │   ├── servers.ts
│   │   │   └── alerts.ts
│   │   ├── composables/
│   │   │   ├── useMetrics.ts
│   │   │   └── useUptime.ts
│   │   └── types/
│   │       └── index.ts
│   └── .env.example
│
├── infra/                     # Infrastructure config
│   ├── prometheus/
│   │   ├── prometheus.yml
│   │   └── alerts.yml
│   ├── alertmanager/
│   │   └── alertmanager.yml
│   ├── grafana/
│   │   └── provisioning/
│   │       ├── datasources/
│   │       └── dashboards/
│   ├── nginx/
│   │   └── serverwatch.conf
│   └── docker-compose.yml
│
├── scripts/
│   ├── install-agent.sh       # Run on each target server
│   ├── install-central.sh     # Run on monitoring server
│   └── add-server.sh          # Add a new target server
│
└── README.md



4. Prerequisites
Central monitoring server
Requirement
Minimum
OS
Ubuntu 20.04 / 22.04
CPU
2 cores
RAM
4 GB
Disk
50 GB (Prometheus TSDB grows ~1–2 GB/month per 10 servers)
Open ports
80, 443, 3000, 3001, 9090, 9093

Each target server
Requirement
Notes
OS
Ubuntu 20.04 / 22.04
Open port
9100 (Node Exporter) — restricted to monitor server IP only
Laravel
spatie/laravel-health installed
PHP
8.1+

Software versions
Software
Version
Prometheus
2.52+
Node Exporter
1.8+
Alertmanager
0.27+
Grafana
10.x
Uptime Kuma
1.23+
Laravel
10 / 11
Vue
3.4+
Node.js
20+
Docker
24+ (optional)




5. Central Monitoring Server Setup
5.1 Install Prometheus
# Create user
sudo useradd --no-create-home --shell /bin/false prometheus

# Download
PROM_VERSION="2.52.0"
wget https://github.com/prometheus/prometheus/releases/download/v${PROM_VERSION}/prometheus-${PROM_VERSION}.linux-amd64.tar.gz
tar xvf prometheus-${PROM_VERSION}.linux-amd64.tar.gz

# Install binaries
sudo cp prometheus-${PROM_VERSION}.linux-amd64/{prometheus,promtool} /usr/local/bin/
sudo chown prometheus:prometheus /usr/local/bin/{prometheus,promtool}

# Install config files
sudo mkdir -p /etc/prometheus /var/lib/prometheus
sudo cp -r prometheus-${PROM_VERSION}.linux-amd64/{consoles,console_libraries} /etc/prometheus/
sudo chown -R prometheus:prometheus /etc/prometheus /var/lib/prometheus

# Create systemd service
sudo tee /etc/systemd/system/prometheus.service > /dev/null <<EOF
[Unit]
Description=Prometheus
After=network.target

[Service]
User=prometheus
ExecStart=/usr/local/bin/prometheus \
  --config.file=/etc/prometheus/prometheus.yml \
  --storage.tsdb.path=/var/lib/prometheus/ \
  --storage.tsdb.retention.time=90d \
  --web.console.templates=/etc/prometheus/consoles \
  --web.console.libraries=/etc/prometheus/console_libraries \
  --web.listen-address=0.0.0.0:9090
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now prometheus
5.2 Install Alertmanager
AM_VERSION="0.27.0"
wget https://github.com/prometheus/alertmanager/releases/download/v${AM_VERSION}/alertmanager-${AM_VERSION}.linux-amd64.tar.gz
tar xvf alertmanager-${AM_VERSION}.linux-amd64.tar.gz

sudo cp alertmanager-${AM_VERSION}.linux-amd64/{alertmanager,amtool} /usr/local/bin/
sudo mkdir -p /etc/alertmanager /var/lib/alertmanager

sudo tee /etc/systemd/system/alertmanager.service > /dev/null <<EOF
[Unit]
Description=Alertmanager
After=network.target

[Service]
User=prometheus
ExecStart=/usr/local/bin/alertmanager \
  --config.file=/etc/alertmanager/alertmanager.yml \
  --storage.path=/var/lib/alertmanager
Restart=always

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable --now alertmanager
5.3 Install Grafana
sudo apt install -y apt-transport-https software-properties-common
wget -q -O - https://packages.grafana.com/gpg.key | sudo apt-key add -
echo "deb https://packages.grafana.com/oss/oss/deb stable main" | \
  sudo tee /etc/apt/sources.list.d/grafana.list
sudo apt update && sudo apt install -y grafana
sudo systemctl enable --now grafana-server



6. Target Server Agent Setup
Run this script on every server you want to monitor:

#!/bin/bash
# scripts/install-agent.sh
# Usage: sudo bash install-agent.sh

set -e

NE_VERSION="1.8.1"
MONITOR_SERVER_IP="YOUR_MONITOR_SERVER_IP"

echo "Installing Node Exporter ${NE_VERSION}..."

useradd --no-create-home --shell /bin/false node_exporter 2>/dev/null || true

wget -q https://github.com/prometheus/node_exporter/releases/download/v${NE_VERSION}/node_exporter-${NE_VERSION}.linux-amd64.tar.gz
tar xvf node_exporter-${NE_VERSION}.linux-amd64.tar.gz
cp node_exporter-${NE_VERSION}.linux-amd64/node_exporter /usr/local/bin/
chown node_exporter:node_exporter /usr/local/bin/node_exporter

tee /etc/systemd/system/node_exporter.service > /dev/null <<EOF
[Unit]
Description=Node Exporter
After=network.target

[Service]
User=node_exporter
ExecStart=/usr/local/bin/node_exporter \
  --collector.systemd \
  --collector.processes
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now node_exporter

# Restrict port 9100 to monitoring server only
ufw allow from ${MONITOR_SERVER_IP} to any port 9100
ufw deny 9100

echo "Node Exporter installed and running on :9100"
echo "Port 9100 restricted to ${MONITOR_SERVER_IP}"
Install spatie/laravel-health on each Laravel app
composer require spatie/laravel-health

php artisan vendor:publish --tag="health-config"
php artisan vendor:publish --tag="health-migrations"
php artisan migrate

Register health checks in app/Providers/AppServiceProvider.php:

use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Checks\Checks\CpuLoadCheck;

public function boot(): void
{
    Health::checks([
        DatabaseCheck::new(),
        RedisCheck::new(),
        QueueCheck::new(),
        UsedDiskSpaceCheck::new()->warnWhenUsedSpaceIsAbovePercentage(70)
                                 ->failWhenUsedSpaceIsAbovePercentage(90),
        CpuLoadCheck::new()->failWhenLoadIsHigherInTheLast5Minutes(2.0),
    ]);
}

Add the health route to routes/web.php:

use Spatie\Health\Http\Controllers\HealthCheckResultsController;

Route::get('/health', HealthCheckResultsController::class);



7. Prometheus Configuration
/etc/prometheus/prometheus.yml:

global:
  scrape_interval:     15s
  evaluation_interval: 15s
  external_labels:
    monitor: 'serverwatch'

alerting:
  alertmanagers:
    - static_configs:
        - targets: ['localhost:9093']

rule_files:
  - "/etc/prometheus/alerts.yml"

scrape_configs:

  # System metrics from all target servers
  - job_name: 'node'
    static_configs:
      - targets:
          - '192.168.1.10:9100'
          - '192.168.1.11:9100'
          - '192.168.1.20:9100'
          - '192.168.1.30:9100'
        labels:
          env: 'production'
      - targets:
          - '192.168.1.30:9100'
        labels:
          env: 'staging'

  # Laravel application health
  - job_name: 'laravel_health'
    metrics_path: '/health'
    params:
      format: ['prometheus']
    static_configs:
      - targets:
          - '192.168.1.10:80'
          - '192.168.1.11:80'
          - '192.168.1.20:80'

  # Prometheus self-monitoring
  - job_name: 'prometheus'
    static_configs:
      - targets: ['localhost:9090']
Adding a new server
To add 192.168.1.40 as a new target:

Run install-agent.sh on the new server
Add the IP to the targets list in prometheus.yml
Reload Prometheus config (no restart needed):

curl -X POST http://localhost:9090/-/reload



8. Alertmanager Configuration
/etc/alertmanager/alertmanager.yml:

global:
  smtp_smarthost: 'smtp.gmail.com:587'
  smtp_from: 'alerts@yourdomain.com'
  smtp_auth_username: 'alerts@yourdomain.com'
  smtp_auth_password: 'YOUR_APP_PASSWORD'

templates:
  - '/etc/alertmanager/templates/*.tmpl'

route:
  group_by: ['alertname', 'instance']
  group_wait:      30s
  group_interval:  5m
  repeat_interval: 4h
  receiver: 'default'
  routes:
    - match:
        severity: critical
      receiver: 'critical-alerts'
    - match:
        severity: warning
      receiver: 'warning-alerts'

receivers:
  - name: 'default'
    email_configs:
      - to: 'admin@yourdomain.com'

  - name: 'critical-alerts'
    email_configs:
      - to: 'admin@yourdomain.com'
        subject: '[CRITICAL] {{ .GroupLabels.alertname }} on {{ .GroupLabels.instance }}'
    slack_configs:
      - api_url: 'YOUR_SLACK_WEBHOOK_URL'
        channel: '#alerts-critical'
        title: 'CRITICAL: {{ .GroupLabels.alertname }}'
        text: '{{ range .Alerts }}{{ .Annotations.summary }}{{ end }}'

  - name: 'warning-alerts'
    slack_configs:
      - api_url: 'YOUR_SLACK_WEBHOOK_URL'
        channel: '#alerts-warning'

inhibit_rules:
  - source_match:
      severity: 'critical'
    target_match:
      severity: 'warning'
    equal: ['alertname', 'instance']



9. Grafana Setup
9.1 Add Prometheus data source
Go to http://YOUR_MONITOR_IP:3000
Login: admin / admin (change immediately)
Navigate to Connections → Data sources → Add data source
Select Prometheus, set URL: http://localhost:9090
Click Save & test
9.2 Import dashboards
Dashboard
Grafana ID
Purpose
Node Exporter Full
1860
CPU, RAM, disk, network per server
Node Exporter Quickstart
13978
Simplified overview
Alertmanager
9578
Alert history
Laravel Health
13659
App-level health


Import: Dashboards → Import → Enter ID → Load
9.3 Provisioning via files (recommended)
infra/grafana/provisioning/datasources/prometheus.yml:

apiVersion: 1
datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus:9090
    isDefault: true



10. Uptime Kuma Setup
Install via Docker
docker run -d \
  --restart=always \
  --name uptime-kuma \
  -p 3001:3001 \
  -v uptime-kuma:/app/data \
  louislam/uptime-kuma:1
Configure monitors
Add each server through the UI at http://YOUR_MONITOR_IP:3001:

Monitor name
Type
Target
Interval
prod-web-01 system
TCP Port
192.168.1.10:9100
60s
prod-web-01 app
HTTP(s)
http://192.168.1.10/health
60s
prod-web-02 system
TCP Port
192.168.1.11:9100
60s
prod-web-02 app
HTTP(s)
http://192.168.1.11/health
60s
prod-api-01 system
TCP Port
192.168.1.20:9100
60s
prod-api-01 app
HTTP(s)
http://192.168.1.20/health
60s
staging-01
TCP Port
192.168.1.30:9100
60s

Public status page
In Uptime Kuma → Status Pages → New Status Page — create a public page and add all monitors to it. This becomes your public-facing uptime URL.



11. Laravel Backend API
11.1 PrometheusService
app/Services/PrometheusService.php:

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PrometheusService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('monitoring.prometheus_url', 'http://localhost:9090');
    }

    public function query(string $query): array
    {
        $response = Http::get("{$this->baseUrl}/api/v1/query", [
            'query' => $query,
        ]);
        return $response->json('data.result', []);
    }

    public function queryRange(string $query, int $start, int $end, string $step = '60'): array
    {
        $response = Http::get("{$this->baseUrl}/api/v1/query_range", [
            'query' => $query,
            'start' => $start,
            'end'   => $end,
            'step'  => $step,
        ]);
        return $response->json('data.result', []);
    }

    public function getServerMetrics(string $instance): array
    {
        return Cache::remember("metrics:{$instance}", 15, function () use ($instance) {
            return [
                'cpu'    => $this->getCpu($instance),
                'memory' => $this->getMemory($instance),
                'disk'   => $this->getDisk($instance),
                'uptime' => $this->getUptime($instance),
                'load'   => $this->getLoad($instance),
            ];
        });
    }

    private function getCpu(string $instance): float
    {
        $result = $this->query(
            "100 - (avg by(instance)(rate(node_cpu_seconds_total{mode=\"idle\",instance=\"{$instance}\"}[5m])) * 100)"
        );
        return round((float)($result[0]['value'][1] ?? 0), 2);
    }

    private function getMemory(string $instance): array
    {
        $total = $this->query("node_memory_MemTotal_bytes{instance=\"{$instance}\"}")[0]['value'][1] ?? 0;
        $avail = $this->query("node_memory_MemAvailable_bytes{instance=\"{$instance}\"}")[0]['value'][1] ?? 0;
        $used  = $total - $avail;
        return [
            'total'   => (int)$total,
            'used'    => (int)$used,
            'percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function getDisk(string $instance): array
    {
        $total = $this->query("node_filesystem_size_bytes{instance=\"{$instance}\",mountpoint=\"/\"}")[0]['value'][1] ?? 0;
        $free  = $this->query("node_filesystem_free_bytes{instance=\"{$instance}\",mountpoint=\"/\"}")[0]['value'][1] ?? 0;
        $used  = $total - $free;
        return [
            'total'   => (int)$total,
            'used'    => (int)$used,
            'percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    private function getUptime(string $instance): float
    {
        $result = $this->query("node_time_seconds{instance=\"{$instance}\"} - node_boot_time_seconds{instance=\"{$instance}\"}");
        return round((float)($result[0]['value'][1] ?? 0));
    }

    private function getLoad(string $instance): float
    {
        $result = $this->query("node_load1{instance=\"{$instance}\"}");
        return round((float)($result[0]['value'][1] ?? 0), 2);
    }
}
11.2 UptimeController
app/Http/Controllers/UptimeController.php:

<?php

namespace App\Http\Controllers;

use App\Services\PrometheusService;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class UptimeController extends Controller
{
    public function __construct(private PrometheusService $prometheus) {}

    public function history(string $instance, int $days = 90): JsonResponse
    {
        $end   = now()->timestamp;
        $start = now()->subDays($days)->timestamp;

        $results = $this->prometheus->queryRange(
            query: "up{instance=\"{$instance}:9100\"}",
            start: $start,
            end:   $end,
            step:  '86400'  // 1-day buckets
        );

        $history = collect($results[0]['values'] ?? [])
            ->map(fn($point) => [
                'date'   => Carbon::createFromTimestamp($point[0])->toDateString(),
                'status' => ((float)$point[1] >= 0.9) ? 'up' : 'down',
                'value'  => round((float)$point[1] * 100, 2),
            ]);

        $uptimePct = $history->where('status', 'up')->count() / max($history->count(), 1) * 100;

        return response()->json([
            'instance'   => $instance,
            'days'       => $days,
            'uptime_pct' => round($uptimePct, 3),
            'history'    => $history->values(),
        ]);
    }

    public function summary(): JsonResponse
    {
        $servers = config('monitoring.servers');

        $data = collect($servers)->map(function ($server) {
            $results = $this->prometheus->queryRange(
                query: "avg_over_time(up{instance=\"{$server['ip']}:9100\"}[90d])",
                start: now()->subDays(90)->timestamp,
                end:   now()->timestamp,
                step:  '86400'
            );

            $latestUp = $this->prometheus->query("up{instance=\"{$server['ip']}:9100\"}");
            $isUp     = ((float)($latestUp[0]['value'][1] ?? 0)) === 1.0;

            return [
                'name'       => $server['name'],
                'instance'   => $server['ip'],
                'role'       => $server['role'],
                'status'     => $isUp ? 'up' : 'down',
                'uptime_pct' => round((float)($results[0]['values'][0][1] ?? 0) * 100, 3),
            ];
        });

        return response()->json(['servers' => $data]);
    }
}
11.3 API Routes
routes/api.php:

use App\Http\Controllers\ServerController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\UptimeController;
use App\Http\Controllers\AlertController;

Route::prefix('v1')->middleware('api')->group(function () {

    // Server list & status
    Route::get('/servers',                [ServerController::class, 'index']);
    Route::get('/servers/{instance}',     [ServerController::class, 'show']);

    // Real-time metrics
    Route::get('/metrics/{instance}',     [MetricsController::class, 'current']);
    Route::get('/metrics/{instance}/history', [MetricsController::class, 'history']);

    // Uptime
    Route::get('/uptime',                 [UptimeController::class, 'summary']);
    Route::get('/uptime/{instance}',      [UptimeController::class, 'history']);

    // Alerts
    Route::get('/alerts',                 [AlertController::class, 'index']);
    Route::get('/alerts/active',          [AlertController::class, 'active']);
});
11.4 Config file
config/monitoring.php:

<?php

return [
    'prometheus_url' => env('PROMETHEUS_URL', 'http://localhost:9090'),
    'alertmanager_url' => env('ALERTMANAGER_URL', 'http://localhost:9093'),
    'uptime_kuma_url' => env('UPTIME_KUMA_URL', 'http://localhost:3001'),

    'servers' => [
        [
            'name'     => 'prod-web-01',
            'ip'       => '192.168.1.10',
            'role'     => 'Vue + Laravel',
            'env'      => 'production',
        ],
        [
            'name'     => 'prod-web-02',
            'ip'       => '192.168.1.11',
            'role'     => 'Vue + Laravel',
            'env'      => 'production',
        ],
        [
            'name'     => 'prod-api-01',
            'ip'       => '192.168.1.20',
            'role'     => 'Laravel API',
            'env'      => 'production',
        ],
        [
            'name'     => 'staging-01',
            'ip'       => '192.168.1.30',
            'role'     => 'Staging env',
            'env'      => 'staging',
        ],
    ],
];



12. Vue 3 Frontend Dashboard
12.1 TypeScript types
src/types/index.ts:

export type ServerStatus = 'up' | 'down' | 'degraded' | 'unknown'
export type UptimeBarStatus = 'up' | 'down' | 'degraded' | 'no-data'

export interface Server {
  name: string
  instance: string
  role: string
  env: 'production' | 'staging'
  status: ServerStatus
  uptime_pct: number
}

export interface ServerMetrics {
  instance: string
  cpu: number
  memory: {
    total: number
    used: number
    percent: number
  }
  disk: {
    total: number
    used: number
    percent: number
  }
  uptime: number
  load: number
}

export interface UptimeDay {
  date: string
  status: UptimeBarStatus
  value: number
}

export interface UptimeHistory {
  instance: string
  days: number
  uptime_pct: number
  history: UptimeDay[]
}

export interface Alert {
  id: string
  name: string
  instance: string
  severity: 'critical' | 'warning' | 'info'
  state: 'firing' | 'resolved'
  summary: string
  started_at: string
  resolved_at: string | null
}
12.2 API composable
src/composables/useMetrics.ts:

import { ref, onMounted, onUnmounted } from 'vue'
import type { ServerMetrics } from '@/types'

export function useMetrics(instance: string, pollInterval = 15000) {
  const metrics = ref<ServerMetrics | null>(null)
  const loading = ref(true)
  const error   = ref<string | null>(null)
  let timer: ReturnType<typeof setInterval>

  async function fetchMetrics() {
    try {
      const res = await fetch(`/api/v1/metrics/${instance}`)
      if (!res.ok) throw new Error(`HTTP ${res.status}`)
      metrics.value = await res.json()
      error.value   = null
    } catch (e) {
      error.value = (e as Error).message
    } finally {
      loading.value = false
    }
  }

  onMounted(() => {
    fetchMetrics()
    timer = setInterval(fetchMetrics, pollInterval)
  })

  onUnmounted(() => clearInterval(timer))

  return { metrics, loading, error, refresh: fetchMetrics }
}
12.3 UptimeBar component
src/components/UptimeBar.vue:

<template>
  <div class="uptime-bar-wrapper">
    <div class="bars" role="img" :aria-label="`Uptime history for ${serverName}`">
      <div
        v-for="(day, i) in visibleHistory"
        :key="i"
        class="bar"
        :class="`bar--${day.status}`"
        :title="`${day.date} — ${labelFor(day.status)}`"
        @mouseenter="hovered = day"
        @mouseleave="hovered = null"
      />
    </div>
    <div class="bars-footer">
      <span>{{ days }} days ago</span>
      <span class="uptime-pct" :class="pctClass">{{ uptimePct }}%</span>
      <span>Today</span>
    </div>
    <div v-if="hovered" class="tooltip">
      {{ hovered.date }} — {{ labelFor(hovered.status) }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { UptimeDay } from '@/types'

const props = defineProps<{
  serverName: string
  history: UptimeDay[]
  days: number
}>()

const hovered = ref<UptimeDay | null>(null)

const visibleHistory = computed(() => props.history.slice(-props.days))

const uptimePct = computed(() => {
  const up = visibleHistory.value.filter(d => d.status === 'up').length
  return ((up / visibleHistory.value.length) * 100).toFixed(2)
})

const pctClass = computed(() => {
  const p = parseFloat(uptimePct.value)
  if (p >= 99.9) return 'pct--excellent'
  if (p >= 99)   return 'pct--good'
  if (p >= 95)   return 'pct--warning'
  return 'pct--critical'
})

function labelFor(status: string): string {
  return { up: 'Operational', down: 'Outage', degraded: 'Degraded', 'no-data': 'No data' }[status] ?? status
}
</script>
12.4 Pinia store
src/stores/servers.ts:

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Server, ServerMetrics } from '@/types'

export const useServersStore = defineStore('servers', () => {
  const servers  = ref<Server[]>([])
  const metrics  = ref<Record<string, ServerMetrics>>({})
  const loading  = ref(false)
  const lastSync = ref<Date | null>(null)

  const onlineCount  = computed(() => servers.value.filter(s => s.status === 'up').length)
  const criticalCount = computed(() => servers.value.filter(s => s.status === 'down').length)
  const avgUptime    = computed(() => {
    if (!servers.value.length) return 0
    return servers.value.reduce((acc, s) => acc + s.uptime_pct, 0) / servers.value.length
  })

  async function fetchServers() {
    loading.value = true
    try {
      const res = await fetch('/api/v1/servers')
      servers.value = await res.json()
      lastSync.value = new Date()
    } finally {
      loading.value = false
    }
  }

  async function fetchMetrics(instance: string) {
    const res = await fetch(`/api/v1/metrics/${instance}`)
    metrics.value[instance] = await res.json()
  }

  return { servers, metrics, loading, lastSync, onlineCount, criticalCount, avgUptime, fetchServers, fetchMetrics }
})



13. Uptime View
The uptime view is a dedicated page (/uptime) that shows the 7 / 30 / 90-day bar history for every server, modelled after BetterUptime / Statuspage.
What it shows per server
Server name, IP, role
Uptime percentage (colour-coded: green ≥99.9%, amber ≥99%, red <99%)
Bar chart — one bar per day, coloured by status
Incident log — last 3 incidents with duration and description
Current status badge (Operational / Degraded / Outage)
Prometheus query for uptime bars
# Average availability per day over 90 days (1 = fully up, 0 = fully down)
avg_over_time(up{job="node"}[1d])
Laravel endpoint for the uptime page
GET /api/v1/uptime?days=90

Response:
{
  "servers": [
    {
      "name": "prod-web-01",
      "instance": "192.168.1.10",
      "uptime_pct": 99.98,
      "status": "up",
      "history": [
        { "date": "2026-03-01", "status": "up",   "value": 100 },
        { "date": "2026-03-02", "status": "up",   "value": 100 },
        { "date": "2026-03-05", "status": "down", "value": 0   }
      ],
      "incidents": [
        { "date": "2026-03-05", "duration": "4m", "type": "down", "message": "Network blip" }
      ]
    }
  ]
}



14. Nginx Reverse Proxy
infra/nginx/serverwatch.conf:

# Vue 3 dashboard
server {
    listen 80;
    server_name monitor.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name monitor.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/monitor.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/monitor.yourdomain.com/privkey.pem;

    # Vue 3 frontend (built static files)
    location / {
        root /var/www/serverwatch/monitor-ui/dist;
        try_files $uri $uri/ /index.html;
    }

    # Laravel API
    location /api/ {
        proxy_pass         http://127.0.0.1:8000;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
        proxy_set_header   X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # Grafana
    location /grafana/ {
        proxy_pass http://127.0.0.1:3000/;
    }

    # Uptime Kuma
    location /status/ {
        proxy_pass         http://127.0.0.1:3001/;
        proxy_http_version 1.1;
        proxy_set_header   Upgrade $http_upgrade;
        proxy_set_header   Connection "upgrade";
    }
}



15. Docker Compose (Full Stack)
infra/docker-compose.yml:

version: "3.9"

networks:
  monitor: {}

volumes:
  prometheus_data: {}
  grafana_data: {}
  uptime_kuma_data: {}

services:

  prometheus:
    image: prom/prometheus:v2.52.0
    container_name: prometheus
    restart: unless-stopped
    command:
      - '--config.file=/etc/prometheus/prometheus.yml'
      - '--storage.tsdb.path=/prometheus'
      - '--storage.tsdb.retention.time=90d'
    volumes:
      - ./prometheus:/etc/prometheus
      - prometheus_data:/prometheus
    ports:
      - "9090:9090"
    networks: [monitor]

  alertmanager:
    image: prom/alertmanager:v0.27.0
    container_name: alertmanager
    restart: unless-stopped
    volumes:
      - ./alertmanager:/etc/alertmanager
    ports:
      - "9093:9093"
    networks: [monitor]

  grafana:
    image: grafana/grafana:10.4.0
    container_name: grafana
    restart: unless-stopped
    environment:
      - GF_SECURITY_ADMIN_PASSWORD=${GRAFANA_PASSWORD}
      - GF_SERVER_ROOT_URL=https://monitor.yourdomain.com/grafana
      - GF_SERVER_SERVE_FROM_SUB_PATH=true
    volumes:
      - grafana_data:/var/lib/grafana
      - ./grafana/provisioning:/etc/grafana/provisioning
    ports:
      - "3000:3000"
    networks: [monitor]

  uptime-kuma:
    image: louislam/uptime-kuma:1
    container_name: uptime-kuma
    restart: unless-stopped
    volumes:
      - uptime_kuma_data:/app/data
    ports:
      - "3001:3001"
    networks: [monitor]

  monitor-api:
    build:
      context: ../monitor-api
      dockerfile: Dockerfile
    container_name: monitor-api
    restart: unless-stopped
    env_file: ../monitor-api/.env
    ports:
      - "8000:8000"
    networks: [monitor]

  monitor-ui:
    build:
      context: ../monitor-ui
      dockerfile: Dockerfile
    container_name: monitor-ui
    restart: unless-stopped
    ports:
      - "5173:80"
    networks: [monitor]



16. Firewall & Security
Central monitoring server
sudo ufw default deny incoming
sudo ufw default allow outgoing

sudo ufw allow 22/tcp       # SSH
sudo ufw allow 80/tcp       # HTTP (Nginx)
sudo ufw allow 443/tcp      # HTTPS (Nginx)

# Internal services — only accessible via Nginx reverse proxy
# Prometheus, Grafana, Uptime Kuma are NOT exposed directly

sudo ufw enable
Each target server
sudo ufw default deny incoming
sudo ufw allow 22/tcp       # SSH
sudo ufw allow 80/tcp       # HTTP (Laravel app)
sudo ufw allow 443/tcp      # HTTPS

# Node Exporter — only allow monitor server
sudo ufw allow from MONITOR_SERVER_IP to any port 9100

sudo ufw enable
Prometheus basic auth (optional)
Add to prometheus.yml web config:

# /etc/prometheus/web-config.yml
basic_auth_users:
  admin: $2b$12$YOUR_BCRYPT_HASH

Generate hash: htpasswd -nBC 12 admin



17. Alert Rules Reference
/etc/prometheus/alerts.yml:

groups:
  - name: system
    rules:

      - alert: ServerDown
        expr: up == 0
        for: 1m
        labels:
          severity: critical
        annotations:
          summary: "Server {{ $labels.instance }} is unreachable"
          description: "Prometheus cannot scrape {{ $labels.instance }} for more than 1 minute."

      - alert: HighCPU
        expr: |
          100 - (avg by(instance)(
            rate(node_cpu_seconds_total{mode="idle"}[5m])
          ) * 100) > 80
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High CPU on {{ $labels.instance }} ({{ $value | printf \"%.1f\" }}%)"

      - alert: CriticalCPU
        expr: |
          100 - (avg by(instance)(
            rate(node_cpu_seconds_total{mode="idle"}[5m])
          ) * 100) > 95
        for: 2m
        labels:
          severity: critical
        annotations:
          summary: "Critical CPU on {{ $labels.instance }} ({{ $value | printf \"%.1f\" }}%)"

      - alert: HighMemory
        expr: |
          (node_memory_MemTotal_bytes - node_memory_MemAvailable_bytes)
          / node_memory_MemTotal_bytes * 100 > 85
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High RAM on {{ $labels.instance }} ({{ $value | printf \"%.1f\" }}%)"

      - alert: DiskWarning
        expr: |
          (node_filesystem_size_bytes{mountpoint="/"} - node_filesystem_free_bytes{mountpoint="/"})
          / node_filesystem_size_bytes{mountpoint="/"} * 100 > 80
        for: 10m
        labels:
          severity: warning
        annotations:
          summary: "Disk at {{ $value | printf \"%.1f\" }}% on {{ $labels.instance }}"

      - alert: DiskCritical
        expr: |
          (node_filesystem_size_bytes{mountpoint="/"} - node_filesystem_free_bytes{mountpoint="/"})
          / node_filesystem_size_bytes{mountpoint="/"} * 100 > 90
        for: 5m
        labels:
          severity: critical
        annotations:
          summary: "Disk critically full on {{ $labels.instance }}"

      - alert: HighLoad
        expr: node_load5 > (count by(instance)(node_cpu_seconds_total{mode="idle"}) * 0.8)
        for: 5m
        labels:
          severity: warning
        annotations:
          summary: "High load average on {{ $labels.instance }}"

      - alert: SSLCertExpiringSoon
        expr: probe_ssl_earliest_cert_expiry - time() < 86400 * 14
        for: 1h
        labels:
          severity: warning
        annotations:
          summary: "SSL cert on {{ $labels.instance }} expires in < 14 days"



18. API Reference
All endpoints are prefixed with /api/v1.
Servers
Method
Endpoint
Description
GET
/servers
List all servers with current status
GET
/servers/{instance}
Single server details + all metrics

Metrics
Method
Endpoint
Description
GET
/metrics/{instance}
Current CPU, RAM, disk, load
GET
/metrics/{instance}/history?hours=24
Time-series for charts

Uptime
Method
Endpoint
Description
GET
/uptime
Uptime summary for all servers
GET
/uptime/{instance}?days=90
Bar history for one server

Alerts
Method
Endpoint
Description
GET
/alerts
All alerts (last 7 days)
GET
/alerts/active
Currently firing alerts

Example response — GET /api/v1/servers
[
  {
    "name": "prod-web-01",
    "instance": "192.168.1.10",
    "role": "Vue + Laravel",
    "env": "production",
    "status": "up",
    "uptime_pct": 99.98,
    "metrics": {
      "cpu": 38.4,
      "memory": { "total": 8589934592, "used": 6341787648, "percent": 73.8 },
      "disk":   { "total": 214748364800, "used": 109521190912, "percent": 51.0 },
      "load": 1.34,
      "uptime": 3654123
    }
  }
]



19. Environment Variables
monitor-api/.env
APP_NAME=ServerWatch
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://monitor.yourdomain.com

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/serverwatch/monitor-api/database/database.sqlite

PROMETHEUS_URL=http://localhost:9090
ALERTMANAGER_URL=http://localhost:9093
UPTIME_KUMA_URL=http://localhost:3001
UPTIME_KUMA_TOKEN=

CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
monitor-ui/.env
VITE_API_BASE_URL=https://monitor.yourdomain.com/api/v1
VITE_REFRESH_INTERVAL=15000
VITE_APP_TITLE=ServerWatch



20. Deployment Guide
First-time deploy
# 1. Clone repo on monitor server
git clone https://github.com/yourorg/serverwatch.git /var/www/serverwatch
cd /var/www/serverwatch

# 2. Deploy monitoring stack
cd infra
cp .env.example .env
# edit .env with your values
docker compose up -d

# 3. Deploy Laravel API
cd ../monitor-api
composer install --no-dev
cp .env.example .env
# edit .env
php artisan key:generate
php artisan migrate --force

# 4. Build and deploy Vue frontend
cd ../monitor-ui
npm install
npm run build
# built files in dist/ — served by Nginx

# 5. Install Nginx config
sudo cp infra/nginx/serverwatch.conf /etc/nginx/sites-available/serverwatch
sudo ln -s /etc/nginx/sites-available/serverwatch /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# 6. SSL with Let's Encrypt
sudo certbot --nginx -d monitor.yourdomain.com
Adding a new target server
# 1. Run agent installer on the new server
ssh user@NEW_SERVER_IP
sudo bash /path/to/install-agent.sh

# 2. Add to prometheus.yml on monitor server
# Under scrape_configs > node > targets, add:
#   - '192.168.1.XX:9100'

# 3. Reload Prometheus (no downtime)
curl -X POST http://localhost:9090/-/reload

# 4. Add to config/monitoring.php in Laravel API
# 5. Add monitor in Uptime Kuma UI
Update deploy
cd /var/www/serverwatch

git pull

# API
cd monitor-api
composer install --no-dev
php artisan migrate --force
php artisan config:cache
php artisan route:cache

# UI
cd ../monitor-ui
npm install
npm run build

# Restart services
sudo systemctl reload nginx



21. Troubleshooting
Prometheus not scraping a target
# Check target status
curl http://localhost:9090/api/v1/targets | jq '.data.activeTargets[] | {instance, health, lastError}'

# Test reachability from monitor server
curl http://192.168.1.10:9100/metrics | head -20

# Check firewall on target
ssh user@192.168.1.10 "sudo ufw status | grep 9100"
Node Exporter not running on target
ssh user@TARGET_IP
sudo systemctl status node_exporter
sudo journalctl -u node_exporter -n 50
sudo systemctl restart node_exporter
Grafana showing no data
Go to Explore → select Prometheus data source
Run query: up — should show 1 for each target
If empty: check prometheus.yml data source URL is http://localhost:9090 (not http://prometheus:9090 unless using Docker)
Laravel health endpoint returning 500
php artisan health:check
tail -n 50 storage/logs/laravel.log
Alertmanager not sending emails
# Test config
amtool check-config /etc/alertmanager/alertmanager.yml

# Send test alert
amtool alert add alertname="TestAlert" severity="warning" --alertmanager.url=http://localhost:9093

# Check logs
sudo journalctl -u alertmanager -n 50



22. Maintenance & Runbooks
Daily checks (automated via cron)
# Add to crontab on monitor server
# Check all services are running
0 8 * * * /usr/local/bin/check-services.sh | mail -s "ServerWatch Daily Report" admin@yourdomain.com
Prometheus disk usage
Prometheus stores ~1–2 GB per 10 servers per month. Retention is set to 90 days. Check usage:

du -sh /var/lib/prometheus/
# Adjust retention in prometheus.service:
# --storage.tsdb.retention.time=60d
Backup Prometheus data
# Snapshot via API (no downtime)
curl -XPOST http://localhost:9090/api/v1/admin/tsdb/snapshot
# Files saved to /var/lib/prometheus/snapshots/
Grafana backup
# Export all dashboards
for id in $(curl -s http://admin:PASSWORD@localhost:3000/api/search | jq -r '.[].uid'); do
  curl -s "http://admin:PASSWORD@localhost:3000/api/dashboards/uid/$id" > "grafana-backup-$id.json"
done
Rotating logs
Ensure logrotate is configured for Laravel logs:

/var/www/serverwatch/monitor-api/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
}



Documentation version 1.0.0 — ServerWatch Infrastructure Monitoring Platform

