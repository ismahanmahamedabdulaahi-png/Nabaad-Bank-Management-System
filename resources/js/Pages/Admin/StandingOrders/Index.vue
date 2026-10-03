<template>
  <AdminLayout
    title="Standing Orders"
    subtitle="Scheduled recurring transfers"
    :breadcrumbs="[{ label: 'Standing Orders' }]"
  >
    <template #actions>
      <Link v-if="can('transactions.transfer')" :href="route('admin.standing-orders.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Standing Order
      </Link>
    </template>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-4">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-success">{{ stats.active }}</div>
          <div class="text-muted small">Active Orders</div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card shadow-sm text-center p-3" :class="stats.due_today > 0 ? 'border-warning' : ''">
          <div class="fw-bold fs-4 text-warning">{{ stats.due_today }}</div>
          <div class="text-muted small">Due Today</div>
        </div>
      </div>
      <div class="col-6 col-md-4">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-secondary">{{ stats.paused }}</div>
          <div class="text-muted small">Paused</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-4">
            <input v-model="form.search" type="text" class="form-control form-control-sm" placeholder="Account number or customer…" />
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="paused">Paused</option>
              <option value="cancelled">Cancelled</option>
              <option value="completed">Completed</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.frequency" class="form-select form-select-sm">
              <option value="">All Frequencies</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Go</button>
            <Link :href="route('admin.standing-orders.index')" class="btn btn-light btn-sm"><i class="bi bi-x"></i></Link>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!orders.data.length" class="text-center text-muted py-5">
          <i class="bi bi-repeat d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
          No standing orders found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Source Account</th>
                <th>Beneficiary</th>
                <th class="text-end">Amount</th>
                <th>Frequency</th>
                <th>Next Run</th>
                <th>Status</th>
                <th class="pe-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="order in orders.data" :key="order.id">
                <td class="ps-3">
                  <div class="fw-semibold font-monospace small">{{ order.source_account?.account_number }}</div>
                  <div class="text-muted" style="font-size:.72rem">{{ order.source_account?.customer?.name }}</div>
                </td>
                <td>
                  <div class="fw-semibold font-monospace small">{{ order.beneficiary_account?.account_number }}</div>
                  <div class="text-muted" style="font-size:.72rem">{{ order.beneficiary_account?.customer?.name }}</div>
                </td>
                <td class="text-end fw-semibold small">{{ formatCurrency(order.amount, order.source_account?.currency) }}</td>
                <td>
                  <span class="badge bg-info-subtle text-info text-capitalize">{{ order.frequency }}</span>
                </td>
                <td class="small" :class="isDueOrOverdue(order) ? 'text-warning fw-semibold' : 'text-muted'">
                  <i v-if="isDueOrOverdue(order)" class="bi bi-exclamation-circle me-1"></i>
                  {{ formatDate(order.next_execution_date) }}
                </td>
                <td><StatusBadge :status="order.status" /></td>
                <td class="pe-3">
                  <Link :href="route('admin.standing-orders.show', order.id)" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="orders.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <span class="text-muted small">Showing {{ orders.from }}–{{ orders.to }} of {{ orders.total }}</span>
          <div class="d-flex gap-1">
            <Link v-for="link in orders.links" :key="link.label" :href="link.url ?? '#'"
              class="btn btn-sm" :class="link.active ? 'btn-primary' : 'btn-light'" :disabled="!link.url" v-html="link.label" />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
  orders:  { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  stats:   { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const form = reactive({
  search:    props.filters.search    ?? '',
  status:    props.filters.status    ?? '',
  frequency: props.filters.frequency ?? '',
});

const applyFilters = () => router.get(route('admin.standing-orders.index'), form, { preserveState: true });

const isDueOrOverdue = (order) => {
  if (order.status !== 'active') return false;
  return new Date(order.next_execution_date) <= new Date();
};

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>
