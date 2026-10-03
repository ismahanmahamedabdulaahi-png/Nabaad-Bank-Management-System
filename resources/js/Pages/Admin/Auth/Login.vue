<template>
  <div class="login-page">
    <div class="login-split">
      <!-- Brand panel (desktop only) -->
      <div class="login-brand-panel">
        <div class="login-brand-content">
          <img src="/images/logo.png" alt="NABAAD Bank" class="login-brand-logo" />
          <h2 class="login-brand-title">Staff Portal</h2>
          <p class="login-brand-tagline">Core banking operations for NABAAD Bank's Garowe Branch — accounts, transactions, approvals, and compliance in one place.</p>
          <ul class="login-brand-points">
            <li><i class="bi bi-shield-check"></i> Two-factor protected sessions</li>
            <li><i class="bi bi-layers"></i> Maker-checker approval controls</li>
            <li><i class="bi bi-clock-history"></i> Full audit trail on every action</li>
          </ul>
        </div>
      </div>

      <!-- Form panel -->
      <div class="login-form-panel">
    <div class="login-card card p-4 p-md-5">
      <!-- Logo & Title (mobile / no-brand-panel context) -->
      <div class="text-center mb-4 login-card-header">
        <div class="mb-3">
          <i class="bi bi-bank2 text-primary" style="font-size: 2.5rem; color: #0B2447 !important;"></i>
        </div>
        <h4 class="fw-bold mb-0" style="color: #0B2447;">NABAAD Bank</h4>
        <p class="text-muted small mb-0">Staff Portal — Garowe Branch</p>
      </div>

      <!-- Session expired warning -->
      <div v-if="$page.props.flash?.warning" class="alert alert-warning alert-sm py-2 small">
        <i class="bi bi-exclamation-triangle me-1"></i>
        {{ $page.props.flash.warning }}
      </div>

      <!-- Status message (password reset etc.) -->
      <div v-if="status" class="alert alert-success alert-sm py-2 small">{{ status }}</div>

      <!-- Wrong-password / lockout alert -->
      <div v-if="form.errors.staff_id" class="alert py-2 small d-flex align-items-start gap-2"
           :class="isLocked ? 'alert-danger' : 'alert-warning'">
        <i class="bi" :class="isLocked ? 'bi-lock-fill' : 'bi-exclamation-triangle-fill'"></i>
        <span>{{ form.errors.staff_id }}</span>
      </div>

      <form @submit.prevent="submit">
        <!-- Staff ID -->
        <div class="mb-3">
          <label class="form-label fw-semibold small">Staff ID</label>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
              <i class="bi bi-person-badge text-muted"></i>
            </span>
            <input
              v-model="form.staff_id"
              type="text"
              class="form-control border-start-0 ps-0 text-uppercase"
              :class="{ 'is-invalid': form.errors.staff_id }"
              placeholder="STF-0001"
              autocomplete="username"
              required
            />
          </div>
        </div>

        <!-- Password -->
        <div class="mb-3">
          <div class="d-flex justify-content-between">
            <label class="form-label fw-semibold small">Password</label>
            <Link :href="route('password.request')" class="small text-decoration-none" style="color: #0B2447;">
              Forgot password?
            </Link>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-light border-end-0">
              <i class="bi bi-lock text-muted"></i>
            </span>
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              class="form-control border-start-0 border-end-0 ps-0"
              :class="{ 'is-invalid': form.errors.password }"
              placeholder="••••••••"
              autocomplete="current-password"
              required
            />
            <button
              type="button"
              class="input-group-text bg-light"
              @click="showPassword = !showPassword"
            >
              <i :class="showPassword ? 'bi bi-eye-slash' : 'bi bi-eye'" class="text-muted"></i>
            </button>
            <div v-if="form.errors.password" class="invalid-feedback">{{ form.errors.password }}</div>
          </div>
        </div>

        <!-- Remember me -->
        <div class="mb-4 form-check">
          <input v-model="form.remember" type="checkbox" class="form-check-input" id="remember" />
          <label class="form-check-label small" for="remember">Remember me on this device</label>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          class="btn btn-primary w-100 fw-semibold"
          :disabled="form.processing"
        >
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2" role="status"></span>
          <i v-else class="bi bi-box-arrow-in-right me-2"></i>
          Sign In to Staff Portal
        </button>
      </form>

      <p class="text-center text-muted small mt-4 mb-0">
        <i class="bi bi-shield-lock me-1"></i>
        Secured • NABAAD Bank © {{ new Date().getFullYear() }}
      </p>
    </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
  canResetPassword: Boolean,
  status: String,
});

const showPassword = ref(false);

const form = useForm({
  staff_id: '',
  password: '',
  remember: false,
});

const isLocked = computed(() => (form.errors.staff_id ?? '').toLowerCase().startsWith('locked'));

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};
</script>

<style scoped>
.login-split {
  width: 100%;
  max-width: 900px;
  display: flex;
  align-items: stretch;
  border-radius: 1.25rem;
  overflow: hidden;
  box-shadow: 0 30px 80px rgba(0,0,0,0.4);
  position: relative;
}

.login-brand-panel {
  display: none;
  flex: 1 1 46%;
  padding: 3rem 2.75rem;
  background:
    radial-gradient(circle at 20% 15%, rgba(201, 151, 47, 0.35), transparent 55%),
    linear-gradient(160deg, #14395B 0%, #0B2447 100%);
  position: relative;
  overflow: hidden;
}

.login-brand-content {
  position: relative;
  z-index: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  color: #fff;
  animation: login-card-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.login-brand-logo {
  height: 40px;
  margin-bottom: 2rem;
  filter: brightness(0) invert(1);
  width: fit-content;
}

.login-brand-title {
  font-weight: 700;
  font-size: 1.65rem;
  margin-bottom: 0.75rem;
}

.login-brand-tagline {
  color: rgba(255,255,255,0.7);
  font-size: 0.9rem;
  line-height: 1.6;
  margin-bottom: 2rem;
}

.login-brand-points {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.9rem;
}

.login-brand-points li {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  font-size: 0.85rem;
  color: rgba(255,255,255,0.85);
}

.login-brand-points i {
  color: #C9972F;
  font-size: 1rem;
}

.login-form-panel {
  flex: 1 1 54%;
  display: flex;
  align-items: stretch;
  background: var(--nabaad-bg);
}

.login-form-panel .login-card {
  box-shadow: none;
  border-radius: 0;
  max-width: none;
  width: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  animation: none;
}

.login-card-header { display: block; }

/* Brand panel only earns its keep once there's room for both columns side by
   side — below that it would squeeze the form, so the form panel's own
   centered header covers the branding instead. */
@media (min-width: 850px) {
  .login-brand-panel { display: block; }
  .login-card-header { display: none; }
}
</style>
