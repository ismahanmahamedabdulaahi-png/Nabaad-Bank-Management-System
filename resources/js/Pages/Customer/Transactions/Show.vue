<template>
  <PortalLayout :title="transaction.reference" subtitle="Transaction Details">
    <template #actions>
      <div class="d-flex gap-2">
        <a :href="route('customer.transactions.receipt', transaction.id)"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-download me-1"></i>Download Receipt
        </a>
        <Link :href="route('customer.transactions.index')" view-transition class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back to Transactions
        </Link>
      </div>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm receipt-card">
          <!-- Amount block -->
          <div class="text-center py-4 px-3 receipt-head">
            <div class="status-check" :class="statusClass"><i class="bi" :class="statusIcon"></i></div>
            <div class="text-white-50 small text-uppercase letter-spacing-1 mt-2">{{ meta.label }}</div>
            <div class="display-6 fw-bold text-white my-1 tabular-nums">{{ meta.sign }}${{ fmt(transaction.amount) }}</div>
            <span class="badge px-3 py-1" :class="statusBadgeClass">{{ transaction.status?.toUpperCase() }}</span>
          </div>
          <div class="card-body">
            <div class="detail-row"><span>Reference</span><strong class="font-monospace small">{{ transaction.reference }}</strong></div>
            <div class="detail-row"><span>{{ isTransferReceived ? 'To Account' : 'Account' }}</span><strong class="font-monospace">{{ transaction.account?.account_number }}</strong></div>
            <div v-if="transaction.related_account" class="detail-row">
              <span>{{ isTransferReceived ? 'From Account' : 'To Account' }}</span><strong class="font-monospace">{{ transaction.related_account?.account_number }}</strong>
            </div>
            <div class="detail-row"><span>Balance Before</span><strong class="tabular-nums">${{ fmt(transaction.balance_before) }}</strong></div>
            <div class="detail-row"><span>Balance After</span><strong class="tabular-nums" style="color:#0B2447">${{ fmt(transaction.balance_after) }}</strong></div>
            <div v-if="transaction.description" class="detail-row"><span>Description</span><strong>{{ transaction.description }}</strong></div>
            <div class="detail-row"><span>Date & Time</span><strong>{{ fmtDatetime(transaction.created_at) }}</strong></div>
            <div class="detail-row"><span>Processed By</span><strong>{{ transaction.processed_by?.name ?? 'System' }}</strong></div>
          </div>
          <div class="card-footer text-center text-muted small bg-white">
            Thank you for banking with NABAAD Bank
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({ transaction: Object })

const isTransferReceived = computed(() =>
  props.transaction.type === 'transfer' &&
  Number(props.transaction.balance_after) >= Number(props.transaction.balance_before)
)

const meta = computed(() => {
  const rising = Number(props.transaction.balance_after) >= Number(props.transaction.balance_before)
  const label = {
    deposit:           'Deposit',
    withdrawal:        'Withdrawal',
    transfer:          rising ? 'Transfer Received' : 'Transfer Sent',
    reversal:          'Reversal',
    loan_disbursement: 'Loan Disbursement',
    loan_repayment:    'Loan Payment',
  }[props.transaction.type] ?? ucfirst(props.transaction.type)
  return { label, sign: rising ? '+' : '-' }
})

const statusClass = computed(() => ({
  completed: 'status-completed',
  pending:   'status-pending',
  rejected:  'status-rejected',
}[props.transaction.status] ?? 'status-pending'))

const statusIcon = computed(() => ({
  completed: 'bi-check-lg',
  pending:   'bi-hourglass-split',
  rejected:  'bi-x-lg',
}[props.transaction.status] ?? 'bi-hourglass-split'))

const statusBadgeClass = computed(() => ({
  completed: 'bg-success',
  pending:   'bg-warning text-dark',
  rejected:  'bg-danger',
}[props.transaction.status] ?? 'bg-secondary'))

const fmt         = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDatetime = (d) => d ? new Date(d).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : ''
const ucfirst     = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
</script>

<style scoped>
.receipt-head { background: linear-gradient(135deg,#0B2447,#14395B); border-radius: 12px 12px 0 0; }
.status-check {
  width: 44px; height: 44px; border-radius: 50%; margin: 0 auto;
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
}
.status-completed { background: rgba(16,185,129,.2); color: #6ee7b7; }
.status-pending    { background: rgba(245,158,11,.2); color: #fcd34d; }
.status-rejected   { background: rgba(220,38,38,.2); color: #fca5a5; }
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid #f0f4f8; font-size: .875rem; gap: 1rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row span { color: #64748b; flex-shrink: 0; }
.detail-row strong { text-align: right; }
</style>
