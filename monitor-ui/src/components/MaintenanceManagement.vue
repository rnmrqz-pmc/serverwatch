<template>
  <div class="maintenance-management">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2>Maintenance & Settings</h2>
        <p class="section-desc">Manage system parameters, outgoing email configurations, and platform maintenance tasks.</p>
      </div>
    </div>

    <!-- Feedback banners -->
    <div v-if="successMsg" class="feedback-banner success">
      <span>✅ {{ successMsg }}</span>
      <button class="close-feedback" @click="successMsg = ''">×</button>
    </div>
    <div v-if="errorMsg" class="feedback-banner error">
      <span>⚠️ {{ errorMsg }}</span>
      <button class="close-feedback" @click="errorMsg = ''">×</button>
    </div>

    <!-- Layout Grid -->
    <div class="settings-grid">
      <!-- SMTP Config Card -->
      <div class="glass-card settings-card">
        <div class="card-header">
          <span class="card-icon">📧</span>
          <div>
            <h3>Outgoing SMTP Configuration</h3>
            <p class="card-subtitle">Configure credentials and server parameters for sending alert emails.</p>
          </div>
        </div>

        <div class="security-note">
          <span class="lock-icon">🔒</span>
          <span>Mail server passwords are stored AES-256 encrypted at rest and never exposed.</span>
        </div>

        <form @submit.prevent="handleSave" class="settings-form">
          <div class="form-row">
            <div class="form-group flex-3">
              <label for="smtp-host">SMTP Host</label>
              <input
                id="smtp-host"
                type="text"
                v-model="form.mail_host"
                placeholder="e.g. smtp.gmail.com"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label for="smtp-port">SMTP Port</label>
              <input
                id="smtp-port"
                type="number"
                v-model.number="form.mail_port"
                placeholder="587"
                min="1"
                max="65535"
                required
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group flex-2">
              <label for="smtp-encryption">Encryption</label>
              <select id="smtp-encryption" v-model="form.mail_encryption" required>
                <option value="none">None (Plaintext)</option>
                <option value="tls">STARTTLS (TLS)</option>
                <option value="ssl">SSL/TLS</option>
              </select>
            </div>
            <div class="form-group flex-2">
              <label for="smtp-user">SMTP Username</label>
              <input
                id="smtp-user"
                type="text"
                v-model="form.mail_username"
                placeholder="e.g. alerts@yourdomain.com"
              />
            </div>
          </div>

          <div class="form-group">
            <label for="smtp-pass">
              SMTP Password
              <span v-if="hasExistingPassword" class="existing-badge">stored ●●●●●●</span>
            </label>
            <div class="password-wrap">
              <input
                id="smtp-pass"
                :type="showPassword ? 'text' : 'password'"
                v-model="form.mail_password"
                :placeholder="hasExistingPassword ? 'Leave blank to keep current' : 'Enter SMTP password'"
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

          <div class="form-row">
            <div class="form-group flex-2">
              <label for="smtp-from-addr">Sender Address</label>
              <input
                id="smtp-from-addr"
                type="email"
                v-model="form.mail_from_address"
                placeholder="e.g. alerts@yourdomain.com"
                required
              />
            </div>
            <div class="form-group flex-2">
              <label for="smtp-from-name">Sender Name</label>
              <input
                id="smtp-from-name"
                type="text"
                v-model="form.mail_from_name"
                placeholder="e.g. ServerWatcher Alerts"
                required
              />
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-save" :disabled="saving">
              <span v-if="saving" class="spinner-small"></span>
              <span v-else>💾 Save Configuration</span>
            </button>
          </div>
        </form>
      </div>

      <!-- Test SMTP Card -->
      <div class="glass-card test-card">
        <div class="card-header">
          <span class="card-icon">🧪</span>
          <div>
            <h3>Test Connection</h3>
            <p class="card-subtitle">Verify SMTP server routing by sending an end-to-end test message.</p>
          </div>
        </div>

        <div class="test-form">
          <div class="form-group">
            <label for="test-email">Recipient Email Address</label>
            <input
              id="test-email"
              type="email"
              v-model="testEmail"
              placeholder="e.g. admin@yourdomain.com"
              required
            />
            <span class="field-hint">Enter your personal or administrator email to check delivery.</span>
          </div>

          <div class="test-feedback-box" v-if="testResult">
            <div class="test-badge" :class="testResult.status">
              {{ testResult.status === 'success' ? 'SUCCESS' : 'FAILURE' }}
            </div>
            <p class="test-msg">{{ testResult.message }}</p>
          </div>

          <button
            type="button"
            class="btn-test"
            @click="handleTest"
            :disabled="testing || !testEmail || !form.mail_host"
          >
            <span v-if="testing" class="spinner-small"></span>
            <span v-else>🚀 Send Test Email</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiFetch } from '../utils/api';

const form = ref({
  mail_host: '',
  mail_port: 587,
  mail_encryption: 'tls',
  mail_username: '',
  mail_password: '',
  mail_from_address: '',
  mail_from_name: '',
});

const hasExistingPassword = ref(false);
const showPassword = ref(false);
const saving = ref(false);
const testing = ref(false);

const successMsg = ref('');
const errorMsg = ref('');

const testEmail = ref('');
const testResult = ref<{ status: 'success' | 'error'; message: string } | null>(null);

async function fetchSettings() {
  errorMsg.value = '';
  try {
    const res = await apiFetch('/maintenance/smtp');
    if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
    const data = await res.json();
    
    form.value.mail_host = data.mail_host || '';
    form.value.mail_port = data.mail_port || 587;
    form.value.mail_encryption = data.mail_encryption || 'tls';
    form.value.mail_username = data.mail_username || '';
    form.value.mail_from_address = data.mail_from_address || '';
    form.value.mail_from_name = data.mail_from_name || '';
    hasExistingPassword.value = data.has_password || false;
  } catch (e) {
    errorMsg.value = 'Failed to load SMTP configuration settings.';
    console.error(e);
  }
}

async function handleSave() {
  saving.value = true;
  successMsg.value = '';
  errorMsg.value = '';

  const payload: Record<string, any> = {
    mail_host: form.value.mail_host,
    mail_port: form.value.mail_port,
    mail_encryption: form.value.mail_encryption,
    mail_username: form.value.mail_username || null,
    mail_from_address: form.value.mail_from_address,
    mail_from_name: form.value.mail_from_name,
  };

  // Only send password if updated
  if (form.value.mail_password) {
    payload.mail_password = form.value.mail_password;
  } else if (!hasExistingPassword.value) {
    payload.mail_password = null;
  }

  try {
    const res = await apiFetch('/maintenance/smtp', {
      method: 'PUT',
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to save configuration.');

    successMsg.value = 'SMTP Configuration updated successfully!';
    form.value.mail_password = '';
    await fetchSettings();
  } catch (e) {
    errorMsg.value = (e as Error).message;
  } finally {
    saving.value = false;
  }
}

async function handleTest() {
  testing.value = true;
  testResult.value = null;

  const payload = {
    email: testEmail.value,
    mail_host: form.value.mail_host,
    mail_port: form.value.mail_port,
    mail_encryption: form.value.mail_encryption,
    mail_username: form.value.mail_username || null,
    mail_password: form.value.mail_password || '',
    mail_from_address: form.value.mail_from_address,
    mail_from_name: form.value.mail_from_name,
  };

  try {
    const res = await apiFetch('/maintenance/smtp/test', {
      method: 'POST',
      body: JSON.stringify(payload),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Connection test failed.');

    testResult.value = {
      status: 'success',
      message: data.message || 'Test email dispatched successfully!',
    };
  } catch (e) {
    testResult.value = {
      status: 'error',
      message: (e as Error).message,
    };
  } finally {
    testing.value = false;
  }
}

onMounted(() => {
  fetchSettings();
});
</script>

<style scoped>
.maintenance-management {
  width: 100%;
}

.section-desc {
  color: #94a3b8;
  font-size: 0.9rem;
  margin-top: 4px;
}

/* Feedback Banners */
.feedback-banner {
  margin-top: 20px;
  border-radius: 8px;
  padding: 12px 18px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.9rem;
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

.close-feedback {
  background: transparent;
  border: none;
  color: inherit;
  font-size: 1.25rem;
  cursor: pointer;
  line-height: 1;
}

/* Layout Grid */
.settings-grid {
  display: grid;
  grid-template-columns: 1.5fr 1fr;
  gap: 24px;
  margin-top: 32px;
}

@media (max-width: 1024px) {
  .settings-grid {
    grid-template-columns: 1fr;
  }
}

/* Settings & Test Cards */
.settings-card, .test-card {
  padding: 28px;
  background: #0f172a;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 12px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
  display: flex;
  flex-direction: column;
}

.card-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 20px;
}

.card-icon {
  font-size: 2.2rem;
  line-height: 1;
  filter: drop-shadow(0 0 8px rgba(99, 102, 241, 0.3));
}

.card-header h3 {
  font-size: 1.1rem;
  font-weight: 700;
  color: #f1f5f9;
  margin: 0 0 4px 0;
}

.card-subtitle {
  font-size: 0.8rem;
  color: #64748b;
  margin: 0;
}

/* Security note */
.security-note {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
  padding: 10px 16px;
  background: rgba(16, 185, 129, 0.04);
  border: 1px solid rgba(16, 185, 129, 0.15);
  border-radius: 8px;
  font-size: 0.78rem;
  color: #6ee7b7;
}

.lock-icon {
  font-size: 0.9rem;
}

/* Forms */
.settings-form, .test-form {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-row {
  display: flex;
  gap: 16px;
}

.flex-1 { flex: 1; }
.flex-2 { flex: 2; }
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

.form-group input, .form-group select {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px;
  color: #f1f5f9;
  font-size: 0.9rem;
  font-family: inherit;
  transition: border-color 0.2s, box-shadow 0.2s;
  min-height: 42px;
  box-sizing: border-box;
}

.form-group select {
  appearance: none;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 14px center;
  background-size: 16px;
  padding-right: 40px;
}

.form-group input:focus, .form-group select:focus {
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

/* Password input wrap */
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

/* Actions */
.form-actions {
  display: flex;
  justify-content: flex-start;
  margin-top: 8px;
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
  padding: 12px 24px;
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

/* Test SMTP details */
.btn-test {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  color: #fff;
  font-size: 0.875rem;
  font-weight: 600;
  padding: 12px 24px;
  cursor: pointer;
  transition: all 0.2s;
  margin-top: 10px;
}

.btn-test:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-1px);
}

.btn-test:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

/* Connection feedback */
.test-feedback-box {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  padding: 14px;
  margin-top: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.test-badge {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 800;
  padding: 3px 6px;
  border-radius: 4px;
  align-self: flex-start;
}

.test-badge.success {
  background: rgba(16, 185, 129, 0.2);
  color: #34d399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.test-badge.error {
  background: rgba(239, 68, 68, 0.2);
  color: #f87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.test-msg {
  font-size: 0.85rem;
  color: #cbd5e1;
  margin: 0;
  word-break: break-all;
}

/* Spinner */
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
