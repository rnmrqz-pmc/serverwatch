<template>
  <div class="app-layout">
    <!-- Navbar -->
    <header class="navbar">
      <div class="nav-container">
        <div class="brand">
          <div class="logo-icon"></div>
          <h1>ServerWatch</h1>
        </div>
        <div class="nav-tabs">
          <button 
            class="tab-btn" 
            :class="{ active: currentTab === 'dashboard' }"
            @click="currentTab = 'dashboard'"
          >
            Dashboard
          </button>
          <button 
            class="tab-btn" 
            :class="{ active: currentTab === 'uptime' }"
            @click="currentTab = 'uptime'"
          >
            Uptime History
          </button>
        </div>
        <div class="sync-status" v-if="store.lastSync">
          <span>Synced {{ formattedSyncTime }}</span>
          <button class="refresh-btn" @click="refreshAll" :disabled="store.loading">
            <span class="refresh-icon" :class="{ spinning: store.loading }">↻</span>
          </button>
        </div>
      </div>
    </header>

    <main class="container">
      <!-- Error Alert banner -->
      <div class="error-banner" v-if="store.error">
        <p>⚠️ Connection Error: {{ store.error }}. Retrying automatically...</p>
      </div>

      <!-- Stats Row -->
      <section class="stats-row">
        <div class="glass-card stat-card">
          <span class="stat-label">Average Uptime</span>
          <span class="stat-value text-green">{{ store.avgUptime }}%</span>
          <div class="stat-indicator green"></div>
        </div>

        <div class="glass-card stat-card">
          <span class="stat-label">Online Nodes</span>
          <span class="stat-value">{{ store.onlineCount }} / {{ store.servers.length }}</span>
          <div class="stat-indicator green"></div>
        </div>

        <div class="glass-card stat-card">
          <span class="stat-label">Degraded Nodes</span>
          <span class="stat-value" :class="{ 'text-amber': store.degradedCount > 0 }">
            {{ store.degradedCount }}
          </span>
          <div class="stat-indicator" :class="store.degradedCount > 0 ? 'amber' : 'gray'"></div>
        </div>

        <div class="glass-card stat-card">
          <span class="stat-label">Firing Alerts</span>
          <span class="stat-value" :class="{ 'text-red': activeAlerts.length > 0 }">
            {{ activeAlerts.length }}
          </span>
          <div class="stat-indicator" :class="activeAlerts.length > 0 ? 'red' : 'gray'"></div>
        </div>
      </section>

      <!-- Firing Alerts Panel (Show if any alerts are active) -->
      <section class="alerts-section glass-card" v-if="activeAlerts.length > 0">
        <div class="section-title">
          <span class="pulse-dot down"></span>
          <h2>Active System Incidents</h2>
        </div>
        <div class="alerts-list">
          <div v-for="alert in activeAlerts" :key="alert.id" class="alert-item" :class="alert.severity">
            <div class="alert-meta">
              <span class="alert-badge" :class="alert.severity">{{ alert.severity }}</span>
              <span class="alert-name">{{ alert.name }}</span>
              <span class="alert-host">@ {{ alert.instance }}</span>
            </div>
            <p class="alert-summary">{{ alert.summary }}</p>
            <span class="alert-time">Triggered {{ formatAlertTime(alert.started_at) }}</span>
          </div>
        </div>
      </section>

      <!-- Tab Content: Dashboard view -->
      <section v-if="currentTab === 'dashboard'">
        <div class="section-header">
          <h2>Monitored Servers</h2>
          <span class="nodes-count">{{ store.servers.length }} nodes total</span>
        </div>
        
        <div class="grid">
          <ServerCard 
            v-for="server in store.servers" 
            :key="server.instance" 
            :server="server"
            :history-data="server.history || []"
          />
        </div>
      </section>

      <!-- Tab Content: Uptime Timeline Details -->
      <section v-else class="timeline-details-view">
        <div class="section-header">
          <h2>Uptime Timeline (Last 90 Days)</h2>
          <p class="section-desc">Historical timeline logs for system targets.</p>
        </div>

        <div class="timeline-container glass-card">
          <div v-for="server in store.servers" :key="server.instance" class="timeline-row">
            <div class="timeline-info">
              <div>
                <h3>{{ server.name }}</h3>
                <span class="timeline-role">{{ server.role }} · <code class="code-ip">{{ server.instance }}</code></span>
              </div>
              <div class="timeline-status-block">
                <span class="status-indicator-pill" :class="server.status">
                  {{ server.status === 'up' ? 'Operational' : 'Outage' }}
                </span>
              </div>
            </div>
            <div class="timeline-chart">
              <UptimeBar :server-name="server.name" :history="server.history || []" :days="90" />
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useServersStore } from './stores/servers';
import ServerCard from './components/ServerCard.vue';
import UptimeBar from './components/UptimeBar.vue';

const store = useServersStore();
const currentTab = ref<'dashboard' | 'uptime'>('dashboard');
let pollTimer: ReturnType<typeof setInterval>;

const activeAlerts = computed(() => {
  return store.alerts.filter(alert => alert.state === 'firing');
});

const formattedSyncTime = computed(() => {
  if (!store.lastSync) return '';
  return store.lastSync.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
});

function formatAlertTime(isoStr: string): string {
  try {
    const diffMs = Date.now() - new Date(isoStr).getTime();
    const diffMin = Math.round(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHrs = Math.round(diffMin / 60);
    return `${diffHrs}h ago`;
  } catch (e) {
    return isoStr;
  }
}

function refreshAll() {
  store.fetchServers();
  store.fetchAlerts();
}

onMounted(() => {
  refreshAll();
  // Poll metrics and alerts every 15 seconds
  pollTimer = setInterval(refreshAll, 15000);
});

onUnmounted(() => {
  clearInterval(pollTimer);
});
</script>

<style>
/* App Layout Container styles */
.app-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.navbar {
  background: rgba(11, 15, 25, 0.7);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 16px 24px;
}

.nav-container {
  max-width: 1400px;
  margin: 0 auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  width: 32px;
  height: 32px;
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  border-radius: 8px;
  box-shadow: 0 0 15px rgba(99, 102, 241, 0.5);
  position: relative;
}

.logo-icon::after {
  content: '';
  position: absolute;
  top: 8px;
  left: 8px;
  width: 16px;
  height: 16px;
  background: #fff;
  border-radius: 4px;
  transform: rotate(45deg);
}

.brand h1 {
  font-size: 1.5rem;
  font-weight: 800;
  background: linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.nav-tabs {
  display: flex;
  gap: 8px;
  background: rgba(255, 255, 255, 0.03);
  padding: 4px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.tab-btn {
  background: transparent;
  border: none;
  color: #94a3b8;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
}

.tab-btn:hover {
  color: #fff;
}

.tab-btn.active {
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.1);
}

.sync-status {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 0.75rem;
  color: #64748b;
}

.refresh-btn {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 50%;
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  cursor: pointer;
}

.refresh-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

.refresh-icon {
  font-size: 1.1rem;
}

.refresh-icon.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.error-banner {
  background: rgba(239, 68, 68, 0.15);
  border: 1px solid rgba(239, 68, 68, 0.25);
  border-radius: 12px;
  padding: 12px 20px;
  margin-bottom: 24px;
  color: #fca5a5;
  font-weight: 500;
  font-size: 0.9rem;
}

/* Stats Dashboard styles */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 24px;
  margin-bottom: 32px;
}

@media (max-width: 1024px) {
  .stats-row {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 600px) {
  .stats-row {
    grid-template-columns: 1fr;
  }
}

.stat-card {
  display: flex;
  flex-direction: column;
  gap: 8px;
  position: relative;
  overflow: hidden;
}

.stat-label {
  font-size: 0.8rem;
  color: #94a3b8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-value {
  font-size: 2rem;
  font-weight: 800;
  color: #fff;
  font-family: var(--font-title);
  line-height: 1.2;
}

.stat-indicator {
  position: absolute;
  top: 0;
  right: 0;
  width: 4px;
  height: 100%;
}

.stat-indicator.green { background: var(--status-up); }
.stat-indicator.amber { background: var(--status-degraded); }
.stat-indicator.red { background: var(--status-down); }
.stat-indicator.gray { background: var(--status-unknown); }

.text-green { color: var(--status-up); }
.text-amber { color: var(--status-degraded); }
.text-red { color: var(--status-down); }

/* Firing Alerts Panel */
.alerts-section {
  background: rgba(239, 68, 68, 0.03);
  border: 1px solid rgba(239, 68, 68, 0.15);
  margin-bottom: 40px;
}

.alerts-section .section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 20px;
}

.alerts-list {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.alert-item {
  background: rgba(255, 255, 255, 0.02);
  border-left: 4px solid var(--status-unknown);
  border-radius: 4px 8px 8px 4px;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.alert-item.critical {
  border-left-color: var(--status-down);
  background: rgba(239, 68, 68, 0.02);
}

.alert-item.warning {
  border-left-color: var(--status-degraded);
  background: rgba(245, 158, 11, 0.01);
}

.alert-meta {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.alert-badge {
  font-size: 0.65rem;
  font-weight: 800;
  text-transform: uppercase;
  padding: 3px 6px;
  border-radius: 4px;
}

.alert-badge.critical {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
}

.alert-badge.warning {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
}

.alert-name {
  font-weight: 700;
  color: #fff;
}

.alert-host {
  font-size: 0.8rem;
  color: #94a3b8;
  font-family: monospace;
}

.alert-summary {
  font-size: 0.9rem;
  color: #cbd5e1;
}

.alert-time {
  font-size: 0.75rem;
  color: #64748b;
}

.section-header {
  margin-bottom: 24px;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
}

.nodes-count {
  font-size: 0.85rem;
  color: #64748b;
  font-weight: 600;
}

/* Timeline Details View */
.timeline-details-view .section-desc {
  color: #94a3b8;
  font-size: 0.9rem;
  margin-top: 4px;
}

.timeline-container {
  display: flex;
  flex-direction: column;
  gap: 32px;
  margin-top: 32px;
}

.timeline-row {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.timeline-row:not(:last-child) {
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  padding-bottom: 32px;
}

.timeline-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.timeline-role {
  font-size: 0.8rem;
  color: #64748b;
}

.code-ip {
  font-family: monospace;
  background: rgba(255, 255, 255, 0.03);
  padding: 2px 6px;
  border-radius: 4px;
  color: #94a3b8;
}

.status-indicator-pill {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 6px 14px;
  border-radius: 20px;
  text-transform: uppercase;
}

.status-indicator-pill.up {
  background: rgba(16, 185, 129, 0.1);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-indicator-pill.down {
  background: rgba(239, 68, 68, 0.1);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.2);
}
</style>
