<template>
  <AdminLayout title="Verify Cheque">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="d-flex align-items-center gap-3 mb-4">
          <Link :href="route('admin.cheques.index')" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
          </Link>
          <div>
            <h2 class="mb-0 fw-bold">Verify Cheque</h2>
            <small class="text-muted">Look up a cheque by number</small>
          </div>
        </div>


        <!-- Search -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-body p-4">
            <form @submit.prevent="search">
              <div class="input-group input-group-lg">
                <span class="input-group-text bg-white">
                  <i class="bi bi-upc-scan text-muted"></i>
                </span>
                <input v-model="q" type="text" class="form-control font-monospace border-start-0"
                       placeholder="Enter cheque number (e.g. GAR0000042)"
                       style="text-transform:uppercase" autofocus>
                <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-search me-1"></i>Search
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- No result -->
        <div v-if="query && !result" class="card border-0 shadow-sm">
          <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-file-earmark-x fs-1 d-block mb-2"></i>
            Cheque <strong class="font-monospace">{{ query }}</strong> not found.
          </div>
        </div>

        <!-- Result card -->
        <div v-if="result" class="card border-0 shadow-sm">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-bold font-monospace fs-5">{{ result.cheque_number }}</span>
            <span :class="leafStatusClass(result.status)" class="badge fs-6 px-3 py-2">
              {{ fmtStatus(result.status) }}
            </span>
          </div>
          <div class="card-body">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <div class="text-muted small">Customer / Drawer</div>
                <div class="fw-bold">{{ result.cheque_book?.customer?.name ?? result.account?.customer?.name }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">Drawer Account</div>
                <div class="fw-bold font-monospace">{{ result.account?.account_number }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">Payee</div>
                <div>{{ result.payee_name ?? '—' }}</div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small">Amount</div>
                <div class="fw-bold">
                  <span v-if="result.amount">{{ fmt(result.amount) }}</span>
                  <span v-else class="text-muted">Not yet processed</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">Issue Date</div>
                <div>{{ formatDate(result.issue_date) }}</div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">Expiry Date</div>
                <div :class="isExpired(result.expiry_date) ? 'text-danger fw-bold' : ''">
                  {{ formatDate(result.expiry_date) }}
                  <span v-if="isExpired(result.expiry_date)" class="badge bg-danger ms-1">Expired</span>
                </div>
              </div>
              <div class="col-md-4">
                <div class="text-muted small">Settlement</div>
                <div>
                  <span v-if="result.settlement_type === 'cash'" class="badge bg-info text-dark">Cash Payment</span>
                  <span v-else-if="result.settlement_type === 'account_transfer'" class="badge bg-primary">Account Transfer</span>
                  <span v-else class="text-muted">—</span>
                </div>
              </div>
              <template v-if="result.status === 'deposited' && result.beneficiary_account">
                <div class="col-12">
                  <div class="text-muted small">Deposited Into</div>
                  <div class="fw-bold font-monospace">
                    {{ result.beneficiary_account.account_number }}
                    <span class="text-muted fw-normal ms-2">{{ result.beneficiary_account.customer?.name }}</span>
                  </div>
                </div>
              </template>
              <div v-if="['paid','deposited','cleared'].includes(result.status)" class="col-md-6">
                <div class="text-muted small">Completed At</div>
                <div class="text-success fw-bold">{{ formatDateTime(result.cleared_at) }}</div>
              </div>
              <div v-if="result.status === 'bounced'" class="col-12">
                <div class="text-muted small">Bounce Reason</div>
                <div class="text-danger">{{ result.bounce_reason }}</div>
              </div>
              <div v-if="result.status === 'cancelled'" class="col-12">
                <div class="text-muted small">Cancellation Notes</div>
                <div class="text-danger">{{ result.notes }}</div>
              </div>
              <div v-if="result.processed_by" class="col-md-6">
                <div class="text-muted small">Processed By</div>
                <div>{{ result.processed_by.name }}</div>
              </div>
            </div>

            <!-- Cheque book link + Action buttons -->
            <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top gap-2">
              <Link :href="route('admin.cheques.show', result.cheque_book_id)" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-journal me-1"></i>View Book {{ result.cheque_book?.book_number }}
              </Link>

              <div class="d-flex gap-2 flex-wrap">
                <!-- Scenario 1: Cash Encashment -->
                <button v-if="result.status === 'issued' && !isExpired(result.expiry_date)"
                        class="btn btn-success" @click="openEncash">
                  <i class="bi bi-cash-coin me-1"></i>Cash Encashment
                </button>
                <!-- Scenario 2: Deposit to Account -->
                <button v-if="result.status === 'issued' && !isExpired(result.expiry_date)"
                        class="btn btn-primary" @click="openDeposit">
                  <i class="bi bi-bank me-1"></i>Deposit to Account
                </button>
                <!-- Stop Payment -->
                <button v-if="result.status === 'issued'"
                        class="btn btn-outline-danger" @click="openCancel">
                  <i class="bi bi-slash-circle me-1"></i>Stop Payment
                </button>
                <!-- Manual clear (legacy pending_clearance) -->
                <button v-if="result.status === 'pending_clearance'"
                        class="btn btn-success" @click="submitClear" :disabled="clearProcessing">
                  <span v-if="clearProcessing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-check2-circle me-1"></i>Clear Now
                </button>
                <button v-if="result.status === 'pending_clearance'"
                        class="btn btn-warning" @click="openBounce">
                  <i class="bi bi-x-circle me-1"></i>Mark Bounced
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Encash Modal (Scenario 1: Cash Payment) -->
    <div v-if="showEncash" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Cash Encashment</h5>
            <button type="button" class="btn-close btn-close-white" @click="showEncash = false"></button>
          </div>
          <form @submit.prevent="submitEncash">
            <div class="modal-body">
              <div class="alert alert-success-subtle border-success small py-2 mb-3">
                <strong>Scenario 1:</strong> Drawer account debited · Teller till decreased · Beneficiary receives physical cash.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="encashForm.amount" type="number" step="0.01" min="0.01"
                         class="form-control" :class="encashForm.errors.amount ? 'is-invalid' : ''" required>
                  <div class="invalid-feedback">{{ encashForm.errors.amount }}</div>
                </div>
                <div class="small text-muted mt-1">
                  Available balance: <strong>{{ fmt(result?.account?.balance) }}</strong>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Beneficiary / Payee Name <span class="text-danger">*</span></label>
                <input v-model="encashForm.payee_name" type="text" class="form-control"
                       :class="encashForm.errors.payee_name ? 'is-invalid' : ''"
                       placeholder="Name of person receiving cash" required>
                <div class="invalid-feedback">{{ encashForm.errors.payee_name }}</div>
              </div>
              <div>
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="encashForm.notes" class="form-control" rows="2"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showEncash = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="encashForm.processing">
                <span v-if="encashForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Pay Cash — Mark Paid
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Deposit Modal (Scenario 2: Account Transfer) -->
    <div v-if="showDeposit" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title"><i class="bi bi-bank me-2"></i>Deposit to Account</h5>
            <button type="button" class="btn-close btn-close-white" @click="showDeposit = false"></button>
          </div>
          <form @submit.prevent="submitDeposit">
            <div class="modal-body">
              <div class="alert alert-primary-subtle border-primary small py-2 mb-3">
                <strong>Scenario 2:</strong> Drawer account debited · Beneficiary account credited · No physical cash.
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="depositForm.amount" type="number" step="0.01" min="0.01"
                           class="form-control" :class="depositForm.errors.amount ? 'is-invalid' : ''" required>
                    <div class="invalid-feedback">{{ depositForm.errors.amount }}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Payee Name</label>
                  <input v-model="depositForm.payee_name" type="text" class="form-control"
                         placeholder="Optional — name on cheque">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Beneficiary Account <span class="text-danger">*</span></label>
                  <input v-model="benSearch" type="text" class="form-control"
                         :class="depositForm.errors.beneficiary_account_id ? 'is-invalid' : ''"
                         placeholder="Search by account number or customer name…">
                  <div class="invalid-feedback">{{ depositForm.errors.beneficiary_account_id }}</div>
                  <!-- Results dropdown -->
                  <div v-if="benSearch.length >= 2 && benResults.length" class="list-group mt-1 shadow-sm" style="max-height:200px;overflow-y:auto;position:relative;z-index:9999">
                    <button v-for="acc in benResults" :key="acc.id" type="button"
                            class="list-group-item list-group-item-action d-flex justify-content-between"
                            :class="depositForm.beneficiary_account_id === acc.id ? 'active' : ''"
                            @click="selectBeneficiary(acc)">
                      <span class="font-monospace">{{ acc.account_number }}</span>
                      <span class="text-muted small">{{ acc.customer?.name }} · {{ fmt(acc.balance) }}</span>
                    </button>
                  </div>
                  <div v-if="selectedBen" class="mt-2 alert alert-info py-2 small">
                    <i class="bi bi-check-circle me-1"></i>
                    Selected: <strong class="font-monospace">{{ selectedBen.account_number }}</strong>
                    — {{ selectedBen.customer?.name }} (Balance: {{ fmt(selectedBen.balance) }})
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Notes</label>
                  <textarea v-model="depositForm.notes" class="form-control" rows="2"></textarea>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showDeposit = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="depositForm.processing">
                <span v-if="depositForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Deposit — Mark Deposited
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Cancel Modal -->
    <div v-if="showCancel" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger">Stop Payment</h5>
            <button type="button" class="btn-close" @click="showCancel = false"></button>
          </div>
          <form @submit.prevent="submitCancel">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea v-model="actionForm.reason" class="form-control" rows="3"
                          :class="actionForm.errors.reason ? 'is-invalid' : ''" required></textarea>
                <div class="invalid-feedback">{{ actionForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary" @click="showCancel = false">Back</button>
              <button type="submit" class="btn btn-danger" :disabled="actionForm.processing">Confirm Stop Payment</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Bounce Modal -->
    <div v-if="showBounce" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-warning">Mark as Bounced</h5>
            <button type="button" class="btn-close" @click="showBounce = false"></button>
          </div>
          <form @submit.prevent="submitBounce">
            <div class="modal-body">
              <div class="alert alert-warning py-2 small mb-3">
                Account will be credited back <strong>{{ fmt(result?.amount) }}</strong>.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea v-model="actionForm.reason" class="form-control" rows="3"
                          :class="actionForm.errors.reason ? 'is-invalid' : ''" required></textarea>
                <div class="invalid-feedback">{{ actionForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary" @click="showBounce = false">Cancel</button>
              <button type="submit" class="btn btn-warning" :disabled="actionForm.processing">Confirm Bounce</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  result:       Object,
  query:        String,
  all_accounts: { type: Array, default: () => [] },
  server_date:  { type: String, default: () => new Date().toISOString().split('T')[0] },
})

const q              = ref(props.query)
const showEncash     = ref(false)
const showDeposit    = ref(false)
const showCancel     = ref(false)
const showBounce     = ref(false)
const clearProcessing= ref(false)
const benSearch      = ref('')
const selectedBen    = ref(null)

const encashForm  = useForm({ amount: '', payee_name: '', notes: '' })
const depositForm = useForm({ amount: '', payee_name: '', beneficiary_account_id: '', notes: '' })
const actionForm  = useForm({ reason: '' })

const benResults = computed(() => {
  if (benSearch.value.length < 2) return []
  const s = benSearch.value.toLowerCase()
  return props.all_accounts.filter(a =>
    a.account_number.toLowerCase().includes(s) ||
    a.customer?.name?.toLowerCase().includes(s)
  ).slice(0, 8)
})

function selectBeneficiary(acc) {
  selectedBen.value = acc
  depositForm.beneficiary_account_id = acc.id
  benSearch.value = acc.account_number
}

function search() {
  router.get(route('admin.cheques.verify'), { q: q.value.toUpperCase() }, { preserveState: true, replace: true })
}

function openEncash()  { encashForm.reset(); selectedBen.value = null; showEncash.value = true }
function openDeposit() { depositForm.reset(); benSearch.value = ''; selectedBen.value = null; showDeposit.value = true }
function openCancel()  { actionForm.reset(); showCancel.value = true }
function openBounce()  { actionForm.reset(); showBounce.value = true }

function submitClear() {
  clearProcessing.value = true
  router.post(route('admin.cheques.clear', props.result.id), {}, {
    onFinish:  () => { clearProcessing.value = false },
    onSuccess: () => router.get(route('admin.cheques.verify'), { q: props.result.cheque_number }),
  })
}
function submitEncash() {
  encashForm.post(route('admin.cheques.encash', props.result.id), {
    onSuccess: () => {
      showEncash.value = false
      router.get(route('admin.cheques.verify'), { q: props.result.cheque_number })
    },
  })
}
function submitDeposit() {
  depositForm.post(route('admin.cheques.deposit', props.result.id), {
    onSuccess: () => {
      showDeposit.value = false
      router.get(route('admin.cheques.verify'), { q: props.result.cheque_number })
    },
  })
}
function submitCancel() {
  actionForm.post(route('admin.cheques.cancel', props.result.id), {
    onSuccess: () => {
      showCancel.value = false
      router.get(route('admin.cheques.verify'), { q: props.result.cheque_number })
    },
  })
}
function submitBounce() {
  actionForm.post(route('admin.cheques.bounce', props.result.id), {
    onSuccess: () => {
      showBounce.value = false
      router.get(route('admin.cheques.verify'), { q: props.result.cheque_number })
    },
  })
}

function leafStatusClass(status) {
  return {
    issued:            'bg-primary',
    pending_clearance: 'bg-warning text-dark',
    cleared:           'bg-success',
    paid:              'bg-success',
    deposited:         'bg-info text-dark',
    bounced:           'bg-danger',
    cancelled:         'bg-dark',
    expired:           'bg-secondary',
  }[status] ?? 'bg-secondary'
}

function fmtStatus(s) {
  return { paid: 'Paid (Cash)', deposited: 'Deposited', pending_clearance: 'Pending Clearance' }[s] ?? s.replace(/_/g, ' ')
}

function isExpired(date) {
  if (!date) return false
  return date < props.server_date
}

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
function formatDateTime(d) {
  if (!d) return '—'
  return new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
