import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { Server, Alert } from '../types';

export const useServersStore = defineStore('servers', () => {
  const servers = ref<Server[]>([]);
  const alerts = ref<Alert[]>([]);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const lastSync = ref<Date | null>(null);

  const apiBase = (import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');

  const onlineCount = computed(() => servers.value.filter(s => s.status === 'up').length);
  const degradedCount = computed(() => servers.value.filter(s => s.status === 'degraded').length);
  const offlineCount = computed(() => servers.value.filter(s => s.status === 'down').length);
  
  const avgUptime = computed(() => {
    if (!servers.value.length) return 100;
    return parseFloat((servers.value.reduce((acc, s) => acc + s.uptime_pct, 0) / servers.value.length).toFixed(2));
  });

  async function fetchServers() {
    loading.value = true;
    error.value = null;
    try {
      const res = await fetch(`${apiBase}/servers`);
      if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
      servers.value = await res.json();
      lastSync.value = new Date();
    } catch (e) {
      error.value = (e as Error).message;
      console.error('Failed to fetch servers:', e);
    } finally {
      loading.value = false;
    }
  }

  async function fetchAlerts() {
    try {
      const res = await fetch(`${apiBase}/alerts`);
      if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
      alerts.value = await res.json();
    } catch (e) {
      console.error('Failed to fetch alerts:', e);
    }
  }

  return { 
    servers, 
    alerts,
    loading, 
    error,
    lastSync, 
    onlineCount, 
    degradedCount,
    offlineCount, 
    avgUptime, 
    fetchServers,
    fetchAlerts
  };
});
