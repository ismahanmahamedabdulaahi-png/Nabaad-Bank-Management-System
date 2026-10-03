<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <i class="bi bi-envelope-paper" style="font-size:2.5rem;color:#0B2447"></i>
        <h4 class="fw-bold mt-2" style="color:#0B2447">Welcome to NABAAD Bank</h4>
      </div>

      <div v-if="already_done" class="text-center">
        <div class="alert alert-secondary small">
          <i class="bi bi-info-circle me-1"></i>
          This invitation has already been accepted (or is no longer valid). If you need help, contact your branch.
        </div>
        <Link :href="route('customer.login')" class="btn btn-outline-primary w-100 mt-2">Go to Login</Link>
      </div>

      <div v-else>
        <p class="text-center small mb-4">
          Hello <strong>{{ name }}</strong>, an account has been opened for you at NABAAD Bank.
          Please confirm you'd like to activate it.
        </p>

        <form @submit.prevent="submit">
          <button type="submit" class="btn w-100 fw-semibold py-2" :disabled="form.processing"
                  style="background:#0B2447;color:#fff;border-radius:8px">
            <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
            <i v-else class="bi bi-check-circle me-2"></i>
            Accept &amp; Activate Account
          </button>
        </form>
      </div>
    </div>

    <p class="text-center text-muted small mt-4">
      &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress
    </p>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  name:         { type: String, required: true },
  already_done: { type: Boolean, default: false },
  accept_url:   { type: String, required: true },
})

const form = useForm({})

const submit = () => form.post(props.accept_url)
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
