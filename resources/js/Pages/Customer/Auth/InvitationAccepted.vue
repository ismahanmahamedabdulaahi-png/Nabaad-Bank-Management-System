<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
        <h4 class="fw-bold mt-2" style="color:#0B2447">Invitation Accepted</h4>
      </div>

      <p class="text-center small mb-4">
        Welcome, <strong>{{ name }}</strong>! Check your email for a temporary password.
        You'll be asked to set your own password the first time you sign in.
      </p>

      <Link :href="route('customer.login')" class="btn w-100 fw-semibold py-2 text-decoration-none d-block text-center"
            style="background:#0B2447;color:#fff;border-radius:8px">
        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
      </Link>
    </div>

    <p class="text-center text-muted small mt-4">
      &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress
    </p>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  name: { type: String, required: true },
})
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
