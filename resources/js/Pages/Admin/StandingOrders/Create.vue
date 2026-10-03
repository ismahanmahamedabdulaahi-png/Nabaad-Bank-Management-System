<template>
  <AdminLayout
    title="New Standing Order"
    subtitle="Schedule a recurring transfer"
    :breadcrumbs="[
      { label: 'Standing Orders', href: route('admin.standing-orders.index') },
      { label: 'Create' }
    ]"
  >
    <div class="row g-4">
      <!-- Form -->
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-repeat me-1 text-primary"></i>Standing Order Details
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">

              <!-- Source Account -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Source Account <span class="text-danger">*</span></label>
                <div v-if="fromAccount" class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold font-monospace">{{ fromAccount.account_number }}</div>
                    <div class="small text-muted">{{ fromAccount.customer?.name }}</div>
                    <div class="small text-success">Balance: {{ fmt(fromAccount.balance, fromAccount.currency) }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFrom">Change</button>
                </div>
                <div v-else>
                  <input v-model="fromSearch" type="text" class="form-control"
                    :class="form.errors.source_account_id ? 'is-invalid' : ''"
                    placeholder="Search source account…" />
                  <div v-if="fromFiltered.length" class="border rounded mt-1 shadow-sm" style="max-height:200px;overflow-y:auto">
                    <div v-for="acc in fromFiltered" :key="acc.id"
                      class="px-3 py-2 d-flex justify-content-between"
                      style="cursor:pointer"
                      :class="fromHov === acc.id ? 'bg-primary text-white' : ''"
                      @mouseenter="fromHov = acc.id" @mouseleave="fromHov = null"
                      @mousedown.prevent="selectFrom(acc)">
                      <div>
                        <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                        <div :class="fromHov === acc.id ? 'text-white-50' : 'text-muted'" style="font-size:.72rem">{{ acc.customer?.name }}</div>
                      </div>
                      <div class="small" :class="fromHov === acc.id ? 'text-white-50' : 'text-muted'">{{ fmt(acc.balance, acc.currency) }}</div>
                    </div>
                  </div>
                  <div v-if="form.errors.source_account_id" class="invalid-feedback d-block">{{ form.errors.source_account_id }}</div>
                </div>
              </div>

              <!-- Beneficiary Account -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Beneficiary Account <span class="text-danger">*</span></label>
                <div v-if="toAccount" class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold font-monospace">{{ toAccount.account_number }}</div>
                    <div class="small text-muted">{{ toAccount.customer?.name }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearTo">Change</button>
                </div>
                <div v-else>
                  <input v-model="toSearch" type="text" class="form-control"
                    :class="form.errors.beneficiary_account_id ? 'is-invalid' : ''"
                    placeholder="Search beneficiary account…" />
                  <div v-if="toFiltered.length" class="border rounded mt-1 shadow-sm" style="max-height:200px;overflow-y:auto">
                    <div v-for="acc in toFiltered" :key="acc.id"
                      class="px-3 py-2 d-flex justify-content-between"
                      style="cursor:pointer"
                      :class="toHov === acc.id ? 'bg-primary text-white' : ''"
                      @mouseenter="toHov = acc.id" @mouseleave="toHov = null"
                      @mousedown.prevent="selectTo(acc)">
                      <div>
                        <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                        <div :class="toHov === acc.id ? 'text-white-50' : 'text-muted'" style="font-size:.72rem">{{ acc.customer?.name }}</div>
                      </div>
                    </div>
                  </div>
                  <div v-if="form.errors.beneficiary_account_id" class="invalid-feedback d-block">{{ form.errors.beneficiary_account_id }}</div>
                </div>
              </div>

              <!-- Amount -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Amount per Execution <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">{{ fromAccount?.currency ?? 'USD' }}</span>
                  <input v-model="form.amount" type="number" min="0.01" step="0.01"
                    class="form-control form-control-lg"
                    :class="form.errors.amount ? 'is-invalid' : ''"
                    placeholder="0.00" />
                  <div v-if="form.errors.amount" class="invalid-feedback">{{ form.errors.amount }}</div>
                </div>
              </div>

              <!-- Frequency -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Frequency <span class="text-danger">*</span></label>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="border rounded p-3 text-center"
                      :class="form.frequency === 'weekly' ? 'border-primary bg-primary-subtle' : ''"
                      style="cursor:pointer"
                      @click="form.frequency = 'weekly'">
                      <i class="bi bi-calendar-week d-block fs-4 mb-1" :class="form.frequency === 'weekly' ? 'text-primary' : 'text-muted'"></i>
                      <span class="fw-semibold small">Weekly</span>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="border rounded p-3 text-center"
                      :class="form.frequency === 'monthly' ? 'border-primary bg-primary-subtle' : ''"
                      style="cursor:pointer"
                      @click="form.frequency = 'monthly'">
                      <i class="bi bi-calendar-month d-block fs-4 mb-1" :class="form.frequency === 'monthly' ? 'text-primary' : 'text-muted'"></i>
                      <span class="fw-semibold small">Monthly</span>
                    </div>
                  </div>
                </div>
                <div v-if="form.errors.frequency" class="text-danger small mt-1">{{ form.errors.frequency }}</div>
              </div>

              <!-- Dates -->
              <div class="row g-3 mb-4">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                  <input v-model="form.start_date" type="date" class="form-control"
                    :class="form.errors.start_date ? 'is-invalid' : ''"
                    :min="today" />
                  <div v-if="form.errors.start_date" class="invalid-feedback">{{ form.errors.start_date }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">End Date <span class="text-muted small">(optional)</span></label>
                  <input v-model="form.end_date" type="date" class="form-control"
                    :class="form.errors.end_date ? 'is-invalid' : ''"
                    :min="form.start_date || today" />
                  <div v-if="form.errors.end_date" class="invalid-feedback">{{ form.errors.end_date }}</div>
                </div>
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Description</label>
                <input v-model="form.description" type="text" class="form-control" placeholder="e.g. Monthly rent payment" maxlength="255" />
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"
                  :disabled="form.processing || !form.source_account_id || !form.beneficiary_account_id || !form.frequency">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-check-lg me-1"></i>Create Standing Order
                </button>
                <Link :href="route('admin.standing-orders.index')" class="btn btn-light">Cancel</Link>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div class="col-lg-5">
        <div class="card shadow-sm border-primary sticky-top" style="top:1rem">
          <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-repeat me-1"></i>Summary
          </div>
          <div class="card-body small">
            <div class="mb-2 d-flex justify-content-between">
              <span class="text-muted">From</span>
              <span class="fw-semibold font-monospace">{{ fromAccount?.account_number ?? '—' }}</span>
            </div>
            <div class="mb-2 d-flex justify-content-between">
              <span class="text-muted">To</span>
              <span class="fw-semibold font-monospace">{{ toAccount?.account_number ?? '—' }}</span>
            </div>
            <div class="mb-2 d-flex justify-content-between">
              <span class="text-muted">Amount</span>
              <span class="fw-semibold text-primary">{{ form.amount ? fmt(form.amount, fromAccount?.currency ?? 'USD') : '—' }}</span>
            </div>
            <div class="mb-2 d-flex justify-content-between">
              <span class="text-muted">Frequency</span>
              <span class="text-capitalize">{{ form.frequency || '—' }}</span>
            </div>
            <div class="mb-2 d-flex justify-content-between">
              <span class="text-muted">First Run</span>
              <span>{{ form.start_date ? formatDate(form.start_date) : '—' }}</span>
            </div>
            <div class="d-flex justify-content-between">
              <span class="text-muted">Expires</span>
              <span>{{ form.end_date ? formatDate(form.end_date) : 'No end date' }}</span>
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
  accounts: { type: Array, required: true },
});

const today      = new Date().toISOString().split('T')[0];
const fromAccount = ref(null);
const toAccount   = ref(null);
const fromSearch  = ref('');
const toSearch    = ref('');
const fromHov     = ref(null);
const toHov       = ref(null);

const form = useForm({
  source_account_id:      '',
  beneficiary_account_id: '',
  amount:      '',
  frequency:   '',
  start_date:  today,
  end_date:    '',
  description: '',
});

const fromFiltered = computed(() => {
  if (!fromSearch.value) return [];
  const q = fromSearch.value.toLowerCase();
  return props.accounts.filter(a => a.id !== toAccount.value?.id &&
    (a.account_number.toLowerCase().includes(q) || a.customer?.name?.toLowerCase().includes(q))
  ).slice(0, 8);
});

const toFiltered = computed(() => {
  if (!toSearch.value) return [];
  const q = toSearch.value.toLowerCase();
  return props.accounts.filter(a => a.id !== fromAccount.value?.id &&
    (a.account_number.toLowerCase().includes(q) || a.customer?.name?.toLowerCase().includes(q))
  ).slice(0, 8);
});

const selectFrom = (acc) => { fromAccount.value = acc; form.source_account_id = acc.id; fromSearch.value = ''; };
const clearFrom  = ()    => { fromAccount.value = null; form.source_account_id = ''; fromSearch.value = ''; };
const selectTo   = (acc) => { toAccount.value = acc; form.beneficiary_account_id = acc.id; toSearch.value = ''; };
const clearTo    = ()    => { toAccount.value = null; form.beneficiary_account_id = ''; toSearch.value = ''; };

const submit = () => form.post(route('admin.standing-orders.store'));

const fmt        = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c }).format(v ?? 0);
const formatDate = (d) => d ? new Date(d + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>
