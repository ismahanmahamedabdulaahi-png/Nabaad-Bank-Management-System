<template>
  <AdminLayout
    :title="account.account_number"
    :subtitle="typeLabel(account.account_type) + ' Account'"
    :breadcrumbs="[{ label: 'Accounts', href: route('admin.accounts.index') }, { label: account.account_number }]"
  >
    <template #actions>
      <div class="d-flex gap-2 flex-wrap">
        <!-- Freeze -->
        <button
          v-if="can('accounts.freeze') && (account.status === 'active' || account.status === 'dormant')"
          class="btn btn-warning btn-sm"
          @click="showFreezeModal = true"
        >
          <i class="bi bi-lock me-1"></i> Freeze
        </button>

        <!-- Unfreeze -->
        <button
          v-if="can('accounts.freeze') && account.status === 'frozen'"
          class="btn btn-success btn-sm"
          @click="confirmUnfreeze"
        >
          <i class="bi bi-unlock me-1"></i> Unfreeze
        </button>

        <!-- Reactivate (dormant) -->
        <button
          v-if="can('accounts.reactivate') && account.status === 'dormant'"
          class="btn btn-success btn-sm"
          @click="confirmReactivate"
        >
          <i class="bi bi-play-circle me-1"></i> Reactivate
        </button>

        <!-- Close -->
        <button
          v-if="can('accounts.close') && !['closed','matured'].includes(account.status)"
          class="btn btn-danger btn-sm"
          @click="showCloseModal = true"
        >
          <i class="bi bi-x-circle me-1"></i> Close Account
        </button>

        <!-- View Customer -->
        <Link
          :href="route('admin.customers.show', account.customer_id)"
          class="btn btn-outline-secondary btn-sm"
        >
          <i class="bi bi-person me-1"></i> Customer
        </Link>
      </div>
    </template>

    <div class="row g-4">

      <!-- ── Left: Account Info ─────────────────────────────────────────── -->
      <div class="col-lg-4">

        <!-- Balance card -->
        <div class="card shadow-sm mb-4 text-center p-4" :class="statusCardClass(account.status)">
          <div class="text-muted small mb-1">{{ typeLabel(account.account_type) }}</div>
          <div class="fw-bold mb-1" style="font-size:1.75rem">
            {{ formatCurrency(account.balance, account.currency) }}
          </div>
          <div class="font-monospace text-muted small mb-3">{{ account.account_number }}</div>
          <div><StatusBadge :status="account.status" /></div>
        </div>

        <!-- Details -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-info-circle me-1 text-primary"></i> Account Details
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <td class="text-muted ps-3">Customer</td>
                  <td class="fw-semibold pe-3">
                    <Link :href="route('admin.customers.show', account.customer_id)" class="text-decoration-none">
                      {{ account.customer?.name }}
                    </Link>
                    <div class="text-muted" style="font-size:.72rem">{{ account.customer?.customer_number }}</div>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Branch</td>
                  <td class="pe-3">{{ account.branch?.name }} ({{ account.branch?.code }})</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Currency</td>
                  <td class="pe-3">{{ account.currency }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Interest Rate</td>
                  <td class="pe-3">{{ account.interest_rate }}%</td>
                </tr>
                <tr v-if="account.minimum_balance > 0">
                  <td class="text-muted ps-3">Min Balance</td>
                  <td class="pe-3">{{ formatCurrency(account.minimum_balance, account.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Opened</td>
                  <td class="pe-3">{{ formatDate(account.opening_date) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Opened By</td>
                  <td class="pe-3">{{ account.opened_by?.name ?? '—' }}</td>
                </tr>
                <tr v-if="account.last_activity_date">
                  <td class="text-muted ps-3">Last Activity</td>
                  <td class="pe-3">{{ formatDate(account.last_activity_date) }}</td>
                </tr>
                <tr v-if="account.closed_at">
                  <td class="text-muted ps-3">Closed</td>
                  <td class="pe-3 text-danger">{{ formatDate(account.closed_at) }}</td>
                </tr>
                <tr v-if="account.frozen_at">
                  <td class="text-muted ps-3">Frozen</td>
                  <td class="pe-3 text-warning">{{ formatDate(account.frozen_at) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Fixed Deposit card -->
        <div v-if="account.account_type === 'fixed_deposit'" class="card shadow-sm mb-4 border-warning">
          <div class="card-header bg-warning-subtle fw-semibold small">
            <i class="bi bi-calendar-lock me-1 text-warning"></i> Fixed Deposit Terms
          </div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <td class="text-muted ps-3">Tenure</td>
                  <td class="pe-3">{{ account.fd_tenure_months }} months</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Maturity Date</td>
                  <td class="pe-3" :class="isMaturityDue ? 'text-danger fw-semibold' : ''">
                    {{ formatDate(account.fd_maturity_date) }}
                    <span v-if="isMaturityDue" class="badge bg-danger ms-1">Due!</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">On Maturity</td>
                  <td class="pe-3 text-capitalize">{{ maturityActionLabel(account.fd_maturity_action) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="account.notes" class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-chat-left-text me-1 text-primary"></i> Notes
          </div>
          <div class="card-body small text-muted">{{ account.notes }}</div>
        </div>

      </div>

      <!-- ── Right: Transactions ────────────────────────────────────────── -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
            <span class="fw-semibold"><i class="bi bi-arrow-left-right me-1 text-primary"></i> Transactions</span>
            <div class="d-flex align-items-center gap-2">
              <a v-if="can('reports.view')"
                 :href="route('admin.reports.statement', account.id)"
                 target="_blank"
                 class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-file-earmark-pdf me-1"></i>Statement
              </a>
              <Link
                v-if="$page.props.auth.permissions.includes('transactions.deposit') && account.status === 'active'"
                :href="route('admin.transactions.deposit.form', { account_id: account.id })"
                class="btn btn-sm btn-outline-success"
              ><i class="bi bi-plus-lg me-1"></i>Deposit</Link>
              <Link
                v-if="$page.props.auth.permissions.includes('transactions.withdraw') && account.status === 'active'"
                :href="route('admin.transactions.withdrawal.form', { account_id: account.id })"
                class="btn btn-sm btn-outline-danger"
              ><i class="bi bi-dash-lg me-1"></i>Withdraw</Link>
            </div>
          </div>

          <!-- Filter bar -->
          <div class="border-bottom px-3 py-2 bg-light d-flex flex-wrap gap-2 align-items-center">
            <input v-model="filterForm.date_from" type="date" class="form-control form-control-sm" style="width:140px">
            <input v-model="filterForm.date_to"   type="date" class="form-control form-control-sm" style="width:140px">
            <select v-model="filterForm.type" class="form-select form-select-sm" style="width:145px">
              <option value="">All Types</option>
              <option value="deposit">Deposit</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="transfer">Transfer</option>
              <option value="loan_disbursement">Loan Disbursement</option>
              <option value="loan_repayment">Loan Repayment</option>
              <option value="reversal">Reversal</option>
            </select>
            <button class="btn btn-sm btn-primary" @click="applyFilters">
              <i class="bi bi-search me-1"></i>Filter
            </button>
            <button v-if="hasActiveFilters" class="btn btn-sm btn-outline-secondary" @click="clearFilters">
              <i class="bi bi-x"></i>
            </button>
            <span class="text-muted small ms-auto">{{ recentTransactions.length }} row(s)</span>
          </div>

          <div class="card-body p-0">
            <div v-if="!recentTransactions.length" class="text-center text-muted py-5">
              <i class="bi bi-receipt d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
              No transactions yet.
            </div>
            <div class="table-responsive" v-else>
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="txn in recentTransactions" :key="txn.id" style="cursor:pointer" @click="$inertia.visit(route('admin.transactions.show', txn.id))">
                    <td class="ps-3 small text-muted">{{ formatDate(txn.created_at) }}</td>
                    <td>
                      <span class="badge" :class="txn.direction === 'credit' ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'">
                        {{ typeLabel(txn.type) }}
                      </span>
                    </td>
                    <td class="small text-muted">{{ txn.reference }}</td>
                    <td class="text-end small fw-semibold" :class="txn.direction === 'credit' ? 'text-success' : 'text-danger'">
                      {{ txn.direction === 'credit' ? '+' : '-' }}{{ formatCurrency(txn.amount, account.currency) }}
                    </td>
                    <td class="text-end small pe-3">{{ formatCurrency(txn.balance_after, account.currency) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Freeze Modal ─────────────────────────────────────────────────── -->
    <div v-if="showFreezeModal" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-lock me-1 text-warning"></i> Freeze Account</h5>
            <button type="button" class="btn-close" @click="showFreezeModal = false"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small">Freezing will block all deposits and withdrawals. Provide a reason below.</p>
            <textarea v-model="freezeReason" class="form-control" rows="3" placeholder="Reason (optional)…"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="showFreezeModal = false">Cancel</button>
            <button class="btn btn-warning" @click="doFreeze" :disabled="processing">
              <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
              Freeze Account
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Close Modal ──────────────────────────────────────────────────── -->
    <div v-if="showCloseModal" class="modal d-block" tabindex="-1" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header border-danger">
            <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-1"></i> Close Account</h5>
            <button type="button" class="btn-close" @click="showCloseModal = false"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-warning small">
              <i class="bi bi-exclamation-triangle me-1"></i>
              This action is <strong>irreversible</strong>. The account must have a zero balance before closing.
            </div>
            <textarea v-model="closeReason" class="form-control" rows="3" placeholder="Reason (optional)…"></textarea>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="showCloseModal = false">Cancel</button>
            <button class="btn btn-danger" @click="doClose" :disabled="processing">
              <span v-if="processing" class="spinner-border spinner-border-sm me-1"></span>
              Close Account
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Unfreeze confirm -->
    <ConfirmModal
      id="unfreezeModal"
      title="Unfreeze Account"
      :message="`Unfreeze account ${account.account_number}? It will return to active status.`"
      variant="success"
      icon="bi-unlock"
      confirm-label="Unfreeze"
      @confirmed="doUnfreeze"
    />

    <!-- Reactivate confirm -->
    <ConfirmModal
      id="reactivateModal"
      title="Reactivate Account"
      :message="`Reactivate dormant account ${account.account_number}?`"
      variant="success"
      icon="bi-play-circle"
      confirm-label="Reactivate"
      @confirmed="doReactivate"
    />

  </AdminLayout>
</template>

<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import StatusBadge  from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
  account:            { type: Object, required: true },
  recentTransactions: { type: Array,  default: () => [] },
  filters:            { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (perm) => page.props.auth.permissions.includes(perm);

const filterForm = reactive({
  date_from: props.filters.date_from ?? '',
  date_to:   props.filters.date_to   ?? '',
  type:      props.filters.type      ?? '',
});

const hasActiveFilters = computed(() =>
  !!(filterForm.date_from || filterForm.date_to || filterForm.type)
);

const applyFilters = () => {
  router.get(route('admin.accounts.show', props.account.id), {
    date_from: filterForm.date_from || undefined,
    date_to:   filterForm.date_to   || undefined,
    type:      filterForm.type      || undefined,
  }, { preserveState: true, preserveScroll: true });
};

const clearFilters = () => {
  filterForm.date_from = '';
  filterForm.date_to   = '';
  filterForm.type      = '';
  router.get(route('admin.accounts.show', props.account.id), {}, { preserveState: true, preserveScroll: true });
};

const processing     = ref(false);
const showFreezeModal = ref(false);
const showCloseModal  = ref(false);
const freezeReason    = ref('');
const closeReason     = ref('');

const isMaturityDue = computed(() => {
  if (props.account.account_type !== 'fixed_deposit' || !props.account.fd_maturity_date) return false;
  return new Date(props.account.fd_maturity_date) <= new Date() && props.account.status !== 'matured';
});

const confirmUnfreeze  = () => new Modal(document.getElementById('unfreezeModal')).show();
const confirmReactivate = () => new Modal(document.getElementById('reactivateModal')).show();

const doFreeze = () => {
  processing.value = true;
  router.post(route('admin.accounts.freeze', props.account.id), { reason: freezeReason.value }, {
    onFinish: () => { processing.value = false; showFreezeModal.value = false; },
  });
};

const doUnfreeze = () => router.post(route('admin.accounts.unfreeze', props.account.id));

const doClose = () => {
  processing.value = true;
  router.post(route('admin.accounts.close', props.account.id), { reason: closeReason.value }, {
    onFinish: () => { processing.value = false; showCloseModal.value = false; },
  });
};

const doReactivate = () => router.post(route('admin.accounts.reactivate', props.account.id));

const typeLabel         = (t) => ({ savings: 'Savings', current: 'Current', fixed_deposit: 'Fixed Deposit', deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer', reversal: 'Reversal', loan_disbursement: 'Loan', loan_repayment: 'Repayment' }[t] ?? t);
const maturityActionLabel = (a) => ({ renew: 'Renew (Roll Over)', transfer_to_savings: 'Transfer to Savings', pending: 'Pending Decision' }[a] ?? a);
const statusCardClass   = (s) => ({ frozen: 'border-warning', closed: 'border-secondary bg-light', matured: 'border-info' }[s] ?? '');

const formatCurrency = (v, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(v ?? 0);
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }) : '—';
</script>
