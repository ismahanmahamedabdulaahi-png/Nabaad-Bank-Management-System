<template>
  <AdminLayout :title="`Cheque Book — ${book.book_number}`">

    <!-- Header -->
    <template #actions>
      <Link :href="route('admin.cheques.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <!-- Summary cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small mb-1">Customer</div>
            <div class="fw-bold">{{ book.customer?.name }}</div>
            <div class="font-monospace text-muted small">{{ book.customer?.customer_number }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small mb-1">Drawer Account</div>
            <div class="fw-bold font-monospace">{{ book.account?.account_number }}</div>
            <div class="text-muted small text-capitalize">{{ book.account?.account_type }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small mb-1">Account Balance</div>
            <div class="fw-bold fs-5">{{ fmt(book.account?.balance) }}</div>
            <div class="text-muted small">Min: {{ fmt(book.account?.minimum_balance) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="text-muted small mb-1">Leaves</div>
            <div class="fw-bold fs-5">{{ book.used_leaves }} / {{ book.total_leaves }}</div>
            <div class="progress mt-1" style="height:5px">
              <div class="progress-bar" :style="{ width: leafPct + '%' }"
                   :class="leafPct > 80 ? 'bg-danger' : leafPct > 50 ? 'bg-warning' : 'bg-success'"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cheque Leaves Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-check me-2 text-primary"></i>Cheque Leaves</span>
        <span class="badge bg-primary rounded-pill">{{ book.cheques?.length }}</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Cheque No</th>
              <th>Payee</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Settlement</th>
              <th>Issue Date</th>
              <th>Expiry Date</th>
              <th>Processed By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(chq, idx) in book.cheques" :key="chq.id" :class="rowClass(chq.status)">
              <td class="text-muted small">{{ idx + 1 }}</td>
              <td class="font-monospace fw-bold">{{ chq.cheque_number }}</td>
              <td class="small">{{ chq.payee_name ?? '—' }}</td>
              <td class="small">
                <span v-if="chq.amount" class="font-monospace">{{ fmt(chq.amount) }}</span>
                <span v-else class="text-muted">—</span>
              </td>
              <td>
                <span :class="leafStatusClass(chq.status)" class="badge">{{ fmtStatus(chq.status) }}</span>
              </td>
              <td>
                <span v-if="chq.settlement_type === 'cash'" class="badge bg-info text-dark">Cash</span>
                <span v-else-if="chq.settlement_type === 'account_transfer'" class="badge bg-primary">Account</span>
                <span v-else class="text-muted small">—</span>
              </td>
              <td class="small text-muted">{{ fmtDate(chq.issue_date) }}</td>
              <td class="small" :class="isExpired(chq.expiry_date) ? 'text-danger fw-semibold' : 'text-muted'">
                {{ fmtDate(chq.expiry_date) }}
                <span v-if="isExpired(chq.expiry_date)" class="badge bg-danger ms-1" style="font-size:.6rem">Expired</span>
              </td>
              <td class="small text-muted">{{ chq.processed_by?.name ?? '—' }}</td>
              <td>
                <div class="d-flex gap-1 flex-wrap">
                  <button v-if="chq.status === 'issued' && can('cheques.cash')" class="btn btn-xs btn-success py-0 px-2"
                          style="font-size:.72rem" @click="openEncash(chq)">
                    <i class="bi bi-cash-coin me-1"></i>Cash
                  </button>
                  <button v-if="chq.status === 'issued' && can('cheques.cash')" class="btn btn-xs btn-primary py-0 px-2"
                          style="font-size:.72rem" @click="openDeposit(chq)">
                    <i class="bi bi-bank me-1"></i>Deposit
                  </button>
                  <button v-if="chq.status === 'issued' && can('cheques.cancel')" class="btn btn-xs btn-outline-danger py-0 px-2"
                          style="font-size:.72rem" @click="openCancel(chq)" title="Stop Payment">
                    <i class="bi bi-slash-circle"></i>
                  </button>
                  <button v-if="chq.status === 'pending_clearance' && can('cheques.cash')" class="btn btn-xs btn-success py-0 px-2"
                          style="font-size:.72rem" @click="submitClear(chq)" title="Clear manually">
                    <i class="bi bi-check2-circle me-1"></i>Clear
                  </button>
                  <button v-if="chq.status === 'pending_clearance' && can('cheques.verify')" class="btn btn-xs btn-warning py-0 px-2"
                          style="font-size:.72rem" @click="openBounce(chq)" title="Bounce">
                    <i class="bi bi-x-circle"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         ENCASH MODAL — Cash Payment
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="showEncash" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header text-white" style="background:#0B2447">
            <h5 class="modal-title">
              <i class="bi bi-cash-coin me-2"></i>Cash Encashment
              <span class="font-monospace ms-2 opacity-75" style="font-size:.85rem">{{ selected?.cheque_number }}</span>
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="showEncash = false"></button>
          </div>

          <div class="modal-body">
            <!-- Cheque Info -->
            <div class="row g-2 mb-3 p-3 rounded cheque-info-panel">
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Cheque No</div>
                <div class="fw-bold font-monospace small">{{ selected?.cheque_number }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Status</div>
                <span :class="leafStatusClass(selected?.status)" class="badge">{{ fmtStatus(selected?.status) }}</span>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Issue Date</div>
                <div class="small">{{ fmtDate(selected?.issue_date) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Expiry Date</div>
                <div class="small" :class="isExpired(selected?.expiry_date) ? 'text-danger fw-bold' : ''">
                  {{ fmtDate(selected?.expiry_date) }}
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Drawer Name</div>
                <div class="small fw-semibold">{{ book.customer?.name }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Drawer Account</div>
                <div class="small font-monospace">{{ book.account?.account_number }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Current Balance</div>
                <div class="small fw-semibold text-success">{{ fmt(book.account?.balance) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Cheque Book</div>
                <div class="small font-monospace">{{ book.book_number }}</div>
              </div>
            </div>

            <!-- Verification Checklist -->
            <div class="mb-3">
              <div class="fw-semibold small mb-2 section-heading-brand">
                <i class="bi bi-shield-check me-1"></i>Verification Checks
              </div>
              <div class="row g-1">
                <div v-for="chk in encashChecks" :key="chk.label" class="col-md-6">
                  <div class="d-flex align-items-center gap-2 px-2 py-1 rounded"
                       :class="chk.pass ? 'check-row-pass' : 'check-row-fail'">
                    <i class="bi fw-bold" :class="chk.pass ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"></i>
                    <span class="small" :class="chk.pass ? 'text-success' : 'text-danger'">{{ chk.label }}</span>
                  </div>
                </div>
                <!-- Signature verified — manual checkbox -->
                <div class="col-md-6">
                  <label class="d-flex align-items-center gap-2 px-2 py-1 rounded cursor-pointer"
                         :class="signatureVerified ? 'check-row-pass' : 'check-row-fail'">
                    <i class="bi fw-bold" :class="signatureVerified ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"></i>
                    <input type="checkbox" v-model="signatureVerified" class="d-none">
                    <span class="small" :class="signatureVerified ? 'text-success' : 'text-danger'">
                      Signature verified {{ signatureVerified ? '✔' : '(tick to confirm)' }}
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <hr class="my-3">

            <!-- Form fields — only when all checks pass -->
            <form @submit.prevent="submitEncash" id="encash-form">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="encashForm.amount" type="number" step="0.01" min="0.01"
                           class="form-control" :class="encashForm.errors.amount ? 'is-invalid' : ''"
                           :disabled="!encashAllPass" required>
                    <div class="invalid-feedback">{{ encashForm.errors.amount }}</div>
                  </div>
                  <div v-if="book.account" class="form-text">
                    Available: <strong>{{ fmt(availableBalance) }}</strong>
                    (Balance {{ fmt(book.account.balance) }} − Min {{ fmt(book.account.minimum_balance) }})
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Beneficiary / Payee Name <span class="text-danger">*</span></label>
                  <input v-model="encashForm.payee_name" type="text" class="form-control"
                         :class="encashForm.errors.payee_name ? 'is-invalid' : ''"
                         :disabled="!encashAllPass"
                         placeholder="Name of person receiving cash" required>
                  <div class="invalid-feedback">{{ encashForm.errors.payee_name }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Notes</label>
                  <textarea v-model="encashForm.notes" class="form-control" rows="2" :disabled="!encashAllPass"></textarea>
                </div>
              </div>
            </form>

            <!-- All-checks warning -->
            <div v-if="!encashAllPass" class="alert alert-warning py-2 small mt-3 mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              All verification checks must pass before this cheque can be processed.
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-light" @click="showEncash = false">Cancel</button>
            <button type="submit" form="encash-form" class="btn btn-success"
                    :disabled="!encashAllPass || encashForm.processing">
              <span v-if="encashForm.processing" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-cash-coin me-1"></i>
              Pay Cash — Mark Used
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         DEPOSIT MODAL — Account Transfer (T+1 Clearing)
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="showDeposit" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <div class="modal-header text-white" style="background:#0B2447">
            <h5 class="modal-title">
              <i class="bi bi-bank me-2"></i>Deposit to Account
              <span class="font-monospace ms-2 opacity-75" style="font-size:.85rem">{{ selected?.cheque_number }}</span>
            </h5>
            <button type="button" class="btn-close btn-close-white" @click="showDeposit = false"></button>
          </div>

          <div class="modal-body">
            <!-- Cheque Info -->
            <div class="row g-2 mb-3 p-3 rounded cheque-info-panel">
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Cheque No</div>
                <div class="fw-bold font-monospace small">{{ selected?.cheque_number }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Status</div>
                <span :class="leafStatusClass(selected?.status)" class="badge">{{ fmtStatus(selected?.status) }}</span>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Issue Date</div>
                <div class="small">{{ fmtDate(selected?.issue_date) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Expiry Date</div>
                <div class="small" :class="isExpired(selected?.expiry_date) ? 'text-danger fw-bold' : ''">
                  {{ fmtDate(selected?.expiry_date) }}
                </div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Drawer Name</div>
                <div class="small fw-semibold">{{ book.customer?.name }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Drawer Account</div>
                <div class="small font-monospace">{{ book.account?.account_number }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Current Balance</div>
                <div class="small fw-semibold text-success">{{ fmt(book.account?.balance) }}</div>
              </div>
              <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.72rem;text-transform:uppercase;letter-spacing:.5px">Cheque Book</div>
                <div class="small font-monospace">{{ book.book_number }}</div>
              </div>
            </div>

            <!-- Verification Checklist -->
            <div class="mb-3">
              <div class="fw-semibold small mb-2 section-heading-brand">
                <i class="bi bi-shield-check me-1"></i>Verification Checks
              </div>
              <div class="row g-1">
                <div v-for="chk in depositChecks" :key="chk.label" class="col-md-6">
                  <div class="d-flex align-items-center gap-2 px-2 py-1 rounded"
                       :class="chk.pass ? 'check-row-pass' : 'check-row-fail'">
                    <i class="bi fw-bold" :class="chk.pass ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"></i>
                    <span class="small" :class="chk.pass ? 'text-success' : 'text-danger'">{{ chk.label }}</span>
                  </div>
                </div>
                <!-- Signature verified -->
                <div class="col-md-6">
                  <label class="d-flex align-items-center gap-2 px-2 py-1 rounded cursor-pointer"
                         :class="signatureVerified ? 'check-row-pass' : 'check-row-fail'">
                    <i class="bi fw-bold" :class="signatureVerified ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger'"></i>
                    <input type="checkbox" v-model="signatureVerified" class="d-none">
                    <span class="small" :class="signatureVerified ? 'text-success' : 'text-danger'">
                      Signature verified {{ signatureVerified ? '✔' : '(tick to confirm)' }}
                    </span>
                  </label>
                </div>
              </div>
            </div>

            <hr class="my-3">

            <!-- Form -->
            <form @submit.prevent="submitDeposit" id="deposit-form">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="depositForm.amount" type="number" step="0.01" min="0.01"
                           class="form-control" :class="depositForm.errors.amount ? 'is-invalid' : ''"
                           :disabled="!depositAllPass" required>
                    <div class="invalid-feedback">{{ depositForm.errors.amount }}</div>
                  </div>
                  <div v-if="book.account" class="form-text">
                    Available: <strong>{{ fmt(availableBalance) }}</strong>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Payee Name</label>
                  <input v-model="depositForm.payee_name" type="text" class="form-control"
                         :disabled="!depositAllPass" placeholder="Optional — name on cheque">
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Beneficiary Account <span class="text-danger">*</span></label>
                  <input v-model="benSearch" type="text" class="form-control"
                         :class="depositForm.errors.beneficiary_account_id ? 'is-invalid' : ''"
                         :disabled="!depositAllPass"
                         placeholder="Search by account number or customer name…">
                  <div class="invalid-feedback">{{ depositForm.errors.beneficiary_account_id }}</div>
                  <div v-if="benSearch.length >= 2 && benResults.length && depositAllPass"
                       class="list-group mt-1 shadow-sm"
                       style="max-height:180px;overflow-y:auto;position:relative;z-index:9999">
                    <button v-for="acc in benResults" :key="acc.id" type="button"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center small"
                            :class="depositForm.beneficiary_account_id === acc.id ? 'active' : ''"
                            @click="selectBeneficiary(acc)">
                      <span class="font-monospace">{{ acc.account_number }}</span>
                      <span class="text-muted">{{ acc.customer?.name }} · {{ fmt(acc.balance) }}</span>
                    </button>
                  </div>
                  <div v-if="selectedBen" class="mt-2 alert alert-info py-2 small">
                    <i class="bi bi-check-circle me-1"></i>
                    <strong class="font-monospace">{{ selectedBen.account_number }}</strong>
                    — {{ selectedBen.customer?.name }}
                    <span class="ms-1 text-muted">(Balance: {{ fmt(selectedBen.balance) }})</span>
                  </div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Notes</label>
                  <textarea v-model="depositForm.notes" class="form-control" rows="2" :disabled="!depositAllPass"></textarea>
                </div>
              </div>

              <div v-if="depositAllPass" class="alert alert-info py-2 small mt-3 mb-0">
                <i class="bi bi-clock me-1"></i>
                Drawer will be debited immediately. Beneficiary account credited after <strong>T+1 clearing</strong>.
              </div>
            </form>

            <div v-if="!depositAllPass" class="alert alert-warning py-2 small mt-3 mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>
              All verification checks must pass before this cheque can be processed.
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-light" @click="showDeposit = false">Cancel</button>
            <button type="submit" form="deposit-form" class="btn btn-primary"
                    :disabled="!depositAllPass || !depositForm.beneficiary_account_id || depositForm.processing">
              <span v-if="depositForm.processing" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-bank me-1"></i>
              Submit for Clearing
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STOP PAYMENT MODAL
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="showCancel" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger">
              <i class="bi bi-slash-circle me-2"></i>Stop Payment
            </h5>
            <button type="button" class="btn-close" @click="showCancel = false"></button>
          </div>
          <form @submit.prevent="submitCancel">
            <div class="modal-body">
              <p class="small text-muted mb-3">
                Cancel cheque <strong class="font-monospace text-dark">{{ selected?.cheque_number }}</strong>
                — this action cannot be undone.
              </p>
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea v-model="actionForm.reason" class="form-control" rows="3"
                          :class="actionForm.errors.reason ? 'is-invalid' : ''"
                          placeholder="e.g. Lost cheque, customer request…" required></textarea>
                <div class="invalid-feedback">{{ actionForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary" @click="showCancel = false">Back</button>
              <button type="submit" class="btn btn-danger" :disabled="actionForm.processing">
                <span v-if="actionForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Confirm Stop Payment
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         BOUNCE MODAL
    ══════════════════════════════════════════════════════════════════ -->
    <div v-if="showBounce" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-warning">
              <i class="bi bi-x-circle me-2"></i>Mark Cheque as Bounced
            </h5>
            <button type="button" class="btn-close" @click="showBounce = false"></button>
          </div>
          <form @submit.prevent="submitBounce">
            <div class="modal-body">
              <div class="alert alert-warning py-2 small mb-3">
                <i class="bi bi-arrow-counterclockwise me-1"></i>
                Drawer account will be credited back <strong>{{ fmt(selected?.amount) }}</strong>.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Bounce Reason <span class="text-danger">*</span></label>
                <textarea v-model="actionForm.reason" class="form-control" rows="3"
                          :class="actionForm.errors.reason ? 'is-invalid' : ''"
                          placeholder="e.g. Insufficient funds, signature mismatch…" required></textarea>
                <div class="invalid-feedback">{{ actionForm.errors.reason }}</div>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary" @click="showBounce = false">Cancel</button>
              <button type="submit" class="btn btn-warning" :disabled="actionForm.processing">
                <span v-if="actionForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Bounce Cheque
              </button>
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
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
  book:         { type: Object, required: true },
  all_accounts: { type: Array,  default: () => [] },
  server_date:  { type: String, default: () => new Date().toISOString().split('T')[0] },
})

// ── State ─────────────────────────────────────────────────────────────────────
const selected         = ref(null)
const showEncash       = ref(false)
const showDeposit      = ref(false)
const showCancel       = ref(false)
const showBounce       = ref(false)
const signatureVerified = ref(false)
const benSearch        = ref('')
const selectedBen      = ref(null)

const encashForm  = useForm({ amount: '', payee_name: '', notes: '' })
const depositForm = useForm({ amount: '', payee_name: '', beneficiary_account_id: '', notes: '' })
const actionForm  = useForm({ reason: '' })

// ── Computed helpers ──────────────────────────────────────────────────────────
const leafPct = computed(() => {
  if (!props.book.total_leaves) return 0
  return Math.round((props.book.used_leaves / props.book.total_leaves) * 100)
})

const availableBalance = computed(() => {
  const bal = parseFloat(props.book.account?.balance ?? 0)
  const min = parseFloat(props.book.account?.minimum_balance ?? 0)
  return Math.max(bal - min, 0)
})

// ── Beneficiary search ────────────────────────────────────────────────────────
const benResults = computed(() => {
  if (benSearch.value.length < 2) return []
  const s = benSearch.value.toLowerCase()
  return props.all_accounts
    .filter(a => a.id !== props.book.account?.id)
    .filter(a => a.account_number.toLowerCase().includes(s) || a.customer?.name?.toLowerCase().includes(s))
    .slice(0, 20)
})

function selectBeneficiary(acc) {
  selectedBen.value = acc
  depositForm.beneficiary_account_id = acc.id
  benSearch.value = acc.account_number
}

// ── Verification checks ───────────────────────────────────────────────────────
function buildChecks(formAmount) {
  const chq     = selected.value
  const acc     = props.book.account
  const amount  = parseFloat(formAmount) || 0
  const balance = parseFloat(acc?.balance ?? 0)
  const minBal  = parseFloat(acc?.minimum_balance ?? 0)
  const available = Math.max(balance - minBal, 0)

  return [
    { label: 'Cheque exists',         pass: !!chq },
    { label: 'Status = Issued',       pass: chq?.status === 'issued' },
    { label: 'Not expired',           pass: !isExpired(chq?.expiry_date) },
    { label: 'Stop payment = No',     pass: chq?.status !== 'cancelled' },
    { label: 'Drawer account active', pass: acc?.status === 'active' },
    { label: 'Sufficient funds',      pass: amount > 0 ? available >= amount : available > 0 },
  ]
}

const encashChecks  = computed(() => buildChecks(encashForm.amount))
const depositChecks = computed(() => buildChecks(depositForm.amount))

// All system checks pass (signature is separate)
const encashSystemPass  = computed(() => encashChecks.value.every(c => c.pass))
const depositSystemPass = computed(() => depositChecks.value.every(c => c.pass))

// All checks including manual signature confirmation
const encashAllPass  = computed(() => encashSystemPass.value  && signatureVerified.value)
const depositAllPass = computed(() => depositSystemPass.value && signatureVerified.value)

// ── Modal openers ─────────────────────────────────────────────────────────────
function openEncash(chq) {
  selected.value     = chq
  signatureVerified.value = false
  encashForm.reset()
  showEncash.value   = true
}

function openDeposit(chq) {
  selected.value     = chq
  signatureVerified.value = false
  depositForm.reset()
  benSearch.value    = ''
  selectedBen.value  = null
  showDeposit.value  = true
}

function openCancel(chq) {
  selected.value = chq
  actionForm.reset()
  showCancel.value = true
}

function openBounce(chq) {
  selected.value = chq
  actionForm.reset()
  showBounce.value = true
}

// ── Submissions ───────────────────────────────────────────────────────────────
function submitClear(chq) {
  router.post(route('admin.cheques.clear', chq.id), {}, { preserveScroll: true })
}

function submitEncash() {
  encashForm.post(route('admin.cheques.encash', selected.value.id), {
    onSuccess: () => { showEncash.value = false },
  })
}

function submitDeposit() {
  depositForm.post(route('admin.cheques.deposit', selected.value.id), {
    onSuccess: () => { showDeposit.value = false },
  })
}

function submitCancel() {
  actionForm.post(route('admin.cheques.cancel', selected.value.id), {
    onSuccess: () => { showCancel.value = false },
  })
}

function submitBounce() {
  actionForm.post(route('admin.cheques.bounce', selected.value.id), {
    onSuccess: () => { showBounce.value = false },
  })
}

// ── Formatting ────────────────────────────────────────────────────────────────
function isExpired(d) {
  return d && d < props.server_date
}

function leafStatusClass(s) {
  return {
    issued:            'bg-primary',
    used:              'bg-secondary',
    pending_clearance: 'bg-warning text-dark',
    cleared:           'bg-success',
    bounced:           'bg-danger',
    cancelled:         'bg-dark',
    expired:           'bg-secondary',
  }[s] ?? 'bg-secondary'
}

function rowClass(s) {
  return {
    used:              'table-secondary',
    cleared:           'table-success',
    bounced:           'table-danger',
    cancelled:         'table-secondary',
    pending_clearance: 'table-warning',
  }[s] ?? ''
}

function fmtStatus(s) {
  return {
    issued:            'Issued',
    used:              'Used (Cash)',
    pending_clearance: 'Pending Clearance',
    cleared:           'Cleared',
    bounced:           'Bounced',
    cancelled:         'Cancelled',
    expired:           'Expired',
  }[s] ?? (s ?? '—').replace(/_/g, ' ')
}

const fmt     = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—'
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
