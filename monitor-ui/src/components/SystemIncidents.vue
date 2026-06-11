<template>
  <div class="system-incidents">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2>System Incidents</h2>
        <p class="section-desc">Audit log of all active and resolved system incidents.</p>
      </div>
    </div>

    <!-- Filters Bar -->
    <div class="filters-bar glass-card">
      <div class="filter-group search-group">
        <label for="search-input">Search Incidents</label>
        <div class="search-input-wrapper">
          <span class="search-icon">🔍</span>
          <input 
            type="text" 
            id="search-input" 
            v-model="searchQuery" 
            placeholder="Search by instance, name, or message..."
          />
        </div>
      </div>

      <div class="filter-group">
        <label for="status-filter">Status</label>
        <select id="status-filter" v-model="statusFilter">
          <option value="all">All States</option>
          <option value="firing">🔥 Firing</option>
          <option value="resolved">✅ Resolved</option>
        </select>
      </div>

      <div class="filter-group">
        <label for="severity-filter">Severity</label>
        <select id="severity-filter" v-model="severityFilter">
          <option value="all">All Severities</option>
          <option value="critical">🔴 Critical</option>
          <option value="warning">🟡 Warning</option>
          <option value="info">🔵 Info</option>
        </select>
      </div>
    </div>

    <!-- Table Container -->
    <div class="incidents-container glass-card">
      <div v-if="store.loading && store.alerts.length === 0" class="loading-state">
        <div class="spinner"></div>
        <p>Loading incidents...</p>
      </div>

      <div v-else-if="filteredAlerts.length === 0" class="empty-state">
        <p>No system incidents found matching current filters.</p>
      </div>

      <table v-else class="incidents-table">
        <thead>
          <tr>
            <th>Status</th>
            <th>Severity</th>
            <th>Target/Instance</th>
            <th>Incident Name</th>
            <th>Message / Summary</th>
            <th>Triggered</th>
            <th>Duration</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="alert in filteredAlerts" :key="alert.id" :class="alert.state">
            <td>
              <span class="status-pill" :class="alert.state">
                <span class="pulse-dot" v-if="alert.state === 'firing'"></span>
                {{ alert.state === 'firing' ? 'Firing' : 'Resolved' }}
              </span>
            </td>
            <td>
              <span class="severity-badge" :class="alert.severity">
                {{ alert.severity }}
              </span>
            </td>
            <td>
              <code class="code-ip">{{ alert.instance }}</code>
            </td>
            <td>
              <span class="incident-name">{{ alert.name }}</span>
            </td>
            <td>
              <span class="incident-summary" :title="alert.summary">{{ alert.summary }}</span>
            </td>
            <td>
              <span class="time-stamp" :title="formatFullDate(alert.started_at)">
                {{ formatAlertTime(alert.started_at) }}
              </span>
            </td>
            <td>
              <span class="duration-span">
                {{ formatDuration(alert.started_at, alert.resolved_at) }}
              </span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { useServersStore } from '../stores/servers';

const store = useServersStore();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'firing' | 'resolved'>('all');
const severityFilter = ref<'all' | 'critical' | 'warning' | 'info'>('all');

const filteredAlerts = computed(() => {
  return store.alerts.filter(alert => {
    // 1. Search Query
    const query = searchQuery.value.toLowerCase();
    const matchesSearch = !query || 
      alert.name.toLowerCase().includes(query) || 
      alert.instance.toLowerCase().includes(query) || 
      alert.summary.toLowerCase().includes(query);

    // 2. Status Filter
    const matchesStatus = statusFilter.value === 'all' || alert.state === statusFilter.value;

    // 3. Severity Filter
    const matchesSeverity = severityFilter.value === 'all' || alert.severity === severityFilter.value;

    return matchesSearch && matchesStatus && matchesSeverity;
  });
});

function formatFullDate(isoStr: string): string {
  try {
    return new Date(isoStr).toLocaleString();
  } catch (e) {
    return isoStr;
  }
}

function formatAlertTime(isoStr: string): string {
  try {
    const diffMs = Date.now() - new Date(isoStr).getTime();
    const diffMin = Math.round(diffMs / 60000);
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return `${diffMin}m ago`;
    const diffHrs = Math.floor(diffMin / 60);
    if (diffHrs < 24) return `${diffHrs}h ago`;
    const diffDays = Math.floor(diffHrs / 24);
    return `${diffDays}d ago`;
  } catch (e) {
    return isoStr;
  }
}

function formatDuration(startedAt: string, resolvedAt: string | null): string {
  try {
    const startTime = new Date(startedAt).getTime();
    const endTime = resolvedAt ? new Date(resolvedAt).getTime() : Date.now();
    const diffMs = endTime - startTime;
    const diffMin = Math.round(diffMs / 60000);
    
    const prefix = resolvedAt ? '' : 'Active for ';
    
    if (diffMin < 1) return resolvedAt ? 'Less than 1m' : 'Active < 1m';
    if (diffMin < 60) return `${prefix}${diffMin}m`;
    
    const diffHrs = Math.floor(diffMin / 60);
    const remMin = diffMin % 60;
    
    if (diffHrs < 24) {
      return `${prefix}${diffHrs}h ${remMin}m`;
    }
    
    const diffDays = Math.floor(diffHrs / 24);
    const remHrs = diffHrs % 24;
    return `${prefix}${diffDays}d ${remHrs}h`;
  } catch (e) {
    return 'Unknown';
  }
}
</script>

<style scoped>
.system-incidents {
  width: 100%;
}

.section-desc {
  color: #94a3b8;
  font-size: 0.9rem;
  margin-top: 4px;
}

/* Filters Bar styling */
.filters-bar {
  display: flex;
  gap: 20px;
  padding: 20px 24px;
  margin-top: 24px;
  align-items: flex-end;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.search-group {
  flex-grow: 1;
  min-width: 250px;
}

.filter-group label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.search-icon {
  position: absolute;
  left: 12px;
  color: #64748b;
  font-size: 0.9rem;
  pointer-events: none;
}

.filter-group input {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px 10px 36px;
  color: #fff;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
}

.filter-group select {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px;
  color: #fff;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  font-family: inherit;
  min-width: 160px;
  cursor: pointer;
  box-sizing: border-box;
}

.filter-group input:focus,
.filter-group select:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

/* Incidents Container and Table */
.incidents-container {
  margin-top: 24px;
  padding: 0;
  overflow: hidden;
  border-color: rgba(255, 255, 255, 0.05);
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #94a3b8;
  gap: 16px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(255, 255, 255, 0.05);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.incidents-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.incidents-table th {
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  padding: 16px 24px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.incidents-table td {
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  vertical-align: middle;
}

.incidents-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.01);
}

.incidents-table tbody tr.firing {
  background: rgba(239, 68, 68, 0.01);
}

.incidents-table tbody tr.firing:hover {
  background: rgba(239, 68, 68, 0.02);
}

/* Status Pill */
.status-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 4px 10px;
  border-radius: 12px;
  text-transform: uppercase;
}

.status-pill.firing {
  background: rgba(239, 68, 68, 0.15);
  color: #fca5a5;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-pill.resolved {
  background: rgba(16, 185, 129, 0.15);
  color: #a7f3d0;
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.pulse-dot {
  width: 6px;
  height: 6px;
  background-color: #ef4444;
  border-radius: 50%;
  box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  animation: pulse 1.6s infinite;
}

@keyframes pulse {
  0% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7);
  }
  70% {
    transform: scale(1);
    box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
  }
  100% {
    transform: scale(0.95);
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
  }
}

/* Severity Badges */
.severity-badge {
  display: inline-block;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 2px 8px;
  border-radius: 4px;
}

.severity-badge.critical {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.severity-badge.warning {
  background: rgba(245, 158, 11, 0.2);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.severity-badge.info {
  background: rgba(6, 182, 212, 0.2);
  color: #22d3ee;
  border: 1px solid rgba(6, 182, 212, 0.3);
}

.code-ip {
  font-family: monospace;
  background: rgba(255, 255, 255, 0.03);
  padding: 3px 6px;
  border-radius: 4px;
  color: #94a3b8;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.incident-name {
  font-weight: 600;
  color: #fff;
}

.incident-summary {
  color: #cbd5e1;
  font-size: 0.88rem;
  display: block;
  max-width: 380px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.time-stamp {
  color: #94a3b8;
  font-size: 0.85rem;
}

.duration-span {
  color: #cbd5e1;
  font-weight: 500;
  font-size: 0.85rem;
}

@media (max-width: 900px) {
  .filters-bar {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  
  .filter-group select {
    min-width: 100%;
  }
  
  .incidents-table {
    display: block;
    overflow-x: auto;
  }
}
</style>
