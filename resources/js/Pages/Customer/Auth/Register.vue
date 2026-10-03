<template>
  <div class="min-vh-100 d-flex align-items-center justify-content-center" style="background:#f0f4f8">
    <div class="card border-0 shadow-sm" style="width:100%;max-width:540px">
      <div class="card-header text-white text-center py-4" style="background:#0B2447">
        <div class="fw-bold fs-5">NABAAD Bank</div>
        <div class="small opacity-75">Open a Customer Account</div>
      </div>
      <div class="card-body p-4">
        <form @submit.prevent="submit">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
              <input v-model="form.name" type="text" class="form-control" :class="form.errors.name ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.name }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
              <input v-model="form.email" type="email" class="form-control" :class="form.errors.email ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.email }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
              <input v-model="form.phone" type="text" class="form-control" :class="form.errors.phone ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.phone }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
              <input v-model="form.date_of_birth" type="date" class="form-control" :class="form.errors.date_of_birth ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.date_of_birth }}</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
              <select v-model="form.gender" class="form-select" :class="form.errors.gender ? 'is-invalid' : ''" required>
                <option value="">Select gender</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
              <div class="invalid-feedback">{{ form.errors.gender }}</div>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
              <input v-model="form.address" type="text" class="form-control" :class="form.errors.address ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.address }}</div>
            </div>
            <div class="col-12">
              <LocationSelect
                v-model:country="form.nationality"
                v-model:region="form.region"
                v-model:city="form.city"
                :errors="{ country: form.errors.nationality, region: form.errors.region, city: form.errors.city }"
              />
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Occupation</label>
              <input v-model="form.occupation" type="text" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
              <input v-model="form.password" type="password" class="form-control" :class="form.errors.password ? 'is-invalid' : ''" required>
              <div class="invalid-feedback">{{ form.errors.password }}</div>
              <div class="form-text">Min 8 characters, mixed case + numbers</div>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
              <input v-model="form.password_confirmation" type="password" class="form-control" required>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100 mt-4" :disabled="form.processing">
            <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
            Submit Registration
          </button>
        </form>

        <div class="text-center mt-3 small">
          Already have an account?
          <Link :href="route('customer.login')" class="text-primary fw-semibold">Sign In</Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import LocationSelect from '@/Components/LocationSelect.vue'

const form = useForm({
  name: '', email: '', phone: '', date_of_birth: '', gender: '',
  address: '', nationality: '', region: '', city: '', occupation: '',
  password: '', password_confirmation: '',
})

const submit = () => form.post(route('customer.register'))
</script>
