<template>
  <AdminLayout
    title="Loans"
    subtitle="Loan portfolio management"
    :breadcrumbs="[{ label: 'Loans' }]"
  >
    <template #actions>
      <Link v-if="can('loans.create')" :href="route('admin.loans.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Application
      </Link>
    </template>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4">{{ stats.total }}</div>
          <div class="text-muted small">Total Loans</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-success">{{ stats.active }}</div>
          <div class="text-muted small">Active</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-danger">{{ stats.overdue }}</div>
          <div class="text-muted small">Overdue</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-4 text-warning">{{ stats.pending }}</div>
          <div class="text-muted small">In Pipeline</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-5 text-primary">{{ fmt(stats.outstanding) }}</div>
          <div class="text-muted small">Outstanding</div>
        </div>
      </div>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold tabular-nums fs-5 text-info">{{ fmt(stats.disbursed) }}</div>
          <div class="text-muted small">Total Disbursed</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-3">
      <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
        <input v-model="f.search" type="text" placeholder="Search loan # or customer…"
          class="form-control form-control-sm" style="width:220px" @keyup.enter="search" />
        <select v-model="f.status" class="form-select form-select-sm" style="width:160px" @change="search">
          <option value="">All Statuses</option>
          <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
        <input v-model="f.date_from" type="date" class="form-control form-control-sm" style="width:140px" @change="search" />
        <input v-model="f.date_to"   type="date" class="form-control form-control-sm" style="width:140px" @change="search" />
        <button class="btn btn-sm btn-outline-secondary" @click="clearFilters">
          <i class="bi bi-x me-1"></i>Clear
        </button>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!loans.data.length" class="text-center text-muted py-5">
          <i class="bi bi-cash-coin d-block mb-2" style="font-size:2rem;opacity:.3"></i>No loans found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Loan #</th>
                <th>Customer</th>
                <th>Account</th>
                <th class="text-end">Amount</th>
                <th class="text-center">Rate</th>
                <th class="text-center">Tenure</th>
                <th class="text-end">Outstanding</th>
                <th class="text-center">Status</th>
                <th class="text-center">Applied</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="loan in loans.data" :key="loan.id">
                <td class="ps-3 font-monospace small fw-semibold">{{ loan.loan_number }}</td>
                <td class="small">{{ loan.customer?.name }}</td>
                <td class="small text-muted">{{ loan.account?.account_number }}</td>
                <td class="text-end small fw-semibold">{{ fmt(loan.amount) }}</td>
                <td class="text-center small">{{ loan.interest_rate }}%</td>
                <td class="text-center small">{{ loan.tenure_months }}m</td>
                <td class="text-end small fw-semibold" :class="loan.status === 'overdue' ? 'text-danger' : ''">
                  {{ ['active','overdue'].includes(loan.status) ? fmt(loan.outstanding_balance) : '—' }}
                </td>
                <td class="text-center">
                  <span class="badge" :class="statusBadge(loan.status)">{{ statusLabel(loan.status) }}</span>
                </td>
                <td class="text-center small text-muted">{{ fmtDate(loan.created_at) }}</td>
                <td class="pe-3">
                  <Link :href="route('admin.loans.show', loan.id)" class="btn btn-xs btn-outline-primary py-0 px-2">
                    <i class="bi bi-eye"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="loans.last_page > 1" class="d-flex justify-content-center py-3 gap-1">
          <button v-if="loans.prev_page_url" @click="goPage(loans.current_page - 1)" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-left"></i>
          </button>
          <span class="btn btn-sm btn-primary disabled">{{ loans.current_page }} / {{ loans.last_page }}</span>
          <button v-if="loans.next_page_url" @click="goPage(loans.current_page + 1)" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-chevron-right"></i>
          </button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  loans:   { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  stats:   { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const f = reactive({
  search:    props.filters.search    ?? '',
  status:    props.filters.status    ?? '',
  date_from: props.filters.date_from ?? '',
  date_to:   props.filters.date_to   ?? '',
});

const search      = () => router.get(route('admin.loans.index'), f);
const clearFilters= () => { Object.assign(f, { search: '', status: '', date_from: '', date_to: '' }); search(); };
const goPage      = (pg) => router.get(route('admin.loans.index'), { ...f, page: pg });

const statuses = [
  { value: 'pending',      label: 'Pending' },
  { value: 'under_review', label: 'Under Review' },
  { value: 'approved',     label: 'Approved' },
  { value: 'rejected',     label: 'Rejected' },
  { value: 'active',       label: 'Active' },
  { value: 'overdue',      label: 'Overdue' },
  { value: 'closed',       label: 'Closed' },
];

const statusLabel = (s) => ({ pending: 'Pending', under_review: 'Under Review', approved: 'Approved', rejected: 'Rejected', disbursed: 'Disbursed', active: 'Active', overdue: 'Overdue', closed: 'Closed' }[s] ?? s);
const statusBadge = (s) => ({
  pending:      'bg-secondary-subtle text-secondary',
  under_review: 'bg-warning-subtle text-warning',
  approved:     'bg-info-subtle text-info',
  rejected:     'bg-danger-subtle text-danger',
  active:       'bg-success-subtle text-success',
  overdue:      'bg-danger text-white',
  closed:       'bg-light text-muted',
}[s] ?? 'bg-light text-muted');

const fmt     = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
</script>
