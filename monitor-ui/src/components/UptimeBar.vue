<template>
  <div class="uptime-bar-wrapper">
    <div class="bars" role="img" :aria-label="`Uptime history for ${serverName}`">
      <div
        v-for="(day, i) in visibleHistory"
        :key="i"
        class="bar"
        :class="`bar--${day.status}`"
        @mouseenter="hovered = day"
        @mouseleave="hovered = null"
      />
    </div>
    <div class="bars-footer">
      <span>{{ startLabel || `${days} days ago` }}</span>
      <span class="uptime-pct" :class="pctClass">{{ uptimePct }}% uptime</span>
      <span>{{ endLabel || 'Today' }}</span>
    </div>
    
    <!-- Tooltip -->
    <div v-if="hovered" class="tooltip">
      <span class="tooltip-date">{{ formatDate(hovered.date) }}</span>
      <span class="tooltip-status" :class="`text--${hovered.status}`">
        {{ labelFor(hovered.status) }} ({{ hovered.value }}%)
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { UptimeDay } from '../types';

const props = defineProps<{
  serverName: string;
  history: UptimeDay[];
  days: number;
  startLabel?: string;
  endLabel?: string;
}>();

const hovered = ref<UptimeDay | null>(null);

const visibleHistory = computed(() => {
  if (!props.history) return [];
  return props.history.slice(-props.days);
});

const uptimePct = computed(() => {
  if (!visibleHistory.value.length) return '0.00';
  const up = visibleHistory.value.filter(d => d.status === 'up').length;
  const degraded = visibleHistory.value.filter(d => d.status === 'degraded').length;
  
  // Degraded counts as 95% availability for percentage calculation
  const totalWeight = up + (degraded * 0.95);
  return ((totalWeight / visibleHistory.value.length) * 100).toFixed(2);
});

const pctClass = computed(() => {
  const p = parseFloat(uptimePct.value);
  if (p >= 99.9) return 'pct--excellent';
  if (p >= 99)   return 'pct--good';
  if (p >= 95)   return 'pct--warning';
  return 'pct--critical';
});

function labelFor(status: string): string {
  const labels: Record<string, string> = {
    up: 'Operational',
    down: 'Outage',
    degraded: 'Degraded Performance',
    'no-data': 'No Data'
  };
  return labels[status] ?? status;
}

function formatDate(dateStr: string): string {
  try {
    const options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' };
    return new Date(dateStr).toLocaleDateString('en-US', options);
  } catch (e) {
    return dateStr;
  }
}
</script>

<style scoped>
.uptime-bar-wrapper {
  position: relative;
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.bars {
  display: flex;
  gap: 3px;
  height: 36px;
  align-items: flex-end;
}

.bar {
  flex: 1;
  height: 100%;
  min-height: 12px;
  border-radius: 3px;
  background-color: var(--status-unknown);
  cursor: pointer;
  transform-origin: bottom;
  transition: transform 0.15s ease, background-color 0.15s ease;
}

.bar:hover {
  transform: scaleY(1.15) scaleX(1.1);
  filter: brightness(1.2);
}

.bar--up {
  background-color: var(--status-up);
}

.bar--degraded {
  background-color: var(--status-degraded);
}

.bar--down {
  background-color: var(--status-down);
}

.bar--no-data {
  background-color: var(--status-unknown);
  opacity: 0.3;
}

.bars-footer {
  display: flex;
  justify-content: space-between;
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
}

.uptime-pct {
  font-weight: 600;
}

.pct--excellent {
  color: var(--status-up);
}

.pct--good {
  color: #34d399;
}

.pct--warning {
  color: var(--status-degraded);
}

.pct--critical {
  color: var(--status-down);
}

/* Tooltip style */
.tooltip {
  position: absolute;
  bottom: 50px;
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 2px;
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.15);
  border-radius: 8px;
  padding: 8px 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  z-index: 10;
  pointer-events: none;
  width: max-content;
}

.tooltip-date {
  font-size: 0.75rem;
  font-weight: 600;
  color: #fff;
}

.tooltip-status {
  font-size: 0.7rem;
  font-weight: 500;
}

.text--up {
  color: var(--status-up);
}

.text--degraded {
  color: var(--status-degraded);
}

.text--down {
  color: var(--status-down);
}
</style>
