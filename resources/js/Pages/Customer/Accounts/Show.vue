<template>
  <PortalLayout :title="account.account_number" :subtitle="ucfirst(account.account_type) + ' Account'">
    <template #actions>
      <div class="d-flex gap-2">
        <Link :href="route('customer.transfer.create', { source_account_id: account.id })"
              class="btn btn-sm btn-primary">
          <i class="bi bi-arrow-left-right me-1"></i>Transfer
        </Link>
        <Link :href="route('customer.standing-orders.create', { source_account_id: account.id })"
              class="btn btn-sm btn-outline-primary">
          <i class="bi bi-arrow-repeat me-1"></i>Standing Order
        </Link>
        <a :href="route('customer.accounts.statement', account.id)" target="_blank"
           class="btn btn-sm btn-outline-success">
          <i class="bi bi-file-earmark-pdf me-1"></i>Statement
        </a>
        <Link :href="route('customer.accounts.index')" view-transition class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back to Accounts
        </Link>
      </div>
    </template>

    <div class="row g-4">
      <!-- Balance card -->
      <div class="col-lg-4">
        <div class="account-card mb-3" :style="{ viewTransitionName: `account-card-${account.id}` }">
          <div class="balance-label">Available Balance</div>
          <div class="balance-amount" :style="{ viewTransitionName: `account-balance-${account.id}` }">${{ fmt(account.balance) }}</div>
          <div class="d-flex justify-content-between mt-3 pt-3" style="border-top:1px solid rgba(255,255,255,.15)">
            <div>
              <div style="font-size:.7rem;color:rgba(255,255,255,.6)">Account No.</div>
              <div class="fw-bold font-monospace small">{{ account.account_number }}</div>
            </div>
            <div class="text-end">
              <div style="font-size:.7rem;color:rgba(255,255,255,.6)">Status</div>
              <span class="badge" :class="statusBadge(account.status)">{{ account.status }}</span>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="fw-semibold mb-3" style="color:#0B2447">Account Details</h6>
            <div class="detail-row"><span>Type</span><strong>{{ ucfirst(account.account_type) }}</strong></div>
            <div class="detail-row"><span>Currency</span><strong>{{ account.currency ?? 'USD' }}</strong></div>
            <div class="detail-row"><span>Opened</span><strong>{{ fmtDate(account.created_at) }}</strong></div>
            <div class="detail-row"><span>Interest Rate</span><strong>{{ account.interest_rate ?? 0 }}%</strong></div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
          <div class="card-body">
            <h6 class="fw-semibold mb-1" style="color:#0B2447">
              <i class="bi bi-bell me-1"></i>Low-Balance Alert
            </h6>
            <p class="text-muted small mb-2">Get notified if this account's balance drops below a threshold you set.</p>
            <form @submit.prevent="saveThreshold" class="d-flex gap-2">
              <div class="input-group input-group-sm">
                <span class="input-group-text">{{ account.currency ?? 'USD' }}</span>
                <input v-model="thresholdForm.low_balance_alert_threshold" type="number" step="0.01" min="0"
                       class="form-control" placeholder="Off">
              </div>
              <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap" :disabled="thresholdForm.processing">
                Save
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Transaction history -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-arrow-left-right me-2 text-primary"></i>Transaction History
          </div>
          <div class="card-body p-0">
            <div v-if="!transactions.data.length" class="text-center text-muted py-4 small">
              No transactions found.
            </div>
            <div v-for="txn in transactions.data" :key="txn.id" class="txn-row">
              <div class="d-flex align-items-center gap-3">
                <div class="txn-icon" :class="txnColor(txn.type)">
                  <i class="bi" :class="txnIcon(txn.type)"></i>
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold small">{{ ucfirst(txn.type) }}</div>
                  <div class="text-muted" style="font-size:.72rem">
                    {{ txn.reference }} · {{ fmtDate(txn.created_at) }}
                  </div>
                  <div v-if="txn.description" class="text-muted" style="font-size:.7rem">{{ txn.description }}</div>
                </div>
                <div class="text-end">
                  <div class="fw-bold" :class="txn.type === 'deposit' ? 'text-success' : 'text-danger'">
                    {{ txn.type === 'deposit' ? '+' : '-' }}${{ fmt(txn.amount) }}
                  </div>
                  <div class="text-muted" style="font-size:.7rem">Bal: ${{ fmt(txn.balance_after) }}</div>
                </div>
              </div>
            </div>
          </div>
          <!-- Pagination -->
          <div v-if="transactions.last_page > 1" class="card-footer bg-white d-flex justify-content-between align-items-center">
            <span class="text-muted small">Page {{ transactions.current_page }} of {{ transactions.last_page }}</span>
            <div class="d-flex gap-2">
              <Link v-if="transactions.prev_page_url" :href="transactions.prev_page_url"
                    class="btn btn-sm btn-outline-secondary">Prev</Link>
              <Link v-if="transactions.next_page_url" :href="transactions.next_page_url"
                    class="btn btn-sm btn-outline-primary">Next</Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({ account: Object, transactions: Object })

const thresholdForm = useForm({
  low_balance_alert_threshold: props.account.low_balance_alert_threshold ?? '',
})

const saveThreshold = () => thresholdForm.patch(route('customer.accounts.alert-threshold', props.account.id), {
  preserveScroll: true,
})

const fmt      = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate  = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : ''
const ucfirst  = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ active: 'bg-success', frozen: 'bg-info', closed: 'bg-secondary' }[s] ?? 'bg-secondary')
const txnIcon  = (t) => ({ deposit: 'bi-arrow-down-circle-fill', withdrawal: 'bi-arrow-up-circle-fill', transfer: 'bi-arrow-left-right', reversal: 'bi-arrow-counterclockwise' }[t] ?? 'bi-circle')
const txnColor = (t) => t === 'deposit' ? 'txn-icon-in' : t === 'withdrawal' ? 'txn-icon-out' : 'txn-icon-other'
</script>

<style scoped>
.account-card { background: linear-gradient(135deg, #0B2447 0%, #14395B 100%); border-radius: 16px; padding: 1.5rem; color: #fff; }
.balance-label { font-size: .75rem; color: rgba(255,255,255,.6); text-transform: uppercase; }
.balance-amount { font-size: 2.2rem; font-weight: 800; }
.detail-row { display: flex; justify-content: space-between; padding: .4rem 0; border-bottom: 1px solid #f0f4f8; font-size: .875rem; }
.detail-row:last-child { border-bottom: none; }
.txn-row { padding: .75rem 1.25rem; border-bottom: 1px solid #f0f4f8; }
.txn-row:last-child { border-bottom: none; }
.txn-icon { width:34px; height:34px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.9rem; flex-shrink:0; }
.txn-icon-in   { background:#d1fae5; color:#065f46; }
.txn-icon-out  { background:#fee2e2; color:#991b1b; }
.txn-icon-other{ background:#dbeafe; color:#1d4ed8; }
</style>
