import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { apiFetch } from '../utils/api';

export interface User {
  id: number;
  name: string;
  email: string;
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(localStorage.getItem('auth_token'));
  const user = ref<User | null>(null);
  const loading = ref(false);
  const error = ref<string | null>(null);

  const isAuthenticated = computed(() => !!token.value);

  function reset() {
    token.value = null;
    user.value = null;
    localStorage.removeItem('auth_token');
  }

  async function login(email: string, password: string): Promise<boolean> {
    loading.value = true;
    error.value = null;
    try {
      const response = await apiFetch('/auth/login', {
        method: 'POST',
        body: JSON.stringify({ email, password, device_name: 'web_app' }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Login failed. Please check your credentials.');
      }

      token.value = data.token;
      user.value = data.user;
      localStorage.setItem('auth_token', data.token);
      return true;
    } catch (err) {
      error.value = (err as Error).message;
      return false;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    loading.value = true;
    try {
      await apiFetch('/auth/logout', { method: 'POST' });
    } catch (e) {
      console.error('Logout request failed:', e);
    } finally {
      reset();
      loading.value = false;
    }
  }

  async function fetchMe(): Promise<boolean> {
    if (!token.value) return false;
    loading.value = true;
    try {
      const response = await apiFetch('/auth/me');
      if (response.ok) {
        user.value = await response.json();
        return true;
      } else {
        reset();
        return false;
      }
    } catch (e) {
      console.error('Fetch me failed:', e);
      reset();
      return false;
    } finally {
      loading.value = false;
    }
  }

  // Setup global event listener for unauthorized requests
  if (typeof window !== 'undefined') {
    window.addEventListener('auth:unauthorized', () => {
      reset();
    });
  }

  return {
    token,
    user,
    loading,
    error,
    isAuthenticated,
    login,
    logout,
    fetchMe,
    reset,
  };
});
