<template>
  <div class="glass-card server-card">
    <div class="card-header">
      <div class="server-info">
        <h3>{{ server.name }}</h3>
        <span class="server-role">{{ server.role }}</span>
      </div>
      <div class="status-badge">
        <span class="pulse-dot" :class="server.status"></span>
        <span class="status-text" :class="server.status">{{ statusLabel }}</span>
      </div>
    </div>

    <div class="tags-row">
      <span class="tag-badge" :class="server.env">{{ server.env }}</span>
      <span class="tag-badge ip">{{ server.instance }}</span>
    </div>

    <!-- Live Hardware Gauges -->
    <div class="metrics-grid" v-if="server.metrics">
      <div class="metric-item">
        <div class="metric-meta">
          <span>CPU</span>
          <span class="metric-val">{{ server.metrics.cpu }}%</span>
        </div>
        <div class="progress-bar-container">
          <div class="progress-bar cpu-bar" :style="{ width: `${server.metrics.cpu}%` }"></div>
        </div>
      </div>

      <div class="metric-item">
        <div class="metric-meta">
          <span>RAM</span>
          <span class="metric-val">{{ server.metrics.memory.percent }}%</span>
        </div>
        <div class="progress-bar-container">
          <div class="progress-bar ram-bar" :style="{ width: `${server.metrics.memory.percent}%` }"></div>
        </div>
      </div>

      <div class="metric-item">
        <div class="metric-meta">
          <span>Disk</span>
          <span class="metric-val">{{ server.metrics.disk.percent }}%</span>
        </div>
        <div class="progress-bar-container">
          <div class="progress-bar disk-bar" :style="{ width: `${server.metrics.disk.percent}%` }"></div>
        </div>
      </div>
    </div>

    <!-- Offline state metrics -->
    <div class="metrics-grid offline" v-else>
      <p class="no-metrics-msg">Server unreachable — no active metrics</p>
    </div>

    <!-- Uptime Timeline (90 Days) -->
    <div class="uptime-section" v-if="historyData">
      <UptimeBar :server-name="server.name" :history="historyData" :days="90" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { Server, UptimeDay } from '../types';
import UptimeBar from './UptimeBar.vue';

const props = defineProps<{
  server: Server;
  historyData?: UptimeDay[];
}>();

const statusLabel = computed(() => {
  const labels: Record<string, string> = {
    up: 'Online',
    down: 'Offline',
    degraded: 'Degraded',
    unknown: 'Unknown'
  };
  return labels[props.server.status] ?? props.server.status;
});
</script>

<style scoped>
.server-card {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.card-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.server-info h3 {
  font-size: 1.25rem;
  margin-bottom: 4px;
}

.server-role {
  font-size: 0.8rem;
  color: #94a3b8;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.03);
  padding: 6px 12px;
  border-radius: 20px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.status-text {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.status-text.up { color: var(--status-up); }
.status-text.degraded { color: var(--status-degraded); }
.status-text.down { color: var(--status-down); }

.tags-row {
  display: flex;
  gap: 8px;
}

.tag-badge {
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(255, 255, 255, 0.05);
  color: #94a3b8;
  letter-spacing: 0.03em;
}

.tag-badge.production {
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.2);
}

.tag-badge.staging {
  background: rgba(245, 158, 11, 0.1);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.15);
}

.tag-badge.ip {
  font-family: monospace;
  font-size: 0.75rem;
  text-transform: none;
}

/* Metrics styles */
.metrics-grid {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.metric-item {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.metric-meta {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #94a3b8;
  font-weight: 500;
}

.metric-val {
  color: #fff;
  font-weight: 600;
}

.progress-bar-container {
  height: 6px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 3px;
  overflow: hidden;
  position: relative;
}

.progress-bar {
  height: 100%;
  border-radius: 3px;
}

.cpu-bar {
  background: linear-gradient(90deg, #6366f1, #818cf8);
  box-shadow: 0 0 8px rgba(99, 102, 241, 0.5);
}

.ram-bar {
  background: linear-gradient(90deg, #10b981, #34d399);
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
}

.disk-bar {
  background: linear-gradient(90deg, #06b6d4, #22d3ee);
  box-shadow: 0 0 8px rgba(6, 182, 212, 0.5);
}

.offline {
  padding: 16px 0;
  text-align: center;
  border: 1px dashed rgba(255, 255, 255, 0.05);
  border-radius: 8px;
  background: rgba(239, 68, 68, 0.02);
}

.no-metrics-msg {
  font-size: 0.8rem;
  color: #ef4444;
  font-weight: 500;
}

.uptime-section {
  border-top: 1px solid rgba(255, 255, 255, 0.05);
  padding-top: 16px;
}
</style>
