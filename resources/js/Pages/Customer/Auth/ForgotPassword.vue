<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <img src="/images/logo.png" alt="NABAAD Bank" style="height:72px;width:auto"
             @error="$event.target.style.display='none'">
        <h5 class="fw-bold mt-2" style="color:#0B2447">Reset Password</h5>
        <p class="text-muted small">Enter your email to receive a reset link</p>
      </div>

      <div v-if="status" class="alert alert-success small">{{ status }}</div>

      <form @submit.prevent="form.post(route('customer.password.email'))">
        <div class="mb-4">
          <label class="form-label fw-semibold small">Email Address</label>
          <input v-model="form.email" type="email" class="form-control"
                 :class="{ 'is-invalid': form.errors.email }"
                 placeholder="you@example.com" autofocus required>
          <div class="invalid-feedback">{{ form.errors.email }}</div>
        </div>
        <button type="submit" class="btn w-100 fw-semibold py-2"
                :disabled="form.processing"
                style="background:#0B2447;color:#fff;border-radius:8px">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
          Send Reset Link
        </button>
      </form>

      <div class="text-center mt-3">
        <Link :href="route('customer.login')" class="small text-decoration-none" style="color:#0B2447">
          <i class="bi bi-arrow-left me-1"></i>Back to Login
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
const props  = defineProps({ status: String })
const form   = useForm({ email: '' })
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
