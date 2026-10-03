<template>
  <PortalLayout title="Cardless Codes" subtitle="Withdraw or deposit cash at a branch counter without any paperwork">

    <div class="row g-4 mb-4">
      <!-- Request Withdrawal -->
      <div class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-cash-stack me-2 text-danger"></i>Request Cash Withdrawal
          </div>
          <div class="card-body">
            <p class="text-muted small">Get a code to collect cash at any branch counter — no form to fill in when you arrive.</p>
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
                Generate Withdrawal Code
              </button>
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
import { useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  codes:    { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
})

const wForm = useForm({ account_id: '', amount: '' })
const dForm = useForm({ account_id: '', amount: '' })

const submitWithdrawal = () => wForm.post(route('customer.service-codes.request-withdrawal'), {
  onSuccess: () => wForm.reset(),
})
const submitDeposit = () => dForm.post(route('customer.service-codes.request-deposit'), {
  onSuccess: () => dForm.reset(),
})

const fmt      = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate  = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst  = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const statusBadge = (s) => ({ pending: 'bg-warning text-dark', redeemed: 'bg-success', expired: 'bg-secondary', cancelled: 'bg-secondary' }[s] ?? 'bg-secondary')
</script>
