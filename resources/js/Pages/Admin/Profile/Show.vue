<template>
  <AdminLayout title="My Profile" subtitle="Manage your account and security">

    <div class="row g-4">

      <!-- Left: Profile Info -->
      <div class="col-lg-5">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-person-circle me-1 text-primary"></i> Profile Information
          </div>
          <div class="card-body">
            <!-- Avatar placeholder -->
            <div class="text-center mb-4">
              <div class="rounded-circle bg-primary d-inline-flex align-items-center justify-content-center"
                   style="width:72px;height:72px;background:#0B2447!important">
                <i class="bi bi-person-fill text-white fs-2"></i>
              </div>
              <div class="fw-bold mt-2">{{ user.name }}</div>
              <div class="text-muted small">{{ user.roles?.[0]?.name ?? 'Staff' }}</div>
            </div>

            <!-- Read-only info -->
            <div class="table-sm mb-4">
              <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Staff ID</span>
                <span class="fw-semibold font-monospace text-primary">{{ user.staff_id ?? '—' }}</span>
              </div>
              <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Email</span>
                <span>{{ user.email }}</span>
              </div>
              <div class="d-flex justify-content-between py-2 border-bottom">
                <span class="text-muted">Role</span>
                <span class="badge bg-primary">{{ user.roles?.[0]?.name ?? '—' }}</span>
              </div>
              <div class="d-flex justify-content-between py-2">
                <span class="text-muted">Status</span>
                <span class="badge" :class="user.status === 'active' ? 'bg-success' : 'bg-secondary'">
                  {{ user.status }}
                </span>
              </div>
            </div>

            <!-- Edit form -->
            <form @submit.prevent="submitProfile">
              <div class="mb-3">
                <label class="form-label fw-semibold">Full Name</label>
                <input v-model="profileForm.name" type="text" class="form-control"
                       :class="profileForm.errors.name ? 'is-invalid' : ''" required>
                <div v-if="profileForm.errors.name" class="invalid-feedback">{{ profileForm.errors.name }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Phone (optional)</label>
                <input v-model="profileForm.phone" type="text" class="form-control"
                       :class="profileForm.errors.phone ? 'is-invalid' : ''">
                <div v-if="profileForm.errors.phone" class="invalid-feedback">{{ profileForm.errors.phone }}</div>
              </div>
              <button type="submit" class="btn btn-primary w-100" :disabled="profileForm.processing">
                <span v-if="profileForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Save Changes
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Right: Change Password -->
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-shield-lock me-1 text-warning"></i> Change Password
          </div>
          <div class="card-body">
            <div class="alert alert-info small py-2 mb-4">
              <i class="bi bi-info-circle me-1"></i>
              Password must be at least 8 characters and include uppercase, lowercase, and a number.
            </div>
            <form @submit.prevent="submitPassword">
              <div class="mb-3">
                <label class="form-label fw-semibold">Current Password</label>
                <input v-model="pwForm.current_password" type="password" class="form-control"
                       :class="pwForm.errors.current_password ? 'is-invalid' : ''" required autocomplete="current-password">
                <div v-if="pwForm.errors.current_password" class="invalid-feedback">{{ pwForm.errors.current_password }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <input v-model="pwForm.password" type="password" class="form-control"
                       :class="pwForm.errors.password ? 'is-invalid' : ''" required autocomplete="new-password">
                <div v-if="pwForm.errors.password" class="invalid-feedback">{{ pwForm.errors.password }}</div>
              </div>
              <div class="mb-4">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input v-model="pwForm.password_confirmation" type="password" class="form-control" required autocomplete="new-password">
              </div>
              <button type="submit" class="btn btn-warning w-100 fw-semibold" :disabled="pwForm.processing">
                <span v-if="pwForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                <i class="bi bi-key me-1"></i> Update Password
              </button>
            </form>
          </div>
        </div>

        <!-- Security info -->
        <div class="card shadow-sm mt-4 border-0 bg-light">
          <div class="card-body small text-muted">
            <i class="bi bi-shield-check me-1 text-success"></i>
            For security, we recommend changing your password every 90 days and never sharing it with anyone.
            All password changes are recorded in the audit log.
          </div>
        </div>

        <!-- Active Sessions -->
        <div class="card shadow-sm mt-4">
          <div class="card-body d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold small"><i class="bi bi-laptop me-1 text-primary"></i>Active Sessions</div>
              <div class="text-muted small">See where you're logged in and sign out other devices.</div>
            </div>
            <Link :href="route('admin.profile.sessions')" class="btn btn-sm btn-outline-primary text-nowrap">
              Manage <i class="bi bi-arrow-right ms-1"></i>
            </Link>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ user: Object })

const profileForm = useForm({
  name:  props.user.name ?? '',
  phone: props.user.phone ?? '',
})

const pwForm = useForm({
  current_password:      '',
  password:              '',
  password_confirmation: '',
})

const submitProfile = () => profileForm.patch(route('admin.profile.update'))

const submitPassword = () => pwForm.post(route('admin.profile.password'), {
  onSuccess: () => pwForm.reset(),
})
</script>
