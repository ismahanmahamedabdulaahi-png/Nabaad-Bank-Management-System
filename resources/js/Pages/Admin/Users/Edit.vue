<template>
  <AdminLayout
    :title="`Edit — ${user.name}`"
    subtitle="Update staff account details"
    :breadcrumbs="[
      { label: 'Staff Management', href: route('admin.users.index') },
      { label: user.name, href: route('admin.users.show', user.id) },
      { label: 'Edit' }
    ]"
  >
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <!-- Staff ID (read-only — used for login) -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Staff ID</label>
                <input type="text" class="form-control font-monospace" :value="user.staff_id" disabled readonly />
                <div class="form-text">Used for login. Cannot be changed.</div>
              </div>

              <!-- Name -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input
                  v-model="form.name"
                  type="text"
                  class="form-control"
                  :class="{ 'is-invalid': errors.name }"
                />
                <div v-if="errors.name" class="invalid-feedback">{{ errors.name }}</div>
              </div>

              <!-- Email -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                <input
                  v-model="form.email"
                  type="email"
                  class="form-control"
                  :class="{ 'is-invalid': errors.email }"
                />
                <div v-if="errors.email" class="invalid-feedback">{{ errors.email }}</div>
              </div>

              <!-- Phone -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Phone Number</label>
                <input
                  v-model="form.phone"
                  type="tel"
                  class="form-control"
                  :class="{ 'is-invalid': errors.phone }"
                />
                <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone }}</div>
              </div>

              <!-- Role (Super Admin only; disabled for self-edit) -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Role <span class="text-danger">*</span></label>
                <select
                  v-model="form.role"
                  class="form-select"
                  :class="{ 'is-invalid': errors.role }"
                  :disabled="isSelf || !isSuperAdmin"
                >
                  <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
                </select>
                <div v-if="isSelf" class="form-text text-warning">
                  <i class="bi bi-exclamation-triangle me-1"></i>You cannot change your own role.
                </div>
                <div v-else-if="!isSuperAdmin" class="form-text text-muted">
                  <i class="bi bi-lock me-1"></i>Only a Super Admin can change a staff member's role.
                </div>
                <div v-if="errors.role" class="invalid-feedback">{{ errors.role }}</div>
              </div>

              <!-- Transaction Limit -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Daily Transaction Limit (USD)</label>
                <div class="input-group">
                  <span class="input-group-text">$</span>
                  <input
                    v-model="form.transaction_limit"
                    type="number"
                    min="0"
                    step="0.01"
                    class="form-control"
                    :class="{ 'is-invalid': errors.transaction_limit }"
                  />
                  <div v-if="errors.transaction_limit" class="invalid-feedback">{{ errors.transaction_limit }}</div>
                </div>
              </div>

              <!-- Status (disabled for self-edit) -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Status</label>
                <select
                  v-model="form.status"
                  class="form-select"
                  :class="{ 'is-invalid': errors.status }"
                  :disabled="isSelf"
                >
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
                </select>
                <div v-if="errors.status" class="invalid-feedback">{{ errors.status }}</div>
              </div>

              <hr class="my-4" />
              <h6 class="fw-bold mb-1 text-muted text-uppercase" style="font-size:.75rem;letter-spacing:.5px">
                <i class="bi bi-lock me-1"></i> Change Password
              </h6>
              <p class="text-muted small mb-3">Leave blank to keep the current password.</p>

              <!-- Password -->
              <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <div class="input-group">
                  <input
                    v-model="form.password"
                    :type="showPw ? 'text' : 'password'"
                    class="form-control"
                    :class="{ 'is-invalid': errors.password }"
                    autocomplete="new-password"
                  />
                  <button type="button" class="btn btn-outline-secondary" @click="showPw = !showPw" tabindex="-1">
                    <i :class="showPw ? 'bi bi-eye-slash' : 'bi bi-eye'"></i>
                  </button>
                  <div v-if="errors.password" class="invalid-feedback">{{ errors.password }}</div>
                </div>
              </div>

              <!-- Confirm Password -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input
                  v-model="form.password_confirmation"
                  :type="showPw ? 'text' : 'password'"
                  class="form-control"
                  autocomplete="new-password"
                />
              </div>

              <!-- Actions -->
              <div class="d-flex gap-2 justify-content-end">
                <Link :href="route('admin.users.show', user.id)" class="btn btn-light">Cancel</Link>
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-floppy me-1"></i>
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  user:   { type: Object, required: true },
  roles:  { type: Array,  default: () => [] },
  errors: { type: Object, default: () => ({}) },
});

const page  = usePage();
const isSelf = computed(() => page.props.auth.user?.id === props.user.id);
const isSuperAdmin = computed(() => page.props.auth.user?.roles?.some(r => r.name === 'Super Admin') ?? false);
const showPw = ref(false);

const form = useForm({
  name:                  props.user.name,
  email:                 props.user.email,
  phone:                 props.user.phone ?? '',
  role:                  props.user.roles?.[0]?.name ?? '',
  transaction_limit:     props.user.transaction_limit ?? '',
  status:                props.user.status,
  password:              '',
  password_confirmation: '',
});

const submit = () => form.put(route('admin.users.update', props.user.id));
</script>
