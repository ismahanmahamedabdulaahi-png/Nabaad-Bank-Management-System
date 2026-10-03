<template>
  <PortalLayout title="My Profile" subtitle="Manage your personal information">

    <div class="row g-4">
      <!-- Profile Update -->
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-person-circle me-2 text-primary"></i>Personal Information
          </div>
          <div class="card-body">

            <!-- Read-only info -->
            <div class="row g-2 mb-4 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
              <div class="col-6">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Customer #</div>
                <div class="fw-semibold font-monospace small">{{ customer.customer_number }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Full Name</div>
                <div class="fw-semibold small">{{ customer.name }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Email</div>
                <div class="small">{{ customer.email }}</div>
              </div>
              <div class="col-6">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Date of Birth</div>
                <div class="small">{{ fmtDate(customer.date_of_birth) }}</div>
              </div>
            </div>

            <form @submit.prevent="profileForm.patch(route('customer.profile.update'))">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Phone</label>
                  <input v-model="profileForm.phone" type="text" class="form-control"
                         :class="profileForm.errors.phone ? 'is-invalid' : ''">
                  <div class="invalid-feedback">{{ profileForm.errors.phone }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">City</label>
                  <input v-model="profileForm.city" type="text" class="form-control"
                         :class="profileForm.errors.city ? 'is-invalid' : ''">
                  <div class="invalid-feedback">{{ profileForm.errors.city }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Address</label>
                  <input v-model="profileForm.address" type="text" class="form-control"
                         :class="profileForm.errors.address ? 'is-invalid' : ''">
                  <div class="invalid-feedback">{{ profileForm.errors.address }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Occupation</label>
                  <input v-model="profileForm.occupation" type="text" class="form-control">
                </div>
              </div>
              <button type="submit" class="btn btn-primary mt-3" :disabled="profileForm.processing">
                <span v-if="profileForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Save Changes
              </button>
            </form>
          </div>
        </div>

        <!-- Next of Kin — read-only, on file from registration/KYC -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-people me-2 text-secondary"></i>Next of Kin
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              <i class="bi bi-lock me-1"></i>On file from registration — visit your branch to update this.
            </p>
            <div class="row g-3">
              <div class="col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Name</div>
                <div class="small">{{ customer.next_of_kin_name || '—' }}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Phone</div>
                <div class="small">{{ customer.next_of_kin_phone || '—' }}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase">Relationship</div>
                <div class="small">{{ customer.next_of_kin_relationship || '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Change Password -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-shield-lock me-2 text-warning"></i>Change Password
          </div>
          <div class="card-body">
            <form @submit.prevent="pwForm.post(route('customer.profile.password'), { onSuccess: () => pwForm.reset() })">
              <div class="mb-3">
                <label class="form-label fw-semibold">Current Password</label>
                <input v-model="pwForm.current_password" type="password" class="form-control"
                       :class="pwForm.errors.current_password ? 'is-invalid' : ''">
                <div class="invalid-feedback">{{ pwForm.errors.current_password }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">New Password</label>
                <input v-model="pwForm.password" type="password" class="form-control"
                       :class="pwForm.errors.password ? 'is-invalid' : ''">
                <div class="invalid-feedback">{{ pwForm.errors.password }}</div>
                <div class="form-text">Min 8 characters, mixed case + numbers</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Confirm New Password</label>
                <input v-model="pwForm.password_confirmation" type="password" class="form-control">
              </div>
              <button type="submit" class="btn btn-warning w-100" :disabled="pwForm.processing">
                <span v-if="pwForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Change Password
              </button>
            </form>
          </div>
        </div>

        <!-- Active Sessions -->
        <div class="card border-0 shadow-sm mt-4">
          <div class="card-body d-flex align-items-center justify-content-between">
            <div>
              <div class="fw-semibold small"><i class="bi bi-laptop me-1 text-primary"></i>Active Sessions</div>
              <div class="text-muted small">See your logged-in devices.</div>
            </div>
            <Link :href="route('customer.profile.sessions')" class="btn btn-sm btn-outline-primary text-nowrap">
              Manage <i class="bi bi-arrow-right ms-1"></i>
            </Link>
          </div>
        </div>
      </div>
    </div>

  </PortalLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({ customer: Object })

const profileForm = useForm({
  phone:      props.customer.phone      ?? '',
  address:    props.customer.address    ?? '',
  city:       props.customer.city       ?? '',
  occupation: props.customer.occupation ?? '',
})

const pwForm = useForm({ current_password: '', password: '', password_confirmation: '' })

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—'
</script>
