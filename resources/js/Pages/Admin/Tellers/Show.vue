<template>
  <AdminLayout
    :title="till.till_name"
    :subtitle="`${till.teller?.name} — ${till.business_date}`"
    :breadcrumbs="[
      { label: 'Teller Operations', href: route('admin.tellers.index') },
      { label: till.till_name }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <!-- Supervisor: direct replenish -->
        <button v-if="till.status === 'open' && can('teller.transfer')" class="btn btn-outline-primary btn-sm" @click="showReplenish = true">
          <i class="bi bi-plus-circle me-1"></i>Replenish
        </button>
        <!-- Teller: request replenishment -->
        <button v-if="till.status === 'open' && !can('teller.transfer') && can('teller.open-till')" class="btn btn-outline-warning btn-sm" @click="showRequestReplen = true">
          <i class="bi bi-send me-1"></i>Request Replenishment
        </button>
        <button v-if="till.status === 'open' && can('teller.transfer')" class="btn btn-outline-secondary btn-sm" @click="showReturn = true">
          <i class="bi bi-arrow-return-left me-1"></i>Return
        </button>
        <button v-if="till.status === 'open' && can('teller.transfer') && open_tills.length" class="btn btn-outline-info btn-sm" @click="showTransfer = true">
          <i class="bi bi-arrow-left-right me-1"></i>Transfer
        </button>
        <button v-if="till.status === 'open' && can('teller.close-till')" class="btn btn-danger btn-sm" @click="showClose = true">
          <i class="bi bi-x-circle me-1"></i>Close Till
        </button>
        <Link :href="route('admin.tellers.index')" class="btn btn-light btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>

    <!-- Large variance alert (shown when closed with significant discrepancy) -->
    <div v-if="till.status === 'closed' && Math.abs(till.variance ?? 0) >= 500"
         class="alert alert-danger d-flex align-items-start gap-3 mb-4">
      <i class="bi bi-exclamation-octagon-fill fs-4 flex-shrink-0 mt-1"></i>
      <div>
        <div class="fw-bold">Large Variance Detected</div>
        <div class="small">
          Till closed with a variance of <strong>{{ till.variance >= 0 ? '+' : '' }}{{ fmt(till.variance) }}</strong>.
          This exceeds the $500 tolerance threshold and requires supervisor review.
          Expected: <strong>{{ fmt(till.expected_balance) }}</strong> —
          Reported: <strong>{{ fmt(till.closing_balance) }}</strong>.
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Left: Summary -->
      <div class="col-lg-4">
        <!-- Status card -->
        <div class="card shadow-sm mb-3" :class="till.status === 'open' ? 'border-success' : 'border-secondary'">
          <div class="card-body text-center py-4">
            <div class="mb-2">
              <span class="badge px-3 py-2 fs-6" :class="statusBadge(till.status)">{{ till.status.toUpperCase() }}</span>
            </div>
            <div class="display-6 fw-bold text-primary">{{ fmt(till.current_balance) }}</div>
            <div class="text-muted small mt-1">Current Balance</div>
          </div>
        </div>

        <!-- Balance breakdown -->
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-info-circle me-1 text-primary"></i>Balance Details</div>
          <div class="card-body p-0">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <td class="text-muted ps-3">Opening Balance</td>
                  <td class="pe-3 fw-semibold">{{ fmt(till.opening_balance) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Expected Balance</td>
                  <td class="pe-3 fw-semibold text-primary">{{ fmt(till.expected_balance) }}</td>
                </tr>
                <tr v-if="till.status === 'closed'">
                  <td class="text-muted ps-3">Closing Balance</td>
                  <td class="pe-3 fw-semibold">{{ fmt(till.closing_balance) }}</td>
                </tr>
                <tr v-if="till.status === 'closed'">
                  <td class="text-muted ps-3">Variance</td>
                  <td class="pe-3 fw-semibold" :class="till.variance < 0 ? 'text-danger' : till.variance > 0 ? 'text-warning' : 'text-success'">
                    {{ till.variance >= 0 ? '+' : '' }}{{ fmt(till.variance) }}
                    <i v-if="till.variance === 0" class="bi bi-check-circle-fill text-success ms-1"></i>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Business Date</td>
                  <td class="pe-3">{{ till.business_date }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Opened At</td>
                  <td class="pe-3">{{ fmtTime(till.opened_at) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Opened By</td>
                  <td class="pe-3">{{ till.opened_by?.name ?? '—' }}</td>
                </tr>
                <tr v-if="till.closed_at">
                  <td class="text-muted ps-3">Closed At</td>
                  <td class="pe-3">{{ fmtTime(till.closed_at) }}</td>
                </tr>
                <tr v-if="till.closed_by">
                  <td class="text-muted ps-3">Closed By</td>
                  <td class="pe-3">{{ till.closed_by?.name }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Cash Movements -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-arrow-up-down me-1 text-primary"></i>Cash Movements</div>
          <div class="card-body">
            <div v-if="!till.cash_movements?.length" class="text-muted small text-center py-2">No cash movements.</div>
            <div v-for="mv in till.cash_movements" :key="mv.id" class="d-flex align-items-center gap-2 mb-2 pb-2 border-bottom">
              <i class="bi" :class="isInflow(mv.type) ? 'bi-arrow-down-circle-fill text-success' : 'bi-arrow-up-circle-fill text-danger'" style="font-size:1.2rem"></i>
              <div class="flex-grow-1">
                <div class="small fw-semibold">{{ mvLabel(mv.type) }}</div>
                <div class="text-muted" style="font-size:.7rem">
                  {{ mv.processed_by?.name }} · {{ fmtTime(mv.created_at) }}
                  <span v-if="mv.related_till"> → {{ mv.related_till?.till_name }}</span>
                </div>
              </div>
              <div class="fw-semibold small" :class="isInflow(mv.type) ? 'text-success' : 'text-danger'">
                {{ isInflow(mv.type) ? '+' : '-' }}{{ fmt(mv.amount) }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Transactions -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-receipt me-1 text-primary"></i>Transactions ({{ transactions.length }})</span>
            <div class="d-flex gap-3 small">
              <span class="text-success"><i class="bi bi-arrow-down me-1"></i>{{ txnCount('deposit') }} deposits</span>
              <span class="text-danger"><i class="bi bi-arrow-up me-1"></i>{{ txnCount('withdrawal') }} withdrawals</span>
              <span class="text-primary"><i class="bi bi-arrow-left-right me-1"></i>{{ txnCount('transfer') }} transfers</span>
            </div>
          </div>
          <div class="card-body p-0">
            <div v-if="!transactions.length" class="text-center text-muted py-5">
              <i class="bi bi-receipt d-block mb-2" style="font-size:2rem;opacity:.3"></i>No transactions yet.
            </div>
            <div class="table-responsive" v-else>
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Time</th>
                    <th>Reference</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end pe-3">Balance After</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="txn in transactions" :key="txn.id" style="cursor:pointer" @click="router.visit(route('admin.transactions.show', txn.id))">
                    <td class="ps-3 small text-muted">{{ fmtTime(txn.created_at) }}</td>
                    <td class="font-monospace small">{{ txn.reference }}</td>
                    <td class="small">
                      <div>{{ txn.account?.account_number }}</div>
                      <div class="text-muted" style="font-size:.7rem">{{ txn.account?.customer?.name }}</div>
                    </td>
                    <td>
                      <span class="badge" :class="typeBadge(txn.type)">{{ typeLabel(txn.type) }}</span>
                    </td>
                    <td class="text-end fw-semibold small" :class="txn.direction === 'credit' ? 'text-success' : 'text-danger'">
                      {{ txn.direction === 'credit' ? '+' : '-' }}{{ fmt(txn.amount) }}
                    </td>
                    <td class="text-end small pe-3">{{ fmt(txn.balance_after) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Close Till Modal -->
    <div v-if="showClose" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Close Till</h5>
            <button type="button" class="btn-close" @click="showClose = false"></button>
          </div>
          <form @submit.prevent="submitClose">
            <div class="modal-body">
              <div class="alert alert-info small">
                System expected balance: <strong>{{ fmt(till.expected_balance) }}</strong>. Count the physical cash and enter the actual amount below.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Actual Closing Balance (Counted) <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="closeForm.closing_balance" type="number" min="0" step="0.01"
                    class="form-control form-control-lg"
                    :class="closeForm.errors.closing_balance ? 'is-invalid' : ''"
                    placeholder="0.00" />
                  <div v-if="closeForm.errors.closing_balance" class="invalid-feedback">{{ closeForm.errors.closing_balance }}</div>
                </div>
                <div v-if="closeForm.closing_balance" class="mt-2 small">
                  Variance:
                  <strong :class="variance < 0 ? 'text-danger' : variance > 0 ? 'text-warning' : 'text-success'">
                    {{ variance >= 0 ? '+' : '' }}{{ fmt(variance) }}
                  </strong>
                  <div v-if="Math.abs(variance) >= 500" class="alert alert-danger py-1 mt-2 mb-0">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Variance exceeds $500 — supervisor approval required after closing.
                  </div>
                </div>
              </div>
              <div>
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="closeForm.notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showClose = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="closeForm.processing">
                <span v-if="closeForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Close Till
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Replenish Modal -->
    <div v-if="showReplenish" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-success"></i>Replenish Till</h5>
            <button type="button" class="btn-close" @click="showReplenish = false"></button>
          </div>
          <form @submit.prevent="submitReplenish">
            <div class="modal-body">
              <p class="small text-muted">Add cash to <strong>{{ till.till_name }}</strong> from the vault.</p>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="movForm.amount" type="number" min="0.01" step="0.01" class="form-control"
                    :class="movForm.errors.amount ? 'is-invalid' : ''" placeholder="0.00" />
                  <div v-if="movForm.errors.amount" class="invalid-feedback">{{ movForm.errors.amount }}</div>
                </div>
              </div>
              <div><label class="form-label fw-semibold">Notes</label>
                <textarea v-model="movForm.notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReplenish = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="movForm.processing">Replenish</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Return Modal -->
    <div v-if="showReturn" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-arrow-return-left me-2 text-secondary"></i>Return Cash to Vault</h5>
            <button type="button" class="btn-close" @click="showReturn = false"></button>
          </div>
          <form @submit.prevent="submitReturn">
            <div class="modal-body">
              <p class="small text-muted">Current till balance: <strong>{{ fmt(till.current_balance) }}</strong></p>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount to Return <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="retForm.amount" type="number" min="0.01" step="0.01" class="form-control"
                    :class="retForm.errors.amount ? 'is-invalid' : ''" placeholder="0.00" />
                  <div v-if="retForm.errors.amount" class="invalid-feedback">{{ retForm.errors.amount }}</div>
                </div>
              </div>
              <div><label class="form-label fw-semibold">Notes</label>
                <textarea v-model="retForm.notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReturn = false">Cancel</button>
              <button type="submit" class="btn btn-secondary" :disabled="retForm.processing">Return to Vault</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Transfer Between Tills Modal -->
    <div v-if="showTransfer" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-arrow-left-right me-2 text-info"></i>Transfer Between Tills</h5>
            <button type="button" class="btn-close" @click="showTransfer = false"></button>
          </div>
          <form @submit.prevent="submitTransfer">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Destination Till <span class="text-danger">*</span></label>
                <select v-model="trfForm.to_till_id" class="form-select">
                  <option value="">— Select Till —</option>
                  <option v-for="t in open_tills" :key="t.id" :value="t.id">
                    {{ t.till_name }} ({{ t.teller?.name }}) — {{ fmt(t.current_balance) }}
                  </option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="trfForm.amount" type="number" min="0.01" step="0.01" class="form-control" placeholder="0.00" />
                </div>
              </div>
              <div><label class="form-label fw-semibold">Notes</label>
                <textarea v-model="trfForm.notes" class="form-control" rows="2"></textarea></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showTransfer = false">Cancel</button>
              <button type="submit" class="btn btn-info text-white" :disabled="trfForm.processing">Transfer</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Replenishment Requests Panel -->
    <div v-if="replenishment_requests.length" class="mt-4">
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-arrow-repeat me-2 text-warning"></i>Replenishment Requests</span>
          <span v-if="pendingRequests.length" class="badge bg-warning text-dark">{{ pendingRequests.length }} Pending</span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Requested By</th>
                <th class="text-end">Amount</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th v-if="can('teller.transfer')">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="req in replenishment_requests" :key="req.id">
                <td>{{ req.requested_by?.name }}</td>
                <td class="text-end font-monospace fw-bold">{{ fmt(req.amount) }}</td>
                <td class="text-muted small">{{ req.reason ?? '—' }}</td>
                <td>
                  <span :class="{ 'badge bg-warning text-dark': req.status==='pending', 'badge bg-success': req.status==='approved', 'badge bg-danger': req.status==='rejected' }">
                    {{ req.status }}
                  </span>
                </td>
                <td class="text-muted small">{{ req.reviewed_by?.name ?? '—' }}</td>
                <td v-if="can('teller.transfer')">
                  <div v-if="req.status === 'pending'" class="d-flex gap-1">
                    <button class="btn btn-xs btn-success py-0 px-2" style="font-size:.75rem" @click="openApprove(req)">
                      Approve
                    </button>
                    <button class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:.75rem" @click="openReject(req)">
                      Reject
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Request Replenishment Modal (Teller) -->
    <div v-if="showRequestReplen" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-send me-2 text-warning"></i>Request Replenishment</h5>
            <button type="button" class="btn-close" @click="showRequestReplen = false"></button>
          </div>
          <form @submit.prevent="submitRequest">
            <div class="modal-body">
              <p class="small text-muted">Submit a request for additional cash. Your supervisor will approve and transfer from the vault.</p>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount Needed <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="reqForm.amount" type="number" min="1" step="0.01" class="form-control" placeholder="0.00" required>
                </div>
                <div v-if="reqForm.errors.amount" class="text-danger small mt-1">{{ reqForm.errors.amount }}</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason</label>
                <textarea v-model="reqForm.reason" class="form-control" rows="2" placeholder="e.g. Running low on small denominations…"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showRequestReplen = false">Cancel</button>
              <button type="submit" class="btn btn-warning" :disabled="reqForm.processing">Submit Request</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Approve Replenishment Modal (Supervisor) -->
    <div v-if="showApprove" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title text-success"><i class="bi bi-check-circle me-2"></i>Approve Replenishment</h5>
            <button type="button" class="btn-close" @click="showApprove = false"></button>
          </div>
          <form @submit.prevent="submitApprove">
            <div class="modal-body">
              <div class="alert alert-info py-2 small">
                Approving will immediately transfer cash from the vault to this till.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="approveForm.notes" class="form-control" rows="2"></textarea>
              </div>
              <input type="hidden" v-model="approveForm.request_id">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showApprove = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="approveForm.processing">Approve & Transfer</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Reject Replenishment Modal (Supervisor) -->
    <div v-if="showReject" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject Replenishment</h5>
            <button type="button" class="btn-close" @click="showReject = false"></button>
          </div>
          <form @submit.prevent="submitReject">
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                <textarea v-model="rejectForm.reason" class="form-control" rows="3" required
                          placeholder="e.g. Vault balance insufficient…"></textarea>
                <div v-if="rejectForm.errors.reason" class="text-danger small mt-1">{{ rejectForm.errors.reason }}</div>
              </div>
              <input type="hidden" v-model="rejectForm.request_id">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showReject = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="rejectForm.processing">Reject</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  till:                   { type: Object, required: true },
  transactions:           { type: Array,  default: () => [] },
  open_tills:             { type: Array,  default: () => [] },
  replenishment_requests: { type: Array,  default: () => [] },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const showClose          = ref(false);
const showReplenish      = ref(false);
const showReturn         = ref(false);
const showTransfer       = ref(false);
const showRequestReplen  = ref(false);
const selectedReqId      = ref(null);
const showApprove        = ref(false);
const showReject         = ref(false);

const closeForm   = useForm({ closing_balance: '', notes: '' });
const movForm     = useForm({ amount: '', notes: '' });
const retForm     = useForm({ amount: '', notes: '' });
const trfForm     = useForm({ to_till_id: '', amount: '', notes: '' });
const reqForm     = useForm({ amount: '', reason: '' });
const approveForm = useForm({ request_id: '', notes: '' });
const rejectForm  = useForm({ request_id: '', reason: '' });

const variance = computed(() =>
  closeForm.closing_balance ? parseFloat(closeForm.closing_balance) - parseFloat(props.till.expected_balance) : 0
);

const pendingRequests = computed(() => props.replenishment_requests.filter(r => r.status === 'pending'))

function openApprove(req) { selectedReqId.value = req.id; approveForm.request_id = req.id; approveForm.notes = ''; showApprove.value = true }
function openReject(req)  { selectedReqId.value = req.id; rejectForm.request_id  = req.id; rejectForm.reason = ''; showReject.value  = true }

const submitClose     = () => closeForm.post(route('admin.tellers.close',                props.till.id), { onSuccess: () => { showClose.value = false; } });
const submitReplenish = () => movForm.post(route('admin.tellers.replenish',              props.till.id), { onSuccess: () => { showReplenish.value = false; movForm.reset(); } });
const submitReturn    = () => retForm.post(route('admin.tellers.return',                 props.till.id), { onSuccess: () => { showReturn.value = false; retForm.reset(); } });
const submitTransfer  = () => trfForm.post(route('admin.tellers.transfer',               props.till.id), { onSuccess: () => { showTransfer.value = false; trfForm.reset(); } });
const submitRequest   = () => reqForm.post(route('admin.tellers.request-replenishment',  props.till.id), { onSuccess: () => { showRequestReplen.value = false; reqForm.reset(); } });
const submitApprove   = () => approveForm.post(route('admin.tellers.approve-replenishment', props.till.id), { onSuccess: () => { showApprove.value = false; } });
const submitReject    = () => rejectForm.post(route('admin.tellers.reject-replenishment',   props.till.id), { onSuccess: () => { showReject.value = false; } });

const txnCount    = (type) => props.transactions.filter(t => t.type === type).length;
const isInflow    = (type) => ['replenishment', 'transfer_in'].includes(type);
const mvLabel     = (t) => ({ replenishment: 'Replenishment', return: 'Return to Vault', transfer_in: 'Transfer In', transfer_out: 'Transfer Out' }[t] ?? t);
const typeLabel   = (t) => ({ deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer', reversal: 'Reversal' }[t] ?? t);
const typeBadge   = (t) => ({ deposit: 'bg-success-subtle text-success', withdrawal: 'bg-danger-subtle text-danger', transfer: 'bg-primary-subtle text-primary', reversal: 'bg-warning-subtle text-warning' }[t] ?? 'bg-light text-muted');
const statusBadge = (s) => ({ open: 'bg-success', closed: 'bg-secondary', suspended: 'bg-warning text-dark' }[s] ?? 'bg-light');

const fmt     = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtTime = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : '—';
</script>
