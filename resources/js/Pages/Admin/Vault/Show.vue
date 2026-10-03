<template>
  <AdminLayout
    title="Vault"
    :subtitle="`${vault.branch?.name ?? 'Branch'} — ${vault.currency}`"
    :breadcrumbs="[{ label: 'Vault' }]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <form v-if="vault.status !== 'open'" @submit.prevent="openVault">
          <button type="submit" class="btn btn-success btn-sm">
            <i class="bi bi-unlock me-1"></i>Open Vault
          </button>
        </form>
        <form v-else @submit.prevent="closeVault">
          <button type="submit" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-lock me-1"></i>Close Vault
          </button>
        </form>
        <button v-if="can('vault.cash-in') && vault.status === 'open'" class="btn btn-primary btn-sm" @click="showCashIn = true">
          <i class="bi bi-plus-circle me-1"></i>Cash In
        </button>
        <button v-if="can('vault.cash-out') && vault.status === 'open'" class="btn btn-outline-danger btn-sm" @click="showCashOut = true">
          <i class="bi bi-dash-circle me-1"></i>Cash Out
        </button>
      </div>
    </template>

    <!-- Vault Balance Hero -->
    <div class="card shadow-sm mb-4 border-0" :style="vault.status === 'open' ? 'background:linear-gradient(135deg,#0B2447,#14395B)' : 'background:linear-gradient(135deg,#4b5563,#374151)'">
      <div class="card-body text-white py-4 px-4 d-flex align-items-center gap-4">
        <i :class="vault.status === 'open' ? 'bi bi-safe2' : 'bi bi-safe'" style="font-size:3rem;opacity:.8"></i>
        <div class="flex-grow-1">
          <div class="small opacity-75 mb-1">Current Vault Balance</div>
          <div class="display-5 fw-bold">{{ fmt(vault.balance) }}</div>
          <div class="small opacity-75 mt-1">
            Last updated {{ fmtAgo(vault.updated_at) }}
            <span v-if="vault.last_updated_by"> by {{ vault.last_updated_by?.name }}</span>
          </div>
        </div>
        <div class="text-end">
          <span class="badge px-3 py-2 fs-6" :class="vault.status === 'open' ? 'bg-success' : 'bg-secondary'">
            <i :class="vault.status === 'open' ? 'bi bi-unlock-fill' : 'bi bi-lock-fill'" class="me-1"></i>
            {{ vault.status === 'open' ? 'Open' : 'Closed' }}
          </span>
          <div class="small opacity-75 mt-1">
            <span v-if="vault.status === 'open' && vault.opened_by">
              Opened by {{ vault.opened_by?.name }}
            </span>
            <span v-else-if="vault.status !== 'open' && vault.closed_by">
              Closed by {{ vault.closed_by?.name }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Today's Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3 border-success">
          <div class="fw-bold fs-5 text-success">+{{ fmt(stats.cash_in_today) }}</div>
          <div class="text-muted small">Cash In Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3 border-danger">
          <div class="fw-bold fs-5 text-danger">-{{ fmt(stats.cash_out_today) }}</div>
          <div class="text-muted small">Cash Out Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3 border-primary">
          <div class="fw-bold fs-5 text-primary">-{{ fmt(stats.teller_transfers_today) }}</div>
          <div class="text-muted small">To Tellers Today</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3 border-secondary">
          <div class="fw-bold fs-5 text-secondary">+{{ fmt(stats.teller_returns_today) }}</div>
          <div class="text-muted small">From Tellers Today</div>
        </div>
      </div>
    </div>

    <!-- Movement Log -->
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-list-ul me-1 text-primary"></i>Movement Log</span>
        <!-- Filters -->
        <div class="d-flex gap-2">
          <input type="date" :value="filters.date" @change="applyFilter('date', $event.target.value)"
            class="form-control form-control-sm" style="width:140px" />
          <select :value="filters.type" @change="applyFilter('type', $event.target.value)" class="form-select form-select-sm" style="width:180px">
            <option value="">All Types</option>
            <option value="cash_in">Cash In</option>
            <option value="cash_out">Cash Out</option>
            <option value="transfer_to_teller">To Teller</option>
            <option value="returned_from_teller">From Teller</option>
          </select>
          <button v-if="filters.date || filters.type" class="btn btn-sm btn-outline-secondary" @click="clearFilters">
            <i class="bi bi-x"></i>
          </button>
        </div>
      </div>

      <div class="card-body p-0">
        <div v-if="!movements.data.length" class="text-center text-muted py-5">
          <i class="bi bi-journal d-block mb-2" style="font-size:2rem;opacity:.3"></i>No vault movements found.
        </div>

        <div class="table-responsive" v-else>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Date / Time</th>
                <th>Reference</th>
                <th>Type</th>
                <th>Till / Notes</th>
                <th class="text-end">Amount</th>
                <th class="text-end">Balance Before</th>
                <th class="text-end pe-3">Balance After</th>
                <th>By</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="mv in movements.data" :key="mv.id">
                <td class="ps-3 small text-muted">{{ fmtDt(mv.created_at) }}</td>
                <td class="font-monospace small">{{ mv.reference ?? '—' }}</td>
                <td>
                  <span class="badge" :class="typeBadge(mv.type)">{{ typeLabel(mv.type) }}</span>
                </td>
                <td class="small">
                  <span v-if="mv.teller_till">{{ mv.teller_till.till_name }} ({{ mv.teller_till.teller?.name }})</span>
                  <span v-else class="text-muted fst-italic">{{ mv.notes ?? '—' }}</span>
                </td>
                <td class="text-end fw-semibold small" :class="isInflow(mv.type) ? 'text-success' : 'text-danger'">
                  {{ isInflow(mv.type) ? '+' : '-' }}{{ fmt(mv.amount) }}
                </td>
                <td class="text-end small text-muted">{{ fmt(mv.balance_before) }}</td>
                <td class="text-end small fw-semibold pe-3">{{ fmt(mv.balance_after) }}</td>
                <td class="small text-muted">{{ mv.processed_by?.name }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="movements.last_page > 1" class="d-flex justify-content-center py-3 gap-1">
          <button v-if="movements.prev_page_url" class="btn btn-sm btn-outline-secondary" @click="goPage(movements.current_page - 1)">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="btn btn-sm btn-primary disabled">{{ movements.current_page }} / {{ movements.last_page }}</span>
          <button v-if="movements.next_page_url" class="btn btn-sm btn-outline-secondary" @click="goPage(movements.current_page + 1)">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Cash In Modal -->
    <div v-if="showCashIn" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-success"></i>Deposit Cash into Vault</h5>
            <button type="button" class="btn-close" @click="showCashIn = false"></button>
          </div>
          <form @submit.prevent="submitCashIn">
            <div class="modal-body">
              <div class="alert alert-success-subtle border-success small">
                <i class="bi bi-info-circle me-1"></i>Record physical cash being deposited into the vault (e.g., from head office, ATM float).
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="cashInForm.amount" type="number" min="0.01" step="0.01" class="form-control form-control-lg"
                    :class="cashInForm.errors.amount ? 'is-invalid' : ''" placeholder="0.00" autofocus />
                  <div v-if="cashInForm.errors.amount" class="invalid-feedback">{{ cashInForm.errors.amount }}</div>
                </div>
                <div v-if="cashInForm.amount" class="small text-muted mt-1">
                  Vault balance will become: <strong>{{ fmt(parseFloat(vault.balance) + parseFloat(cashInForm.amount || 0)) }}</strong>
                </div>
              </div>
              <div>
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="cashInForm.notes" class="form-control" rows="2" placeholder="Source of cash, ref number, etc."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showCashIn = false">Cancel</button>
              <button type="submit" class="btn btn-success" :disabled="cashInForm.processing">
                <span v-if="cashInForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Deposit into Vault
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Cash Out Modal -->
    <div v-if="showCashOut" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-dash-circle me-2 text-danger"></i>Withdraw Cash from Vault</h5>
            <button type="button" class="btn-close" @click="showCashOut = false"></button>
          </div>
          <form @submit.prevent="submitCashOut">
            <div class="modal-body">
              <div class="alert alert-danger-subtle border-danger small">
                <i class="bi bi-exclamation-triangle me-1"></i>Record physical cash being removed from the vault. Available: <strong>{{ fmt(vault.balance) }}</strong>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="cashOutForm.amount" type="number" min="0.01" step="0.01" class="form-control form-control-lg"
                    :class="cashOutForm.errors.amount ? 'is-invalid' : ''" placeholder="0.00" />
                  <div v-if="cashOutForm.errors.amount" class="invalid-feedback">{{ cashOutForm.errors.amount }}</div>
                </div>
                <div v-if="cashOutForm.amount" class="small mt-1" :class="parseFloat(cashOutForm.amount) > parseFloat(vault.balance) ? 'text-danger' : 'text-muted'">
                  <span v-if="parseFloat(cashOutForm.amount) > parseFloat(vault.balance)">
                    <i class="bi bi-exclamation-triangle me-1"></i>Exceeds vault balance!
                  </span>
                  <span v-else>Vault balance will become: <strong>{{ fmt(parseFloat(vault.balance) - parseFloat(cashOutForm.amount || 0)) }}</strong></span>
                </div>
              </div>
              <div>
                <label class="form-label fw-semibold">Notes</label>
                <textarea v-model="cashOutForm.notes" class="form-control" rows="2" placeholder="Reason for withdrawal, etc."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showCashOut = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="cashOutForm.processing">
                <span v-if="cashOutForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Withdraw from Vault
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
import { router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  vault:     { type: Object, required: true },
  movements: { type: Object, required: true },
  stats:     { type: Object, default: () => ({}) },
  filters:   { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

function openVault()  { router.post(route('admin.vault.open'),  {}, { preserveScroll: true }) }
function closeVault() { router.post(route('admin.vault.close'), {}, { preserveScroll: true }) }

const showCashIn  = ref(false);
const showCashOut = ref(false);

const cashInForm  = useForm({ amount: '', notes: '' });
const cashOutForm = useForm({ amount: '', notes: '' });

const submitCashIn = () => cashInForm.post(route('admin.vault.cash-in'), {
  onSuccess: () => { showCashIn.value = false; cashInForm.reset(); },
});

const submitCashOut = () => cashOutForm.post(route('admin.vault.cash-out'), {
  onSuccess: () => { showCashOut.value = false; cashOutForm.reset(); },
});

const applyFilter = (key, val) => router.get(route('admin.vault.show'), { ...props.filters, [key]: val });
const clearFilters = () => router.get(route('admin.vault.show'));
const goPage = (page) => router.get(route('admin.vault.show'), { ...props.filters, page });

const isInflow  = (t) => ['cash_in', 'returned_from_teller'].includes(t);
const typeLabel = (t) => ({ cash_in: 'Cash In', cash_out: 'Cash Out', transfer_to_teller: 'To Teller', returned_from_teller: 'From Teller' }[t] ?? t);
const typeBadge = (t) => ({
  cash_in:              'bg-success-subtle text-success',
  cash_out:             'bg-danger-subtle text-danger',
  transfer_to_teller:   'bg-primary-subtle text-primary',
  returned_from_teller: 'bg-secondary-subtle text-secondary',
}[t] ?? 'bg-light text-muted');

const fmt    = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtDt  = (d) => d ? new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }) : '—';
const fmtAgo = (d) => {
  if (!d) return '';
  const diff = Math.floor((Date.now() - new Date(d)) / 1000);
  if (diff < 60)   return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return new Date(d).toLocaleDateString();
};
</script>
