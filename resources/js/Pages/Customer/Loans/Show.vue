<template>
  <PortalLayout :title="loan.loan_number" subtitle="Loan Details">
    <template #actions>
      <div class="d-flex gap-2">
        <a v-if="loan.disbursed_at" :href="route('admin.reports.loan-letter', loan.id)"
           target="_blank" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-file-earmark-text me-1"></i>Letter
        </a>
        <Link :href="route('customer.loans.index')" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>


    <!-- Next Installment + Quick Repay (only for active/overdue loans) -->
    <div v-if="next_due && ['active','overdue'].includes(loan.status)" class="card border-0 shadow-sm mb-4"
         :class="loan.status === 'overdue' ? 'border-danger' : ''">
      <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
          <div class="fw-semibold" :class="loan.status === 'overdue' ? 'text-danger' : 'text-success'">
            <i class="bi bi-calendar-check me-2"></i>
            {{ loan.status === 'overdue' ? 'Overdue Instalment' : 'Next Instalment Due' }}
          </div>
          <div class="small text-muted mt-1">
            Instalment #{{ next_due.installment_number }} — Due {{ fmtDate(next_due.due_date) }}
          </div>
        </div>
        <div class="text-center">
          <div class="text-muted small">EMI Amount</div>
          <div class="fw-bold fs-5" style="color:#0B2447">${{ fmt(next_due.emi_amount) }}</div>
        </div>
        <button class="btn btn-success btn-sm" @click="showRepay = true">
          <i class="bi bi-credit-card me-1"></i>Make Payment
        </button>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left: summary -->
      <div class="col-lg-4">
        <!-- Status card -->
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-body text-center py-4">
            <span class="badge px-3 py-2 fs-6 mb-3" :class="statusBadge(loan.status)">
              {{ ucfirst(loan.status) }}
            </span>
            <div class="display-6 fw-bold" style="color:#0B2447">${{ fmt(loan.amount) }}</div>
            <div class="text-muted small">Loan Amount</div>

            <!-- progress -->
            <div class="mt-3 mb-1 d-flex justify-content-between small">
              <span class="text-muted">Progress</span>
              <span class="fw-semibold">{{ progressPct }}%</span>
            </div>
            <div class="progress" style="height:10px;border-radius:5px">
              <div class="progress-bar bg-success" :style="{ width: progressPct + '%' }"></div>
            </div>
          </div>
        </div>

        <!-- Details -->
        <div class="card border-0 shadow-sm">
          <div class="card-body">
            <h6 class="fw-semibold mb-3" style="color:#0B2447">Loan Details</h6>
            <div class="detail-row"><span>Loan Number</span><strong class="font-monospace small">{{ loan.loan_number }}</strong></div>
            <div class="detail-row"><span>Account</span><strong class="font-monospace">{{ loan.account?.account_number }}</strong></div>
            <div class="detail-row"><span>Interest Rate</span><strong>{{ loan.interest_rate }}% p.a.</strong></div>
            <div class="detail-row"><span>Tenure</span><strong>{{ loan.tenure_months }} months</strong></div>
            <div class="detail-row"><span>Monthly EMI</span><strong>${{ fmt(loan.monthly_emi) }}</strong></div>
            <div class="detail-row"><span>Total Payable</span><strong>${{ fmt(loan.total_payable) }}</strong></div>
            <div class="detail-row"><span>Total Interest</span><strong>${{ fmt(loan.total_interest) }}</strong></div>
            <div class="detail-row"><span>Total Paid</span><strong class="text-success">${{ fmt(loan.total_paid) }}</strong></div>
            <div class="detail-row"><span>Outstanding</span><strong class="text-danger">${{ fmt(loan.outstanding_balance) }}</strong></div>
            <div v-if="loan.disbursed_at" class="detail-row"><span>Disbursed</span><strong>{{ fmtDate(loan.disbursed_at) }}</strong></div>
          </div>
        </div>
      </div>

      <!-- Right: repayments -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-list-check me-2 text-success"></i>Repayment History
          </div>
          <div class="card-body p-0">
            <div v-if="!loan.payments?.length" class="text-center text-muted py-4 small">
              No repayments recorded yet.
            </div>
            <div v-else class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th class="text-end">Amount Paid</th>
                  <th class="text-end">Principal</th>
                  <th class="text-end">Interest</th>
                  <th class="text-end">Penalty</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in loan.payments" :key="r.id">
                  <td class="small">{{ fmtDate(r.payment_date) }}</td>
                  <td class="text-end fw-semibold text-success small">${{ fmt(r.amount_paid) }}</td>
                  <td class="text-end small">${{ fmt(r.principal_paid) }}</td>
                  <td class="text-end small">${{ fmt(r.interest_paid) }}</td>
                  <td class="text-end small">${{ fmt(r.penalty_paid) }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- Repay Modal -->
    <div v-if="showRepay" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title"><i class="bi bi-credit-card me-2 text-success"></i>Make Repayment</h5>
            <button type="button" class="btn-close" @click="showRepay = false"></button>
          </div>
          <form @submit.prevent="submitRepay">
            <div class="modal-body">
              <div class="alert alert-info py-2 small mb-3">
                Amount will be debited from your loan account: <strong class="font-monospace">{{ loan.account?.account_number }}</strong>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Instalment</label>
                <select v-model="repayForm.schedule_id" class="form-select" required>
                  <option value="">Select instalment</option>
                  <option v-for="s in unpaidSchedules" :key="s.id" :value="s.id">
                    #{{ s.installment_number }} — Due {{ fmtDate(s.due_date) }} — {{ fmt(s.emi_amount) }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount (USD)</label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="repayForm.amount" type="number" step="0.01" min="0.01"
                         class="form-control" required>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes (optional)</label>
                <input v-model="repayForm.notes" type="text" class="form-control">
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-light" @click="showRepay = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="repayForm.processing">
                <span v-if="repayForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Submit Payment
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </PortalLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({ loan: Object, next_due: Object })

const showRepay = ref(false)
const repayForm = useForm({ schedule_id: props.next_due?.id ?? '', amount: props.next_due?.emi_amount ?? '', notes: '' })

const unpaidSchedules = computed(() =>
  (props.loan.repaymentSchedules ?? []).filter(s => s.status !== 'paid').slice(0, 12)
)

function submitRepay() {
  repayForm.post(route('customer.loans.repay', props.loan.id), {
    onSuccess: () => { showRepay.value = false; repayForm.reset() },
  })
}

const fmt         = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate     = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : ''
const ucfirst     = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const progressPct = computed(() => props.loan.amount > 0 ? Math.min(100, Math.round((props.loan.total_paid / props.loan.amount) * 100)) : 0)
const statusBadge = (s) => ({ active: 'bg-success', overdue: 'bg-danger', closed: 'bg-secondary', defaulted: 'bg-dark', pending_approval: 'bg-warning text-dark', approved: 'bg-info' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.detail-row { display:flex; justify-content:space-between; padding:.4rem 0; border-bottom:1px solid #f0f4f8; font-size:.875rem; }
.detail-row:last-child { border-bottom:none; }
.detail-row span { color:#64748b; }
</style>
