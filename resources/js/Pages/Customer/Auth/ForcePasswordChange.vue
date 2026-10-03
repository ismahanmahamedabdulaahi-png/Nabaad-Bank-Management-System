<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <i class="bi bi-key" style="font-size:2.5rem;color:#0B2447"></i>
        <h4 class="fw-bold mt-2" style="color:#0B2447">Set a New Password</h4>
        <p class="text-muted small">You're using a temporary password — choose your own to continue.</p>
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
          >
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
          >
          <div v-if="form.errors.password_confirmation" class="invalid-feedback">{{ form.errors.password_confirmation }}</div>
        </div>

        <button type="submit" class="btn w-100 fw-semibold py-2" :disabled="form.processing"
                style="background:#0B2447;color:#fff;border-radius:8px">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
          <i v-else class="bi bi-check-circle me-2"></i>
          Set Password &amp; Continue
        </button>
      </form>

      <div class="text-center mt-3">
        <form @submit.prevent="logout">
          <button type="submit" class="btn btn-link btn-sm text-decoration-none text-muted">Log out instead</button>
        </form>
      </div>
    </div>

    <p class="text-center text-muted small mt-4">
      &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress
    </p>
  </div>
</template>

<script setup>
import { router, useForm } from '@inertiajs/vue3'

const form = useForm({
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('customer.password.change.store'), {
    onFinish: () => form.reset(),
  })
}

const logout = () => router.post(route('customer.logout'))
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
