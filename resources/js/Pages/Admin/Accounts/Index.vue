<template>
  <AdminLayout
    title="Accounts"
    subtitle="All bank accounts"
    :breadcrumbs="[{ label: 'Accounts' }]"
  >
    <template #actions>
      <Link
        v-if="$page.props.auth.permissions.includes('accounts.create')"
        :href="route('admin.accounts.create')"
        class="btn btn-primary btn-sm"
      >
        <i class="bi bi-plus-lg me-1"></i> Open Account
      </Link>
    </template>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3 col-lg">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-primary">{{ stats.total }}</div>
          <div class="text-muted small">Total</div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-success">{{ stats.active }}</div>
          <div class="text-muted small">Active</div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-warning">{{ stats.frozen }}</div>
          <div class="text-muted small">Frozen</div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-info">{{ stats.fixed_deposit }}</div>
          <div class="text-muted small">Fixed Deposits</div>
        </div>
      </div>
      <div v-if="stats.maturity_due" class="col-6 col-md-3 col-lg">
        <div class="card shadow-sm text-center p-3 border-danger">
          <div class="fw-bold tabular-nums fs-4 text-danger">{{ stats.maturity_due }}</div>
          <div class="text-muted small">FD Due</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-4">
            <input
              v-model="form.search"
              type="text"
              class="form-control form-control-sm"
              placeholder="Account #, customer name…"
            />
          </div>
          <div class="col-md-2">
            <select v-model="form.account_type" class="form-select form-select-sm">
              <option value="">All Types</option>
              <option value="savings">Savings</option>
              <option value="current">Current</option>
              <option value="fixed_deposit">Fixed Deposit</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="frozen">Frozen</option>
              <option value="dormant">Dormant</option>
              <option value="closed">Closed</option>
              <option value="matured">Matured</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.branch_id" class="form-select form-select-sm">
              <option value="">All Branches</option>
              <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
            <Link :href="route('admin.accounts.index')" class="btn btn-light btn-sm">Reset</Link>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!accounts.data.length" class="text-center text-muted py-5">
          <i class="bi bi-wallet2 d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
          No accounts found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Account #</th>
                <th>Customer</th>
                <th>Type</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Branch</th>
                <th>Opened</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="acc in accounts.data" :key="acc.id">
                <td class="ps-3">
                  <span class="font-monospace fw-semibold small">{{ acc.account_number }}</span>
                </td>
                <td>
                  <div class="small fw-semibold">{{ acc.customer?.name }}</div>
                  <div class="text-muted" style="font-size:.72rem">{{ acc.customer?.customer_number }}</div>
                </td>
                <td>
                  <span class="badge" :class="typeBadge(acc.account_type)">{{ typeLabel(acc.account_type) }}</span>
                </td>
                <td class="fw-semibold small">{{ formatCurrency(acc.balance, acc.currency) }}</td>
                <td><StatusBadge :status="acc.status" /></td>
                <td class="small text-muted">{{ acc.branch?.code }}</td>
                <td class="small text-muted">{{ formatDate(acc.opening_date) }}</td>
                <td class="pe-3">
                  <Link :href="route('admin.accounts.show', acc.id)" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="accounts.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <span class="text-muted small">
            Showing {{ accounts.from }}–{{ accounts.to }} of {{ accounts.total }}
          </span>
          <div class="d-flex gap-1">
            <Link
              v-for="link in accounts.links"
              :key="link.label"
              :href="link.url ?? '#'"
              class="btn btn-sm"
              :class="link.active ? 'btn-primary' : 'btn-light'"
              :disabled="!link.url"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import StatusBadge  from '@/Components/StatusBadge.vue';

const props = defineProps({
  accounts: { type: Object, required: true },
  branches: { type: Array,  default: () => [] },
  filters:  { type: Object, default: () => ({}) },
  stats:    { type: Object, default: () => ({}) },
});

const form = reactive({
  search:       props.filters.search       ?? '',
  account_type: props.filters.account_type ?? '',
  status:       props.filters.status       ?? '',
  branch_id:    props.filters.branch_id    ?? '',
});

const applyFilters = () => router.get(route('admin.accounts.index'), form, { preserveState: true });

const typeLabel = (t) => ({ savings: 'Savings', current: 'Current', fixed_deposit: 'Fixed Deposit' }[t] ?? t);
const typeBadge = (t) => ({ savings: 'bg-primary-subtle text-primary', current: 'bg-info-subtle text-info', fixed_deposit: 'bg-warning-subtle text-warning' }[t] ?? 'bg-light text-muted');

const formatCurrency = (v, currency = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency }).format(v ?? 0);
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' }) : '—';
</script>
