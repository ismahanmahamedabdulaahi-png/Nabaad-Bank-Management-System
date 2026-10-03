<template>
  <AdminLayout
    title="Transactions"
    subtitle="All banking transactions"
    :breadcrumbs="[{ label: 'Transactions' }]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <Link v-if="can('transactions.deposit')"  :href="route('admin.transactions.deposit.form')"  class="btn btn-success btn-sm"><i class="bi bi-plus-lg me-1"></i>Deposit</Link>
        <Link v-if="can('transactions.withdraw')" :href="route('admin.transactions.withdrawal.form')" class="btn btn-danger btn-sm"><i class="bi bi-dash-lg me-1"></i>Withdraw</Link>
        <Link v-if="can('transactions.transfer')" :href="route('admin.transactions.transfer.form')"  class="btn btn-primary btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Transfer</Link>
      </div>
    </template>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-primary">{{ stats.today_count }}</div>
          <div class="text-muted small">Transactions Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-success">{{ formatCurrency(stats.today_deposits) }}</div>
          <div class="text-muted small">Deposits Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-danger">{{ formatCurrency(stats.today_withdrawals) }}</div>
          <div class="text-muted small">Withdrawals Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3" :class="stats.pending > 0 ? 'border-warning' : ''">
          <div class="fw-bold tabular-nums fs-4 text-warning">{{ stats.pending }}</div>
          <div class="text-muted small">Pending Approval</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-3">
            <input v-model="form.search" type="text" class="form-control form-control-sm" placeholder="Reference, account, customer…" />
          </div>
          <div class="col-md-2">
            <select v-model="form.type" class="form-select form-select-sm">
              <option value="">All Types</option>
              <option value="deposit">Deposit</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="transfer">Transfer</option>
              <option value="reversal">Reversal</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="rejected">Rejected</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="form.date_from" type="date" class="form-control form-control-sm" placeholder="From date" />
          </div>
          <div class="col-md-2">
            <input v-model="form.date_to" type="date" class="form-control form-control-sm" placeholder="To date" />
          </div>
          <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Go</button>
            <Link :href="route('admin.transactions.index')" class="btn btn-light btn-sm"><i class="bi bi-x"></i></Link>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!transactions.data.length" class="text-center text-muted py-5">
          <i class="bi bi-receipt d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
          No transactions found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Reference</th>
                <th>Account</th>
                <th>Type</th>
                <th class="text-end">Amount</th>
                <th>Status</th>
                <th>Processed By</th>
                <th>Date</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="txn in transactions.data" :key="txn.id">
                <td class="ps-3">
                  <span class="font-monospace small fw-semibold">{{ txn.reference }}</span>
                </td>
                <td class="small">
                  <div class="fw-semibold">{{ txn.account?.account_number }}</div>
                  <div class="text-muted" style="font-size:.72rem">{{ txn.account?.customer?.name }}</div>
                </td>
                <td>
                  <span class="badge" :class="typeBadge(txn.type)">
                    <i :class="typeIcon(txn.type)" class="me-1"></i>{{ typeLabel(txn.type) }}
                  </span>
                </td>
                <td class="text-end fw-semibold small tabular-nums" :class="isCredit(txn) ? 'text-success' : 'text-danger'">
                  {{ isCredit(txn) ? '+' : '-' }}{{ formatCurrency(txn.amount, txn.currency) }}
                </td>
                <td><StatusBadge :status="txn.status" /></td>
                <td class="small text-muted">{{ txn.processed_by?.name ?? '—' }}</td>
                <td class="small text-muted">{{ formatDate(txn.created_at) }}</td>
                <td class="pe-3">
                  <Link :href="route('admin.transactions.show', txn.id)" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="transactions.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <span class="text-muted small">Showing {{ transactions.from }}–{{ transactions.to }} of {{ transactions.total }}</span>
          <div class="d-flex gap-1">
            <Link v-for="link in transactions.links" :key="link.label" :href="link.url ?? '#'"
              class="btn btn-sm" :class="link.active ? 'btn-primary' : 'btn-light'" :disabled="!link.url" v-html="link.label" />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
  transactions: { type: Object, required: true },
  filters:      { type: Object, default: () => ({}) },
  stats:        { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const form = reactive({
  search:    props.filters.search    ?? '',
  type:      props.filters.type      ?? '',
  status:    props.filters.status    ?? '',
  date_from: props.filters.date_from ?? '',
  date_to:   props.filters.date_to   ?? '',
});

const applyFilters = () => router.get(route('admin.transactions.index'), form, { preserveState: true });

const isCredit = (txn) => ['deposit', 'loan_disbursement'].includes(txn.type) ||
  (txn.type === 'reversal' && txn.balance_after > txn.balance_before) ||
  (txn.type === 'transfer' && txn.balance_after > txn.balance_before);

const typeLabel = (t) => ({ deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer', reversal: 'Reversal', loan_disbursement: 'Loan Disbursement', loan_repayment: 'Loan Repayment' }[t] ?? t);
const typeIcon  = (t) => ({ deposit: 'bi bi-arrow-down-circle', withdrawal: 'bi bi-arrow-up-circle', transfer: 'bi bi-arrow-left-right', reversal: 'bi bi-arrow-counterclockwise', loan_disbursement: 'bi bi-cash', loan_repayment: 'bi bi-cash-stack' }[t] ?? 'bi bi-circle');
const typeBadge = (t) => ({ deposit: 'bg-success-subtle text-success', withdrawal: 'bg-danger-subtle text-danger', transfer: 'bg-primary-subtle text-primary', reversal: 'bg-warning-subtle text-warning', loan_disbursement: 'bg-info-subtle text-info', loan_repayment: 'bg-info-subtle text-info' }[t] ?? 'bg-light text-muted');

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c }).format(v ?? 0);
const formatDate     = (d) => d ? new Date(d).toLocaleString('en-US', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
</script>
