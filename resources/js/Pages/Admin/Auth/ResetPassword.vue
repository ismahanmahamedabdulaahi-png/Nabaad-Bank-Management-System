<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-shield-lock section-heading-brand" style="font-size: 2.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-0 section-heading-brand">Set a New Password</h4>
        <p class="text-muted small mb-0">Choose a new password for your staff account</p>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email Address</label>
          <input
            v-model="form.email"
            type="email"
            class="form-control"
            :class="{ 'is-invalid': form.errors.email }"
            autocomplete="email"
            required
          />
          <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
        </div>

        <div class="mb-3">
          <label class="form-label fw-semibold small">New Password</label>
          <input
            v-model="form.password"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': form.errors.password }"
            autocomplete="new-password"
            autofocus
            required
          />
          <div v-if="form.errors.password" class="invalid-feedback">{{ form.errors.password }}</div>
          <div class="form-text">At least 8 characters, with uppercase, lowercase, a number, and a symbol.</div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold small">Confirm New Password</label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="form-control"
            :class="{ 'is-invalid': form.errors.password_confirmation }"
            autocomplete="new-password"
            required
          />
          <div v-if="form.errors.password_confirmation" class="invalid-feedback">{{ form.errors.password_confirmation }}</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status"></span>
          <i v-else class="bi bi-check-circle me-2"></i>
          Reset Password
        </button>
      </form>

      <p class="text-center text-muted small mt-4 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
  token: String,
  email: String,
});

const form = useForm({
  token: props.token,
  email: props.email ?? '',
  password: '',
  password_confirmation: '',
});

const submit = () => form.post(route('password.store'), {
  onFinish: () => form.reset('password', 'password_confirmation'),
});
</script>
