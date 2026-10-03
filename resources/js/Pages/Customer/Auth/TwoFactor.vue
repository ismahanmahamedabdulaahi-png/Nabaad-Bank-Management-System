<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <i class="bi bi-shield-lock" style="font-size:2.5rem;color:#0B2447"></i>
        <h4 class="fw-bold mt-2" style="color:#0B2447">Verification Required</h4>
        <p class="text-muted small">Enter the 6-digit code sent to your email</p>
      </div>

      <div v-if="status" class="alert alert-success small">{{ status }}</div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="form-label fw-semibold small">Verification Code</label>
          <input
            v-model="form.code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            autocomplete="one-time-code"
            class="form-control text-center fw-bold"
            :class="{ 'is-invalid': form.errors.code }"
            style="letter-spacing:8px;font-size:1.5rem;padding:0.75rem"
            placeholder="······"
            autofocus
            required
          >
          <div v-if="form.errors.code" class="invalid-feedback d-block">{{ form.errors.code }}</div>
        </div>

        <button type="submit" class="btn w-100 fw-semibold py-2"
                :disabled="form.processing"
                style="background:#0B2447;color:#fff;border-radius:8px">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
          Verify &amp; Continue
        </button>
      </form>

      <div class="text-center mt-3">
        <button type="button" class="btn btn-link btn-sm text-decoration-none" :disabled="resendForm.processing" @click="resend">
          <span v-if="resendForm.processing" class="spinner-border spinner-border-sm me-1"></span>
          Didn't get a code? Resend
        </button>
      </div>
    </div>

    <p class="text-center text-muted small mt-4">
      &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress
    </p>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'

defineProps({ status: String })

const form = useForm({ code: '' })
const resendForm = useForm({})

const submit = () => {
  form.post(route('customer.two-factor.verify'), {
    onFinish: () => form.reset('code'),
  })
}

const resend = () => resendForm.post(route('customer.two-factor.resend'))
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
