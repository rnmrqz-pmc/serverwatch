<template>
  <div class="server-management">
    <!-- Header -->
    <div class="section-header">
      <div>
        <h2>Server Target Settings</h2>
        <p class="section-desc">Manage the target nodes monitored by Prometheus and Uptime metrics.</p>
      </div>
      <button class="add-server-btn" @click="openAddModal">
        <span class="plus-icon">+</span> Add Target Node
      </button>
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

    <!-- Servers Grid/List -->
    <div class="servers-container glass-card">
      <div v-if="loading" class="loading-state">
        <div class="spinner"></div>
        <p>Loading targets...</p>
      </div>

      <div v-else-if="servers.length === 0" class="empty-state">
        <p>No target servers registered.</p>
      </div>

      <table v-else class="servers-table">
        <thead>
          <tr>
            <th>Server Name</th>
            <th>IP Address / Host</th>
            <th>Role</th>
            <th>Environment</th>
            <th>Database</th>
            <th>SSH</th>
            <th class="actions-col">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="srv in servers" :key="srv.id">
            <td>
              <div class="server-name-cell">
                <span class="server-icon-badge">🖥️</span>
                <span class="server-name">{{ srv.name }}</span>
              </div>
            </td>
            <td>
              <code class="code-ip">{{ srv.ip }}</code>
            </td>
            <td>
              <span class="server-role">{{ srv.role }}</span>
            </td>
            <td>
              <span class="env-badge" :class="srv.env">{{ srv.env }}</span>
            </td>
            <td>
              <div class="db-cell" v-if="srv.db_type && srv.db_type !== 'none'">
                <span class="db-cell__icon">{{ dbIcon(srv.db_type) }}</span>
                <div class="db-cell__info">
                  <span class="db-cell__type">{{ dbLabel(srv.db_type) }}</span>
                  <span class="db-cell__status" :class="srv.has_db_credentials ? 'configured' : 'unconfigured'">
                    {{ srv.has_db_credentials ? '● Configured' : '○ Credentials missing' }}
                  </span>
                </div>
              </div>
              <span v-else class="db-none-badge">—</span>
            </td>
            <td>
              <div class="ssh-cell" v-if="srv.ssh_user">
                <span class="ssh-cell__icon">🔑</span>
                <div class="ssh-cell__info">
                  <span class="ssh-cell__user">{{ srv.ssh_user }} (port {{ srv.ssh_port || 22 }})</span>
                  <span class="ssh-cell__status" :class="srv.has_ssh_credentials ? 'configured' : 'unconfigured'">
                    {{ srv.has_ssh_credentials ? '● Configured' : '○ Password missing' }}
                  </span>
                </div>
              </div>
              <span v-else class="ssh-none-badge">—</span>
            </td>
            <td class="actions-col">
              <div class="action-buttons">
                <button class="action-btn edit-btn" @click="openEditModal(srv)" title="Edit Target">
                  ✏️
                </button>
                <button class="action-btn db-btn" @click="openDbModal(srv)" title="Configure Database Credentials">
                  🗄️
                </button>
                <button class="action-btn ssh-btn" @click="openSshModal(srv)" title="Configure SSH Credentials">
                  🔑
                </button>
                <button class="action-btn alert-btn" @click="openThresholdsModal(srv)" title="Configure Alert Thresholds">
                  🔔
                </button>
                <button class="action-btn delete-btn" @click="confirmDelete(srv)" title="Delete Target">
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
          <h3>{{ isEditMode ? 'Edit Target Node' : 'Register Target Node' }}</h3>
          <button class="close-modal-btn" @click="closeModal">×</button>
        </div>
        
        <form @submit.prevent="handleSubmit" class="modal-form">
          <div class="form-group">
            <label for="modal-name">Server Name</label>
            <input 
              type="text" 
              id="modal-name" 
              v-model="form.name" 
              placeholder="e.g. web-server-01" 
              required
            />
          </div>

          <div class="form-group">
            <label for="modal-ip">IP Address / Hostname</label>
            <input 
              type="text" 
              id="modal-ip" 
              v-model="form.ip" 
              placeholder="e.g. {IP_ADDRESS}" 
              required
            />
            <span class="field-hint">IP address or hostname target that has Node Exporter installed.</span>
          </div>

          <div class="form-group">
            <label for="modal-role">Role Description</label>
            <input 
              type="text" 
              id="modal-role" 
              v-model="form.role" 
              placeholder="e.g. Production Web Node" 
              required
            />
          </div>

          <div class="form-group">
            <label for="modal-env">Environment</label>
            <select id="modal-env" v-model="form.env" required>
              <option value="production">Production</option>
              <option value="staging">Staging</option>
              <option value="development">Development</option>
            </select>
          </div>

          <div class="modal-actions">
            <button type="button" class="modal-btn-cancel" @click="closeModal">Cancel</button>
            <button type="submit" class="modal-btn-submit" :disabled="submitting">
              <span v-if="submitting" class="spinner-small"></span>
              <span v-else>{{ isEditMode ? 'Save Changes' : 'Register' }}</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Database Credentials Modal -->
    <DatabaseCredentialsModal
      v-if="showDbModal && selectedDbServer"
      :server="selectedDbServer"
      @close="closeDbModal"
      @saved="onDbCredentialsSaved"
    />

    <!-- SSH Credentials Modal -->
    <SshCredentialsModal
      v-if="showSshModal && selectedSshServer"
      :server="selectedSshServer"
      @close="closeSshModal"
      @saved="onSshCredentialsSaved"
    />

    <!-- Alert Thresholds Modal -->
    <ThresholdsModal
      v-if="showThresholdsModal && selectedThresholdsServer"
      :server="selectedThresholdsServer"
      @close="closeThresholdsModal"
      @saved="onThresholdsSaved"
    />

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="closeDeleteModal">
      <div class="glass-card modal-card delete-confirm-card">
        <h3>Deregister Target Node?</h3>
        <p>Are you sure you want to stop monitoring <strong>{{ selectedServer?.name }}</strong> ({{ selectedServer?.ip }})? Metrics history will remain in Prometheus, but BIT DevOps ServerWatcher will stop gathering logs.</p>
        
        <div class="modal-actions delete-actions">
          <button type="button" class="modal-btn-cancel" @click="closeDeleteModal">Cancel</button>
          <button type="button" class="modal-btn-danger" @click="deleteServer" :disabled="submitting">
            <span v-if="submitting" class="spinner-small"></span>
            <span v-else>Deregister</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { apiFetch } from '../utils/api';
import { useServersStore } from '../stores/servers';
import DatabaseCredentialsModal from './DatabaseCredentialsModal.vue';
import SshCredentialsModal from './SshCredentialsModal.vue';
import ThresholdsModal from './ThresholdsModal.vue';

interface ServerNode {
  id: number;
  name: string;
  ip: string;
  role: string;
  env: string;
  db_type?: string;
  db_host?: string | null;
  db_port?: number | null;
  db_user?: string | null;
  db_name?: string | null;
  has_db_credentials?: boolean;
  ssh_user?: string | null;
  ssh_port?: number | null;
  has_ssh_credentials?: boolean;
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

const serversStore = useServersStore();
const servers = ref<ServerNode[]>([]);
const loading = ref(false);
const submitting = ref(false);

const successMsg = ref('');
const errorMsg = ref('');

// Modal visibility states
const showModal = ref(false);
const isEditMode = ref(false);
const showDeleteModal = ref(false);
const selectedServer = ref<ServerNode | null>(null);

// DB credentials modal
const showDbModal = ref(false);
const selectedDbServer = ref<ServerNode | null>(null);

// SSH credentials modal
const showSshModal = ref(false);
const selectedSshServer = ref<ServerNode | null>(null);

// Thresholds modal
const showThresholdsModal = ref(false);
const selectedThresholdsServer = ref<ServerNode | null>(null);

// Form data
const form = ref({
  id: 0,
  name: '',
  ip: '',
  role: '',
  env: 'production',
});

async function fetchServersList() {
  loading.value = true;
  errorMsg.value = '';
  try {
    const res = await apiFetch('/servers');
    if (!res.ok) throw new Error(`HTTP Error ${res.status}`);
    const data = await res.json();
    // API returns calculated server models with status/metrics, map properties
    servers.value = data.map((srv: any) => ({
      id:                      srv.id,
      name:                    srv.name,
      ip:                      srv.instance,
      role:                    srv.role,
      env:                     srv.env,
      db_type:                 srv.db_type ?? 'none',
      db_host:                 srv.db_host ?? null,
      db_port:                 srv.db_port ?? null,
      db_user:                 srv.db_user ?? null,
      db_name:                 srv.db_name ?? null,
      has_db_credentials:      srv.has_db_credentials ?? false,
      ssh_user:                srv.ssh_user ?? null,
      ssh_port:                srv.ssh_port ?? null,
      has_ssh_credentials:     srv.has_ssh_credentials ?? false,
      cpu_threshold_info:      srv.cpu_threshold_info ?? 60,
      cpu_threshold_warning:   srv.cpu_threshold_warning ?? 70,
      cpu_threshold_critical:  srv.cpu_threshold_critical ?? 90,
      ram_threshold_info:      srv.ram_threshold_info ?? 60,
      ram_threshold_warning:   srv.ram_threshold_warning ?? 70,
      ram_threshold_critical:  srv.ram_threshold_critical ?? 90,
      disk_threshold_info:     srv.disk_threshold_info ?? 60,
      disk_threshold_warning:  srv.disk_threshold_warning ?? 70,
      disk_threshold_critical: srv.disk_threshold_critical ?? 90,
    }));
  } catch (e) {
    errorMsg.value = 'Failed to load target servers.';
    console.error(e);
  } finally {
    loading.value = false;
  }
}

// ── DB credential helpers ─────────────────────────────────────────────────

const DB_META: Record<string, { label: string; icon: string }> = {
  mariadb:    { label: 'MariaDB',    icon: '🦁' },
  mysql:      { label: 'MySQL',      icon: '🐬' },
  postgresql: { label: 'PostgreSQL', icon: '🐘' },
};

function dbIcon(type?: string): string {
  return DB_META[type ?? '']?.icon ?? '🗄️';
}

function dbLabel(type?: string): string {
  return DB_META[type ?? '']?.label ?? (type ?? '—');
}

function openDbModal(srv: ServerNode) {
  selectedDbServer.value = srv;
  showDbModal.value = true;
}

function closeDbModal() {
  showDbModal.value = false;
  selectedDbServer.value = null;
}

function onDbCredentialsSaved() {
  fetchServersList();
  serversStore.fetchServers();
}

// ── SSH credential helpers ────────────────────────────────────────────────

function openSshModal(srv: ServerNode) {
  selectedSshServer.value = srv;
  showSshModal.value = true;
}

function closeSshModal() {
  showSshModal.value = false;
  selectedSshServer.value = null;
}

function onSshCredentialsSaved() {
  fetchServersList();
  serversStore.fetchServers();
}

// ── Threshold helpers ───────────────────────────────────────────────────

function openThresholdsModal(srv: ServerNode) {
  selectedThresholdsServer.value = srv;
  showThresholdsModal.value = true;
}

function closeThresholdsModal() {
  showThresholdsModal.value = false;
  selectedThresholdsServer.value = null;
}

function onThresholdsSaved() {
  fetchServersList();
  serversStore.fetchServers();
}

function openAddModal() {
  isEditMode.value = false;
  form.value = { id: 0, name: '', ip: '', role: '', env: 'production' };
  showModal.value = true;
}

function openEditModal(srv: ServerNode) {
  isEditMode.value = true;
  form.value = { id: srv.id, name: srv.name, ip: srv.ip, role: srv.role, env: srv.env };
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function confirmDelete(srv: ServerNode) {
  selectedServer.value = srv;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  selectedServer.value = null;
}

async function handleSubmit() {
  submitting.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    if (isEditMode.value) {
      // Update
      const res = await apiFetch(`/servers/${form.value.id}`, {
        method: 'PUT',
        body: JSON.stringify({
          name: form.value.name,
          ip: form.value.ip,
          role: form.value.role,
          env: form.value.env,
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to update target server.');
      
      successMsg.value = `Successfully updated target node ${form.value.name}.`;
      closeModal();
      await fetchServersList();
      serversStore.fetchServers(); // refresh main store
    } else {
      // Create
      const res = await apiFetch('/servers', {
        method: 'POST',
        body: JSON.stringify({
          name: form.value.name,
          ip: form.value.ip,
          role: form.value.role,
          env: form.value.env,
        }),
      });

      const data = await res.json();
      if (!res.ok) throw new Error(data.message || 'Failed to register server.');

      successMsg.value = `Successfully registered node ${form.value.name}.`;
      closeModal();
      await fetchServersList();
      serversStore.fetchServers(); // refresh main store
    }
  } catch (e) {
    errorMsg.value = (e as Error).message;
  } finally {
    submitting.value = false;
  }
}

async function deleteServer() {
  if (!selectedServer.value) return;
  submitting.value = true;
  errorMsg.value = '';
  successMsg.value = '';

  try {
    const res = await apiFetch(`/servers/${selectedServer.value.id}`, {
      method: 'DELETE',
    });

    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'Failed to delete target.');

    successMsg.value = `Successfully deregistered node ${selectedServer.value.name}.`;
    closeDeleteModal();
    await fetchServersList();
    serversStore.fetchServers(); // refresh main store
  } catch (e) {
    errorMsg.value = (e as Error).message;
    closeDeleteModal();
  } finally {
    submitting.value = false;
  }
}

onMounted(() => {
  fetchServersList();
});
</script>

<style scoped>
.server-management {
  width: 100%;
}

.section-desc {
  color: #94a3b8;
  font-size: 0.9rem;
  margin-top: 4px;
}

.add-server-btn {
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

.add-server-btn:hover {
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

/* Server Card and Table */
.servers-container {
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

.servers-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.servers-table th {
  background: rgba(255, 255, 255, 0.02);
  border-bottom: 1px solid rgba(255, 255, 255, 0.06);
  padding: 16px 24px;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
}

.servers-table td {
  padding: 16px 24px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.04);
  vertical-align: middle;
}

.servers-table tbody tr:hover {
  background: rgba(255, 255, 255, 0.01);
}

.server-name-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.server-icon-badge {
  font-size: 1.1rem;
}

.server-name {
  font-weight: 600;
  color: #fff;
}

.code-ip {
  font-family: monospace;
  background: rgba(255, 255, 255, 0.03);
  padding: 4px 8px;
  border-radius: 4px;
  color: #94a3b8;
  border: 1px solid rgba(255, 255, 255, 0.05);
}

.server-role {
  color: #cbd5e1;
}

.env-badge {
  display: inline-block;
  font-size: 0.7rem;
  padding: 3px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  font-weight: 700;
}

.env-badge.production {
  background: rgba(105, 239, 68, 0.15);
  color: #23b805ff;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.env-badge.staging {
  background: rgba(245, 158, 11, 0.15);
  color: #fbbf24;
  border: 1px solid rgba(245, 158, 11, 0.2);
}

.env-badge.development {
  background: rgba(59, 130, 246, 0.15);
  color: #60a5fa;
  border: 1px solid rgba(59, 130, 246, 0.2);
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

.action-btn:hover {
  background: rgba(255, 255, 255, 0.08);
  border-color: rgba(255, 255, 255, 0.15);
  transform: translateY(-1px);
}

.edit-btn:hover {
  background: rgba(99, 102, 241, 0.1);
  border-color: rgba(99, 102, 241, 0.3);
}

.db-btn:hover {
  background: rgba(6, 182, 212, 0.1);
  border-color: rgba(6, 182, 212, 0.3);
}

.alert-btn:hover {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.3);
}

.delete-btn:hover {
  background: rgba(239, 68, 68, 0.1);
  border-color: rgba(239, 68, 68, 0.3);
}

/* ── Database cell ──────────────────────────────────────────────────────────*/
.db-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.db-cell__icon {
  font-size: 1.1rem;
  line-height: 1;
}

.db-cell__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.db-cell__type {
  font-size: 0.8rem;
  font-weight: 600;
  color: #cbd5e1;
}

.db-cell__status {
  font-size: 0.68rem;
  font-weight: 600;
}

.db-cell__status.configured {
  color: #34d399;
}

.db-cell__status.unconfigured {
  color: #f59e0b;
}

.db-none-badge {
  color: #334155;
  font-size: 0.9rem;
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

.field-hint {
  font-size: 0.75rem;
  color: #64748b;
  line-height: 1.3;
}

.modal-form input, .modal-form select {
  background: rgba(15, 23, 42, 0.6);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
  padding: 10px 14px;
  color: #fff;
  font-size: 0.9rem;
  transition: all 0.2s ease;
  font-family: inherit;
}

.modal-form select {
  cursor: pointer;
}

.modal-form select option {
  background: #121826;
  color: #fff;
}

.modal-form input:focus, .modal-form select:focus {
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

/* ── SSH cell ──────────────────────────────────────────────────────────*/
.ssh-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.ssh-cell__icon {
  font-size: 1.1rem;
  line-height: 1;
}

.ssh-cell__info {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ssh-cell__user {
  font-size: 0.8rem;
  font-weight: 600;
  color: #cbd5e1;
}

.ssh-cell__status {
  font-size: 0.68rem;
  font-weight: 600;
}

.ssh-cell__status.configured {
  color: #34d399;
}

.ssh-cell__status.unconfigured {
  color: #f59e0b;
}

.ssh-none-badge {
  color: #334155;
  font-size: 0.9rem;
}

.ssh-btn:hover {
  background: rgba(168, 85, 247, 0.1);
  border-color: rgba(168, 85, 247, 0.3);
}
</style>
