<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-key section-heading-brand" style="font-size: 2.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-0 section-heading-brand">Set a New Password</h4>
        <p class="text-muted small mb-0">You're using a temporary password — choose your own to continue.</p>
      </div>

      <form @submit.prevent="submit">
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
          Set Password &amp; Continue
        </button>
      </form>

      <div class="text-center mt-3">
        <form @submit.prevent="logout">
          <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted">Log out instead</button>
        </form>
      </div>

      <p class="text-center text-muted small mt-3 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3'

const form = useForm({
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('admin.password.change.store'), {
    onFinish: () => form.reset(),
  })
}

const logout = () => router.post(route('admin.logout'))
</script>
