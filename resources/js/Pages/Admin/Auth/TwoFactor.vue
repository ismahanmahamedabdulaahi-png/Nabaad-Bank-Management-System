<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-shield-lock text-primary" style="font-size: 2.5rem; color: #0B2447 !important;"></i>
        </div>
        <h4 class="fw-bold mb-0" style="color: #0B2447;">Verification Required</h4>
        <p class="text-muted small mb-0">Enter the 6-digit code sent to your email</p>
      </div>

      <div v-if="status" class="alert alert-success alert-sm py-2 small">{{ status }}</div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="form-label fw-semibold small">Verification Code</label>
          <input
            v-model="form.code"
            type="text"
            inputmode="numeric"
            maxlength="6"
            autocomplete="one-time-code"
            class="form-control form-control-lg text-center fw-bold"
            :class="{ 'is-invalid': form.errors.code }"
            style="letter-spacing: 8px; font-size: 1.5rem;"
            placeholder="······"
            autofocus
            required
          />
          <div v-if="form.errors.code" class="invalid-feedback d-block">{{ form.errors.code }}</div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status"></span>
          <i v-else class="bi bi-check-circle me-2"></i>
          Verify &amp; Continue
        </button>
      </form>

      <div class="text-center mt-3">
        <button type="button" class="btn btn-link btn-sm text-decoration-none" :disabled="resendForm.processing" @click="resend">
          <span v-if="resendForm.processing" class="spinner-border spinner-border-sm me-1"></span>
          Didn't get a code? Resend
        </button>
      </div>

      <p class="text-center text-muted small mt-3 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({ status: String });

const form = useForm({ code: '' });
const resendForm = useForm({});

const submit = () => {
  form.post(route('two-factor.verify'), {
    onFinish: () => form.reset('code'),
  });
};

const resend = () => resendForm.post(route('two-factor.resend'));
</script>
