<template>
  <div class="db-badge" :class="`db-badge--${db.health}`">
    <!-- Header row: icon + type + health pill -->
    <div class="db-badge__header">
      <div class="db-type-info">
        <span class="db-icon" :class="`db-icon--${db.type}`">{{ dbIcon }}</span>
        <div class="db-name-wrap">
          <span class="db-type-label">{{ dbLabel }}</span>
          <span class="db-version" v-if="db.version">v{{ db.version }}</span>
        </div>
      </div>
      <div class="db-health-pill" :class="`db-health-pill--${db.health}`">
        <span class="db-health-dot" :class="db.health === 'healthy' ? 'pulse' : ''"></span>
        <span>{{ healthLabel }}</span>
      </div>
    </div>

    <!-- Stats row: size + connections -->
    <div class="db-badge__stats">
      <div class="db-stat">
        <span class="db-stat__icon">🗄</span>
        <div class="db-stat__body">
          <span class="db-stat__val">{{ sizeGb }} <small>GB</small></span>
          <span class="db-stat__lbl">Used</span>
        </div>
      </div>
      <div class="db-stat-divider"></div>
      <div class="db-stat">
        <span class="db-stat__icon">🔗</span>
        <div class="db-stat__body">
          <span class="db-stat__val">{{ db.connections }}</span>
          <span class="db-stat__lbl">Connections</span>
        </div>
      </div>
    </div>

    <!-- Usage bar -->
    <div class="db-usage-bar-wrap" v-if="maxSizeBytes > 0">
      <div class="db-usage-bar" :class="`db-usage-bar--${db.type}`"
           :style="{ width: `${usagePercent}%` }"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { DatabaseInfo } from '../types';

const props = defineProps<{
  db: DatabaseInfo;
  /** Optional: total disk size of the server in bytes, for relative usage bar */
  diskTotal?: number;
}>();

// ── Label / icon maps ──────────────────────────────────────────────────────

const DB_META: Record<string, { label: string; icon: string }> = {
  postgresql: { label: 'PostgreSQL', icon: '🐘' },
  mysql:      { label: 'MySQL',      icon: '🐬' },
  mariadb:    { label: 'MariaDB',    icon: '🦁' },
};

const dbIcon    = computed(() => DB_META[props.db.type]?.icon  ?? '🗄');
const dbLabel   = computed(() => DB_META[props.db.type]?.label ?? props.db.type);

const healthLabel = computed(() => {
  const map: Record<string, string> = {
    healthy:  'Healthy',
    degraded: 'Degraded',
    down:     'Down',
  };
  return map[props.db.health] ?? props.db.health;
});

// ── Derived metrics ────────────────────────────────────────────────────────

const sizeGb = computed(() =>
  (props.db.size_bytes / (1024 ** 3)).toFixed(2)
);

const maxSizeBytes = computed(() => props.diskTotal ?? 0);

const usagePercent = computed(() => {
  if (!maxSizeBytes.value) return 0;
  return Math.min(100, (props.db.size_bytes / maxSizeBytes.value) * 100);
});
</script>

<style scoped>
/* ── Outer card ─────────────────────────────────────────────────────────── */
.db-badge {
  background: rgba(255, 255, 255, 0.025);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  overflow: hidden;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.db-badge::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 2px;
  border-radius: 12px 12px 0 0;
}

/* Health-state accent line */
.db-badge--healthy::before  { background: linear-gradient(90deg, #10b981, #34d399); }
.db-badge--degraded::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.db-badge--down::before     { background: linear-gradient(90deg, #ef4444, #f87171); }

.db-badge--down {
  border-color: rgba(239, 68, 68, 0.2);
  background: rgba(239, 68, 68, 0.03);
}

/* ── Header row ─────────────────────────────────────────────────────────── */
.db-badge__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}

.db-type-info {
  display: flex;
  align-items: center;
  gap: 10px;
}

.db-icon {
  font-size: 1.4rem;
  line-height: 1;
  /* Subtle glow per DB type */
  filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.15));
}

.db-name-wrap {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.db-type-label {
  font-size: 0.85rem;
  font-weight: 700;
  color: #e2e8f0;
  letter-spacing: 0.01em;
}

.db-version {
  font-size: 0.68rem;
  color: #64748b;
  font-family: monospace;
}

/* ── Health pill ─────────────────────────────────────────────────────────── */
.db-health-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 4px 10px;
  border-radius: 20px;
  white-space: nowrap;
}

.db-health-pill--healthy {
  background: rgba(16, 185, 129, 0.12);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.25);
}

.db-health-pill--degraded {
  background: rgba(245, 158, 11, 0.12);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.25);
}

.db-health-pill--down {
  background: rgba(239, 68, 68, 0.12);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.25);
}

.db-health-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

.db-health-dot.pulse {
  animation: db-pulse 2s ease-in-out infinite;
}

@keyframes db-pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50%       { opacity: 0.4; transform: scale(0.75); }
}

/* ── Stats row ───────────────────────────────────────────────────────────── */
.db-badge__stats {
  display: flex;
  align-items: center;
  gap: 12px;
}

.db-stat {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
}

.db-stat__icon {
  font-size: 1rem;
  opacity: 0.7;
}

.db-stat__body {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.db-stat__val {
  font-size: 1rem;
  font-weight: 800;
  color: #f1f5f9;
  line-height: 1;
}

.db-stat__val small {
  font-size: 0.65rem;
  font-weight: 600;
  color: #94a3b8;
  margin-left: 2px;
}

.db-stat__lbl {
  font-size: 0.65rem;
  color: #64748b;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.db-stat-divider {
  width: 1px;
  height: 28px;
  background: rgba(255, 255, 255, 0.06);
  flex-shrink: 0;
}

/* ── Usage bar ───────────────────────────────────────────────────────────── */
.db-usage-bar-wrap {
  height: 3px;
  background: rgba(255, 255, 255, 0.05);
  border-radius: 2px;
  overflow: hidden;
}

.db-usage-bar {
  height: 100%;
  border-radius: 2px;
  transition: width 0.6s ease;
}

.db-usage-bar--postgresql {
  background: linear-gradient(90deg, #336791, #5b9bd5);
  box-shadow: 0 0 6px rgba(91, 155, 213, 0.5);
}

.db-usage-bar--mysql {
  background: linear-gradient(90deg, #f29111, #fbbf24);
  box-shadow: 0 0 6px rgba(242, 145, 17, 0.5);
}

.db-usage-bar--mariadb {
  background: linear-gradient(90deg, #c0392b, #e74c3c);
  box-shadow: 0 0 6px rgba(231, 76, 60, 0.5);
}
</style>
