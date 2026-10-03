<template>
  <AdminLayout title="Active Sessions" subtitle="Devices currently signed in to your account">
    <template #actions>
      <Link :href="route('admin.profile')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
      </Link>
    </template>

    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <span class="fw-semibold small"><i class="bi bi-laptop me-1 text-primary"></i>Active Sessions</span>
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#logoutOthersModal">
          Log Out Other Devices
        </button>
      </div>
      <div class="list-group list-group-flush">
        <div v-for="s in sessions" :key="s.id" class="list-group-item d-flex align-items-center justify-content-between py-3">
          <div class="d-flex align-items-center gap-3">
            <i class="bi fs-4 text-muted" :class="deviceIcon(s.platform)"></i>
            <div>
              <div class="fw-semibold small">
                {{ s.browser }} on {{ s.platform }}
                <span v-if="s.is_current" class="badge bg-success ms-2">This device</span>
              </div>
              <div class="text-muted" style="font-size:.78rem">
                {{ s.ip_address }} · Last active {{ fmtRelative(s.last_active) }}
              </div>
            </div>
          </div>
          <button v-if="!s.is_current" type="button" class="btn btn-sm btn-outline-secondary"
                  @click="confirmLogout(s)">
            Log Out
          </button>
        </div>
      </div>
    </div>

    <!-- Log out other devices confirmation -->
    <div class="modal fade" id="logoutOthersModal" tabindex="-1">
      <div class="modal-dialog">
        <div class="modal-content">
          <form @submit.prevent="logoutOthers">
            <div class="modal-header">
              <h6 class="modal-title fw-bold">Log Out Other Devices</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <p class="text-muted small">Enter your password to confirm. Every other session will be signed out immediately.</p>
              <input v-model="pwForm.password" type="password" class="form-control"
                     :class="pwForm.errors.password ? 'is-invalid' : ''" placeholder="Current password" required>
              <div v-if="pwForm.errors.password" class="invalid-feedback">{{ pwForm.errors.password }}</div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="pwForm.processing">
                <span v-if="pwForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Log Out Other Devices
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Log out this device confirmation -->
    <ConfirmModal
      id="logoutDeviceModal"
      title="Log Out This Device"
      :message="logoutTarget ? `Sign out the session on ${logoutTarget.browser} on ${logoutTarget.platform}?` : ''"
      variant="secondary"
      icon="bi-box-arrow-right"
      confirm-label="Log Out"
      @confirmed="doLogout"
    />
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineProps({ sessions: { type: Array, default: () => [] } })

const pwForm = useForm({ password: '' })

const logoutTarget = ref(null)
const confirmLogout = (s) => { logoutTarget.value = s; new Modal(document.getElementById('logoutDeviceModal')).show() }
const doLogout = () => {
  router.delete(route('admin.profile.sessions.destroy', logoutTarget.value.id), { preserveScroll: true })
}

const logoutOthers = () => {
  pwForm.post(route('admin.profile.sessions.destroy-others'), {
    preserveScroll: true,
    onSuccess: () => {
      pwForm.reset()
      window.bootstrap?.Modal.getInstance(document.getElementById('logoutOthersModal'))?.hide()
    },
  })
}

const deviceIcon = (platform) => ({
  Windows: 'bi-windows', macOS: 'bi-apple', iOS: 'bi-apple',
  Android: 'bi-phone', Linux: 'bi-ubuntu',
}[platform] ?? 'bi-question-circle')

function fmtRelative(iso) {
  const diffMin = Math.floor((Date.now() - new Date(iso)) / 60000)
  if (diffMin < 1)    return 'just now'
  if (diffMin < 60)   return `${diffMin}m ago`
  if (diffMin < 1440) return `${Math.floor(diffMin / 60)}h ago`
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
