<template>
  <div class="app-layout" :class="{ 'dashboard-layout': authStore.isAuthenticated }">
    <!-- If not authenticated, render Login view -->
    <LoginView v-if="!authStore.isAuthenticated" />

    <!-- If authenticated, render full application layout -->
    <template v-else>
      <!-- Navbar -->
      <!-- Sidebar Navigation -->
      <aside class="sidebar">
        <div class="sidebar-brand">
          <img :src="bdLogo" alt="BD Logo" class="brand-logo-img" />
          <h1 class="brand-title">ServerWatcher</h1>
        </div>

        <nav class="sidebar-nav">
          <button 
            class="nav-item" 
            :class="{ active: currentTab === 'dashboard' }"
            @click="currentTab = 'dashboard'"
          >
            <span class="nav-label">Dashboard</span>
          </button>
          <button 
            class="nav-item" 
            :class="{ active: currentTab === 'uptime' }"
            @click="currentTab = 'uptime'"
          >
            <span class="nav-label">Uptime History</span>
          </button>
          <button 
            class="nav-item" 
            :class="{ active: currentTab === 'users' }"
            @click="currentTab = 'users'"
          >
            <span class="nav-label">User Management</span>
          </button>
          <button 
            class="nav-item" 
            :class="{ active: currentTab === 'servers' }"
            @click="currentTab = 'servers'"
          >
            <span class="nav-label">Server Settings</span>
          </button>
          <button 
            class="nav-item" 
            :class="{ active: currentTab === 'maintenance' }"
            @click="currentTab = 'maintenance'"
          >
            <span class="nav-label">Maintenance</span>
          </button>
        </nav>

        <div class="sidebar-footer">
          <div class="sync-status" v-if="store.lastSync && currentTab !== 'users' && currentTab !== 'servers' && currentTab !== 'maintenance'">
            <span>Synced {{ formattedSyncTime }}</span>
            <button class="refresh-btn" @click="refreshAll" :disabled="store.loading">
              <span class="refresh-icon" :class="{ spinning: store.loading }">↻</span>
            </button>
          </div>
          
          <div class="user-info" v-if="authStore.user">
            <span class="user-nav-avatar">{{ authStore.user.name.charAt(0).toUpperCase() }}</span>
            <span class="user-nav-name">{{ authStore.user.name }}</span>
          </div>
          <button class="logout-btn" @click="authStore.logout()" :disabled="authStore.loading">
            Logout
          </button>
        </div>
      </aside>

      <main class="main-content">
        <!-- Error Alert banner -->
        <div class="error-banner" v-if="store.error">
          <p>⚠️ Connection Error: {{ store.error }}. Retrying automatically...</p>
        </div>

        <!-- Stats Row (hidden on Admin tabs) -->
        <section class="stats-row" v-if="currentTab !== 'users' && currentTab !== 'servers' && currentTab !== 'maintenance'">
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

          <div class="glass-card stat-card">
            <span class="stat-label">Total CPU Cores</span>
            <span class="stat-value text-indigo">{{ store.totalCpuCores }}</span>
            <div class="stat-indicator indigo"></div>
          </div>

          <div class="glass-card stat-card">
            <span class="stat-label">Total RAM</span>
            <span class="stat-value text-cyan">{{ store.totalRamGb }} <small class="stat-unit">GB</small></span>
            <div class="stat-indicator cyan"></div>
          </div>

          <div class="glass-card stat-card">
            <span class="stat-label">Total Disk</span>
            <span class="stat-value text-violet">{{ store.totalDiskGb }} <small class="stat-unit">GB</small></span>
            <div class="stat-indicator violet"></div>
          </div>
        </section>


        <!-- Firing Alerts Panel (Show if any alerts are active, hidden on Admin tabs) -->
        <section class="alerts-section glass-card" v-if="activeAlerts.length > 0 && currentTab !== 'users' && currentTab !== 'servers' && currentTab !== 'maintenance'">
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
        <section v-else-if="currentTab === 'uptime'" class="timeline-details-view">
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

        <!-- Tab Content: User Management -->
        <section v-else-if="currentTab === 'users'" class="user-management-view">
          <UserManagement />
        </section>

        <!-- Tab Content: Server Settings -->
        <section v-else-if="currentTab === 'servers'" class="server-management-view">
          <ServerManagement />
        </section>

        <!-- Tab Content: Maintenance Settings -->
        <section v-else class="maintenance-view">
          <MaintenanceManagement />
        </section>
      </main>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useServersStore } from './stores/servers';
import { useAuthStore } from './stores/auth';
import bdLogo from './assets/BD.png';
import ServerCard from './components/ServerCard.vue';
import UptimeBar from './components/UptimeBar.vue';
import LoginView from './components/LoginView.vue';
import UserManagement from './components/UserManagement.vue';
import ServerManagement from './components/ServerManagement.vue';
import MaintenanceManagement from './components/MaintenanceManagement.vue';

const store = useServersStore();
const authStore = useAuthStore();
const currentTab = ref<'dashboard' | 'uptime' | 'users' | 'servers' | 'maintenance'>('dashboard');
let pollTimer: ReturnType<typeof setInterval> | null = null;

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
  if (!authStore.isAuthenticated) return;
  store.fetchServers();
  store.fetchAlerts();
}

function startPolling() {
  stopPolling();
  refreshAll();
  pollTimer = setInterval(refreshAll, 15000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

// Watch authentication status changes
watch(() => authStore.isAuthenticated, (isAuth) => {
  if (isAuth) {
    startPolling();
  } else {
    stopPolling();
  }
});

onMounted(async () => {
  const isAuthed = await authStore.fetchMe();
  if (isAuthed) {
    startPolling();
  }
});

onUnmounted(() => {
  stopPolling();
});
</script>

<style>
/* App Layout Container styles */
.app-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-layout.dashboard-layout {
  flex-direction: row;
}

/* Sidebar Navigation Layout */
.sidebar {
  width: 260px;
  height: 100vh;
  position: sticky;
  top: 0;
  background: rgba(11, 15, 25, 0.75);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  border-right: 1px solid rgba(255, 255, 255, 0.05);
  display: flex;
  flex-direction: column;
  padding: 24px;
  box-sizing: border-box;
  flex-shrink: 0;
  z-index: 100;
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 32px;
  padding-bottom: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.brand-logo-img {
  width: 32px;
  height: 32px;
  object-fit: contain;
  border-radius: 6px;
  filter: drop-shadow(0 0 6px rgba(99, 102, 241, 0.3));
}

.brand-title {
  font-size: 1.2rem;
  font-weight: 800;
  background: linear-gradient(135deg, #fff 30%, #a5b4fc 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  margin: 0;
  letter-spacing: -0.01em;
}

.sidebar-nav {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex-grow: 1;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  background: transparent;
  border: 1px solid transparent;
  color: #94a3b8;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 10px 14px;
  border-radius: 8px;
  cursor: pointer;
  width: 100%;
  text-align: left;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.nav-item:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.03);
}

.nav-item.active {
  background: rgba(99, 102, 241, 0.12);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.1);
}

.nav-icon {
  font-size: 1.05rem;
  line-height: 1;
}

.sidebar-footer {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding-top: 16px;
  border-top: 1px solid rgba(255, 255, 255, 0.05);
}

.sync-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.72rem;
  color: #64748b;
  padding: 2px 4px;
}

.refresh-btn {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 50%;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.2s;
}

.refresh-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

.refresh-icon {
  font-size: 0.95rem;
}

.refresh-icon.spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.main-content {
  flex-grow: 1;
  min-width: 0;
  padding: 40px;
  box-sizing: border-box;
  overflow-y: auto;
  width: 100%;
}

@media (max-width: 900px) {
  .app-layout.dashboard-layout {
    flex-direction: column;
  }

  .sidebar {
    width: 100%;
    height: auto;
    position: sticky;
    top: 0;
    border-right: none;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    flex-direction: column;
    padding: 16px;
    gap: 12px;
  }

  .sidebar-brand {
    margin-bottom: 0;
    padding-bottom: 12px;
    width: 100%;
    justify-content: space-between;
  }

  .sidebar-nav {
    flex-direction: row;
    overflow-x: auto;
    width: 100%;
    padding-bottom: 4px;
    gap: 8px;
    scrollbar-width: none; /* Firefox */
  }

  .sidebar-nav::-webkit-scrollbar {
    display: none; /* Safari and Chrome */
  }

  .nav-item {
    width: auto;
    flex-shrink: 0;
    white-space: nowrap;
    padding: 8px 14px;
  }

  .sidebar-footer {
    flex-direction: row;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    padding-top: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    gap: 12px;
  }

  .sync-status {
    padding: 0;
  }

  .user-info {
    padding: 6px 10px;
  }

  .logout-btn {
    width: auto;
    padding: 8px 16px;
  }

  .main-content {
    padding: 24px 16px;
  }
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
  grid-template-columns: repeat(7, 1fr);
  gap: 16px;
  margin-bottom: 32px;
}

@media (max-width: 1280px) {
  .stats-row {
    grid-template-columns: repeat(4, 1fr);
  }
}

@media (max-width: 900px) {
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
.stat-indicator.indigo { background: #6366f1; }
.stat-indicator.cyan { background: #06b6d4; }
.stat-indicator.violet { background: #a855f7; }

.text-green { color: var(--status-up); }
.text-amber { color: var(--status-degraded); }
.text-red { color: var(--status-down); }
.text-indigo { color: #818cf8; }
.text-cyan { color: #22d3ee; }
.text-violet { color: #c084fc; }

.stat-unit {
  font-size: 1rem;
  font-weight: 600;
  opacity: 0.7;
}

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

.user-info {
  display: flex;
  align-items: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.03);
  padding: 8px 12px;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.user-nav-avatar {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  color: #fff;
  font-weight: 700;
  font-size: 0.65rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-nav-name {
  font-size: 0.8rem;
  font-weight: 600;
  color: #cbd5e1;
}

.logout-btn {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  color: #fca5a5;
  font-size: 0.85rem;
  font-weight: 600;
  padding: 10px 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  width: 100%;
  text-align: center;
}

.logout-btn:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.25);
  color: #fff;
  border-color: rgba(239, 68, 68, 0.4);
}

.logout-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
</style>
