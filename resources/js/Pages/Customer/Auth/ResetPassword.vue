<template>
  <div class="login-root">
    <div class="login-card">
      <div class="text-center mb-4">
        <img src="/images/logo.png" alt="NABAAD Bank" style="height:72px;width:auto"
             @error="$event.target.style.display='none'">
        <h5 class="fw-bold mt-2" style="color:#0B2447">New Password</h5>
      </div>
      <form @submit.prevent="form.post(route('customer.password.store'))">
        <input type="hidden" v-model="form.token">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email</label>
          <input v-model="form.email" type="email" class="form-control"
                 :class="{ 'is-invalid': form.errors.email }" required>
          <div class="invalid-feedback">{{ form.errors.email }}</div>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold small">New Password</label>
          <input v-model="form.password" type="password" class="form-control"
                 :class="{ 'is-invalid': form.errors.password }" required>
          <div class="invalid-feedback">{{ form.errors.password }}</div>
        </div>
        <div class="mb-4">
          <label class="form-label fw-semibold small">Confirm Password</label>
          <input v-model="form.password_confirmation" type="password" class="form-control" required>
        </div>
        <button type="submit" class="btn w-100 fw-semibold py-2"
                :disabled="form.processing"
                style="background:#0B2447;color:#fff;border-radius:8px">
          Reset Password
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
const props = defineProps({ token: String, email: String })
const form  = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' })
</script>

<style scoped>
.login-root { min-height: 100vh; background: linear-gradient(135deg, #0B2447 0%, #14395B 50%, #0B2447 100%); display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; }
.login-card { background: #fff; border-radius: 16px; padding: 2.5rem 2rem; width: 100%; max-width: 420px; box-shadow: 0 20px 60px rgba(0,0,0,.2); }
</style>
