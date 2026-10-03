<template>
  <AdminLayout title="Reports" subtitle="Generate PDF and Excel reports">

    <div class="row g-4">

      <!-- ── Transaction Report ──────────────────────────────────────────── -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white d-flex align-items-center gap-2 fw-semibold">
            <i class="bi bi-arrow-left-right text-primary fs-5"></i>
            Transaction Report
          </div>
          <div class="card-body">
            <form @submit.prevent="generate('transactions', txnForm)">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label fw-semibold small">From <span class="text-danger">*</span></label>
                  <input v-model="txnForm.date_from" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold small">To <span class="text-danger">*</span></label>
                  <input v-model="txnForm.date_to" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold small">Account</label>
                  <input v-model="txnAccSearch" type="text" class="form-control form-control-sm"
                         placeholder="Search account…" @input="txnForm.account_id = ''">
                  <div v-if="txnAccSearch.length >= 2 && txnAccResults.length"
                       class="list-group mt-1 shadow-sm" style="max-height:160px;overflow-y:auto;position:absolute;z-index:999;width:calc(50% - 1.5rem)">
                    <button v-for="acc in txnAccResults" :key="acc.id" type="button"
                            class="list-group-item list-group-item-action small py-1"
                            @click="selectTxnAcc(acc)">
                      <span class="font-monospace">{{ acc.account_number }}</span>
                      <span class="text-muted ms-2">{{ acc.customer?.name }}</span>
                    </button>
                  </div>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold small">Type</label>
                  <select v-model="txnForm.type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    <option value="deposit">Deposit</option>
                    <option value="withdrawal">Withdrawal</option>
                    <option value="transfer">Transfer</option>
                    <option value="reversal">Reversal</option>
                  </select>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" name="fmt" value="pdf" @click="txnForm.format='pdf'"
                        class="btn btn-sm btn-danger">
                  <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
                <button type="submit" name="fmt" value="excel" @click="txnForm.format='excel'"
                        class="btn btn-sm btn-success">
                  <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Teller Summary ─────────────────────────────────────────────── -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white d-flex align-items-center gap-2 fw-semibold">
            <i class="bi bi-cash-stack text-warning fs-5"></i>
            Teller Till Summary
          </div>
          <div class="card-body">
            <form @submit.prevent="generate('teller-summary', tellerForm)">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
                  <input v-model="tellerForm.date" type="date" class="form-control form-control-sm" required>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" @click="tellerForm.format='pdf'" class="btn btn-sm btn-danger">
                  <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
                <button type="submit" @click="tellerForm.format='excel'" class="btn btn-sm btn-success">
                  <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Loan Portfolio ─────────────────────────────────────────────── -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white d-flex align-items-center gap-2 fw-semibold">
            <i class="bi bi-cash-coin text-success fs-5"></i>
            Loan Portfolio Report
          </div>
          <div class="card-body">
            <form @submit.prevent="generate('loans', loanForm)">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label fw-semibold small">From <span class="text-danger">*</span></label>
                  <input v-model="loanForm.date_from" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold small">To <span class="text-danger">*</span></label>
                  <input v-model="loanForm.date_to" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold small">Status</label>
                  <select v-model="loanForm.status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="overdue">Overdue</option>
                    <option value="defaulted">Defaulted</option>
                    <option value="closed">Closed</option>
                    <option value="pending_approval">Pending Approval</option>
                  </select>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" @click="loanForm.format='pdf'" class="btn btn-sm btn-danger">
                  <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
                <button type="submit" @click="loanForm.format='excel'" class="btn btn-sm btn-success">
                  <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Cheque Register ────────────────────────────────────────────── -->
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white d-flex align-items-center gap-2 fw-semibold">
            <i class="bi bi-file-earmark-text text-info fs-5"></i>
            Cheque Register
          </div>
          <div class="card-body">
            <form @submit.prevent="generate('cheques', chequeForm)">
              <div class="row g-3">
                <div class="col-6">
                  <label class="form-label fw-semibold small">From <span class="text-danger">*</span></label>
                  <input v-model="chequeForm.date_from" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-6">
                  <label class="form-label fw-semibold small">To <span class="text-danger">*</span></label>
                  <input v-model="chequeForm.date_to" type="date" class="form-control form-control-sm" required>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold small">Status</label>
                  <select v-model="chequeForm.status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="issued">Issued</option>
                    <option value="paid">Paid (Cash)</option>
                    <option value="deposited">Deposited</option>
                    <option value="bounced">Bounced</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button type="submit" @click="chequeForm.format='pdf'" class="btn btn-sm btn-danger">
                  <i class="bi bi-file-earmark-pdf me-1"></i>PDF
                </button>
                <button type="submit" @click="chequeForm.format='excel'" class="btn btn-sm btn-success">
                  <i class="bi bi-file-earmark-excel me-1"></i>Excel
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- ── Quick Receipts ─────────────────────────────────────────────── -->
      <div class="col-12">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-receipt me-2 text-secondary"></i>Quick Receipt / Letter Lookup
          </div>
          <div class="card-body">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Transaction Reference</label>
                <input v-model="txnRef" type="text" class="form-control form-control-sm font-monospace"
                       placeholder="e.g. TXN-20260627-00001">
              </div>
              <div class="col-md-2">
                <a v-if="txnRef" :href="route('admin.reports.receipt', txnRef)"
                   target="_blank" class="btn btn-sm btn-outline-danger w-100">
                  <i class="bi bi-printer me-1"></i>Print Receipt
                </a>
                <button v-else class="btn btn-sm btn-outline-secondary w-100" disabled>Print Receipt</button>
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold small">Loan Number</label>
                <input v-model="loanNum" type="text" class="form-control form-control-sm font-monospace"
                       placeholder="e.g. LN-20260627-00001">
              </div>
              <div class="col-md-2">
                <a v-if="loanNum" :href="route('admin.reports.loan-letter', loanNum)"
                   target="_blank" class="btn btn-sm btn-outline-primary w-100">
                  <i class="bi bi-file-text me-1"></i>Disbursement Letter
                </a>
                <button v-else class="btn btn-sm btn-outline-secondary w-100" disabled>Letter</button>
              </div>
            </div>
            <div class="text-muted small mt-2">
              <i class="bi bi-info-circle me-1"></i>
              Receipt buttons are also available on each Transaction and Loan detail page.
            </div>
          </div>
        </div>
      </div>

    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  accounts: { type: Array, default: () => [] },
})

// Transaction form
const txnAccSearch = ref('')
const txnForm = ref({ date_from: '', date_to: '', account_id: '', type: '', format: 'pdf' })

const txnAccResults = computed(() => {
  if (txnAccSearch.value.length < 2) return []
  const s = txnAccSearch.value.toLowerCase()
  return props.accounts.filter(a =>
    a.account_number.toLowerCase().includes(s) || a.customer?.name?.toLowerCase().includes(s)
  ).slice(0, 6)
})

function selectTxnAcc(acc) {
  txnForm.value.account_id = acc.id
  txnAccSearch.value = acc.account_number
}

// Teller form
const tellerForm = ref({ date: new Date().toISOString().slice(0, 10), format: 'pdf' })

// Loan form
const loanForm = ref({ date_from: '', date_to: '', status: '', format: 'pdf' })

// Cheque form
const chequeForm = ref({ date_from: '', date_to: '', status: '', format: 'pdf' })

// Quick receipt lookup
const txnRef = ref('')
const loanNum = ref('')

function generate(report, form) {
  const params = new URLSearchParams()
  Object.entries(form.value ?? form).forEach(([k, v]) => { if (v) params.append(k, v) })
  window.open(route(`admin.reports.${report}`) + '?' + params.toString(), '_blank')
}
</script>
