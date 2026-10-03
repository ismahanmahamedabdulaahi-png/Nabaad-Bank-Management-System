<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-envelope-paper section-heading-brand" style="font-size: 2.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-0 section-heading-brand">Reset Password</h4>
        <p class="text-muted small mb-0">Enter your staff email to receive a reset link</p>
      </div>

      <div v-if="status" class="alert alert-success alert-sm py-2 small">{{ status }}</div>

      <form @submit.prevent="submit">
        <div class="mb-4">
          <label class="form-label fw-semibold small">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
              <i class="bi bi-envelope text-muted"></i>
            </span>
            <input
              v-model="form.email"
              type="email"
              class="form-control border-start-0 ps-0"
              :class="{ 'is-invalid': form.errors.email }"
              placeholder="you@nabaadbank.so"
              autocomplete="email"
              autofocus
              required
            />
            <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-semibold" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status"></span>
          <i v-else class="bi bi-send me-2"></i>
          Send Reset Link
        </button>
      </form>

      <p class="text-center mt-4 mb-0">
        <Link :href="route('login')" class="small text-decoration-none">
          <i class="bi bi-arrow-left me-1"></i>Back to Sign In
        </Link>
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';

defineProps({
  status: String,
});

const form = useForm({ email: '' });

const submit = () => form.post(route('password.email'));
</script>
