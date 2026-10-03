<template>
  <PortalLayout title="Dashboard" :subtitle="`Welcome back, ${customer_auth.name}`">

    <!-- Low-balance warnings -->
    <div v-for="acc in low_balance_accounts" :key="acc.id"
         class="alert alert-warning d-flex align-items-center gap-3 mb-3 py-2">
      <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
      <div class="small">
        Account <strong class="font-monospace">{{ acc.account_number }}</strong> has a low balance of
        <strong>${{ fmt(acc.balance) }}</strong>.
        <Link :href="route('customer.accounts.show', acc.id)" class="alert-link ms-1">View account</Link>
      </div>
    </div>

    <!-- Summary stat cards -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <div class="stat-card">
          <div class="stat-icon" style="background:#dbeafe;color:#1d4ed8"><i class="bi bi-wallet2"></i></div>
          <div class="stat-label">Total Balance</div>
          <div class="stat-value">${{ fmt(stats.total_balance) }}</div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="stat-card">
          <div class="stat-icon" style="background:#d1fae5;color:#065f46"><i class="bi bi-bank2"></i></div>
          <div class="stat-label">Accounts</div>
          <div class="stat-value">{{ stats.account_count }}</div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="stat-card">
          <div class="stat-icon" style="background:#ede9fe;color:#6d28d9"><i class="bi bi-person-check"></i></div>
          <div class="stat-label">Customer ID</div>
          <div class="d-flex align-items-center gap-2">
            <div class="stat-value font-monospace" style="font-size:1rem">{{ customer_auth.customer_number }}</div>
            <button type="button" class="btn btn-sm btn-link p-0 text-muted" title="Copy" @click="copyCustomerId">
              <i class="bi" :class="copied ? 'bi-check-lg text-success' : 'bi-clipboard'"></i>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Loans -->
    <div class="row g-4 mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div v-if="loan_summary" class="card-body d-flex flex-wrap align-items-center gap-4">
            <div class="d-flex align-items-center gap-3">
              <div class="stat-icon" style="background:#fef3c7;color:#92400e"><i class="bi bi-cash-coin"></i></div>
              <div>
                <div class="stat-label">Active Loans</div>
                <div class="stat-value">{{ loan_summary.count }}</div>
              </div>
            </div>
            <div class="loan-metric">
              <div class="stat-label">Outstanding</div>
              <div class="fw-bold" style="color:#0B2447">${{ fmt(loan_summary.outstanding_balance) }}</div>
            </div>
            <div v-if="loan_summary.next_payment_amount" class="loan-metric">
              <div class="stat-label">Next Payment</div>
              <div class="fw-bold" style="color:#0B2447">${{ fmt(loan_summary.next_payment_amount) }}</div>
            </div>
            <div v-if="loan_summary.next_payment_due_date" class="loan-metric">
              <div class="stat-label">Due</div>
              <div class="fw-bold" :class="loan_summary.next_payment_overdue ? 'text-danger' : ''" style="color:#0B2447">
                {{ fmtDate(loan_summary.next_payment_due_date) }}
                <span v-if="loan_summary.next_payment_overdue" class="badge bg-danger ms-1" style="font-size:.6rem">Overdue</span>
              </div>
            </div>
            <Link :href="route('customer.loans.show', loan_summary.loan_id)" class="btn btn-sm btn-outline-primary ms-auto">
              View Loans <i class="bi bi-arrow-right ms-1"></i>
            </Link>
          </div>
          <div v-else class="card-body text-center py-4">
            <i class="bi bi-cash-coin d-block mb-2 text-muted" style="font-size:1.75rem;opacity:.4"></i>
            <div class="fw-semibold small">No Active Loans</div>
            <p class="text-muted small mb-3">You currently have no active loans.</p>
            <Link :href="route('customer.loans.create')" class="btn btn-sm btn-primary">Apply for a Loan</Link>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Accounts summary -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-wallet2 me-2 text-primary"></i>My Accounts</span>
            <Link :href="route('customer.accounts.index')"
                  class="btn btn-sm btn-outline-primary">View All</Link>
          </div>
          <div class="card-body p-0">
            <div v-if="!accounts.length" class="text-center text-muted py-4 small">No active accounts</div>
            <div v-for="acc in accounts" :key="acc.id" class="account-row">
              <div class="d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                  <div class="text-muted" style="font-size:.75rem">{{ ucfirst(acc.account_type) }}</div>
                </div>
                <div class="text-end">
                  <div class="fw-bold" style="color:#0B2447">${{ fmt(acc.balance) }}</div>
                  <span class="badge" :class="acc.status === 'active' ? 'bg-success' : 'bg-secondary'"
                        style="font-size:.65rem">{{ acc.status }}</span>
                </div>
              </div>
              <Link :href="route('customer.accounts.show', acc.id)"
                    class="stretched-link"></Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent transactions -->
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-arrow-left-right me-2 text-primary"></i>Recent Transactions</span>
            <Link :href="route('customer.transactions.index')"
                  class="btn btn-sm btn-outline-primary">View All</Link>
          </div>
          <div class="card-body p-0">
            <div v-if="!recent_transactions.length" class="text-center text-muted py-4 small">No transactions yet</div>
            <div v-for="txn in recent_transactions" :key="txn.id" class="txn-row">
              <div class="d-flex align-items-center gap-3">
                <div class="txn-icon" :class="txnColor(txn.type)">
                  <i class="bi" :class="txnIcon(txn.type)"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold small">
                    {{ ucfirst(txn.type) }}
                    <span v-if="txn.status !== 'completed'" class="badge ms-1" :class="statusBadge(txn.status)" style="font-size:.62rem">
                      {{ ucfirst(txn.status) }}
                    </span>
                  </div>
                  <div class="text-muted" style="font-size:.72rem">
                    {{ txn.account?.account_number }} · {{ fmtDate(txn.created_at) }}
                  </div>
                </div>
                <div class="text-end">
                  <div class="fw-bold" :class="txn.type === 'deposit' ? 'text-success' : 'text-danger'">
                    {{ txn.type === 'deposit' ? '+' : '-' }}${{ fmt(txn.amount) }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const page = usePage()
const props = defineProps({
  customer:             Object,
  accounts:             { type: Array, default: () => [] },
  recent_transactions:  { type: Array, default: () => [] },
  stats:                Object,
  loan_summary:         { type: Object, default: null },
  low_balance_accounts: { type: Array,  default: () => [] },
})

const customer_auth = page.props.customer_auth ?? {}
const fmt     = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const txnIcon  = (t) => ({ deposit: 'bi-arrow-down-circle-fill', withdrawal: 'bi-arrow-up-circle-fill', transfer: 'bi-arrow-left-right', reversal: 'bi-arrow-counterclockwise' }[t] ?? 'bi-circle')
const txnColor = (t) => t === 'deposit' ? 'txn-icon-in' : t === 'withdrawal' ? 'txn-icon-out' : 'txn-icon-other'
const statusBadge = (s) => ({ pending: 'bg-warning text-dark', rejected: 'bg-danger' }[s] ?? 'bg-secondary')

const copied = ref(false)
function copyCustomerId() {
  navigator.clipboard?.writeText(customer_auth.customer_number ?? '').then(() => {
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  })
}
</script>

<style scoped>
.stat-card {
  background: var(--nabaad-bg);
  border: 1px solid var(--nabaad-border);
  border-radius: .75rem;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 3px rgba(0,0,0,.04);
  transition: box-shadow .15s ease, transform .15s ease, border-color .15s ease;
}
.stat-card:hover {
  box-shadow: 0 .5rem 1.25rem rgba(11,36,71,.08);
  transform: translateY(-2px);
  border-color: rgba(11,36,71,.15);
}
.dark .stat-card:hover { box-shadow: 0 .5rem 1.25rem rgba(0,0,0,.35); border-color: var(--nabaad-border); }
.stat-icon { width:44px; height:44px; border-radius:.65rem; display:flex; align-items:center; justify-content:center; font-size:1.15rem; margin-bottom:.75rem; }
.stat-label { font-size:.78rem; color: var(--nabaad-muted); font-weight:600; text-transform:uppercase; letter-spacing:.03em; }
.stat-value { font-size:1.6rem; font-weight:700; color: var(--nabaad-text); margin-top: .15rem; }
.loan-metric { min-width:110px; }

.account-row { padding:.875rem 1.25rem; border-bottom:1px solid var(--nabaad-border); position:relative; cursor:pointer; transition:background .15s; }
.account-row:hover { background: var(--nabaad-section-bg); }
.account-row:last-child { border-bottom:none; }

.txn-row { padding:.75rem 1.25rem; border-bottom:1px solid var(--nabaad-border); transition: background .15s; }
.txn-row:hover { background: var(--nabaad-section-bg); }
.txn-row:last-child { border-bottom:none; }
.txn-icon { width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.95rem; flex-shrink:0; }
.txn-icon-in   { background:#d1fae5; color:#065f46; }
.txn-icon-out  { background:#fee2e2; color:#991b1b; }
.txn-icon-other{ background:#dbeafe; color:#1d4ed8; }
.dark .txn-icon-in   { background: rgba(16,185,129,.18); color:#6ee7b7; }
.dark .txn-icon-out  { background: rgba(239,68,68,.2);   color:#fca5a5; }
.dark .txn-icon-other{ background: rgba(56,189,248,.18);  color:#7dd3fc; }
</style>
