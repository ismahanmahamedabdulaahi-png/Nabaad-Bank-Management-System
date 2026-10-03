<template>
  <div class="login-page">
    <div class="login-card card p-4 p-md-5">
      <div class="text-center mb-4">
        <div class="mb-3">
          <i class="bi bi-envelope-paper section-heading-brand" style="font-size: 2.5rem;"></i>
        </div>
        <h4 class="fw-bold mb-0 section-heading-brand">You're Invited</h4>
      </div>

      <div v-if="already_done" class="text-center">
        <div class="alert alert-secondary small">
          <i class="bi bi-info-circle me-1"></i>
          This invitation has already been accepted (or is no longer valid). If you need access, contact your administrator.
        </div>
        <Link :href="route('login')" class="btn btn-outline-primary w-100 mt-2">Go to Login</Link>
      </div>

      <div v-else>
        <p class="text-center small mb-4">
          Hello <strong>{{ name }}</strong>, you've been selected to join NABAAD Bank's staff system
          <span v-if="role">as a <strong>{{ role }}</strong></span>. Do you accept this position?
        </p>

        <form @submit.prevent="submit">
          <button type="submit" class="btn btn-primary w-100 fw-semibold" :disabled="form.processing">
            <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status"></span>
            <i v-else class="bi bi-check-circle me-2"></i>
            Accept Invitation
          </button>
        </form>
      </div>

      <p class="text-center text-muted small mt-4 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
  name:         { type: String, required: true },
  role:         { type: String, default: null },
  already_done: { type: Boolean, default: false },
  accept_url:   { type: String, required: true },
})

const form = useForm({})

const submit = () => form.post(props.accept_url)
</script>
