<template>
  <PortalLayout title="Cardless Codes" subtitle="Withdraw directly from your account, or pre-register a cash deposit">

    <!-- Withdrawal receipt (demo — no real cash is dispensed) -->
    <div v-if="receipt && showReceipt" class="card border-0 shadow-sm mb-4 border-start border-success border-4">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h5 class="mb-1 text-success"><i class="bi bi-check-circle-fill me-2"></i>Lacagta waa la bixiyay</h5>
            <div class="text-muted small">Withdrawal completed</div>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark">DEMO</span>
            <button type="button" class="btn-close" aria-label="Close" @click="showReceipt = false"></button>
          </div>
        </div>
        <div class="display-6 fw-bold tabular-nums mb-3">− {{ fmt(receipt.amount) }}</div>
        <div class="row small g-2">
          <div class="col-6 col-md-3"><div class="text-muted">Reference</div><div class="font-monospace fw-semibold">{{ receipt.reference }}</div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Account</div><div class="font-monospace">{{ receipt.account_number }}</div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Balance</div><div class="tabular-nums">{{ fmt(receipt.balance_before) }} → <strong>{{ fmt(receipt.balance_after) }}</strong></div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Time</div><div>{{ fmtDate(receipt.completed_at) }}</div></div>
        </div>
        <div class="text-muted small mt-3">
          <i class="bi bi-info-circle me-1"></i>Tani waa tusaale (demo): lacag dhab ah ma bixin, laakiin akoonka waa laga jaray.
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <!-- Request Withdrawal -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-cash-stack me-2 text-danger"></i>Withdraw Cash
          </div>
          <div class="card-body">
            <p class="text-muted small">Withdraw directly from your account — no code, no teller needed.</p>
            <form @submit.prevent="submitWithdrawal">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Account</label>
                <select v-model="wForm.account_id" class="form-select form-select-sm" required>
                  <option value="">Select account</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                    {{ acc.account_number }} — Balance: {{ fmt(acc.balance) }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <div class="input-group input-group-sm">
                  <span class="input-group-text">USD</span>
                  <input v-model="wForm.amount" type="number" step="0.01" min="1" class="form-control"
                         :class="wForm.errors.amount ? 'is-invalid' : ''" placeholder="Amount" required>
                  <div class="invalid-feedback">{{ wForm.errors.amount }}</div>
                </div>
              </div>
              <button type="submit" class="btn btn-outline-danger btn-sm w-100" :disabled="wForm.processing">
                <span v-if="wForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Withdraw Now
              </button>
              <div v-if="wForm.errors.balance || wForm.errors.account" class="text-danger small mt-2">
                {{ wForm.errors.balance || wForm.errors.account }}
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Request Deposit -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-piggy-bank me-2 text-success"></i>Pre-Register a Deposit
          </div>
          <div class="card-body">
            <p class="text-muted small">Let the teller know you're coming — show this code when you bring your cash in.</p>
            <form @submit.prevent="submitDeposit">
              <div class="mb-3">
                <label class="form-label fw-semibold small">Account</label>
                <select v-model="dForm.account_id" class="form-select form-select-sm" required>
                  <option value="">Select account</option>
                  <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                    {{ acc.account_number }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <div class="input-group input-group-sm">
                  <span class="input-group-text">USD</span>
                  <input v-model="dForm.amount" type="number" step="0.01" min="1" class="form-control"
                         :class="dForm.errors.amount ? 'is-invalid' : ''" placeholder="Approximate amount" required>
                  <div class="invalid-feedback">{{ dForm.errors.amount }}</div>
                </div>
              </div>
              <button type="submit" class="btn btn-outline-success btn-sm w-100" :disabled="dForm.processing">
                <span v-if="dForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Generate Deposit Code
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- History -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Recent Codes</div>
      <div v-if="!codes.length" class="text-center text-muted py-5">
        <i class="bi bi-qr-code fs-1 mb-2 d-block"></i>
        No codes generated yet.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Code</th>
              <th>Type</th>
              <th>Account</th>
              <th class="text-end">Amount</th>
              <th>Status</th>
              <th>Expires</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="code in codes" :key="code.id">
              <td class="font-monospace fw-bold">{{ code.code }}</td>
              <td>
                <span class="badge" :class="code.type === 'withdrawal' ? 'bg-danger' : 'bg-success'">
                  {{ ucfirst(code.type) }}
                </span>
              </td>
              <td class="font-monospace small">{{ code.account?.account_number }}</td>
              <td class="text-end tabular-nums">{{ fmt(code.amount) }}</td>
              <td><span class="badge" :class="statusBadge(code.status)">{{ ucfirst(code.status) }}</span></td>
              <td class="small text-muted">{{ fmtDate(code.expires_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  codes:    { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
  receipt:  { type: Object, default: null },
})

const showReceipt = ref(true)
watch(() => props.receipt, () => { showReceipt.value = true })

const wForm = useForm({ account_id: '', amount: '' })
const dForm = useForm({ account_id: '', amount: '' })

const submitWithdrawal = () => {
  if (!confirm(`Withdraw ${fmt(wForm.amount)} from your account now?`)) return
  wForm.post(route('customer.service-codes.withdraw'), {
    onSuccess: () => wForm.reset(),
  })
}
const submitDeposit = () => dForm.post(route('customer.service-codes.request-deposit'), {
  onSuccess: () => dForm.reset(),
})

const fmt      = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate  = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst  = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const statusBadge = (s) => ({ pending: 'bg-warning text-dark', redeemed: 'bg-success', expired: 'bg-secondary', cancelled: 'bg-secondary' }[s] ?? 'bg-secondary')
</script>
