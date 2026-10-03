<template>
  <AdminLayout
    title="Review Transaction"
    :subtitle="transaction.reference"
    :breadcrumbs="[
      { label: 'Approvals', href: route('admin.approvals.index') },
      { label: transaction.reference }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <button v-if="can_act" class="btn btn-success btn-sm" @click="showApprove = true">
          <i class="bi bi-check-lg me-1"></i>Approve
        </button>
        <button v-if="can_act" class="btn btn-danger btn-sm" @click="showReject = true">
          <i class="bi bi-x-lg me-1"></i>Reject
        </button>
        <button v-if="transaction.status === 'pending' && !transaction.escalated_at"
          class="btn btn-outline-danger btn-sm" :disabled="escalateForm.processing" @click="submitEscalate">
          <span v-if="escalateForm.processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="bi bi-flag-fill me-1"></i>Escalate
        </button>
        <Link :href="route('admin.approvals.index')" class="btn btn-light btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>

    <!-- Escalation banner -->
    <div v-if="transaction.escalated_at" class="alert alert-danger d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-flag-fill fs-5"></i>
      <div>
        <strong>Escalated</strong> — this approval has been waiting since
        {{ formatDateFull(transaction.created_at) }} and was flagged on {{ formatDateFull(transaction.escalated_at) }}.
        Requires urgent attention.
      </div>
    </div>
    <div v-else-if="is_stale" class="alert alert-warning d-flex align-items-center gap-2 mb-3">
      <i class="bi bi-hourglass-split fs-5"></i>
      <div>
        This approval has been pending for over 24 hours and will be auto-escalated shortly.
        You can escalate it now for immediate attention.
      </div>
    </div>

    <!-- Warning banner for high-value -->
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
      <div>
        <strong>High-value transaction</strong> — requires Level {{ transaction.approval_level_required }} authorization.
        Currently at Level {{ transaction.approval_level_reached }}.
        <span v-if="can_act">Your approval will advance this to Level {{ next_level }}.</span>
        <span v-else-if="is_own_transaction" class="text-danger ms-1">You initiated this transaction and cannot approve it.</span>
        <span v-else class="ms-1 text-muted">You do not have sufficient authority to act at Level {{ next_level }}.</span>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left: Transaction details -->
      <div class="col-lg-8">
        <!-- Amount card -->
        <div class="card shadow-sm mb-4 border-warning">
          <div class="card-body text-center py-4">
            <div class="text-muted small mb-1">{{ typeLabel(transaction.type) }}</div>
            <div class="display-5 fw-bold text-dark">{{ formatCurrency(transaction.amount, transaction.currency) }}</div>
            <div class="mt-2">
              <span class="badge bg-warning text-dark px-3 py-1">Pending Approval (L{{ transaction.approval_level_reached }}/L{{ transaction.approval_level_required }})</span>
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
                  <td class="pe-3 font-monospace fw-semibold">{{ transaction.reference }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Type</td>
                  <td class="pe-3"><span class="badge" :class="typeBadge(transaction.type)">{{ typeLabel(transaction.type) }}</span></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Amount</td>
                  <td class="pe-3 fw-semibold fs-6">{{ formatCurrency(transaction.amount, transaction.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Current Balance (snapshot)</td>
                  <td class="pe-3">{{ formatCurrency(transaction.balance_before, transaction.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Projected Balance</td>
                  <td class="pe-3" :class="transaction.balance_after < 0 ? 'text-danger' : ''">
                    {{ formatCurrency(transaction.balance_after, transaction.currency) }}
                  </td>
                </tr>
                <tr v-if="transaction.related_account">
                  <td class="text-muted ps-3">Destination Account</td>
                  <td class="pe-3">
                    <span class="font-monospace">{{ transaction.related_account?.account_number }}</span>
                    <span class="text-muted small ms-1">{{ transaction.related_account?.customer?.name }}</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Description</td>
                  <td class="pe-3">{{ transaction.description || '—' }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Initiated By</td>
                  <td class="pe-3">{{ transaction.processed_by?.name }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Date &amp; Time</td>
                  <td class="pe-3">{{ formatDateFull(transaction.created_at) }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>

        <!-- Approval History -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-1 text-primary"></i>Approval History</div>
          <div class="card-body">
            <div v-if="!transaction.approvals?.length" class="text-muted small text-center py-2">
              No approvals recorded yet.
            </div>
            <div v-else class="timeline">
              <div v-for="approval in transaction.approvals" :key="approval.id" class="d-flex gap-3 mb-3">
                <div class="flex-shrink-0">
                  <span class="badge rounded-circle p-2"
                    :class="approval.action === 'approved' ? 'bg-success' : 'bg-danger'"
                    style="width:2rem;height:2rem;display:flex;align-items:center;justify-content:center">
                    <i :class="approval.action === 'approved' ? 'bi bi-check-lg' : 'bi bi-x-lg'"></i>
                  </span>
                </div>
                <div>
                  <div class="fw-semibold small">
                    Level {{ approval.level }} — {{ approval.action === 'approved' ? 'Approved' : 'Rejected' }}
                  </div>
                  <div class="text-muted small">{{ approval.approver?.name }} · {{ formatDateFull(approval.acted_at) }}</div>
                  <div v-if="approval.notes" class="text-muted small mt-1 fst-italic">"{{ approval.notes }}"</div>
                </div>
              </div>
            </div>

            <!-- Pending levels -->
            <div v-for="l in pendingLevels" :key="l" class="d-flex gap-3 mb-3 opacity-50">
              <div class="flex-shrink-0">
                <span class="badge rounded-circle p-2 bg-secondary"
                  style="width:2rem;height:2rem;display:flex;align-items:center;justify-content:center">
                  {{ l }}
                </span>
              </div>
              <div>
                <div class="fw-semibold small">Level {{ l }} — Pending</div>
                <div class="text-muted small">{{ levelLabel(l) }} required</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Account -->
      <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-wallet2 me-1 text-primary"></i>Account</div>
          <div class="card-body">
            <div class="fw-bold font-monospace mb-1">{{ transaction.account?.account_number }}</div>
            <div class="text-muted small mb-2">{{ accountTypeLabel(transaction.account?.account_type) }}</div>
            <div class="small"><i class="bi bi-person me-1 text-muted"></i>{{ transaction.account?.customer?.name }}</div>
            <div class="text-success small fw-semibold mt-1">Balance: {{ formatCurrency(transaction.account?.balance, transaction.currency) }}</div>
            <Link :href="route('admin.accounts.show', transaction.account?.id)" class="btn btn-sm btn-outline-primary mt-2 w-100">
              View Account
            </Link>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-shield-check me-1 text-primary"></i>Authorization Status</div>
          <div class="card-body small">
            <div v-for="l in transaction.approval_level_required" :key="l" class="d-flex align-items-center gap-2 mb-2">
              <i class="bi" :class="l <= transaction.approval_level_reached ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'"></i>
              <span :class="l <= transaction.approval_level_reached ? '' : 'text-muted'">
                Level {{ l }} — {{ levelLabel(l) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApprove" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-check-circle me-2 text-success"></i>Approve Transaction</h5>
            <button type="button" class="btn-close" @click="showApprove = false"></button>
          </div>
          <form @submit.prevent="submitApprove">
            <div class="modal-body">
              <div class="alert alert-success small">
                You are approving <strong>{{ transaction.reference }}</strong> at Level {{ next_level }}.
                <span v-if="next_level >= transaction.approval_level_required">
                  This is the final approval — the transaction will be <strong>executed immediately</strong>.
                </span>
                <span v-else>
                  The transaction will proceed to Level {{ next_level + 1 }} for further authorization.
                </span>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes <span class="text-muted small">(optional)</span></label>
                <textarea v-model="approveForm.notes" class="form-control" rows="3"
                  placeholder="Add optional authorization notes…"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showApprove = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="approveForm.processing">
                <span v-if="approveForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Confirm Approval
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showReject" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Reject Transaction</h5>
            <button type="button" class="btn-close" @click="showReject = false"></button>
          </div>
          <form @submit.prevent="submitReject">
            <div class="modal-body">
              <div class="alert alert-danger small">
                Rejecting <strong>{{ transaction.reference }}</strong> will permanently cancel it.
                The balance will not be affected.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-muted small">(optional)</span></label>
                <textarea v-model="rejectForm.notes" class="form-control" rows="3"
                  :class="rejectForm.errors.notes ? 'is-invalid' : ''"
                  placeholder="Explain why this transaction is being rejected…"></textarea>
                <div v-if="rejectForm.errors.notes" class="invalid-feedback">{{ rejectForm.errors.notes }}</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReject = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="rejectForm.processing">
                <span v-if="rejectForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Confirm Rejection
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  transaction:        { type: Object, required: true },
  my_level:           { type: Number, required: true },
  can_act:            { type: Boolean, required: true },
  is_own_transaction: { type: Boolean, default: false },
  next_level:         { type: Number, required: true },
  is_stale:           { type: Boolean, default: false },
});

const page        = usePage();
const showApprove = ref(false);
const showReject  = ref(false);

const approveForm  = useForm({ notes: '' });
const rejectForm   = useForm({ notes: '' });
const escalateForm = useForm({});

const pendingLevels = computed(() => {
  const reached = props.transaction.approval_level_reached;
  const required = props.transaction.approval_level_required;
  return Array.from({ length: required - reached }, (_, i) => reached + i + 1);
});

const submitApprove = () => {
  approveForm.post(route('admin.approvals.approve', props.transaction.id), {
    onSuccess: () => { showApprove.value = false; },
  });
};

const submitReject = () => {
  rejectForm.post(route('admin.approvals.reject', props.transaction.id), {
    onSuccess: () => { showReject.value = false; },
  });
};

const submitEscalate = () => {
  escalateForm.post(route('admin.approvals.escalate', props.transaction.id));
};

const levelLabel       = (l) => ({ 1: 'Branch Manager', 2: 'Compliance Officer', 3: 'Super Admin' }[l] ?? `Level ${l}`);
const typeLabel        = (t) => ({ deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer', reversal: 'Reversal' }[t] ?? t);
const typeBadge        = (t) => ({ deposit: 'bg-success-subtle text-success', withdrawal: 'bg-danger-subtle text-danger', transfer: 'bg-primary-subtle text-primary' }[t] ?? 'bg-light text-muted');
const accountTypeLabel = (t) => ({ savings: 'Savings Account', current: 'Current Account', fixed_deposit: 'Fixed Deposit' }[t] ?? t);

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);
const formatDateFull = (d) => d ? new Date(d).toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';
</script>
