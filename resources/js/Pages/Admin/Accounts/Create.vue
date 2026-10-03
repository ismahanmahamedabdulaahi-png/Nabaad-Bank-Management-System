<template>
  <AdminLayout
    title="Open New Account"
    subtitle="Create a bank account for an active customer"
    :breadcrumbs="[{ label: 'Accounts', href: route('admin.accounts.index') }, { label: 'Open Account' }]"
  >
    <form @submit.prevent="submit">
      <div class="row g-4">

        <!-- ── Main column ────────────────────────────────────────────── -->
        <div class="col-lg-8">

          <!-- Step 1 · Customer -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
              <span class="badge rounded-pill bg-primary" style="width:24px;height:24px;line-height:17px;font-size:.75rem">1</span>
              <span class="fw-semibold">Select Customer</span>
            </div>
            <div class="card-body">

              <!-- Selected customer card -->
              <div v-if="selectedCustomer" class="d-flex align-items-center gap-3 p-3 border rounded bg-light mb-2">
                <div
                  class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0"
                  :style="`width:48px;height:48px;background:${avatarColor(selectedCustomer.name)};font-size:1rem`"
                >
                  {{ initials(selectedCustomer.name) }}
                </div>
                <div class="flex-grow-1">
                  <div class="fw-semibold">{{ selectedCustomer.name }}</div>
                  <div class="text-muted small">
                    {{ selectedCustomer.customer_number }}
                    <span class="mx-1">·</span>
                    {{ selectedCustomer.email }}
                    <span v-if="selectedCustomer.phone" class="mx-1">·</span>
                    {{ selectedCustomer.phone }}
                  </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearCustomer">
                  <i class="bi bi-x-lg"></i> Change
                </button>
              </div>

              <!-- Search input + dropdown -->
              <div v-else class="position-relative">
                <div class="input-group">
                  <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                  <input
                    ref="searchInput"
                    v-model="customerSearch"
                    type="text"
                    class="form-control border-start-0"
                    :class="{ 'is-invalid': form.errors.customer_id }"
                    placeholder="Type customer name, number, or phone…"
                    autocomplete="off"
                    @input="onSearchInput"
                    @focus="showDropdown = true"
                    @keydown.escape="showDropdown = false"
                    @keydown.down.prevent="moveFocus(1)"
                    @keydown.up.prevent="moveFocus(-1)"
                    @keydown.enter.prevent="selectFocused"
                  />
                </div>
                <div v-if="form.errors.customer_id" class="invalid-feedback d-block">{{ form.errors.customer_id }}</div>

                <!-- Dropdown results -->
                <div
                  v-if="showDropdown && customerSearch.length >= 1"
                  class="position-absolute w-100 border rounded shadow-sm bg-white mt-1 overflow-auto"
                  style="z-index:1050;max-height:260px"
                >
                  <div v-if="!filteredCustomers.length" class="text-center text-muted py-3 small">
                    <i class="bi bi-person-x d-block mb-1" style="font-size:1.5rem"></i>
                    No active customers match "{{ customerSearch }}"
                  </div>
                  <div
                    v-for="(c, idx) in filteredCustomers"
                    :key="c.id"
                    class="d-flex align-items-center gap-3 px-3 py-2 cursor-pointer"
                    :class="{ 'bg-primary-subtle': focusedIndex === idx }"
                    style="cursor:pointer"
                    @mousedown.prevent="selectCustomer(c)"
                    @mouseover="focusedIndex = idx"
                  >
                    <div
                      class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white flex-shrink-0 small"
                      :style="`width:36px;height:36px;background:${avatarColor(c.name)}`"
                    >
                      {{ initials(c.name) }}
                    </div>
                    <div class="flex-grow-1 small">
                      <div class="fw-semibold">{{ c.name }}</div>
                      <div class="text-muted" style="font-size:.72rem">{{ c.customer_number }} · {{ c.phone }}</div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="form.errors.customer_id" class="alert alert-danger small py-2 mt-2">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ form.errors.customer_id }}
              </div>
              <div class="form-text mt-1">
                <i class="bi bi-shield-check me-1 text-success"></i>
                Only customers with <strong>active status</strong> and <strong>approved KYC</strong> can open accounts.
              </div>
            </div>
          </div>

          <!-- Step 2 · Account Type -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
              <span class="badge rounded-pill bg-primary" style="width:24px;height:24px;line-height:17px;font-size:.75rem">2</span>
              <span class="fw-semibold">Account Type</span>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div v-for="type in accountTypes" :key="type.value" class="col-md-4">
                  <div
                    class="border rounded p-3 h-100 d-flex flex-column gap-1"
                    :class="form.account_type === type.value
                      ? 'border-primary bg-primary-subtle'
                      : 'border-light-subtle bg-light cursor-pointer'"
                    style="cursor:pointer;transition:.15s"
                    @click="selectType(type.value)"
                  >
                    <div class="d-flex align-items-center gap-2 mb-1">
                      <i :class="type.icon" class="fs-4" :style="`color:${type.color}`"></i>
                      <span class="fw-semibold small">{{ type.label }}</span>
                      <i v-if="form.account_type === type.value" class="bi bi-check-circle-fill text-primary ms-auto"></i>
                    </div>
                    <div class="text-muted" style="font-size:.72rem">{{ type.desc }}</div>
                  </div>
                </div>
              </div>
              <div v-if="form.errors.account_type" class="text-danger small mt-2">{{ form.errors.account_type }}</div>
            </div>
          </div>

          <!-- Step 3 · Account Settings -->
          <div v-if="form.account_type" class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex align-items-center gap-2">
              <span class="badge rounded-pill bg-primary" style="width:24px;height:24px;line-height:17px;font-size:.75rem">3</span>
              <span class="fw-semibold">Account Settings</span>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Branch <span class="text-danger">*</span></label>
                  <select v-model="form.branch_id" class="form-select" :class="{ 'is-invalid': form.errors.branch_id }">
                    <option value="">— Select —</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }} ({{ b.code }})</option>
                  </select>
                  <div v-if="form.errors.branch_id" class="invalid-feedback">{{ form.errors.branch_id }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Currency</label>
                  <select v-model="form.currency" class="form-select">
                    <option value="USD">USD — US Dollar</option>
                    <option value="SOS">SOS — Somali Shilling</option>
                    <option value="EUR">EUR — Euro</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Opening Balance</label>
                  <div class="input-group">
                    <span class="input-group-text small">{{ form.currency }}</span>
                    <input v-model="form.opening_balance" type="number" min="0" step="0.01" class="form-control" placeholder="0.00" />
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Interest Rate (%)</label>
                  <div class="input-group">
                    <input v-model="form.interest_rate" type="number" min="0" max="100" step="0.01" class="form-control" placeholder="0.00" />
                    <span class="input-group-text small">%</span>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Minimum Balance</label>
                  <div class="input-group">
                    <span class="input-group-text small">{{ form.currency }}</span>
                    <input v-model="form.minimum_balance" type="number" min="0" step="0.01" class="form-control" placeholder="0.00" />
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Notes</label>
                  <input v-model="form.notes" type="text" class="form-control" placeholder="Optional…" />
                </div>
              </div>
            </div>
          </div>

          <!-- Step 4 · Fixed Deposit Terms (conditional) -->
          <div v-if="form.account_type === 'fixed_deposit'" class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning-subtle d-flex align-items-center gap-2">
              <span class="badge rounded-pill bg-warning text-dark" style="width:24px;height:24px;line-height:17px;font-size:.75rem">4</span>
              <span class="fw-semibold">Fixed Deposit Terms</span>
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Tenure (Months) <span class="text-danger">*</span></label>
                  <input
                    v-model.number="form.fd_tenure_months"
                    type="number"
                    min="1"
                    max="360"
                    class="form-control"
                    :class="{ 'is-invalid': form.errors.fd_tenure_months }"
                    placeholder="e.g. 12"
                  />
                  <div v-if="form.errors.fd_tenure_months" class="invalid-feedback">{{ form.errors.fd_tenure_months }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Maturity Date</label>
                  <input :value="maturityDateDisplay" type="text" class="form-control bg-light text-muted" readonly />
                  <div class="form-text">Auto-calculated</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">On Maturity <span class="text-danger">*</span></label>
                  <select v-model="form.fd_maturity_action" class="form-select" :class="{ 'is-invalid': form.errors.fd_maturity_action }">
                    <option value="pending">Pending Customer Decision</option>
                    <option value="renew">Renew (Roll Over)</option>
                    <option value="transfer_to_savings">Transfer to Savings</option>
                  </select>
                  <div v-if="form.errors.fd_maturity_action" class="invalid-feedback">{{ form.errors.fd_maturity_action }}</div>
                </div>
              </div>

              <!-- FD quick presets -->
              <div class="mt-3">
                <div class="text-muted small mb-2">Quick tenures:</div>
                <div class="d-flex gap-2 flex-wrap">
                  <button
                    v-for="m in [3,6,12,24,36,60]"
                    :key="m"
                    type="button"
                    class="btn btn-sm"
                    :class="form.fd_tenure_months === m ? 'btn-warning' : 'btn-outline-secondary'"
                    @click="form.fd_tenure_months = m"
                  >
                    {{ m }}mo
                  </button>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- ── Sidebar ─────────────────────────────────────────────────── -->
        <div class="col-lg-4">

          <!-- Live preview card -->
          <div class="card shadow-sm mb-4" :class="selectedCustomer && form.account_type ? 'border-primary' : ''">
            <div class="card-header bg-white fw-semibold small">
              <i class="bi bi-eye me-1 text-primary"></i> Account Preview
            </div>
            <div class="card-body">
              <div v-if="!selectedCustomer && !form.account_type" class="text-muted small text-center py-2">
                Fill in the steps to see a preview.
              </div>
              <template v-else>
                <!-- Account type badge -->
                <div v-if="form.account_type" class="mb-3 text-center">
                  <span class="badge rounded-pill px-3 py-2 fs-6" :class="typeBadge(form.account_type)">
                    <i :class="accountTypes.find(t=>t.value===form.account_type)?.icon" class="me-1"></i>
                    {{ typeLabel(form.account_type) }}
                  </span>
                </div>

                <div class="small">
                  <div v-if="selectedCustomer" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Customer</span>
                    <span class="fw-semibold">{{ selectedCustomer.name }}</span>
                  </div>
                  <div v-if="form.branch_id" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Branch</span>
                    <span>{{ branches.find(b=>b.id===form.branch_id)?.name }}</span>
                  </div>
                  <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Currency</span>
                    <span>{{ form.currency }}</span>
                  </div>
                  <div v-if="form.opening_balance > 0" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Opening Balance</span>
                    <span class="fw-semibold text-success">{{ formatCurrency(form.opening_balance, form.currency) }}</span>
                  </div>
                  <div v-if="form.interest_rate > 0" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Interest Rate</span>
                    <span>{{ form.interest_rate }}%</span>
                  </div>
                  <div v-if="form.account_type === 'fixed_deposit' && maturityDateDisplay" class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Matures</span>
                    <span class="fw-semibold text-warning">{{ maturityDateDisplay }}</span>
                  </div>
                </div>
              </template>
            </div>
          </div>

          <!-- Rules -->
          <div class="card shadow-sm border-0 bg-light mb-4">
            <div class="card-body small text-muted">
              <div class="fw-semibold text-dark mb-2"><i class="bi bi-info-circle me-1"></i> Notes</div>
              <ul class="ps-3 mb-0">
                <li class="mb-1">Only <strong>KYC-approved</strong> active customers can open accounts.</li>
                <li class="mb-1">Account number is auto-generated.</li>
                <li>FD maturity date = today + tenure months.</li>
              </ul>
            </div>
          </div>

          <!-- Submit -->
          <div class="d-grid gap-2">
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="form.processing || !form.customer_id || !form.account_type"
            >
              <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
              <i v-else class="bi bi-wallet2 me-1"></i>
              Open Account
            </button>
            <Link :href="route('admin.accounts.index')" class="btn btn-light">Cancel</Link>
          </div>

        </div>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  branches:            { type: Array,  required: true },
  customers:           { type: Array,  required: true },
  preselectedCustomer: { type: Object, default: null },
});

// ── Customer search ───────────────────────────────────────────────────────────
const customerSearch    = ref('');
const showDropdown      = ref(false);
const focusedIndex      = ref(-1);
const searchInput       = ref(null);

const selectedCustomer = ref(props.preselectedCustomer ?? null);

const filteredCustomers = computed(() => {
  const q = customerSearch.value.trim().toLowerCase();
  if (!q) return [];
  return props.customers.filter(c =>
    c.name.toLowerCase().includes(q) ||
    c.customer_number.toLowerCase().includes(q) ||
    (c.phone ?? '').includes(q) ||
    (c.email ?? '').toLowerCase().includes(q)
  ).slice(0, 10);
});

const onSearchInput = () => {
  showDropdown.value = true;
  focusedIndex.value = -1;
  form.customer_id   = '';
};

const selectCustomer = (c) => {
  selectedCustomer.value = c;
  form.customer_id       = c.id;
  customerSearch.value   = '';
  showDropdown.value     = false;
};

const clearCustomer = () => {
  selectedCustomer.value = null;
  form.customer_id       = '';
  customerSearch.value   = '';
  setTimeout(() => searchInput.value?.focus(), 50);
};

const moveFocus = (dir) => {
  const max = filteredCustomers.value.length - 1;
  focusedIndex.value = Math.max(0, Math.min(max, focusedIndex.value + dir));
};

const selectFocused = () => {
  if (focusedIndex.value >= 0 && filteredCustomers.value[focusedIndex.value]) {
    selectCustomer(filteredCustomers.value[focusedIndex.value]);
  }
};

const closeOnClickOutside = (e) => {
  if (!e.target.closest('.position-relative')) showDropdown.value = false;
};
onMounted(() => document.addEventListener('click', closeOnClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', closeOnClickOutside));

// ── Account types ─────────────────────────────────────────────────────────────
const accountTypes = [
  { value: 'savings',       label: 'Savings',       icon: 'bi bi-piggy-bank',    color: '#0B2447', desc: 'Standard savings with interest. Ideal for individuals.' },
  { value: 'current',       label: 'Current',       icon: 'bi bi-credit-card-2-front', color: '#0d6efd', desc: 'Transactional account for businesses. No interest.' },
  { value: 'fixed_deposit', label: 'Fixed Deposit', icon: 'bi bi-calendar-lock', color: '#fd7e14', desc: 'Lock funds for a fixed term at a higher interest rate.' },
];

const selectType = (value) => {
  form.account_type = value;
  if (value !== 'fixed_deposit') {
    form.fd_tenure_months  = 12;
    form.fd_maturity_action = 'pending';
  }
};

// ── Form ──────────────────────────────────────────────────────────────────────
const form = useForm({
  customer_id:        props.preselectedCustomer?.id ?? '',
  branch_id:          props.branches[0]?.id ?? '',
  account_type:       '',
  currency:           'USD',
  opening_balance:    0,
  interest_rate:      0,
  minimum_balance:    0,
  notes:              '',
  fd_tenure_months:   12,
  fd_maturity_action: 'pending',
});

const maturityDateDisplay = computed(() => {
  if (!form.fd_tenure_months) return '';
  const d = new Date();
  d.setMonth(d.getMonth() + Number(form.fd_tenure_months));
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
});

const submit = () => form.post(route('admin.accounts.store'));

// ── Helpers ───────────────────────────────────────────────────────────────────
const typeLabel  = (t) => ({ savings: 'Savings', current: 'Current', fixed_deposit: 'Fixed Deposit' }[t] ?? t);
const typeBadge  = (t) => ({ savings: 'bg-primary text-white', current: 'bg-info text-white', fixed_deposit: 'bg-warning text-dark' }[t] ?? 'bg-light');
const formatCurrency = (v, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(v ?? 0);

const colors     = ['#0B2447','#14395B','#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6'];
const avatarColor = (n) => colors[(n?.charCodeAt(0) ?? 0) % colors.length];
const initials    = (n) => n?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?';
</script>
