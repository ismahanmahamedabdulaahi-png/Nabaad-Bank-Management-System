<template>
  <PortalLayout title="Transactions" subtitle="Your complete transaction history">

    <!-- Summary -->
    <div v-if="transactions.total" class="txn-summary mb-3">
      {{ transactions.total }} transaction{{ transactions.total === 1 ? '' : 's' }}
      <span class="mx-1">·</span> Last updated {{ lastUpdated }}
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="applyFilters">
          <div class="mb-3">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input v-model="f.search" type="text" class="form-control border-start-0 ps-0"
                     placeholder="Search by reference, account, description…">
            </div>
          </div>
          <div class="row g-3 align-items-end">
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Account</label>
              <select v-model="f.account_id" class="form-select form-select-sm">
                <option value="">All Accounts</option>
                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                  {{ acc.account_number }}
                </option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Type</label>
              <select v-model="f.type" class="form-select form-select-sm">
                <option value="">All Types</option>
                <option value="deposit">Deposit</option>
                <option value="withdrawal">Withdrawal</option>
                <option value="transfer">Transfer</option>
                <option value="loan_disbursement">Loan Disbursement</option>
                <option value="loan_repayment">Loan Payment</option>
                <option value="reversal">Reversal</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Date Range</label>
              <select v-model="rangePreset" class="form-select form-select-sm" @change="applyPreset">
                <option value="">All Time</option>
                <option value="today">Today</option>
                <option value="7d">Last 7 days</option>
                <option value="30d">Last 30 days</option>
                <option value="this_month">This month</option>
                <option value="last_month">Last month</option>
                <option value="custom">Custom range…</option>
              </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
              <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                <i class="bi bi-funnel me-1"></i>Filter
              </button>
              <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFilters">
                Clear
              </button>
            </div>
          </div>
          <div v-if="rangePreset === 'custom'" class="row g-3 mt-1">
            <div class="col-md-3">
              <label class="form-label fw-semibold small">From</label>
              <input v-model="f.date_from" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label fw-semibold small">To</label>
              <input v-model="f.date_to" type="date" class="form-control form-control-sm">
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!transactions.data.length" class="card border-0 shadow-sm">
      <div class="text-center text-muted py-5">
        <i class="bi bi-arrow-left-right fs-1 mb-2 d-block"></i>
        No transactions found.
      </div>
    </div>

    <template v-else>
      <!-- Desktop table -->
      <div class="card border-0 shadow-sm d-none d-md-block">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Reference</th>
                  <th>Account</th>
                  <th>Transaction</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end">Balance After</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="txn in transactions.data" :key="txn.id" class="txn-row" @click="open(txn)">
                  <td class="small text-muted">{{ fmtDate(txn.created_at) }}</td>
                  <td class="font-monospace small">{{ txn.reference }}</td>
                  <td class="font-monospace small">{{ txn.account?.account_number }}</td>
                  <td>
                    <span class="txn-type">
                      <span class="txn-type-icon" :style="{ background: describe(txn).bg, color: describe(txn).color }">
                        <i class="bi" :class="describe(txn).icon"></i>
                      </span>
                      {{ describe(txn).label }}
                    </span>
                  </td>
                  <td class="text-end fw-semibold tabular-nums" :style="{ color: describe(txn).color }">
                    {{ describe(txn).sign }}${{ fmt(txn.amount) }}
                  </td>
                  <td class="text-end small tabular-nums">${{ fmt(txn.balance_after) }}</td>
                  <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none d-flex flex-column gap-2">
        <div v-for="txn in transactions.data" :key="txn.id" class="txn-card" @click="open(txn)">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted">{{ fmtDate(txn.created_at) }}</span>
            <i class="bi bi-chevron-right text-muted"></i>
          </div>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="txn-type-icon" :style="{ background: describe(txn).bg, color: describe(txn).color }">
              <i class="bi" :class="describe(txn).icon"></i>
            </span>
            <span class="fw-semibold small">{{ describe(txn).label }}</span>
          </div>
          <div class="font-monospace text-muted mb-2" style="font-size:.72rem">{{ txn.reference }}</div>
          <div class="d-flex justify-content-between align-items-end">
            <span class="fw-bold tabular-nums" :style="{ color: describe(txn).color }">
              {{ describe(txn).sign }}${{ fmt(txn.amount) }}
            </span>
            <span class="text-end">
              <div class="tabular-nums small">${{ fmt(txn.balance_after) }}</div>
              <div class="text-muted" style="font-size:.65rem">Balance after</div>
            </span>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <Pagination :links="transactions.links" :from="transactions.from" :to="transactions.to"
                    :total="transactions.total" @navigate="goToPage" />
      </div>
    </template>
  </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  transactions: Object,
  accounts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const f = ref({
  account_id: props.filters.account_id ?? '',
  type:       props.filters.type ?? '',
  date_from:  props.filters.date_from ?? '',
  date_to:    props.filters.date_to ?? '',
  search:     props.filters.search ?? '',
})

const rangePreset = ref(props.filters.date_from || props.filters.date_to ? 'custom' : '')

const iso = (d) => d.toISOString().split('T')[0]

function applyPreset() {
  const today = new Date()
  if (rangePreset.value === 'today') {
    f.value.date_from = iso(today); f.value.date_to = iso(today)
  } else if (rangePreset.value === '7d') {
    const d = new Date(today); d.setDate(d.getDate() - 6)
    f.value.date_from = iso(d); f.value.date_to = iso(today)
  } else if (rangePreset.value === '30d') {
    const d = new Date(today); d.setDate(d.getDate() - 29)
    f.value.date_from = iso(d); f.value.date_to = iso(today)
  } else if (rangePreset.value === 'this_month') {
    f.value.date_from = iso(new Date(today.getFullYear(), today.getMonth(), 1)); f.value.date_to = iso(today)
  } else if (rangePreset.value === 'last_month') {
    f.value.date_from = iso(new Date(today.getFullYear(), today.getMonth() - 1, 1))
    f.value.date_to   = iso(new Date(today.getFullYear(), today.getMonth(), 0))
  } else if (rangePreset.value === '') {
    f.value.date_from = ''; f.value.date_to = ''
  }
  // 'custom' leaves date_from/date_to for manual entry below
}

const applyFilters = () => {
  router.get(route('customer.transactions.index'),
    Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)),
    { preserveState: true })
}
const clearFilters = () => {
  f.value = { account_id: '', type: '', date_from: '', date_to: '', search: '' }
  rangePreset.value = ''
  router.get(route('customer.transactions.index'))
}
const goToPage = (url) => router.get(url, {}, { preserveState: true, preserveScroll: true })
const open = (txn) => router.visit(route('customer.transactions.show', txn.id), { viewTransition: true })

const lastUpdated = computed(() => {
  const latest = props.transactions.data[0]?.created_at
  if (!latest) return '—'
  const d = new Date(latest)
  const today = new Date()
  if (d.toDateString() === today.toDateString()) return 'today'
  const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1)
  if (d.toDateString() === yesterday.toDateString()) return 'yesterday'
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
})

// A transfer's row is the "sent" (debit) leg or the "received" (credit) leg —
// both share type=transfer, so direction is read off whether balance rose or
// fell, the same signal the balance_after column already carries.
function describe(t) {
  const rising = Number(t.balance_after) >= Number(t.balance_before)
  const base = {
    deposit:            { label: 'Deposit',            icon: 'bi-arrow-down-circle-fill' },
    withdrawal:         { label: 'Withdrawal',         icon: 'bi-arrow-up-circle-fill' },
    transfer:           rising
      ? { label: 'Transfer Received', icon: 'bi-arrow-down-left-circle-fill' }
      : { label: 'Transfer Sent',     icon: 'bi-arrow-up-right-circle-fill' },
    reversal:           { label: 'Reversal',           icon: 'bi-arrow-counterclockwise' },
    loan_disbursement:  { label: 'Loan Disbursement',  icon: 'bi-cash-coin' },
    loan_repayment:     { label: 'Loan Payment',       icon: 'bi-cash-coin' },
  }[t.type] ?? { label: ucfirst(t.type), icon: 'bi-circle' }

  const palette = {
    deposit:           { color: '#0ca30c', bg: 'rgba(16,185,129,.12)' },
    withdrawal:        { color: '#dc2626', bg: 'rgba(220,38,38,.1)' },
    transfer:          { color: '#2a78d6', bg: 'rgba(42,120,214,.12)' },
    reversal:          { color: '#b45309', bg: 'rgba(245,158,11,.14)' },
    loan_disbursement: { color: '#0ca30c', bg: 'rgba(16,185,129,.12)' },
    loan_repayment:    { color: '#6366f1', bg: 'rgba(99,102,241,.12)' },
  }[t.type] ?? { color: '#64748b', bg: 'rgba(100,116,139,.1)' }

  return { ...base, ...palette, sign: rising ? '+' : '-' }
}

const fmt     = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
</script>

<style scoped>
.txn-summary { color: var(--nabaad-muted, #64748b); font-size: .85rem; }
.txn-row { cursor: pointer; }
.txn-row:hover { background: rgba(11,36,71,.035); }
.txn-type { display: inline-flex; align-items: center; gap: .5rem; font-size: .85rem; }
.txn-type-icon {
  width: 28px; height: 28px; border-radius: 50%;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: .8rem; flex-shrink: 0;
}
.txn-card {
  background: #fff; border: 1px solid #eef1f5; border-radius: .75rem;
  padding: .85rem 1rem; cursor: pointer; transition: box-shadow .15s ease, border-color .15s ease;
}
.txn-card:active { box-shadow: 0 2px 8px rgba(11,36,71,.12); border-color: rgba(11,36,71,.15); }
</style>
