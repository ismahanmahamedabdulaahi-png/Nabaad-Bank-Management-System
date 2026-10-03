<template>
  <AdminLayout title="Redeem Service Code" subtitle="Cardless withdrawal & deposit pre-register codes">

    <div class="row justify-content-center">
      <div class="col-lg-6">

        <!-- Lookup -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-search me-2 text-primary"></i>Look Up a Code
          </div>
          <div class="card-body">
            <form @submit.prevent="lookup">
              <div class="input-group">
                <input v-model="lookupForm.code" type="text" class="form-control font-monospace"
                       :class="lookupForm.errors.code ? 'is-invalid' : ''"
                       placeholder="6-digit code" maxlength="6" required>
                <button type="submit" class="btn btn-primary" :disabled="lookupForm.processing">
                  <span v-if="lookupForm.processing" class="spinner-border spinner-border-sm"></span>
                  <span v-else>Look Up</span>
                </button>
              </div>
              <div v-if="lookupForm.errors.code" class="invalid-feedback d-block">{{ lookupForm.errors.code }}</div>
            </form>
          </div>
        </div>

        <!-- Found code -->
        <div v-if="found" class="card shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">
              <i class="bi" :class="found.type === 'withdrawal' ? 'bi-cash-stack text-danger' : 'bi-piggy-bank text-success'"></i>
              {{ ucfirst(found.type) }} Code {{ found.code }}
            </span>
            <span class="badge bg-warning text-dark">Pending</span>
          </div>
          <div class="card-body">
            <div class="detail-row"><span>Customer</span><strong>{{ found.customer?.name }}</strong></div>
            <div class="detail-row"><span>Account</span><strong class="font-monospace">{{ found.account?.account_number }}</strong></div>
            <div class="detail-row">
              <span>{{ found.type === 'withdrawal' ? 'Amount to Pay' : 'Expected Amount' }}</span>
              <strong>{{ fmt(found.amount) }}</strong>
            </div>
            <div class="detail-row"><span>Expires</span><strong>{{ fmtDate(found.expires_at) }}</strong></div>

            <!-- Withdrawal: single confirm -->
            <form v-if="found.type === 'withdrawal'" @submit.prevent="confirmWithdrawal" class="mt-3">
              <button type="submit" class="btn btn-danger w-100" :disabled="wForm.processing">
                <span v-if="wForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                <i class="bi bi-cash me-1"></i>Pay Out {{ fmt(found.amount) }}
              </button>
            </form>

            <!-- Deposit: confirm actual cash received -->
            <form v-else @submit.prevent="confirmDeposit" class="mt-3">
              <label class="form-label fw-semibold small">Cash Amount Actually Received</label>
              <div class="input-group mb-2">
                <span class="input-group-text">USD</span>
                <input v-model="dForm.amount" type="number" step="0.01" min="1" class="form-control"
                       :class="dForm.errors.amount ? 'is-invalid' : ''" required>
                <div class="invalid-feedback">{{ dForm.errors.amount }}</div>
              </div>
              <button type="submit" class="btn btn-success w-100" :disabled="dForm.processing">
                <span v-if="dForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                <i class="bi bi-check-circle me-1"></i>Confirm Deposit
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ found: { type: Object, default: null } })

const lookupForm = useForm({ code: '' })
const wForm      = useForm({ code: '' })
const dForm      = useForm({ code: '', amount: '', notes: '' })

const lookup = () => lookupForm.post(route('admin.service-codes.lookup'))

const confirmWithdrawal = () => {
  wForm.code = props.found.code
  wForm.post(route('admin.service-codes.redeem-withdrawal'))
}

const confirmDeposit = () => {
  dForm.code = props.found.code
  dForm.post(route('admin.service-codes.redeem-deposit'))
}

const fmt      = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate  = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst  = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
</script>

<style scoped>
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid rgba(11,11,11,.06); font-size: .875rem; gap: 1rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row span { color: var(--nabaad-muted); flex-shrink: 0; }
</style>
