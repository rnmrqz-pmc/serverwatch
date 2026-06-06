<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="thresholds-modal glass-card">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-title-block">
          <span class="modal-alert-icon">🔔</span>
          <div>
            <h3>Alert Thresholds</h3>
            <p class="modal-subtitle">Configure CPU, RAM, and Disk metrics thresholds for <strong>{{ serverName }}</strong></p>
          </div>
        </div>
        <button class="close-btn" @click="$emit('close')" aria-label="Close">✕</button>
      </div>

      <!-- Help note -->
      <div class="help-note">
        <span class="info-icon">ℹ️</span>
        <span>Set the resource usage percentage (1-100) that will trigger email alerts for each severity level.</span>
      </div>

      <!-- Feedback banners -->
      <div v-if="successMsg" class="feedback-banner success">✅ {{ successMsg }}</div>
      <div v-if="errorMsg"   class="feedback-banner error">⚠️ {{ errorMsg }}</div>

      <form @submit.prevent="handleSubmit" class="thresholds-form">

        <!-- CPU Section -->
        <div class="metric-section">
          <div class="metric-section-title">
            <h4>CPU Thresholds</h4>
          </div>
          <div class="form-row">
            <div class="form-group flex-1">
              <label>Info (%)</label>
              <input
                type="number"
                v-model.number="form.cpu_threshold_info"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Alert (%)</label>
              <input
                type="number"
                v-model.number="form.cpu_threshold_warning"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Critical (%)</label>
              <input
                type="number"
                v-model.number="form.cpu_threshold_critical"
                min="1"
                max="100"
                required
              />
            </div>
          </div>
        </div>

        <!-- RAM Section -->
        <div class="metric-section">
          <div class="metric-section-title">
            <h4>RAM Thresholds</h4>
          </div>
          <div class="form-row">
            <div class="form-group flex-1">
              <label>Info (%)</label>
              <input
                type="number"
                v-model.number="form.ram_threshold_info"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Alert (%)</label>
              <input
                type="number"
                v-model.number="form.ram_threshold_warning"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Critical (%)</label>
              <input
                type="number"
                v-model.number="form.ram_threshold_critical"
                min="1"
                max="100"
                required
              />
            </div>
          </div>
        </div>

        <!-- Disk Section -->
        <div class="metric-section">
          <div class="metric-section-title">
            <h4>Disk Thresholds</h4>
          </div>
          <div class="form-row">
            <div class="form-group flex-1">
              <label>Info (%)</label>
              <input
                type="number"
                v-model.number="form.disk_threshold_info"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Alert (%)</label>
              <input
                type="number"
                v-model.number="form.disk_threshold_warning"
                min="1"
                max="100"
                required
              />
            </div>
            <div class="form-group flex-1">
              <label>Critical (%)</label>
              <input
                type="number"
                v-model.number="form.disk_threshold_critical"
                min="1"
                max="100"
                required
              />
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn-save" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>💾 Save Thresholds</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { apiFetch } from '../utils/api';

interface ServerWithThresholds {
  id: number;
  name: string;
  ip: string;
  cpu_threshold_info?: number;
  cpu_threshold_warning?: number;
  cpu_threshold_critical?: number;
  ram_threshold_info?: number;
  ram_threshold_warning?: number;
  ram_threshold_critical?: number;
  disk_threshold_info?: number;
  disk_threshold_warning?: number;
  disk_threshold_critical?: number;
}

const props = defineProps<{
  server: ServerWithThresholds;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const serverName = computed(() => props.server.name);

// Setup form defaults, fallback to standard settings if database values are null/undefined
const form = ref({
  cpu_threshold_info:     props.server.cpu_threshold_info ?? 60,
  cpu_threshold_warning:  props.server.cpu_threshold_warning ?? 70,
  cpu_threshold_critical: props.server.cpu_threshold_critical ?? 90,
  ram_threshold_info:     props.server.ram_threshold_info ?? 60,
  ram_threshold_warning:  props.server.ram_threshold_warning ?? 70,
  ram_threshold_critical: props.server.ram_threshold_critical ?? 90,
  disk_threshold_info:     props.server.disk_threshold_info ?? 60,
  disk_threshold_warning:  props.server.disk_threshold_warning ?? 70,
  disk_threshold_critical: props.server.disk_threshold_critical ?? 90,
});

const submitting = ref(false);
const successMsg = ref('');
const errorMsg   = ref('');

async function handleSubmit() {
  // Client-side validations
  if (form.value.cpu_threshold_info > form.value.cpu_threshold_warning || 
      form.value.cpu_threshold_warning > form.value.cpu_threshold_critical) {
    errorMsg.value = 'CPU thresholds must satisfy: Info <= Alert <= Critical.';
    return;
  }
  if (form.value.ram_threshold_info > form.value.ram_threshold_warning || 
      form.value.ram_threshold_warning > form.value.ram_threshold_critical) {
    errorMsg.value = 'RAM thresholds must satisfy: Info <= Alert <= Critical.';
    return;
  }
  if (form.value.disk_threshold_info > form.value.disk_threshold_warning || 
      form.value.disk_threshold_warning > form.value.disk_threshold_critical) {
    errorMsg.value = 'Disk thresholds must satisfy: Info <= Alert <= Critical.';
    return;
  }

  submitting.value = true;
  successMsg.value = '';
  errorMsg.value   = '';

  try {
    const res = await apiFetch(`/servers/${props.server.id}/thresholds`, {
      method: 'PUT',
      body: JSON.stringify(form.value),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to save alert thresholds.');

    successMsg.value = data.message || 'Thresholds updated successfully.';

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
/* Overlay */
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

/* Modal Card */
.thresholds-modal {
  width: 100%;
  max-width: 520px;
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

/* Header */
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

.modal-alert-icon {
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

/* Help note */
.help-note {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 12px 28px;
  background: rgba(99, 102, 241, 0.04);
  border-bottom: 1px solid rgba(99, 102, 241, 0.08);
  font-size: 0.78rem;
  color: #a5b4fc;
}

.info-icon {
  font-size: 0.9rem;
  flex-shrink: 0;
}

/* Feedback */
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

/* Form */
.thresholds-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px 28px 28px;
}

.metric-section {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 16px;
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.03);
  border-radius: 10px;
}

.metric-section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  padding-bottom: 8px;
}

.metric-section-title h4 {
  font-size: 0.9rem;
  font-weight: 600;
  color: #cbd5e1;
  margin: 0;
}

.metric-icon {
  font-size: 1.1rem;
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

.form-group label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.form-group input {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 12px;
  color: #f1f5f9;
  font-size: 0.9rem;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
  text-align: center;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.form-group input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

/* Actions */
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding-top: 8px;
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
