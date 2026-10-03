<template>
  <AdminLayout
    title="New Loan Application"
    subtitle="Submit a loan application for a customer"
    :breadcrumbs="[
      { label: 'Loans', href: route('admin.loans.index') },
      { label: 'New Application' }
    ]"
  >
    <div class="row g-4">
      <!-- Form -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-file-earmark-text me-1 text-primary"></i>Loan Application Details
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">

              <!-- Customer -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Customer <span class="text-danger">*</span></label>
                <div class="position-relative">
                  <input v-model="customerSearch" type="text" class="form-control"
                    placeholder="Type customer name or number…"
                    :class="form.errors.customer_id ? 'is-invalid' : ''"
                    @input="filterCustomers" @focus="showDropdown = true" @blur="hideDropdown" />
                  <div v-if="showDropdown && filteredCustomers.length" class="dropdown-menu show w-100" style="max-height:200px;overflow-y:auto">
                    <button v-for="c in filteredCustomers" :key="c.id" type="button"
                      class="dropdown-item small" @mousedown.prevent="selectCustomer(c)">
                      <span class="fw-semibold">{{ c.name }}</span>
                      <span class="text-muted ms-2 font-monospace" style="font-size:.75rem">{{ c.customer_number }}</span>
                    </button>
                  </div>
                </div>
                <div v-if="form.errors.customer_id" class="text-danger small mt-1">{{ form.errors.customer_id }}</div>

                <!-- Eligibility check result -->
                <div v-if="eligibility" class="mt-2">
                  <div v-if="eligibility.eligible" class="alert alert-success py-2 small mb-0">
                    <i class="bi bi-check-circle me-1"></i> Customer is eligible for a loan.
                  </div>
                  <div v-else class="alert alert-danger py-2 small mb-0">
                    <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle me-1"></i> Eligibility Issues</div>
                    <ul class="mb-0 ps-3">
                      <li v-for="issue in eligibility.issues" :key="issue">{{ issue }}</li>
                    </ul>
                  </div>
                </div>
              </div>

              <!-- Account (loads after customer selected) -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Disbursement Account <span class="text-danger">*</span></label>
                <select v-model="form.account_id" class="form-select"
                  :class="form.errors.account_id ? 'is-invalid' : ''"
                  :disabled="!customerAccounts.length">
                  <option value="">{{ form.customer_id ? '— Select Account —' : '— Select customer first —' }}</option>
                  <option v-for="a in customerAccounts" :key="a.id" :value="a.id">
                    {{ a.account_number }} ({{ a.account_type }}) — Balance: {{ fmt(a.balance) }}
                  </option>
                </select>
                <div v-if="form.errors.account_id" class="invalid-feedback">{{ form.errors.account_id }}</div>
              </div>

              <div class="row g-3 mb-4">
                <!-- Amount -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Loan Amount <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="form.amount" type="number" min="100" step="0.01" class="form-control"
                      :class="form.errors.amount ? 'is-invalid' : ''" @input="recalc" />
                    <div v-if="form.errors.amount" class="invalid-feedback">{{ form.errors.amount }}</div>
                  </div>
                </div>

                <!-- Interest Rate -->
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Interest Rate <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input v-model="form.interest_rate" type="number" min="0" max="100" step="0.01"
                      class="form-control" :class="form.errors.interest_rate ? 'is-invalid' : ''" @input="recalc" />
                    <span class="input-group-text">%</span>
                  </div>
                </div>

                <!-- Tenure -->
                <div class="col-md-3">
                  <label class="form-label fw-semibold">Tenure <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <input v-model="form.tenure_months" type="number" min="1" max="360"
                      class="form-control" :class="form.errors.tenure_months ? 'is-invalid' : ''" @input="recalc" />
                    <span class="input-group-text">months</span>
                  </div>
                </div>
              </div>

              <!-- First Repayment Date -->
              <div class="mb-4">
                <label class="form-label fw-semibold">First Repayment Date <span class="text-danger">*</span></label>
                <input v-model="form.first_repayment_date" type="date" class="form-control"
                  :class="form.errors.first_repayment_date ? 'is-invalid' : ''"
                  :min="tomorrow" />
                <div v-if="form.errors.first_repayment_date" class="invalid-feedback">{{ form.errors.first_repayment_date }}</div>
              </div>

              <!-- Purpose -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Purpose</label>
                <input v-model="form.purpose" type="text" class="form-control" placeholder="e.g. Working capital, Equipment purchase…" maxlength="255" />
              </div>

              <!-- Collateral -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Collateral</label>
                <textarea v-model="form.collateral" class="form-control" rows="2" placeholder="Describe collateral if any…"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-send me-1"></i>Submit Application
                </button>
                <Link :href="route('admin.loans.index')" class="btn btn-light">Cancel</Link>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Live EMI Calculator -->
      <div class="col-lg-4">
        <div class="card shadow-sm position-sticky" style="top:1rem">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-calculator me-1 text-success"></i>EMI Calculator
          </div>
          <div class="card-body">
            <div v-if="!emi" class="text-muted small text-center py-3">
              Enter amount, rate, and tenure to see calculation.
            </div>
            <div v-else>
              <div class="text-center mb-3">
                <div class="text-muted small">Monthly EMI</div>
                <div class="display-6 fw-bold text-primary">{{ fmt(emi.monthly_emi) }}</div>
              </div>
              <div class="table-responsive">
              <table class="table table-sm table-borderless mb-0">
                <tbody>
                  <tr>
                    <td class="text-muted small">Principal</td>
                    <td class="text-end fw-semibold small">{{ fmt(form.amount) }}</td>
                  </tr>
                  <tr>
                    <td class="text-muted small">Total Interest</td>
                    <td class="text-end fw-semibold small text-warning">{{ fmt(emi.total_interest) }}</td>
                  </tr>
                  <tr class="border-top">
                    <td class="fw-semibold small">Total Payable</td>
                    <td class="text-end fw-bold small text-danger">{{ fmt(emi.total_payable) }}</td>
                  </tr>
                  <tr>
                    <td class="text-muted small">Tenure</td>
                    <td class="text-end small">{{ form.tenure_months }} months</td>
                  </tr>
                  <tr>
                    <td class="text-muted small">Interest Rate</td>
                    <td class="text-end small">{{ form.interest_rate }}% p.a.</td>
                  </tr>
                </tbody>
              </table>
              </div>

              <!-- Schedule Preview (up to 12 rows) -->
              <div class="mt-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                  <span class="small fw-semibold text-muted">Schedule Preview</span>
                  <span class="small text-muted">{{ form.tenure_months }} instalment(s)</span>
                </div>
                <div style="max-height:220px;overflow-y:auto;overflow-x:auto;border:1px solid #e2e8f0;border-radius:6px">
                  <table class="table table-xs table-sm mb-0" style="font-size:.72rem">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th>#</th>
                        <th class="text-end">Principal</th>
                        <th class="text-end">Interest</th>
                        <th class="text-end">Balance</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in schedulePreview" :key="row.n">
                        <td>{{ row.n }}</td>
                        <td class="text-end">{{ fmtS(row.principal) }}</td>
                        <td class="text-end text-warning">{{ fmtS(row.interest) }}</td>
                        <td class="text-end">{{ fmtS(row.balance) }}</td>
                      </tr>
                    </tbody>
                    <tfoot v-if="emi" class="table-light fw-semibold" style="font-size:.72rem">
                      <tr>
                        <td>Σ</td>
                        <td class="text-end">{{ fmtS(form.amount) }}</td>
                        <td class="text-end text-warning">{{ fmtS(emi.total_interest) }}</td>
                        <td class="text-end">0.00</td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
                <div class="text-muted mt-1" style="font-size:.68rem">
                  <i class="bi bi-info-circle me-1"></i>Indicative — final figures confirmed on submission.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  customers: { type: Array, required: true },
  defaults:  { type: Object, default: () => ({}) },
});

const tomorrow = new Date(Date.now() + 86400000).toISOString().split('T')[0];

const form = useForm({
  customer_id:          '',
  account_id:           '',
  amount:               '',
  interest_rate:        props.defaults.interest_rate ?? 5,
  tenure_months:        12,
  first_repayment_date: '',
  purpose:              '',
  collateral:           '',
});

// Customer search
const customerSearch    = ref('');
const showDropdown      = ref(false);
const filteredCustomers = ref([...props.customers]);
const customerAccounts  = ref([]);
const eligibility       = ref(null);

const filterCustomers = () => {
  const q = customerSearch.value.toLowerCase();
  filteredCustomers.value = props.customers.filter(c =>
    c.name.toLowerCase().includes(q) || c.customer_number.toLowerCase().includes(q)
  );
};

const selectCustomer = async (c) => {
  customerSearch.value = c.name;
  form.customer_id     = c.id;
  form.account_id      = '';
  showDropdown.value   = false;
  eligibility.value    = null;

  const headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };

  // Fetch accounts + eligibility in parallel
  const [accRes, eligRes] = await Promise.all([
    fetch(route('admin.loans.accounts', c.id), { headers }),
    fetch(route('admin.loans.eligibility', c.id), { headers }),
  ]);
  customerAccounts.value = await accRes.json();
  eligibility.value      = await eligRes.json();
  if (customerAccounts.value.length === 1) form.account_id = customerAccounts.value[0].id;
};

const hideDropdown = () => setTimeout(() => { showDropdown.value = false; }, 150);

// EMI calculation (client-side mirror of LoanService)
const emi = ref(null);

const calcEMI = (principal, rate, tenure) => {
  if (!principal || !tenure) return null;
  if (rate <= 0) {
    const m = parseFloat((principal / tenure).toFixed(2));
    return { monthly_emi: m, total_payable: parseFloat((m * tenure).toFixed(2)), total_interest: 0 };
  }
  const r   = rate / 12 / 100;
  const pow = Math.pow(1 + r, tenure);
  const m   = parseFloat((principal * r * pow / (pow - 1)).toFixed(2));
  const ti  = parseFloat((m * tenure - principal).toFixed(2));
  return { monthly_emi: m, total_payable: parseFloat((principal + ti).toFixed(2)), total_interest: ti };
};

const recalc = () => {
  emi.value = calcEMI(parseFloat(form.amount), parseFloat(form.interest_rate), parseInt(form.tenure_months));
};

watch([() => form.amount, () => form.interest_rate, () => form.tenure_months], recalc);

// Full schedule preview (all rows, capped at 360 for safety)
const schedulePreview = computed(() => {
  if (!emi.value || !form.amount) return [];
  const r       = parseFloat(form.interest_rate) / 12 / 100;
  const monthly = emi.value.monthly_emi;
  const tenure  = Math.min(parseInt(form.tenure_months) || 0, 360);
  const rows    = [];
  let outstanding = parseFloat(form.amount);
  for (let i = 1; i <= tenure; i++) {
    const interest  = parseFloat((outstanding * r).toFixed(2));
    const principal = parseFloat(Math.min(monthly - interest, outstanding).toFixed(2));
    outstanding     = parseFloat(Math.max(0, outstanding - principal).toFixed(2));
    rows.push({ n: i, principal, interest, balance: outstanding });
  }
  return rows;
});

const submit = () => form.post(route('admin.loans.store'));

const fmt  = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtS = (v) => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v ?? 0);
</script>
