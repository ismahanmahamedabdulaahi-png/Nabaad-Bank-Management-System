<template>
  <AdminLayout
    :title="loan.loan_number"
    :subtitle="`${loan.customer?.name} — ${loan.account?.account_number}`"
    :breadcrumbs="[
      { label: 'Loans', href: route('admin.loans.index') },
      { label: loan.loan_number }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2 flex-wrap">
        <!-- Workflow actions by status -->
        <button v-if="loan.status === 'pending' && can('loans.review')"
          class="btn btn-info btn-sm text-white" @click="showReview = true">
          <i class="bi bi-eye me-1"></i>Send for Review
        </button>
        <button v-if="['pending','under_review'].includes(loan.status) && can('loans.approve')"
          class="btn btn-success btn-sm" @click="showApprove = true">
          <i class="bi bi-check-circle me-1"></i>Approve
        </button>
        <button v-if="['pending','under_review','approved'].includes(loan.status) && can('loans.approve')"
          class="btn btn-outline-danger btn-sm" @click="showReject = true">
          <i class="bi bi-x-circle me-1"></i>Reject
        </button>
        <button v-if="loan.status === 'approved' && can('loans.disburse')"
          class="btn btn-primary btn-sm" @click="showDisburse = true">
          <i class="bi bi-bank me-1"></i>Disburse
        </button>
        <button v-if="['active','overdue'].includes(loan.status) && can('loans.close')"
          class="btn btn-success btn-sm" @click="openRepay">
          <i class="bi bi-cash-coin me-1"></i>Make Repayment
        </button>
        <a v-if="loan.disbursed_at && can('reports.view')"
           :href="route('admin.reports.loan-letter', loan.id)"
           target="_blank" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-file-earmark-text me-1"></i>Letter
        </a>
        <Link :href="route('admin.loans.index')" class="btn btn-light btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>

    <div class="row g-4">
      <!-- Left column -->
      <div class="col-lg-4">
        <!-- Status card -->
        <div class="card shadow-sm mb-3" :class="loan.status === 'overdue' ? 'border-danger' : ''">
          <div class="card-body text-center py-4">
            <span class="badge px-3 py-2 fs-6 mb-2" :class="statusBadge(loan.status)">{{ statusLabel(loan.status) }}</span>
            <div class="display-6 fw-bold text-primary">{{ fmt(loan.outstanding_balance) }}</div>
            <div class="text-muted small">Outstanding Balance</div>
            <div class="row g-2 mt-3 text-center">
              <div class="col-4">
                <div class="small text-muted">Principal</div>
                <div class="fw-semibold small">{{ fmt(loan.amount) }}</div>
              </div>
              <div class="col-4">
                <div class="small text-muted">Paid</div>
                <div class="fw-semibold small text-success">{{ fmt(loan.total_paid) }}</div>
              </div>
              <div class="col-4">
                <div class="small text-muted">EMI</div>
                <div class="fw-semibold small text-primary">{{ fmt(loan.monthly_emi) }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Loan Details -->
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i>Loan Details</div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr><td class="text-muted ps-3">Rate</td><td>{{ loan.interest_rate }}% p.a.</td></tr>
                <tr><td class="text-muted ps-3">Tenure</td><td>{{ loan.tenure_months }} months</td></tr>
                <tr><td class="text-muted ps-3">Total Payable</td><td>{{ fmt(loan.total_payable) }}</td></tr>
                <tr><td class="text-muted ps-3">Total Interest</td><td class="text-warning">{{ fmt(loan.total_interest) }}</td></tr>
                <tr><td class="text-muted ps-3">Grace Period</td><td>{{ loan.grace_period_days }} days</td></tr>
                <tr><td class="text-muted ps-3">Penalty Rate</td><td>{{ loan.penalty_rate }}% /month</td></tr>
                <tr><td class="text-muted ps-3">1st Repayment</td><td>{{ fmtDate(loan.first_repayment_date) }}</td></tr>
                <tr v-if="loan.purpose"><td class="text-muted ps-3">Purpose</td><td>{{ loan.purpose }}</td></tr>
                <tr v-if="loan.collateral"><td class="text-muted ps-3">Collateral</td><td>{{ loan.collateral }}</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Workflow Timeline -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-diagram-3 me-1 text-primary"></i>Workflow</div>
          <div class="card-body">
            <div v-for="step in workflow" :key="step.label" class="d-flex gap-2 mb-2 align-items-start">
              <i class="bi mt-1" :class="step.done ? 'bi-check-circle-fill text-success' : 'bi-circle text-muted'" style="font-size:.9rem"></i>
              <div>
                <div class="small fw-semibold" :class="step.done ? '' : 'text-muted'">{{ step.label }}</div>
                <div v-if="step.by" class="text-muted" style="font-size:.7rem">{{ step.by }} · {{ step.at }}</div>
                <div v-if="step.note" class="text-danger" style="font-size:.7rem">{{ step.note }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div class="col-lg-8">
        <!-- Next Installment Alert -->
        <div v-if="next_installment && ['active','overdue'].includes(loan.status)" class="alert mb-3"
          :class="next_installment.status === 'overdue' ? 'alert-danger' : isNearDue(next_installment.due_date) ? 'alert-warning' : 'alert-info'">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <i class="bi bi-calendar-event me-1"></i>
              <strong>Installment #{{ next_installment.installment_number }}</strong> due
              <strong>{{ fmtDate(next_installment.due_date) }}</strong>
              <span v-if="next_installment.status === 'overdue'" class="ms-1">(OVERDUE)</span>
            </div>
            <div class="fw-bold">{{ fmt(next_installment.total_due) }}</div>
          </div>
        </div>

        <!-- Amortization Summary -->
        <div v-if="loan.repayment_schedules?.length" class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-pie-chart me-1 text-info"></i>Amortization Summary
          </div>
          <div class="card-body py-3">
            <div class="row g-3 text-center">
              <div class="col-6 col-md-3">
                <div class="text-muted small">Total Instalments</div>
                <div class="fw-bold fs-5">{{ loan.repayment_schedules.length }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted small">Total Principal</div>
                <div class="fw-bold fs-6 text-primary">{{ fmt(amort.totalPrincipal) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted small">Total Interest</div>
                <div class="fw-bold fs-6 text-warning">{{ fmt(amort.totalInterest) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted small">Interest Share</div>
                <div class="fw-bold fs-6">{{ amort.interestPct }}%</div>
              </div>
            </div>
            <div class="mt-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-primary">Principal {{ amort.interestPct < 100 ? (100 - amort.interestPct) + '%' : '' }}</span>
                <span class="text-warning">Interest {{ amort.interestPct }}%</span>
              </div>
              <div class="progress" style="height:10px;border-radius:5px">
                <div class="progress-bar bg-primary" :style="{ width: (100 - amort.interestPct) + '%' }"></div>
                <div class="progress-bar bg-warning" :style="{ width: amort.interestPct + '%' }"></div>
              </div>
            </div>
            <div class="row g-3 mt-2 text-center border-top pt-3">
              <div class="col-4">
                <div class="text-muted small">Paid</div>
                <div class="fw-bold text-success">{{ amort.paid }}</div>
              </div>
              <div class="col-4">
                <div class="text-muted small">Pending</div>
                <div class="fw-bold text-secondary">{{ amort.pending }}</div>
              </div>
              <div class="col-4">
                <div class="text-muted small">Overdue</div>
                <div class="fw-bold text-danger">{{ amort.overdue }}</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Repayment Schedule -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-calendar3 me-1 text-primary"></i>Repayment Schedule
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">#</th>
                    <th>Due Date</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th>
                    <th class="text-end">Total Due</th>
                    <th class="text-end">Paid</th>
                    <th class="text-center">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="s in loan.repayment_schedules" :key="s.id"
                    :class="s.status === 'paid' ? 'table-success' : s.status === 'overdue' ? 'table-danger' : ''">
                    <td class="ps-3 fw-semibold">{{ s.installment_number }}</td>
                    <td>
                      {{ fmtDate(s.due_date) }}
                      <span v-if="s.grace_deadline" class="d-block" style="font-size:.7rem"
                            :class="isGracePast(s.grace_deadline) && s.status !== 'paid' ? 'text-danger fw-semibold' : 'text-muted'">
                        Grace: {{ fmtDate(s.grace_deadline) }}
                        <span v-if="isGracePast(s.grace_deadline) && s.status !== 'paid'"
                              class="badge bg-danger ms-1" style="font-size:.6rem">EXPIRED</span>
                      </span>
                    </td>
                    <td class="text-end">{{ fmt(s.principal_amount) }}</td>
                    <td class="text-end text-warning">{{ fmt(s.interest_amount) }}</td>
                    <td class="text-end text-danger">{{ s.penalty_amount > 0 ? fmt(s.penalty_amount) : '—' }}</td>
                    <td class="text-end fw-semibold">{{ fmt(s.total_due) }}</td>
                    <td class="text-end text-success">{{ s.amount_paid > 0 ? fmt(s.amount_paid) : '—' }}</td>
                    <td class="text-center">
                      <span class="badge" :class="scheduleBadge(s.status)">{{ scheduleLabel(s.status) }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Payment History -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-receipt me-1 text-success"></i>Payment History ({{ loan.payments?.length ?? 0 }})
          </div>
          <div class="card-body p-0">
            <div v-if="!loan.payments?.length" class="text-center text-muted py-4 small">No payments recorded.</div>
            <div class="table-responsive" v-else>
              <table class="table table-sm align-middle mb-0" style="font-size:.82rem">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Date</th>
                    <th class="text-center">Installment</th>
                    <th class="text-end">Amount Paid</th>
                    <th class="text-end">Principal</th>
                    <th class="text-end">Interest</th>
                    <th class="text-end">Penalty</th>
                    <th>Processed By</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in loan.payments" :key="p.id">
                    <td class="ps-3">{{ fmtDate(p.payment_date) }}</td>
                    <td class="text-center">#{{ p.schedule?.installment_number }}</td>
                    <td class="text-end fw-semibold text-success">{{ fmt(p.amount_paid) }}</td>
                    <td class="text-end">{{ fmt(p.principal_paid) }}</td>
                    <td class="text-end text-warning">{{ fmt(p.interest_paid) }}</td>
                    <td class="text-end text-danger">{{ p.penalty_paid > 0 ? fmt(p.penalty_paid) : '—' }}</td>
                    <td class="text-muted">{{ p.processed_by?.name }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Review Modal -->
    <div v-if="showReview" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title">Send for Review</h5><button type="button" class="btn-close" @click="showReview = false"></button></div>
          <form @submit.prevent="reviewForm.post(route('admin.loans.review', loan.id), { onSuccess: () => showReview = false })">
            <div class="modal-body">
              <p class="text-muted small">This will move <strong>{{ loan.loan_number }}</strong> to "Under Review" status.</p>
              <textarea v-model="reviewForm.notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReview = false">Cancel</button>
              <button type="submit" class="btn btn-info text-white" :disabled="reviewForm.processing">Confirm</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Approve Modal -->
    <div v-if="showApprove" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>Approve Loan</h5><button type="button" class="btn-close" @click="showApprove = false"></button></div>
          <form @submit.prevent="approveForm.post(route('admin.loans.approve', loan.id), { onSuccess: () => showApprove = false })">
            <div class="modal-body">
              <p class="small">Approve <strong>{{ loan.loan_number }}</strong> for <strong>{{ fmt(loan.amount) }}</strong> at <strong>{{ loan.interest_rate }}%</strong> for <strong>{{ loan.tenure_months }} months</strong>?</p>
              <textarea v-model="approveForm.notes" class="form-control" rows="2" placeholder="Optional approval notes"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showApprove = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="approveForm.processing">Approve</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Reject Modal -->
    <div v-if="showReject" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Loan</h5><button type="button" class="btn-close" @click="showReject = false"></button></div>
          <form @submit.prevent="rejectForm.post(route('admin.loans.reject', loan.id), { onSuccess: () => showReject = false })">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                <textarea v-model="rejectForm.reason" class="form-control" rows="3" required :class="rejectForm.errors.reason ? 'is-invalid' : ''"></textarea>
                <div v-if="rejectForm.errors.reason" class="invalid-feedback">{{ rejectForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReject = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="rejectForm.processing">Reject</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Disburse Modal -->
    <div v-if="showDisburse" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title text-primary"><i class="bi bi-bank me-2"></i>Disburse Loan</h5><button type="button" class="btn-close" @click="showDisburse = false"></button></div>
          <form @submit.prevent="disburseForm.post(route('admin.loans.disburse', loan.id), { onSuccess: () => showDisburse = false })">
            <div class="modal-body">
              <div class="alert alert-primary small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>{{ fmt(loan.amount) }}</strong> will be credited to account <strong>{{ loan.account?.account_number }}</strong>.
                This action cannot be undone.
              </div>
              <textarea v-model="disburseForm.notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showDisburse = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="disburseForm.processing">
                <span v-if="disburseForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Confirm Disbursement
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Repayment Modal -->
    <div v-if="showRepay" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header"><h5 class="modal-title text-success"><i class="bi bi-cash-coin me-2"></i>Make Repayment</h5><button type="button" class="btn-close" @click="showRepay = false"></button></div>
          <form @submit.prevent="repayForm.post(route('admin.loans.repay', loan.id), { onSuccess: () => showRepay = false })">
            <div class="modal-body">
              <!-- Installment picker -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Installment <span class="text-danger">*</span></label>
                <select v-model="repayForm.schedule_id" class="form-select" @change="onScheduleChange">
                  <option v-for="s in unpaidSchedules" :key="s.id" :value="s.id">
                    #{{ s.installment_number }} — Due {{ fmtDate(s.due_date) }} — {{ fmt(s.total_due) }}
                    <span v-if="s.status === 'overdue'"> (OVERDUE)</span>
                  </option>
                </select>
              </div>

              <div v-if="selectedSchedule" class="alert alert-light small border mb-3">
                <div class="d-flex justify-content-between">
                  <span>EMI</span><strong>{{ fmt(selectedSchedule.emi_amount) }}</strong>
                </div>
                <div class="d-flex justify-content-between" v-if="selectedSchedule.penalty_amount > 0">
                  <span class="text-danger">Penalty</span><strong class="text-danger">{{ fmt(selectedSchedule.penalty_amount) }}</strong>
                </div>
                <div class="d-flex justify-content-between border-top mt-1 pt-1">
                  <strong>Total Due</strong><strong>{{ fmt(selectedSchedule.total_due) }}</strong>
                </div>
              </div>

              <!-- Amount -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="repayForm.amount" type="number" min="0.01" step="0.01" class="form-control form-control-lg"
                    :class="repayForm.errors.amount ? 'is-invalid' : ''" />
                  <div v-if="repayForm.errors.amount" class="invalid-feedback">{{ repayForm.errors.amount }}</div>
                </div>
              </div>
              <div>
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="repayForm.notes" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showRepay = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="repayForm.processing">Post Repayment</button>
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
  loan:             { type: Object, required: true },
  next_installment: { type: Object, default: null },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const showReview  = ref(false);
const showApprove = ref(false);
const showReject  = ref(false);
const showDisburse= ref(false);
const showRepay   = ref(false);

const reviewForm  = useForm({ notes: '' });
const approveForm = useForm({ notes: '' });
const rejectForm  = useForm({ reason: '' });
const disburseForm= useForm({ notes: '' });
const repayForm   = useForm({ schedule_id: '', amount: '', notes: '' });

const unpaidSchedules = computed(() =>
  (props.loan.repayment_schedules ?? []).filter(s => s.status !== 'paid')
);

const selectedSchedule = computed(() =>
  unpaidSchedules.value.find(s => s.id == repayForm.schedule_id) ?? null
);

const openRepay = () => {
  if (props.next_installment) {
    repayForm.schedule_id = props.next_installment.id;
    repayForm.amount      = props.next_installment.total_due;
  }
  showRepay.value = true;
};

const onScheduleChange = () => {
  if (selectedSchedule.value) repayForm.amount = selectedSchedule.value.total_due;
};

// Workflow timeline
const workflow = computed(() => [
  { label: 'Application Submitted', done: true, by: props.loan.customer?.name, at: fmtDate(props.loan.created_at) },
  { label: 'Under Review', done: !!props.loan.reviewed_by, by: props.loan.reviewed_by?.name, at: fmtDt(props.loan.reviewed_at) },
  { label: 'Decision', done: ['approved','rejected'].includes(props.loan.status),
    by: props.loan.approved_by?.name ?? (props.loan.status === 'rejected' ? 'Rejected' : null),
    at: fmtDt(props.loan.approved_at),
    note: props.loan.status === 'rejected' ? props.loan.rejection_reason : null },
  { label: 'Disbursed', done: !!props.loan.disbursed_by, by: props.loan.disbursed_by?.name, at: fmtDt(props.loan.disbursed_at) },
  { label: 'Closed', done: props.loan.status === 'closed', by: null, at: fmtDt(props.loan.closed_at) },
]);

const isNearDue = (d) => {
  const diff = (new Date(d) - Date.now()) / 86400000;
  return diff >= 0 && diff <= 7;
};

const isGracePast = (d) => d && new Date(d) < new Date();

const amort = computed(() => {
  const schedules = props.loan.repayment_schedules ?? [];
  const totalPrincipal = schedules.reduce((s, r) => s + parseFloat(r.principal_amount ?? 0), 0);
  const totalInterest  = schedules.reduce((s, r) => s + parseFloat(r.interest_amount  ?? 0), 0);
  const total = totalPrincipal + totalInterest;
  return {
    totalPrincipal,
    totalInterest,
    interestPct: total > 0 ? Math.round((totalInterest / total) * 100) : 0,
    paid:    schedules.filter(s => s.status === 'paid').length,
    pending: schedules.filter(s => s.status === 'pending').length,
    overdue: schedules.filter(s => s.status === 'overdue').length,
  };
});

const statusLabel  = (s) => ({ pending: 'Pending', under_review: 'Under Review', approved: 'Approved', rejected: 'Rejected', active: 'Active', overdue: 'OVERDUE', closed: 'Closed' }[s] ?? s);
const statusBadge  = (s) => ({ pending: 'bg-secondary', under_review: 'bg-warning text-dark', approved: 'bg-info', rejected: 'bg-danger', active: 'bg-success', overdue: 'bg-danger', closed: 'bg-light text-muted' }[s] ?? 'bg-light');
const scheduleLabel= (s) => ({ pending: 'Pending', paid: 'Paid', partially_paid: 'Partial', overdue: 'Overdue' }[s] ?? s);
const scheduleBadge= (s) => ({ pending: 'bg-secondary-subtle text-secondary', paid: 'bg-success-subtle text-success', partially_paid: 'bg-warning-subtle text-warning', overdue: 'bg-danger text-white' }[s] ?? 'bg-light');

const fmt     = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
const fmtDt   = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : null;
</script>
