<template>
  <div class="login-root">
    <div class="login-split">
      <!-- Brand panel (desktop only) -->
      <div class="login-brand-panel">
        <div class="login-brand-content">
          <img src="/images/logo.png" alt="NABAAD Bank" class="login-brand-logo" />
          <h2 class="login-brand-title">Bank with confidence</h2>
          <p class="login-brand-tagline">Check balances, move money, and manage loans and cheques from anywhere — your NABAAD Bank accounts, all in one place.</p>
          <ul class="login-brand-points">
            <li><i class="bi bi-phone"></i> Manage every account, anywhere</li>
            <li><i class="bi bi-send-check"></i> Instant transfers &amp; standing orders</li>
            <li><i class="bi bi-shield-check"></i> Two-factor secured sign-in</li>
          </ul>
        </div>
      </div>

      <!-- Form panel -->
      <div class="login-form-panel">
    <div class="login-card">
      <!-- Logo -->
      <div class="text-center mb-4 login-card-header">
        <img src="/images/logo.png" alt="NABAAD Bank" style="height:80px;width:auto"
             @error="$event.target.style.display='none'">
        <h4 class="fw-bold mt-2" style="color:#0B2447">Customer Portal</h4>
        <p class="text-muted small">Sign in to access your accounts</p>
      </div>

      <div v-if="status" class="alert alert-success small">{{ status }}</div>

      <!-- Wrong-password / lockout alert -->
      <div v-if="form.errors.email" class="alert py-2 small d-flex align-items-start gap-2"
           :class="isLocked ? 'alert-danger' : 'alert-warning'">
        <i class="bi" :class="isLocked ? 'bi-lock-fill' : 'bi-exclamation-triangle-fill'"></i>
        <span>{{ form.errors.email }}</span>
      </div>

      <form @submit.prevent="submit">
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email Address</label>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
            <input v-model="form.email" type="email" class="form-control"
                   :class="{ 'is-invalid': form.errors.email }"
                   placeholder="you@example.com" autofocus required>
          </div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label fw-semibold small mb-0">Password</label>
            <Link :href="route('customer.password.request')"
                  class="small text-decoration-none" style="color:#0B2447">
              Forgot password?
            </Link>
          </div>
          <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
            <input v-model="form.password" :type="showPwd ? 'text' : 'password'"
                   class="form-control" :class="{ 'is-invalid': form.errors.password }"
                   placeholder="••••••••" required>
            <button type="button" class="input-group-text bg-white border-start-0"
                    @click="showPwd = !showPwd" tabindex="-1">
              <i class="bi" :class="showPwd ? 'bi-eye-slash' : 'bi-eye'" style="color:#94a3b8"></i>
            </button>
            <div class="invalid-feedback">{{ form.errors.password }}</div>
          </div>
        </div>

        <div class="mb-4 form-check">
          <input v-model="form.remember" type="checkbox" class="form-check-input" id="remember">
          <label class="form-check-label small" for="remember">Remember me</label>
        </div>

        <button type="submit" class="btn w-100 fw-semibold py-2"
                :disabled="form.processing"
                style="background:#0B2447;color:#fff;border-radius:8px">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
          Sign In
        </button>
      </form>

      <p class="text-center text-muted small mt-4 mb-0">
        Staff login?
        <a :href="route('login')" class="text-decoration-none fw-semibold" style="color:#0B2447">Click here</a>
      </p>

      <!-- Footer -->
      <p class="text-center text-muted small mt-4 mb-0">
        &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress
      </p>
    </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'

const props = defineProps({ status: String })
const showPwd = ref(false)

const form = useForm({ email: '', password: '', remember: false })
const submit = () => form.post(route('customer.login'))

const isLocked = computed(() => (form.errors.email ?? '').toLowerCase().startsWith('locked'))
</script>

<style scoped>
.login-root {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(circle at 15% 15%, rgba(201, 151, 47, 0.16), transparent 45%),
    radial-gradient(circle at 85% 85%, rgba(20, 57, 91, 0.5), transparent 50%),
    linear-gradient(135deg, #0B2447 0%, #14395B 100%);
}

.login-root::before {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
  background-size: 44px 44px;
  pointer-events: none;
}

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
}

.login-brand-content {
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  color: #fff;
  animation: login-card-in 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.login-brand-logo { height: 36px; margin-bottom: 2rem; filter: brightness(0) invert(1); width: fit-content; }
.login-brand-title { font-weight: 700; font-size: 1.6rem; margin-bottom: 0.75rem; }
.login-brand-tagline { color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6; margin-bottom: 2rem; }
.login-brand-points { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.9rem; }
.login-brand-points li { display: flex; align-items: center; gap: 0.65rem; font-size: 0.85rem; color: rgba(255,255,255,0.85); }
.login-brand-points i { color: #C9972F; font-size: 1rem; }

.login-form-panel { flex: 1 1 54%; display: flex; background: #fff; }

.login-card {
  background: #fff;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 420px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  justify-content: center;
  animation: login-card-in 0.5s cubic-bezier(0.16, 1, 0.3, 1) both;
}

@keyframes login-card-in {
  from { opacity: 0; transform: translateY(14px) scale(0.98); }
  to   { opacity: 1; transform: translateY(0) scale(1); }
}

.login-card-header { display: block; }

@media (min-width: 850px) {
  .login-brand-panel { display: block; }
  .login-card-header { display: none; }
}
</style>
