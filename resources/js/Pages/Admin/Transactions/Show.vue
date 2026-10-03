<template>
  <AdminLayout
    title="Transaction Details"
    :subtitle="txn.reference"
    :breadcrumbs="[
      { label: 'Transactions', href: route('admin.transactions.index') },
      { label: txn.reference }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <a v-if="txn.status === 'completed' && can('reports.view')"
           :href="route('admin.reports.receipt', txn.id)"
           target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer me-1"></i>Receipt
        </a>
        <button
          v-if="can('transactions.reverse') && txn.status === 'completed' && !txn.reversal_of && !txn.reversal"
          class="btn btn-warning btn-sm"
          @click="showReversal = true"
        >
          <i class="bi bi-arrow-counterclockwise me-1"></i>Reverse
        </button>
        <button v-if="can('compliance.manage')" class="btn btn-outline-danger btn-sm" @click="showFlag = true">
          <i class="bi bi-shield-exclamation me-1"></i>Flag as Suspicious
        </button>
        <Link :href="route('admin.transactions.index')" class="btn btn-light btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>

    <div class="row g-4">
      <!-- Main Info -->
      <div class="col-lg-8">
        <!-- Amount card -->
        <div class="card shadow-sm mb-4 border-0" :class="isCredit ? 'bg-success-subtle' : 'bg-danger-subtle'">
          <div class="card-body text-center py-4">
            <div class="text-muted small mb-1">{{ typeLabel(txn.type) }}</div>
            <div class="display-5 fw-bold" :class="isCredit ? 'text-success' : 'text-danger'">
              {{ isCredit ? '+' : '-' }}{{ formatCurrency(txn.amount, txn.currency) }}
            </div>
            <div class="mt-2">
              <StatusBadge :status="txn.status" />
            </div>
          </div>
        </div>

        <!-- Details -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1 text-primary"></i>Transaction Details</div>
          <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <td class="text-muted ps-3" style="width:40%">Reference</td>
                  <td class="pe-3 font-monospace fw-semibold">{{ txn.reference }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Type</td>
                  <td class="pe-3">
                    <span class="badge" :class="typeBadge(txn.type)">{{ typeLabel(txn.type) }}</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Amount</td>
                  <td class="pe-3 fw-semibold">{{ formatCurrency(txn.amount, txn.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Balance Before</td>
                  <td class="pe-3">{{ formatCurrency(txn.balance_before, txn.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Balance After</td>
                  <td class="pe-3">{{ formatCurrency(txn.balance_after, txn.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Description</td>
                  <td class="pe-3">{{ txn.description || '—' }}</td>
                </tr>
                <tr v-if="txn.notes">
                  <td class="text-muted ps-3">Notes</td>
                  <td class="pe-3 small text-muted">{{ txn.notes }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Date &amp; Time</td>
                  <td class="pe-3">{{ formatDateFull(txn.created_at) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Processed By</td>
                  <td class="pe-3">{{ txn.processed_by?.name ?? '—' }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>

        <!-- Transfer counterpart -->
        <div v-if="txn.type === 'transfer' && txn.related_account" class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-left-right me-1 text-primary"></i>Counterpart Account</div>
          <div class="card-body">
            <div class="d-flex align-items-center gap-3">
              <i class="bi bi-wallet2 fs-3 text-muted"></i>
              <div>
                <div class="fw-semibold font-monospace">{{ txn.related_account?.account_number }}</div>
                <div class="small text-muted">{{ txn.related_account?.customer?.name }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Reversal info -->
        <div v-if="txn.reversal_of" class="alert alert-warning">
          <i class="bi bi-arrow-counterclockwise me-2"></i>
          This is a <strong>reversal</strong> of transaction
          <Link :href="route('admin.transactions.show', txn.reversal_of.id)" class="fw-semibold">
            {{ txn.reversal_of.reference }}
          </Link>.
        </div>
        <div v-if="txn.reversal" class="alert alert-secondary">
          <i class="bi bi-check-circle me-2"></i>
          This transaction was <strong>reversed</strong>. See
          <Link :href="route('admin.transactions.show', txn.reversal.id)" class="fw-semibold">
            {{ txn.reversal.reference }}
          </Link>.
        </div>

        <!-- Approval chain -->
        <div v-if="txn.requires_approval && txn.approvals?.length" class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-check2-all me-1 text-success"></i>Approval Chain
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Level</th>
                  <th>Approver</th>
                  <th>Action</th>
                  <th>Date</th>
                  <th>Notes</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="a in txn.approvals" :key="a.id">
                  <td class="ps-3 fw-semibold">L{{ a.level }}</td>
                  <td class="small">{{ a.approver?.name ?? '—' }}</td>
                  <td>
                    <span class="badge" :class="a.action === 'approved' ? 'bg-success' : 'bg-danger'">
                      {{ a.action }}
                    </span>
                  </td>
                  <td class="small text-muted">{{ formatDateFull(a.acted_at ?? a.created_at) }}</td>
                  <td class="small text-muted">{{ a.notes || '—' }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
        <div v-else-if="txn.requires_approval && txn.status === 'pending'" class="alert alert-warning small py-2 mb-4">
          <i class="bi bi-hourglass-split me-1"></i>
          Awaiting approval — no approvers have acted yet.
          Level required: <strong>{{ txn.approval_level_reached + 1 }}</strong>.
        </div>
      </div>

      <!-- Sidebar -->
      <div class="col-lg-4">
        <!-- Account -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-wallet2 me-1 text-primary"></i>Account</div>
          <div class="card-body">
            <div class="fw-bold font-monospace mb-1">{{ txn.account?.account_number }}</div>
            <div class="text-muted small mb-2">{{ accountTypeLabel(txn.account?.account_type) }}</div>
            <div class="d-flex align-items-center gap-2 mb-1">
              <i class="bi bi-person text-muted"></i>
              <span class="small">{{ txn.account?.customer?.name }}</span>
            </div>
            <Link :href="route('admin.accounts.show', txn.account?.id)" class="btn btn-sm btn-outline-primary mt-2 w-100">
              View Account
            </Link>
          </div>
        </div>

        <!-- Status timeline -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1 text-primary"></i>Timeline</div>
          <div class="card-body small">
            <div class="d-flex gap-2 mb-2">
              <i class="bi bi-circle-fill text-success" style="font-size:.6rem;margin-top:.3rem"></i>
              <div>
                <div class="fw-semibold">Created</div>
                <div class="text-muted">{{ formatDateFull(txn.created_at) }}</div>
              </div>
            </div>
            <div class="d-flex gap-2 mb-2">
              <i class="bi bi-circle-fill" :class="txn.status === 'completed' ? 'text-success' : 'text-muted'" style="font-size:.6rem;margin-top:.3rem"></i>
              <div>
                <div class="fw-semibold">{{ txn.status === 'completed' ? 'Completed' : 'Pending' }}</div>
                <div class="text-muted">{{ txn.processed_by?.name ?? 'Awaiting' }}</div>
              </div>
            </div>
            <div v-if="txn.reversal" class="d-flex gap-2">
              <i class="bi bi-circle-fill text-warning" style="font-size:.6rem;margin-top:.3rem"></i>
              <div>
                <div class="fw-semibold">Reversed</div>
                <div class="text-muted">{{ txn.reversal.processed_by?.name ?? '—' }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Reversal Modal -->
    <div v-if="showReversal" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-arrow-counterclockwise me-2 text-warning"></i>Reverse Transaction</h5>
            <button type="button" class="btn-close" @click="showReversal = false"></button>
          </div>
          <form @submit.prevent="submitReversal">
            <div class="modal-body">
              <div class="alert alert-warning small">
                This will create a counter-transaction to reverse <strong>{{ txn.reference }}</strong>
                ({{ formatCurrency(txn.amount, txn.currency) }}). This action cannot be undone.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea v-model="reversalForm.reason" class="form-control" rows="3"
                  :class="reversalForm.errors.reason ? 'is-invalid' : ''"
                  placeholder="Explain why this transaction is being reversed…"></textarea>
                <div v-if="reversalForm.errors.reason" class="invalid-feedback">{{ reversalForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReversal = false">Cancel</button>
              <button type="submit" class="btn btn-warning" :disabled="reversalForm.processing">
                <span v-if="reversalForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Confirm Reversal
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Flag as Suspicious Modal -->
    <div v-if="showFlag" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Flag as Suspicious</h5>
            <button type="button" class="btn-close" @click="showFlag = false"></button>
          </div>
          <form @submit.prevent="submitFlag">
            <div class="modal-body">
              <div class="alert alert-secondary small">
                Opens an internal compliance case for <strong>{{ txn.account?.customer?.name }}</strong> referencing
                <strong>{{ txn.reference }}</strong>. The customer is never notified.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Severity</label>
                <select v-model="flagForm.severity" class="form-select">
                  <option value="low">Low</option>
                  <option value="medium">Medium</option>
                  <option value="high">High</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes <span class="text-danger">*</span></label>
                <textarea v-model="flagForm.notes" class="form-control" rows="3"
                  :class="flagForm.errors.notes ? 'is-invalid' : ''"
                  placeholder="Why is this transaction suspicious?"></textarea>
                <div v-if="flagForm.errors.notes" class="invalid-feedback">{{ flagForm.errors.notes }}</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showFlag = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="flagForm.processing">
                <span v-if="flagForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Flag for Review
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
  transaction: { type: Object, required: true },
});

const page        = usePage();
const txn         = props.transaction;
const showReversal = ref(false);
const showFlag      = ref(false);
const can         = (p) => page.props.auth.permissions.includes(p);

const isCredit = txn.direction === 'credit';

const reversalForm = useForm({ reason: '' });
const submitReversal = () => {
  reversalForm.post(route('admin.transactions.reverse', txn.id), {
    onSuccess: () => { showReversal.value = false; },
  });
};

const flagForm = useForm({ customer_id: txn.account?.customer_id, transaction_id: txn.id, severity: 'medium', notes: '' });
const submitFlag = () => {
  flagForm.post(route('admin.compliance.flag'), {
    onSuccess: () => { showFlag.value = false; },
  });
};

const typeLabel     = (t) => ({ deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer', reversal: 'Reversal', loan_disbursement: 'Loan Disbursement', loan_repayment: 'Loan Repayment' }[t] ?? t);
const typeBadge     = (t) => ({ deposit: 'bg-success-subtle text-success', withdrawal: 'bg-danger-subtle text-danger', transfer: 'bg-primary-subtle text-primary', reversal: 'bg-warning-subtle text-warning' }[t] ?? 'bg-light text-muted');
const accountTypeLabel = (t) => ({ savings: 'Savings Account', current: 'Current Account', fixed_deposit: 'Fixed Deposit' }[t] ?? t);

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);
const formatDateFull = (d) => d ? new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—';
</script>
