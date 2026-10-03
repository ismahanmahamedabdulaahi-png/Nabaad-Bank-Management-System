<template>
  <PortalLayout title="Apply for a Loan" subtitle="Submit your loan application">
    <template #actions>
      <Link :href="route('customer.loans.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="row g-4">
      <!-- Form -->
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Disbursement Account <span class="text-danger">*</span></label>
                  <select v-model="form.account_id" class="form-select"
                          :class="form.errors.account_id ? 'is-invalid' : ''" required @change="onAccountChange">
                    <option value="">Select account</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                      {{ acc.account_number }} ({{ acc.account_type }}) — Balance: {{ fmt(acc.balance) }}
                    </option>
                  </select>
                  <div class="invalid-feedback">{{ form.errors.account_id }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Loan Amount (USD) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="form.amount" type="number" step="0.01" min="100"
                           class="form-control" :class="form.errors.amount ? 'is-invalid' : ''"
                           @input="calcEmi" required>
                    <div class="invalid-feedback">{{ form.errors.amount }}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Interest Rate (% p.a.) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input v-model="form.interest_rate" type="number" step="0.01" min="0" max="50"
                           class="form-control" @input="calcEmi" required>
                    <span class="input-group-text">% p.a.</span>
                  </div>
                  <div class="form-text">Bank rate: {{ defaults?.loan_interest_rate ?? 'N/A' }}%</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Tenure (months) <span class="text-danger">*</span></label>
                  <input v-model="form.tenure_months" type="number" min="1" max="360"
                         class="form-control" @input="calcEmi" required>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">First Repayment Date <span class="text-danger">*</span></label>
                  <input v-model="form.first_repayment_date" type="date" class="form-control"
                         :class="form.errors.first_repayment_date ? 'is-invalid' : ''" required>
                  <div class="invalid-feedback">{{ form.errors.first_repayment_date }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Purpose <span class="text-danger">*</span></label>
                  <input v-model="form.purpose" type="text" class="form-control"
                         :class="form.errors.purpose ? 'is-invalid' : ''"
                         placeholder="e.g. Business expansion, home renovation…" required>
                  <div class="invalid-feedback">{{ form.errors.purpose }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Collateral (optional)</label>
                  <input v-model="form.collateral" type="text" class="form-control"
                         placeholder="e.g. Land title, vehicle logbook…">
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-4" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                Submit Loan Application
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- EMI Preview -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-calculator me-2 text-success"></i>EMI Preview
          </div>
          <div class="card-body">
            <div v-if="emi" class="text-center mb-4">
              <div class="text-muted small">Monthly EMI</div>
              <div class="display-6 fw-bold" style="color:#0B2447">{{ fmt(emi.monthly_emi) }}</div>
              <div class="text-muted small mt-1">for {{ form.tenure_months }} months</div>
            </div>
            <div v-if="emi" class="detail-row"><span>Principal</span><strong>{{ fmt(form.amount) }}</strong></div>
            <div v-if="emi" class="detail-row"><span>Total Interest</span><strong class="text-warning">{{ fmt(emi.total_interest) }}</strong></div>
            <div v-if="emi" class="detail-row"><span>Total Payable</span><strong class="text-primary">{{ fmt(emi.total_payable) }}</strong></div>
            <div v-if="!emi" class="text-center text-muted py-5">
              <i class="bi bi-calculator fs-1 d-block mb-2 opacity-25"></i>
              Enter amount, rate and tenure to preview
            </div>

            <div class="alert alert-info small mt-3 mb-0 py-2">
              <i class="bi bi-info-circle me-1"></i>
              Your application will be reviewed by the bank. Approval is not guaranteed.
            </div>
          </div>
        </div>
      </div>
    </div>

  </PortalLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  accounts: { type: Array,  default: () => [] },
  defaults: { type: Object, default: () => ({}) },
})

const today     = new Date().toISOString().split('T')[0]
const nextMonth = new Date(Date.now() + 30 * 864e5).toISOString().split('T')[0]

const form = useForm({
  account_id:           '',
  amount:               '',
  interest_rate:        props.defaults?.loan_interest_rate ?? '15',
  tenure_months:        '12',
  first_repayment_date: nextMonth,
  purpose:              '',
  collateral:           '',
})

const emi = ref(null)

function calcEmi() {
  const p = parseFloat(form.amount)
  const r = parseFloat(form.interest_rate)
  const n = parseInt(form.tenure_months)
  if (!p || !n || p <= 0 || n <= 0) { emi.value = null; return }

  if (r <= 0) {
    const monthly = Math.round((p / n) * 100) / 100
    emi.value = { monthly_emi: monthly, total_interest: 0, total_payable: monthly * n }
    return
  }

  const mr  = r / 12 / 100
  const pow = Math.pow(1 + mr, n)
  const monthly = Math.round((p * mr * pow / (pow - 1)) * 100) / 100
  const interest = Math.round((monthly * n - p) * 100) / 100
  emi.value = { monthly_emi: monthly, total_interest: interest, total_payable: monthly * n }
}

const submit = () => form.post(route('customer.loans.store'))
const fmt    = (v) => new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(v ?? 0)
</script>

<style scoped>
.detail-row { display:flex; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid #f0f4f8; font-size:.875rem; }
.detail-row:last-child { border-bottom:none; }
.detail-row span { color:#64748b; }
</style>
