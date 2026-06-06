<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="db-cred-modal glass-card">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-title-block">
          <span class="modal-db-icon">🗄️</span>
          <div>
            <h3>Database Credentials</h3>
            <p class="modal-subtitle">Configure the monitoring user for <strong>{{ serverName }}</strong></p>
          </div>
        </div>
        <button class="close-btn" @click="$emit('close')" aria-label="Close">✕</button>
      </div>

      <!-- Security note -->
      <div class="security-note">
        <span class="lock-icon">🔒</span>
        <span>Passwords are AES-256 encrypted at rest and <strong>never</strong> returned via the API.</span>
      </div>

      <!-- Feedback banners -->
      <div v-if="successMsg" class="feedback-banner success">✅ {{ successMsg }}</div>
      <div v-if="errorMsg"   class="feedback-banner error">⚠️ {{ errorMsg }}</div>

      <form @submit.prevent="handleSubmit" class="db-form">

        <!-- DB Type -->
        <div class="form-group">
          <label for="db-type">Database Type</label>
          <div class="db-type-grid">
            <button
              v-for="opt in dbTypeOptions"
              :key="opt.value"
              type="button"
              class="db-type-btn"
              :class="{ active: form.db_type === opt.value }"
              @click="selectDbType(opt.value)"
            >
              <span class="db-type-emoji">{{ opt.icon }}</span>
              <span>{{ opt.label }}</span>
            </button>
          </div>
        </div>

        <!-- Connection fields (hidden when type is none) -->
        <template v-if="form.db_type !== 'none'">
          <div class="form-row">
            <div class="form-group flex-3">
              <label for="db-host">Host / IP</label>
              <input
                id="db-host"
                type="text"
                v-model="form.db_host"
                :placeholder="form.db_type === 'postgresql' ? 'e.g. [IP_ADDRESS]' : 'e.g. [IP_ADDRESS]'"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label for="db-port">Port</label>
              <input
                id="db-port"
                type="number"
                v-model.number="form.db_port"
                :placeholder="defaultPort.toString()"
                min="1"
                max="65535"
                required
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group flex-1">
              <label for="db-user">Username</label>
              <input
                id="db-user"
                type="text"
                v-model="form.db_user"
                placeholder="e.g. monitor"
                autocomplete="off"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label for="db-pass">
                Password
                <span v-if="hasExistingCredentials" class="existing-badge">stored ●●●●●●</span>
              </label>
              <div class="password-wrap">
                <input
                  id="db-pass"
                  :type="showPassword ? 'text' : 'password'"
                  v-model="form.db_password"
                  :placeholder="hasExistingCredentials ? 'Leave blank to keep current' : 'Enter password'"
                  autocomplete="new-password"
                />
                <button
                  type="button"
                  class="toggle-pass-btn"
                  @click="showPassword = !showPassword"
                  :title="showPassword ? 'Hide password' : 'Show password'"
                >
                  {{ showPassword ? '🙈' : '👁️' }}
                </button>
              </div>
            </div>
          </div>

          <div class="form-group">
            <label for="db-name">Database Name <span class="optional">(optional)</span></label>
            <input
              id="db-name"
              type="text"
              v-model="form.db_name"
              placeholder="Leave blank to monitor all databases"
              autocomplete="off"
            />
            <span class="field-hint">
              Leave blank to monitor all accessible databases. Use a specific name to restrict monitoring scope.
            </span>
          </div>

          <!-- Connection preview -->
          <div class="conn-preview" v-if="form.db_host && form.db_user">
            <span class="conn-preview__label">Connection string preview</span>
            <code class="conn-preview__code">
              {{ form.db_type }}://{{ form.db_user }}:●●●●@{{ form.db_host }}:{{ form.db_port || defaultPort }}/{{ form.db_name || '*' }}
            </code>
          </div>
        </template>

        <!-- Clear credentials notice when type is none -->
        <div v-if="form.db_type === 'none' && hasExistingCredentials" class="clear-warning">
          ⚠️ Saving with <strong>None</strong> will permanently remove the stored credentials for this server.
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn-save" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>💾 Save Credentials</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { apiFetch } from '../utils/api';

interface ServerWithDb {
  id: number;
  name: string;
  db_type?: string;
  db_host?: string | null;
  db_port?: number | null;
  db_user?: string | null;
  db_name?: string | null;
  has_db_credentials?: boolean;
}

const props = defineProps<{
  server: ServerWithDb;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

// ── DB type options ─────────────────────────────────────────────────────────

const dbTypeOptions = [
  { value: 'none',       label: 'None',       icon: '🚫' },
  { value: 'mariadb',    label: 'MariaDB',    icon: '🦁' },
  { value: 'mysql',      label: 'MySQL',      icon: '🐬' },
  { value: 'postgresql', label: 'PostgreSQL', icon: '🐘' },
];

const DEFAULT_PORTS: Record<string, number> = {
  mariadb:    3306,
  mysql:      3306,
  postgresql: 5432,
  none:       0,
};

// ── Form state ───────────────────────────────────────────────────────────────

const serverName = computed(() => props.server.name);
const hasExistingCredentials = computed(() => props.server.has_db_credentials ?? false);

const form = ref({
  db_type:     (props.server.db_type || 'none') as string,
  db_host:     props.server.db_host || '',
  db_port:     props.server.db_port || 0,
  db_user:     props.server.db_user || '',
  db_password: '',
  db_name:     props.server.db_name || '',
});

const showPassword = ref(false);
const submitting   = ref(false);
const successMsg   = ref('');
const errorMsg     = ref('');

const defaultPort = computed(() => DEFAULT_PORTS[form.value.db_type] || 3306);

// Auto-fill default port when DB type changes
function selectDbType(type: string) {
  form.value.db_type = type;
  if (type !== 'none' && !form.value.db_port) {
    form.value.db_port = DEFAULT_PORTS[type];
  }
}

// Reset port when type changes to one with a different default
watch(() => form.value.db_type, (newType) => {
  if (newType !== 'none') {
    const expected = DEFAULT_PORTS[newType];
    const otherDefaults = Object.values(DEFAULT_PORTS).filter(p => p !== expected && p > 0);
    // Only auto-update if port is still one of the "default" values (not user-customised)
    if (otherDefaults.includes(form.value.db_port)) {
      form.value.db_port = expected;
    }
  }
});

// ── Submit ───────────────────────────────────────────────────────────────────

async function handleSubmit() {
  submitting.value = true;
  successMsg.value = '';
  errorMsg.value   = '';

  const payload: Record<string, unknown> = {
    db_type: form.value.db_type,
  };

  if (form.value.db_type !== 'none') {
    payload.db_host = form.value.db_host || null;
    payload.db_port = form.value.db_port || DEFAULT_PORTS[form.value.db_type];
    payload.db_user = form.value.db_user || null;
    payload.db_name = form.value.db_name || null;
    // Only include password if a new value was entered
    if (form.value.db_password) {
      payload.db_password = form.value.db_password;
    }
  }

  try {
    const res = await apiFetch(`/servers/${props.server.id}/db-credentials`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to save credentials.');

    successMsg.value = data.message || 'Credentials saved successfully.';
    // Clear password field after save
    form.value.db_password = '';

    setTimeout(() => {
      emit('saved');
      emit('close');
    }, 1200);
  } catch (e) {
    errorMsg.value = (e as Error).message;
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
/* ── Overlay ────────────────────────────────────────────────────────────────*/
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(5, 7, 12, 0.75);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

/* ── Modal card ─────────────────────────────────────────────────────────────*/
.db-cred-modal {
  width: 100%;
  max-width: 540px;
  padding: 0;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 16px;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.7);
  animation: modalSlideIn 0.28s cubic-bezier(0.16, 1, 0.3, 1);
  overflow: hidden;
}

@keyframes modalSlideIn {
  from { opacity: 0; transform: translateY(12px) scale(0.97); }
  to   { opacity: 1; transform: translateY(0)    scale(1); }
}

/* ── Header ─────────────────────────────────────────────────────────────────*/
.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 24px 28px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.06) 0%, rgba(168, 85, 247, 0.04) 100%);
}

.modal-title-block {
  display: flex;
  align-items: center;
  gap: 14px;
}

.modal-db-icon {
  font-size: 2rem;
  line-height: 1;
  filter: drop-shadow(0 0 10px rgba(99, 102, 241, 0.4));
}

.modal-title-block h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #f1f5f9;
  margin: 0 0 3px;
}

.modal-subtitle {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}

.modal-subtitle strong {
  color: #94a3b8;
}

.close-btn {
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 8px;
  color: #64748b;
  width: 30px;
  height: 30px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s;
  flex-shrink: 0;
}

.close-btn:hover {
  color: #fff;
  background: rgba(255, 255, 255, 0.08);
}

/* ── Security note ──────────────────────────────────────────────────────────*/
.security-note {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 10px 28px;
  background: rgba(16, 185, 129, 0.04);
  border-bottom: 1px solid rgba(16, 185, 129, 0.1);
  font-size: 0.78rem;
  color: #6ee7b7;
}

.lock-icon {
  font-size: 0.9rem;
  flex-shrink: 0;
}

/* ── Feedback ───────────────────────────────────────────────────────────────*/
.feedback-banner {
  margin: 16px 28px 0;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 500;
}

.feedback-banner.success {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.2);
  color: #a7f3d0;
}

.feedback-banner.error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  color: #fca5a5;
}

/* ── Form ───────────────────────────────────────────────────────────────────*/
.db-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
  padding: 24px 28px 28px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row {
  display: flex;
  gap: 14px;
}

.flex-1 { flex: 1; }
.flex-3 { flex: 3; }

.form-group label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  display: flex;
  align-items: center;
  gap: 8px;
}

.optional {
  font-weight: 400;
  text-transform: none;
  color: #475569;
  font-size: 0.7rem;
  letter-spacing: 0;
}

.existing-badge {
  background: rgba(99, 102, 241, 0.15);
  color: #a5b4fc;
  border: 1px solid rgba(99, 102, 241, 0.25);
  padding: 2px 8px;
  border-radius: 20px;
  font-size: 0.65rem;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: none;
}

.form-group input {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px;
  color: #f1f5f9;
  font-size: 0.9rem;
  font-family: inherit;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.form-group input::placeholder {
  color: #334155;
}

.field-hint {
  font-size: 0.72rem;
  color: #475569;
  line-height: 1.4;
}

/* ── Password toggle ────────────────────────────────────────────────────────*/
.password-wrap {
  position: relative;
}

.password-wrap input {
  width: 100%;
  padding-right: 40px;
  box-sizing: border-box;
}

.toggle-pass-btn {
  position: absolute;
  right: 8px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 0.95rem;
  line-height: 1;
  padding: 4px;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.toggle-pass-btn:hover {
  opacity: 1;
}

/* ── DB type grid ───────────────────────────────────────────────────────────*/
.db-type-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
}

.db-type-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
  padding: 12px 8px;
  background: rgba(255, 255, 255, 0.02);
  border: 1px solid rgba(255, 255, 255, 0.07);
  border-radius: 10px;
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.db-type-btn:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.15);
  color: #cbd5e1;
}

.db-type-btn.active {
  background: rgba(99, 102, 241, 0.12);
  border-color: rgba(99, 102, 241, 0.4);
  color: #a5b4fc;
  box-shadow: 0 0 0 1px rgba(99, 102, 241, 0.2);
}

.db-type-emoji {
  font-size: 1.4rem;
  line-height: 1;
}

/* ── Connection preview ─────────────────────────────────────────────────────*/
.conn-preview {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 12px 14px;
  background: rgba(0, 0, 0, 0.3);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
}

.conn-preview__label {
  font-size: 0.65rem;
  font-weight: 700;
  color: #475569;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.conn-preview__code {
  font-family: 'JetBrains Mono', 'Fira Code', monospace;
  font-size: 0.78rem;
  color: #7dd3fc;
  word-break: break-all;
}

/* ── Clear warning ──────────────────────────────────────────────────────────*/
.clear-warning {
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 8px;
  padding: 10px 14px;
  font-size: 0.83rem;
  color: #fcd34d;
  line-height: 1.4;
}

/* ── Actions ────────────────────────────────────────────────────────────────*/
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 4px;
}

.btn-cancel {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  color: #94a3b8;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 10px 18px;
  cursor: pointer;
  transition: all 0.2s;
}

.btn-cancel:hover {
  background: rgba(255, 255, 255, 0.07);
  color: #fff;
}

.btn-save {
  display: flex;
  align-items: center;
  gap: 8px;
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border: none;
  border-radius: 8px;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 10px 22px;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
  transition: all 0.2s;
}

.btn-save:hover:not(:disabled) {
  background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
  box-shadow: 0 6px 18px rgba(99, 102, 241, 0.4);
  transform: translateY(-1px);
}

.btn-save:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.spinner-small {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
  display: inline-block;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
