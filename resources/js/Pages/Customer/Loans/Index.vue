<template>
  <PortalLayout title="My Loans" subtitle="Your loan accounts and repayment status">
    <template #actions>
      <Link :href="route('customer.loans.create')" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Apply for Loan
      </Link>
    </template>

    <div class="row g-4">
      <div v-for="loan in loans" :key="loan.id" class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div class="fw-bold font-monospace" style="color:#0B2447">{{ loan.loan_number }}</div>
                <div class="text-muted small">{{ loan.account?.account_number }}</div>
              </div>
              <span class="badge px-3 py-2" :class="statusBadge(loan.status)">
                {{ ucfirst(loan.status) }}
              </span>
            </div>

            <!-- Progress bar -->
            <div class="mb-3">
              <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Repaid</span>
                <span class="fw-semibold">${{ fmt(loan.total_paid) }} / ${{ fmt(loan.amount) }}</span>
              </div>
              <div class="progress" style="height:8px;border-radius:4px">
                <div class="progress-bar" :class="loan.status === 'overdue' ? 'bg-danger' : 'bg-success'"
                     :style="{ width: progressPct(loan) + '%' }"></div>
              </div>
            </div>

            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="info-box">
                  <div class="info-label">Outstanding</div>
                  <div class="info-val text-danger">${{ fmt(loan.outstanding_balance) }}</div>
                </div>
              </div>
              <div class="col-6">
                <div class="info-box">
                  <div class="info-label">Monthly EMI</div>
                  <div class="info-val">${{ fmt(loan.monthly_emi) }}</div>
                </div>
              </div>
              <div class="col-6">
                <div class="info-box">
                  <div class="info-label">Interest Rate</div>
                  <div class="info-val">{{ loan.interest_rate }}% p.a.</div>
                </div>
              </div>
              <div class="col-6">
                <div class="info-box">
                  <div class="info-label">Tenure</div>
                  <div class="info-val">{{ loan.tenure_months }} months</div>
                </div>
              </div>
            </div>

            <Link :href="route('customer.loans.show', loan.id)"
                  class="btn btn-sm w-100 fw-semibold"
                  style="background:#0B2447;color:#fff">
              View Details <i class="bi bi-arrow-right ms-1"></i>
            </Link>
          </div>
        </div>
      </div>

      <div v-if="!loans.length" class="col-12">
        <div class="text-center text-muted py-5">
          <i class="bi bi-cash-coin fs-1 mb-2 d-block"></i>
          No loans found.
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineProps({ loans: { type: Array, default: () => [] } })

const fmt         = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const ucfirst     = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const progressPct = (l) => l.amount > 0 ? Math.min(100, Math.round((l.total_paid / l.amount) * 100)) : 0
const statusBadge = (s) => ({ active: 'bg-success', overdue: 'bg-danger', closed: 'bg-secondary', defaulted: 'bg-dark', pending_approval: 'bg-warning text-dark', approved: 'bg-info' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.info-box { background: #f8fafc; border-radius: 8px; padding: .5rem .75rem; }
.info-label { font-size: .7rem; color: #94a3b8; text-transform: uppercase; }
.info-val { font-size: .95rem; font-weight: 700; color: #0B2447; }
</style>
