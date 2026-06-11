<template>
  <div class="modal-overlay" @click.self="$emit('close')">
    <div class="change-password-modal glass-card">
      <!-- Header -->
      <div class="modal-header">
        <div class="modal-title-block">
          <span class="modal-icon">🔑</span>
          <div>
            <h3>Change Password</h3>
            <p class="modal-subtitle">Update your account password securely</p>
          </div>
        </div>
        <button class="close-btn" @click="$emit('close')" aria-label="Close">✕</button>
      </div>

      <!-- Feedback banners -->
      <div v-if="successMsg" class="feedback-banner success">✅ {{ successMsg }}</div>
      <div v-if="errorMsg"   class="feedback-banner error">⚠️ {{ errorMsg }}</div>

      <form @submit.prevent="handleSubmit" class="modal-form">
        <!-- Current Password -->
        <div class="form-group">
          <label for="current-password">Current Password</label>
          <div class="password-input-wrapper">
            <input
              :type="showCurrent ? 'text' : 'password'"
              id="current-password"
              v-model="form.current_password"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
            <button 
              type="button" 
              class="password-toggle"
              @click="showCurrent = !showCurrent"
              :aria-label="showCurrent ? 'Hide password' : 'Show password'"
            >
              {{ showCurrent ? '👁️' : '👁️‍🗨️' }}
            </button>
          </div>
        </div>

        <!-- New Password -->
        <div class="form-group">
          <label for="new-password">New Password</label>
          <div class="password-input-wrapper">
            <input
              :type="showNew ? 'text' : 'password'"
              id="new-password"
              v-model="form.new_password"
              placeholder="••••••••"
              autocomplete="new-password"
              minlength="8"
              required
            />
            <button 
              type="button" 
              class="password-toggle"
              @click="showNew = !showNew"
              :aria-label="showNew ? 'Hide password' : 'Show password'"
            >
              {{ showNew ? '👁️' : '👁️‍🗨️' }}
            </button>
          </div>
        </div>

        <!-- Confirm Password -->
        <div class="form-group">
          <label for="new-password-confirmation">Confirm New Password</label>
          <div class="password-input-wrapper">
            <input
              :type="showConfirm ? 'text' : 'password'"
              id="new-password-confirmation"
              v-model="form.new_password_confirmation"
              placeholder="••••••••"
              autocomplete="new-password"
              required
            />
            <button 
              type="button" 
              class="password-toggle"
              @click="showConfirm = !showConfirm"
              :aria-label="showConfirm ? 'Hide password' : 'Show password'"
            >
              {{ showConfirm ? '👁️' : '👁️‍🗨️' }}
            </button>
          </div>
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button type="button" class="btn-cancel" @click="$emit('close')">Cancel</button>
          <button type="submit" class="btn-save" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>Update Password</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { apiFetch } from '../utils/api';

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'success'): void;
}>();

const form = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const submitting = ref(false);
const successMsg = ref('');
const errorMsg   = ref('');

async function handleSubmit() {
  if (form.value.new_password !== form.value.new_password_confirmation) {
    errorMsg.value = 'New password and confirmation do not match.';
    return;
  }
  if (form.value.new_password.length < 8) {
    errorMsg.value = 'New password must be at least 8 characters long.';
    return;
  }

  submitting.value = true;
  successMsg.value = '';
  errorMsg.value   = '';

  try {
    const res = await apiFetch('/auth/change-password', {
      method: 'POST',
      body: JSON.stringify(form.value),
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to change password.');

    successMsg.value = data.message || 'Password changed successfully.';
    
    // Clear passwords
    form.value.current_password = '';
    form.value.new_password = '';
    form.value.new_password_confirmation = '';

    setTimeout(() => {
      emit('success');
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
.change-password-modal {
  width: 100%;
  max-width: 440px;
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

.modal-icon {
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
.modal-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
  padding: 24px 28px 28px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-group label {
  font-size: 0.72rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.password-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  width: 100%;
}

.password-input-wrapper input {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 40px 10px 12px;
  color: #f1f5f9;
  font-size: 0.9rem;
  font-family: inherit;
  width: 100%;
  box-sizing: border-box;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.password-input-wrapper input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.password-toggle {
  position: absolute;
  right: 12px;
  background: transparent;
  border: none;
  color: #64748b;
  cursor: pointer;
  font-size: 1.1rem;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: color 0.2s;
}

.password-toggle:hover {
  color: #cbd5e1;
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
