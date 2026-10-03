<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-check-circle-fill text-success" style="font-size: 2.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-0 section-heading-brand">Invitation Accepted</h4>
      </div>

      <p class="text-center small mb-4">
        Welcome, <strong>{{ name }}</strong>! Check your email for your Staff ID and a one-time password.
        You'll be asked to set your own password the first time you sign in.
      </p>

      <Link :href="route('login')" class="btn btn-primary w-100 fw-semibold">
        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
      </Link>

      <p class="text-center text-muted small mt-4 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  name: { type: String, required: true },
})
</script>
