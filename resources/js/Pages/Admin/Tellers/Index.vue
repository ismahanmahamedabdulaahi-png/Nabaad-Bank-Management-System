<template>
  <AdminLayout
    title="Teller Operations"
    :subtitle="`Business Date: ${filters.date}`"
    :breadcrumbs="[{ label: 'Teller Operations' }]"
  >
    <template #actions>
      <div class="d-flex gap-2 align-items-center">
        <input type="date" :value="filters.date" @change="changeDate" class="form-control form-control-sm" style="width:150px" />
        <Link v-if="can('teller.assign')" :href="route('admin.tellers.create')" class="btn btn-primary btn-sm">
          <i class="bi bi-plus-lg me-1"></i>Open Till
        </Link>
      </div>
    </template>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-success">{{ stats.open }}</div>
          <div class="text-muted small">Open Tills</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-secondary">{{ stats.closed }}</div>
          <div class="text-muted small">Closed Tills</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-primary">{{ fmt(stats.total_current) }}</div>
          <div class="text-muted small">Cash in Circulation</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-info">{{ stats.total_txns_today }}</div>
          <div class="text-muted small">Transactions Today</div>
        </div>
      </div>
    </div>

    <!-- Tills Grid -->
    <div v-if="!tills.length" class="card shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-cash-stack d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
        No tills for {{ filters.date }}.
        <Link v-if="can('teller.assign')" :href="route('admin.tellers.create')" class="btn btn-primary btn-sm ms-2">Open First Till</Link>
      </div>
    </div>

    <div v-else class="row g-3">
      <div v-for="till in tills" :key="till.id" class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100" :class="till.status === 'open' ? 'border-success' : till.status === 'closed' ? 'border-secondary' : 'border-warning'">
          <!-- Header -->
          <div class="card-header d-flex justify-content-between align-items-center py-2"
            :class="till.status === 'open' ? 'bg-success-subtle' : 'bg-light'">
            <div>
              <span class="fw-semibold small">{{ till.till_name }}</span>
              <span class="badge ms-2" :class="statusBadge(till.status)">{{ till.status }}</span>
            </div>
            <Link :href="route('admin.tellers.show', till.id)" class="btn btn-sm btn-outline-secondary py-0 px-2">
              <i class="bi bi-eye"></i>
            </Link>
          </div>

          <!-- Body -->
          <div class="card-body small">
            <div class="d-flex align-items-center gap-2 mb-2">
              <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                style="width:32px;height:32px;flex-shrink:0;font-size:.7rem;font-weight:700">
                {{ initials(till.teller?.name) }}
              </div>
              <div>
                <div class="fw-semibold">{{ till.teller?.name }}</div>
                <div class="text-muted" style="font-size:.7rem">{{ till.opened_at ? 'Opened ' + fmtTime(till.opened_at) : 'Not opened' }}</div>
              </div>
            </div>

            <div class="row g-1 text-center mt-2">
              <div class="col-4">
                <div class="text-muted" style="font-size:.65rem">Opening</div>
                <div class="fw-semibold" style="font-size:.8rem">{{ fmt(till.opening_balance) }}</div>
              </div>
              <div class="col-4">
                <div class="text-muted" style="font-size:.65rem">Current</div>
                <div class="fw-semibold text-primary" style="font-size:.8rem">{{ fmt(till.current_balance) }}</div>
              </div>
              <div class="col-4">
                <div v-if="till.status === 'closed'" class="text-muted" style="font-size:.65rem">Variance</div>
                <div v-if="till.status === 'closed'" class="fw-semibold" :class="till.variance < 0 ? 'text-danger' : till.variance > 0 ? 'text-warning' : 'text-success'" style="font-size:.8rem">
                  {{ till.variance >= 0 ? '+' : '' }}{{ fmt(till.variance) }}
                </div>
                <div v-if="till.status === 'open'" class="text-muted" style="font-size:.65rem">Txns</div>
                <div v-if="till.status === 'open'" class="fw-semibold text-info" style="font-size:.8rem">{{ tillTxnCount(till.id) }}</div>
              </div>
            </div>
          </div>

          <!-- Footer actions -->
          <div v-if="till.status === 'open'" class="card-footer bg-transparent py-2 d-flex gap-1">
            <Link v-if="can('teller.close-till')"
              :href="route('admin.tellers.show', till.id)" class="btn btn-sm btn-outline-danger flex-grow-1">
              <i class="bi bi-x-circle me-1"></i>Close
            </Link>
            <Link :href="route('admin.tellers.show', till.id)" class="btn btn-sm btn-outline-primary flex-grow-1">
              <i class="bi bi-eye me-1"></i>View
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Performance Table -->
    <div v-if="tills.length" class="card shadow-sm mt-4">
      <div class="card-header bg-white fw-semibold">
        <i class="bi bi-bar-chart me-1 text-primary"></i>Teller Performance — {{ filters.date }}
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Teller</th>
                <th>Till</th>
                <th class="text-center">Deposits</th>
                <th class="text-center">Withdrawals</th>
                <th class="text-center">Transfers</th>
                <th class="text-end pe-3">Total Volume</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="till in tills" :key="till.id + 'p'">
                <td class="ps-3 small fw-semibold">{{ till.teller?.name }}</td>
                <td class="small text-muted">{{ till.till_name }}</td>
                <td class="text-center small">
                  <span class="text-success">{{ perfCount(till.id, 'deposit') }}</span>
                </td>
                <td class="text-center small">
                  <span class="text-danger">{{ perfCount(till.id, 'withdrawal') }}</span>
                </td>
                <td class="text-center small">
                  <span class="text-primary">{{ perfCount(till.id, 'transfer') }}</span>
                </td>
                <td class="text-end small pe-3 fw-semibold">{{ fmt(perfTotal(till.id)) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  tills:       { type: Array,  required: true },
  filters:     { type: Object, default: () => ({}) },
  stats:       { type: Object, default: () => ({}) },
  performance: { type: Object, default: () => ({}) },
});

const page = usePage();
const can  = (p) => page.props.auth.permissions.includes(p);

const changeDate = (e) => router.get(route('admin.tellers.index'), { date: e.target.value });

const statusBadge = (s) => ({ open: 'bg-success', closed: 'bg-secondary', suspended: 'bg-warning text-dark' }[s] ?? 'bg-light');
const initials    = (n) => (n ?? '--').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();

const fmt     = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const fmtTime = (d) => d ? new Date(d).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false }) : '';

const tillTxnCount = (tillId) => {
  const p = props.performance[tillId];
  return p ? p.reduce((s, r) => s + Number(r.count), 0) : 0;
};

const perfCount = (tillId, type) => {
  const p = props.performance[tillId];
  if (!p) return 0;
  const row = p.find(r => r.type === type);
  return row ? Number(row.count) : 0;
};

const perfTotal = (tillId) => {
  const p = props.performance[tillId];
  return p ? p.reduce((s, r) => s + Number(r.total), 0) : 0;
};
</script>
