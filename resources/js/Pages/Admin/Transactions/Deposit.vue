<template>
  <AdminLayout
    title="Deposit"
    subtitle="Post a cash deposit to an account"
    :breadcrumbs="[
      { label: 'Transactions', href: route('admin.transactions.index') },
      { label: 'Deposit' }
    ]"
  >
    <div class="row g-4">
      <!-- Form -->
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-arrow-down-circle me-1 text-success"></i>Deposit Details
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">

              <!-- Account selector -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Account <span class="text-danger">*</span></label>
                <div v-if="selected" class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold font-monospace">{{ selected.account_number }}</div>
                    <div class="small text-muted">{{ selected.customer?.name }} &bull; {{ accountTypeLabel(selected.account_type) }}</div>
                    <div class="small text-success fw-semibold">Balance: {{ formatCurrency(selected.balance, selected.currency) }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearAccount">Change</button>
                </div>
                <div v-else>
                  <input
                    v-model="search"
                    type="text"
                    class="form-control"
                    :class="form.errors.account_id ? 'is-invalid' : ''"
                    placeholder="Search by account number or customer name…"
                    @input="onSearch"
                  />
                  <div v-if="filtered.length" class="border rounded mt-1 shadow-sm" style="max-height:200px;overflow-y:auto">
                    <div
                      v-for="acc in filtered" :key="acc.id"
                      class="px-3 py-2 d-flex justify-content-between align-items-center"
                      style="cursor:pointer"
                      :class="hoveredId === acc.id ? 'bg-primary text-white' : 'hover-light'"
                      @mouseenter="hoveredId = acc.id"
                      @mouseleave="hoveredId = null"
                      @mousedown.prevent="selectAccount(acc)"
                    >
                      <div>
                        <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                        <div :class="hoveredId === acc.id ? 'text-white-50' : 'text-muted'" style="font-size:.72rem">{{ acc.customer?.name }}</div>
                      </div>
                      <div class="small" :class="hoveredId === acc.id ? 'text-white-50' : 'text-muted'">{{ formatCurrency(acc.balance, acc.currency) }}</div>
                    </div>
                  </div>
                  <div v-if="search && !filtered.length" class="text-muted small mt-1">No active accounts found.</div>
                  <div v-if="form.errors.account_id" class="invalid-feedback d-block">{{ form.errors.account_id }}</div>
                </div>
              </div>

              <!-- Amount -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">{{ selected?.currency ?? 'USD' }}</span>
                  <input
                    v-model="form.amount"
                    type="number"
                    min="0.01"
                    step="0.01"
                    class="form-control form-control-lg"
                    :class="form.errors.amount ? 'is-invalid' : ''"
                    placeholder="0.00"
                  />
                  <div v-if="form.errors.amount" class="invalid-feedback">{{ form.errors.amount }}</div>
                </div>
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Description</label>
                <input
                  v-model="form.description"
                  type="text"
                  class="form-control"
                  :class="form.errors.description ? 'is-invalid' : ''"
                  placeholder="e.g. Cash deposit over counter"
                  maxlength="255"
                />
                <div v-if="form.errors.description" class="invalid-feedback">{{ form.errors.description }}</div>
              </div>

              <!-- Notes -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Internal Notes</label>
                <textarea
                  v-model="form.notes"
                  class="form-control"
                  rows="2"
                  placeholder="Optional internal notes (not shown on receipt)"
                ></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4" :disabled="form.processing || !form.account_id">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-check-lg me-1"></i>Post Deposit
                </button>
                <Link :href="route('admin.transactions.index')" class="btn btn-light">Cancel</Link>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Summary -->
      <div class="col-lg-5">
        <div class="card shadow-sm border-success">
          <div class="card-header bg-success text-white fw-semibold">
            <i class="bi bi-receipt me-1"></i>Preview
          </div>
          <div class="card-body">
            <div v-if="!selected" class="text-center text-muted py-3">Select an account to preview</div>
            <div v-else>
              <div class="mb-3 pb-3 border-bottom">
                <div class="text-muted small">Account</div>
                <div class="fw-bold font-monospace">{{ selected.account_number }}</div>
                <div class="small text-muted">{{ selected.customer?.name }}</div>
              </div>
              <div class="mb-3 pb-3 border-bottom">
                <div class="text-muted small">Current Balance</div>
                <div class="fw-semibold">{{ formatCurrency(selected.balance, selected.currency) }}</div>
              </div>
              <div class="mb-3 pb-3 border-bottom">
                <div class="text-muted small">Deposit Amount</div>
                <div class="fw-bold fs-5 text-success">{{ form.amount ? formatCurrency(form.amount, selected.currency) : '—' }}</div>
              </div>
              <div>
                <div class="text-muted small">New Balance (est.)</div>
                <div class="fw-bold fs-5">
                  {{ form.amount ? formatCurrency(parseFloat(selected.balance) + parseFloat(form.amount || 0), selected.currency) : '—' }}
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
import { ref, computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  accounts:    { type: Array, required: true },
  preselected: { type: Object, default: null },
});

const search    = ref('');
const hoveredId = ref(null);
const selected  = ref(props.preselected ?? null);

const form = useForm({
  account_id:  props.preselected?.id ?? '',
  amount:      '',
  description: '',
  notes:       '',
});

const filtered = computed(() => {
  if (!search.value) return [];
  const q = search.value.toLowerCase();
  return props.accounts.filter(a =>
    a.account_number.toLowerCase().includes(q) ||
    a.customer?.name?.toLowerCase().includes(q)
  ).slice(0, 8);
});

const selectAccount = (acc) => {
  selected.value     = acc;
  form.account_id    = acc.id;
  search.value       = '';
};

const clearAccount = () => {
  selected.value  = null;
  form.account_id = '';
  search.value    = '';
};

const onSearch = () => {
  if (!search.value) clearAccount();
};

const submit = () => form.post(route('admin.transactions.deposit'));

const accountTypeLabel = (t) => ({ savings: 'Savings', current: 'Current', fixed_deposit: 'Fixed Deposit' }[t] ?? t);
const formatCurrency   = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);
</script>
