<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="ssh-cred-modal glass-card">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-title-block">
          <span class="modal-ssh-icon">🔑</span>
          <div>
            <h3>SSH Credentials</h3>
            <p class="modal-subtitle">Configure SSH management access for <strong>{{ serverName }}</strong></p>
          </div>
        </div>
        <button class="close-btn" @click="$emit('close')" aria-label="Close">✕</button>
      </div>

      <!-- Security note -->
      <div class="security-note">
        <span class="lock-icon">🔒</span>
        <span>Credentials are AES-256 encrypted at rest and <strong>never</strong> returned via the API.</span>
      </div>

      <!-- Feedback banners -->
      <div v-if="successMsg" class="feedback-banner success">✅ {{ successMsg }}</div>
      <div v-if="errorMsg"   class="feedback-banner error">⚠️ {{ errorMsg }}</div>

      <form @submit.prevent="handleSubmit" class="ssh-form">

        <!-- Connection details -->
        <div class="form-row">
          <div class="form-group flex-3">
            <label for="ssh-user">SSH Username</label>
            <input
              id="ssh-user"
              type="text"
              v-model="form.ssh_user"
              placeholder="e.g. ubuntu, root, deployer"
              autocomplete="off"
            />
          </div>
          <div class="form-group flex-1">
            <label for="ssh-port">SSH Port</label>
            <input
              id="ssh-port"
              type="number"
              v-model.number="form.ssh_port"
              placeholder="22"
              min="1"
              max="65535"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="ssh-pass">
            SSH Password
            <span v-if="hasExistingCredentials" class="existing-badge">stored ●●●●●●</span>
          </label>
          <div class="password-wrap">
            <input
              id="ssh-pass"
              :type="showPassword ? 'text' : 'password'"
              v-model="form.ssh_password"
              :placeholder="hasExistingCredentials ? 'Leave blank to keep current' : 'Enter SSH password'"
              autocomplete="new-password"
              :required="!hasExistingCredentials && !!form.ssh_user"
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
          <span class="field-hint">
            Ensure this user has sufficient privileges to read system metrics (or sudo access if needed).
          </span>
        </div>

        <!-- Connection preview -->
        <div class="conn-preview" v-if="form.ssh_user">
          <span class="conn-preview__label">SSH Connection string preview</span>
          <code class="conn-preview__code">
            ssh {{ form.ssh_user }}@{{ serverIp }} -p {{ form.ssh_port || 22 }}
          </code>
        </div>

        <!-- Clear credentials notice -->
        <div v-if="!form.ssh_user && hasExistingCredentials" class="clear-warning">
          ⚠️ Leaving the username blank will permanently remove the stored SSH credentials for this server upon saving.
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn-save" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>💾 Save SSH Config</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { apiFetch } from '../utils/api';

interface ServerWithSsh {
  id: number;
  name: string;
  ip: string;
  ssh_user?: string | null;
  ssh_port?: number | null;
  has_ssh_credentials?: boolean;
}

const props = defineProps<{
  server: ServerWithSsh;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

// ── Form state ───────────────────────────────────────────────────────────────

const serverName = computed(() => props.server.name);
const serverIp   = computed(() => props.server.ip);
const hasExistingCredentials = computed(() => props.server.has_ssh_credentials ?? false);

const form = ref({
  ssh_user:     props.server.ssh_user || '',
  ssh_port:     props.server.ssh_port || 22,
  ssh_password: '',
});

const showPassword = ref(false);
const submitting   = ref(false);
const successMsg   = ref('');
const errorMsg     = ref('');

// ── Submit ───────────────────────────────────────────────────────────────────

async function handleSubmit() {
  submitting.value = true;
  successMsg.value = '';
  errorMsg.value   = '';

  const payload: Record<string, unknown> = {
    ssh_user: form.value.ssh_user || null,
    ssh_port: form.value.ssh_port || 22,
  };

  // Only include password if a value is specified or required
  if (form.value.ssh_password) {
    payload.ssh_password = form.value.ssh_password;
  }

  try {
    const res = await apiFetch(`/servers/${props.server.id}/ssh-credentials`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to save SSH credentials.');

    successMsg.value = data.message || 'SSH credentials saved successfully.';
    form.value.ssh_password = '';

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
.ssh-cred-modal {
  width: 100%;
  max-width: 500px;
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

.modal-ssh-icon {
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
.ssh-form {
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
