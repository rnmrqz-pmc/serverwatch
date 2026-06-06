<template>
  <div class="login-wrapper">
    <div class="login-glow"></div>
    <div class="glass-card login-card">
      <div class="login-header">
        <img :src="bdLogo" alt="BD Logo" class="login-logo-img" />
        <h2>Welcome to BIT DevOps ServerWatcher</h2>
      </div>

      <form @submit.prevent="handleSubmit" class="login-form">
        <div v-if="authStore.error" class="login-error">
          <span>⚠️ {{ authStore.error }}</span>
        </div>

        <div class="form-group">
          <label for="email">Email Address</label>
          <div class="input-container">
            <span class="input-icon">✉️</span>
            <input 
              type="email" 
              id="email" 
              v-model="email" 
              placeholder="name@example.com" 
              required
              :disabled="authStore.loading"
            />
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-container">
            <span class="input-icon">🔒</span>
            <input 
              type="password" 
              id="password" 
              v-model="password" 
              placeholder="••••••••" 
              required
              :disabled="authStore.loading"
            />
          </div>
        </div>

        <button type="submit" class="submit-btn" :disabled="authStore.loading">
          <span v-if="authStore.loading" class="spinner"></span>
          <span v-else>Sign In</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore } from '../stores/auth';
import bdLogo from '../assets/BD.png';

const authStore = useAuthStore();
const email = ref('');
const password = ref('');

async function handleSubmit() {
  if (!email.value || !password.value) return;
  await authStore.login(email.value, password.value);
}
</script>

<style scoped>
.login-wrapper {
  min-height: 100vh;
  width: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  position: relative;
  background: radial-gradient(circle at 50% 30%, #1a1e36 0%, #0b0d16 70%);
  padding: 20px;
}

.login-glow {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 400px;
  height: 400px;
  background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, rgba(99, 102, 241, 0) 70%);
  pointer-events: none;
  filter: blur(40px);
}

.login-card {
  width: 100%;
  max-width: 440px;
  backdrop-filter: blur(24px);
  -webkit-backdrop-filter: blur(24px);
  border: 1px solid rgba(255, 255, 255, 0.08);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
  padding: 40px;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.login-header {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  margin-bottom: 32px;
}

.login-logo-img {
  width: 132px;
  height: 132px;
  object-fit: contain;
  border-radius: 8px;
  margin-bottom: 16px;
}

.login-header h2 {
  font-size: 1.75rem;
  font-weight: 800;
  margin-bottom: 8px;
  background: linear-gradient(135deg, #fff 40%, #c7d2fe 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.login-header p {
  color: #94a3b8;
  font-size: 0.9rem;
  line-height: 1.4;
}

.login-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.login-error {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  padding: 12px 16px;
  color: #fca5a5;
  font-size: 0.85rem;
  font-weight: 500;
  display: flex;
  align-items: center;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-group label {
  font-size: 0.8rem;
  font-weight: 600;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.input-container {
  position: relative;
  display: flex;
  align-items: center;
}

.input-icon {
  position: absolute;
  left: 14px;
  font-size: 1rem;
  color: #64748b;
  pointer-events: none;
}

.input-container input {
  width: 100%;
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 12px 16px 12px 42px;
  color: #fff;
  font-size: 0.95rem;
  transition: all 0.2s ease;
  font-family: inherit;
}

.input-container input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  background: rgba(15, 23, 42, 0.8);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.input-container input::placeholder {
  color: #475569;
}

.submit-btn {
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border: none;
  border-radius: 8px;
  padding: 14px;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.submit-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
  background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
}

.submit-btn:active:not(:disabled) {
  transform: translateY(1px);
  box-shadow: 0 2px 6px rgba(99, 102, 241, 0.2);
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.spinner {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
