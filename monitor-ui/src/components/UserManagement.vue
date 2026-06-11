<template>
  <div class="user-management">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2>User Management</h2>
        <p class="section-desc">Manage admin credentials and monitor access permissions.</p>
      </div>
      <button 
        class="add-user-btn" 
        @click="openAddModal"
        v-if="authStore.hasPermission('users', 'create')"
      >
        <span class="plus-icon">+</span> Add New User
      </button>
    </div>

    <!-- Feedback Alerts -->
    <div v-if="successMsg" class="feedback-banner success">
      <span>✅ {{ successMsg }}</span>
      <button class="close-feedback" @click="successMsg = ''">×</button>
    </div>
    <div v-if="errorMsg" class="feedback-banner error">
      <span>⚠️ {{ errorMsg }}</span>
      <button class="close-feedback" @click="errorMsg = ''">×</button>
    </div>

    <!-- User Grid/List -->
    <div class="users-container glass-card">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading users...</p>
      </div>

      <div v-else-if="users.length === 0" class="empty-state">
        <p>No administrative users found.</p>
      </div>

      <table v-else class="users-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Created At</th>
            <th class="actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in users" :key="user.id" :class="{ 'current-user-row': authStore.user?.id === user.id }">
            <td>
              <div class="user-name-cell">
                <span class="user-avatar">{{ user.name.charAt(0).toUpperCase() }}</span>
                <div>
                  <span class="user-name">{{ user.name }}</span>
                  <span v-if="authStore.user?.id === user.id" class="self-badge">You</span>
                </div>
              </div>
            </td>
            <td>
              <span class="user-email">{{ user.email }}</span>
            </td>
            <td>
              <span class="user-date">{{ formatDate(user.created_at) }}</span>
            </td>
            <td class="actions-col">
              <div class="action-buttons">
                <button 
                  class="action-btn reset-btn" 
                  @click="confirmResetPassword(user)" 
                  :disabled="authStore.user?.id === user.id || !authStore.hasPermission('users', 'update')"
                  title="Reset Password"
                >
                  🔑
                </button>
                <button 
                  class="action-btn edit-btn" 
                  @click="openEditModal(user)" 
                  :disabled="authStore.user?.id !== user.id && !authStore.hasPermission('users', 'update')"
                  title="Edit User"
                >
                  ✏️
                </button>
                <button 
                  class="action-btn delete-btn" 
                  @click="confirmDelete(user)" 
                  :disabled="authStore.user?.id === user.id || !authStore.hasPermission('users', 'delete')"
                  title="Delete User"
                >
                  🗑️
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Add/Edit Modal Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="glass-card modal-card">
        <div class="modal-header">
          <h3>{{ isEditMode ? 'Edit User Credentials' : 'Create Administrative User' }}</h3>
          <button class="close-modal-btn" @click="closeModal">×</button>
        </div>
        
        <form @submit.prevent="handleSubmit" class="modal-form">
          <div class="form-group">
            <label for="modal-name">Full Name</label>
            <input 
              type="text" 
              id="modal-name" 
              v-model="form.name" 
              placeholder="Alex Johnson" 
              required
            />
          </div>

          <div class="form-group">
            <label for="modal-email">Email Address</label>
            <input 
              type="email" 
              id="modal-email" 
              v-model="form.email" 
              placeholder="alex@example.com" 
              required
            />
          </div>

          <div class="form-group" v-if="isEditMode">
            <label for="modal-password">
              Password 
              <span class="optional-label">(leave blank to keep current)</span>
            </label>
            <input 
              type="password" 
              id="modal-password" 
              v-model="form.password" 
              placeholder="••••••••" 
            />
          </div>

          <!-- Permissions matrix section -->
          <div class="form-group permissions-group">
            <label>Permissions Configuration</label>
            <div class="permissions-matrix">
              <!-- Servers -->
              <div class="matrix-row">
                <span class="matrix-module">Server Settings</span>
                <div class="matrix-checkboxes">
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.servers" value="view" :disabled="authStore.user?.id === form.id" /> View
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.servers" value="create" :disabled="authStore.user?.id === form.id" /> Create
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.servers" value="update" :disabled="authStore.user?.id === form.id" /> Update
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.servers" value="delete" :disabled="authStore.user?.id === form.id" /> Delete
                  </label>
                </div>
              </div>

              <!-- Users -->
              <div class="matrix-row">
                <span class="matrix-module">Users</span>
                <div class="matrix-checkboxes">
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.users" value="view" :disabled="authStore.user?.id === form.id" /> View
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.users" value="create" :disabled="authStore.user?.id === form.id" /> Create
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.users" value="update" :disabled="authStore.user?.id === form.id" /> Update
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.users" value="delete" :disabled="authStore.user?.id === form.id" /> Delete
                  </label>
                </div>
              </div>

              <!-- Maintenance -->
              <div class="matrix-row">
                <span class="matrix-module">Maintenance</span>
                <div class="matrix-checkboxes">
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.maintenance" value="view" :disabled="authStore.user?.id === form.id" /> View
                  </label>
                  <label class="matrix-checkbox-label">
                    <input type="checkbox" v-model="form.permissions.maintenance" value="update" :disabled="authStore.user?.id === form.id" /> Update
                  </label>
                </div>
              </div>
            </div>
          </div>

          <div class="modal-actions">
            <button type="button" class="modal-btn-cancel" @click="closeModal">Cancel</button>
            <button type="submit" class="modal-btn-submit" :disabled="submitting">
              <span v-if="submitting" class="spinner-small"></span>
              <span v-else>{{ isEditMode ? 'Save Changes' : 'Create User' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDeleteModal">
      <div class="glass-card modal-card delete-confirm-card">
        <h3>Delete User Account?</h3>
        <p>Are you sure you want to delete <strong>{{ selectedUser?.name }}</strong> ({{ selectedUser?.email }})? This action cannot be undone.</p>
        
        <div class="modal-actions delete-actions">
          <button type="button" class="modal-btn-cancel" @click="closeDeleteModal">Cancel</button>
          <button type="button" class="modal-btn-danger" @click="deleteUser" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>Confirm Delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Reset Password Confirmation Modal -->
    <div v-if="showResetModal" class="modal-overlay" @click.self="closeResetModal">
      <div class="glass-card modal-card delete-confirm-card">
        <h3 style="color: #818cf8;">Reset User Password?</h3>
        <p>Are you sure you want to reset the password for <strong>{{ selectedUser?.name }}</strong> ({{ selectedUser?.email }})? A new random 12-character password will be generated and emailed to them immediately.</p>
        
        <div class="modal-actions delete-actions">
          <button type="button" class="modal-btn-cancel" @click="closeResetModal">Cancel</button>
          <button type="button" class="modal-btn-submit" @click="resetPassword" :disabled="submitting" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>Confirm Reset</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useAuthStore } from '../stores/auth';
import { apiFetch } from '../utils/api';

interface User {
  id: number;
  name: string;
  email: string;
  permissions?: {
    servers: string[];
    users: string[];
    maintenance: string[];
  };
  created_at: string;
}

const authStore = useAuthStore();
const users = ref<User[]>([]);
const loading = ref(false);
const submitting = ref(false);

const successMsg = ref('');
const errorMsg = ref('');

// Modal visibility states
const showModal = ref(false);
const isEditMode = ref(false);
const showDeleteModal = ref(false);
const showResetModal = ref(false);
const selectedUser = ref<User | null>(null);

// Form data
const form = ref({
  id: 0,
  name: '',
  email: '',
  password: '',
  permissions: {
    servers: ['view', 'create', 'update', 'delete'],
    users: ['view', 'create', 'update', 'delete'],
    maintenance: ['view', 'update']
  }
});

async function fetchUsers() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await apiFetch('/users');
    if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
    users.value = await res.json();
  } catch (e) {
    errorMsg.value = 'Failed to load administrative users.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

function formatDate(isoStr: string): string {
  try {
    return new Date(isoStr).toLocaleDateString([], { 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    });
  } catch (e) {
    return isoStr;
  }
}

function openAddModal() {
  isEditMode.value = false;
  form.value = {
    id: 0,
    name: '',
    email: '',
    password: '',
    permissions: {
      servers: ['view', 'create', 'update', 'delete'],
      users: ['view', 'create', 'update', 'delete'],
      maintenance: ['view', 'update']
    }
  };
  showModal.value = true;
}

function openEditModal(user: User) {
  isEditMode.value = true;
  form.value = {
    id: user.id,
    name: user.name,
    email: user.email,
    password: '',
    permissions: user.permissions ? JSON.parse(JSON.stringify(user.permissions)) : {
      servers: ['view', 'create', 'update', 'delete'],
      users: ['view', 'create', 'update', 'delete'],
      maintenance: ['view', 'update']
    }
  };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function confirmDelete(user: User) {
  selectedUser.value = user;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedUser.value = null;
}

function confirmResetPassword(user: User) {
  selectedUser.value = user;
  showResetModal.value = true;
}

function closeResetModal() {
  showResetModal.value = false;
  selectedUser.value = null;
}

async function resetPassword() {
  if (!selectedUser.value) return;
  submitting.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await apiFetch(`/users/${selectedUser.value.id}/reset-password`, {
      method: 'POST',
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to reset password.');

    successMsg.value = data.message || `Successfully reset password for ${selectedUser.value.name}.`;
    closeResetModal();
    await fetchUsers();
  } catch (e) {
    errorMsg.value = (e as Error).message;
    closeResetModal();
  } finally {
    submitting.value = false;
  }
}

async function handleSubmit() {
  submitting.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    if (isEditMode.value) {
      // Update
      const body: Record<string, any> = {
        name: form.value.name,
        email: form.value.email,
        permissions: form.value.permissions,
      };
      if (form.value.password) {
        body.password = form.value.password;
      }

      const res = await apiFetch(`/users/${form.value.id}`, {
        method: 'PUT',
        body: JSON.stringify(body),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to update user.');
      
      successMsg.value = `Successfully updated profile for ${form.value.name}.`;
      closeModal();
      await fetchUsers();
    } else {
      // Create
      const res = await apiFetch('/users', {
        method: 'POST',
        body: JSON.stringify({
          name: form.value.name,
          email: form.value.email,
          permissions: form.value.permissions,
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to create user.');

      successMsg.value = `Successfully created user ${form.value.name}.`;
      closeModal();
      await fetchUsers();
    }
  } catch (e) {
    errorMsg.value = (e as Error).message;
  } finally {
    submitting.value = false;
  }
}

async function deleteUser() {
  if (!selectedUser.value) return;
  submitting.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await apiFetch(`/users/${selectedUser.value.id}`, {
      method: 'DELETE',
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to delete user.');

    successMsg.value = `Successfully deleted user ${selectedUser.value.name}.`;
    closeDeleteModal();
    await fetchUsers();
  } catch (e) {
    errorMsg.value = (e as Error).message;
    closeDeleteModal();
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchUsers();
});
</script>

<style scoped>
.user-management {
  width: 100%;
}

.section-desc {
  color: #94a3b8;
  font-size: 0.9rem;
  margin-top: 4px;
}

.add-user-btn {
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border: none;
  border-radius: 8px;
  padding: 10px 18px;
  color: #fff;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
}

.add-user-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
  background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
}

.plus-icon {
  font-size: 1.1rem;
  font-weight: 800;
}

/* Feedback banners */
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

/* User Card and Table */
.users-container {
  margin-top: 32px;
  padding: 0;
  overflow: hidden;
  border-color: rgba(255, 255, 255, 0.05);
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  color: #94a3b8;
  gap: 16px;
}

.spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(255, 255, 255, 0.05);
  border-top-color: #6366f1;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.users-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.users-table th {
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  padding: 16px 24px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.users-table td {
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  vertical-align: middle;
}

.users-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.01);
}

.current-user-row {
  background: rgba(99, 102, 241, 0.02);
}

.user-name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
  color: #fff;
  font-weight: 700;
  font-size: 0.9rem;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 0 10px rgba(99, 102, 241, 0.3);
}

.user-name {
  font-weight: 600;
  color: #fff;
  display: block;
}

.self-badge {
  display: inline-block;
  font-size: 0.65rem;
  background: rgba(99, 102, 241, 0.15);
  color: #818cf8;
  border: 1px solid rgba(99, 102, 241, 0.2);
  padding: 1px 6px;
  border-radius: 10px;
  margin-top: 2px;
  font-weight: 700;
  text-transform: uppercase;
}

.user-email {
  color: #cbd5e1;
}

.user-date {
  color: #64748b;
  font-size: 0.85rem;
}

.actions-col {
  text-align: right;
  width: 140px;
}

.action-buttons {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}

.action-btn {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 6px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 0.85rem;
  transition: all 0.2s ease;
}

.action-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-1px);
}

.edit-btn:hover:not(:disabled) {
  background: rgba(99, 102, 241, 0.1);
  border-color: rgba(99, 102, 241, 0.3);
}

.reset-btn:hover:not(:disabled) {
  background: rgba(129, 140, 248, 0.1);
  border-color: rgba(129, 140, 248, 0.3);
}

.delete-btn:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
}

.action-btn:disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

/* Modals */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(5, 7, 12, 0.7);
  backdrop-filter: blur(8px);
  z-index: 1000;
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
}

.modal-card {
  width: 100%;
  max-width: 480px;
  padding: 32px;
  border-color: rgba(255, 255, 255, 0.08);
  box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
  animation: modalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  background: #121826;
}

@keyframes modalIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
}

.close-modal-btn {
  background: transparent;
  border: none;
  color: #64748b;
  font-size: 1.5rem;
  cursor: pointer;
}

.close-modal-btn:hover {
  color: #fff;
}

.modal-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.modal-form .form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.modal-form label {
  font-size: 0.75rem;
  font-weight: 700;
  color: #94a3b8;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.optional-label {
  text-transform: none;
  font-weight: 500;
  color: #475569;
  letter-spacing: 0;
}

.modal-form input {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px;
  color: #fff;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  font-family: inherit;
}

.modal-form input:focus {
  outline: none;
  border-color: rgba(99, 102, 241, 0.5);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
}

.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  margin-top: 12px;
}

.modal-btn-cancel {
  background: rgba(255, 255, 255, 0.03);
  border: 1px solid rgba(255, 255, 255, 0.08);
  color: #cbd5e1;
  padding: 10px 18px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.modal-btn-cancel:hover {
  background: rgba(255, 255, 255, 0.08);
  color: #fff;
}

.modal-btn-submit {
  background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
  border: none;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.25);
  transition: all 0.2s ease;
}

.modal-btn-submit:hover:not(:disabled) {
  background: linear-gradient(135deg, #818cf8 0%, #6366f1 100%);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.35);
}

.spinner-small {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}

/* Delete confirmation details */
.delete-confirm-card h3 {
  color: #ef4444;
  font-size: 1.25rem;
  margin-bottom: 12px;
}

.delete-confirm-card p {
  color: #cbd5e1;
  font-size: 0.95rem;
  line-height: 1.5;
  margin-bottom: 24px;
}

.delete-actions {
  margin-top: 0;
}

.modal-btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  border: none;
  color: #fff;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
  transition: all 0.2s ease;
}

.modal-btn-danger:hover:not(:disabled) {
  background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
  box-shadow: 0 6px 16px rgba(239, 68, 68, 0.35);
}

.permissions-group {
  margin-top: 8px;
}

.permissions-matrix {
  background: rgba(0, 0, 0, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.04);
  border-radius: 8px;
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.matrix-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid rgba(255, 255, 255, 0.03);
  padding-bottom: 8px;
}

.matrix-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.matrix-module {
  font-size: 0.85rem;
  font-weight: 600;
  color: #cbd5e1;
  width: 100px;
}

.matrix-checkboxes {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
}

.matrix-checkbox-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 0.8rem;
  color: #94a3b8;
  cursor: pointer;
}

.matrix-checkbox-label input {
  cursor: pointer;
  accent-color: #6366f1;
}

.matrix-checkbox-label input:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
