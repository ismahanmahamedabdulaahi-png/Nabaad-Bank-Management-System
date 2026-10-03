<template>
  <AdminLayout
    title="Approvals"
    subtitle="Pending transactions awaiting authorization"
    :breadcrumbs="[{ label: 'Approvals' }]"
  >
    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3" :class="stats.my_queue > 0 ? 'border-warning' : ''">
          <div class="fw-bold fs-4" :class="stats.my_queue > 0 ? 'text-warning' : 'text-secondary'">{{ stats.my_queue }}</div>
          <div class="text-muted small">My Queue</div>
          <div class="text-muted" style="font-size:.7rem">Level {{ stats.my_level }} authority</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3">
          <div class="fw-bold fs-4 text-primary">{{ stats.all_pending }}</div>
          <div class="text-muted small">All Pending</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3" :class="stats.escalated > 0 ? 'border-danger' : ''">
          <div class="fw-bold fs-4" :class="stats.escalated > 0 ? 'text-danger' : 'text-secondary'">{{ stats.escalated }}</div>
          <div class="text-muted small">Escalated</div>
          <div class="text-muted" style="font-size:.7rem">{{ stats.stale }} waiting &gt;24h</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card shadow-sm text-center p-3 bg-light">
          <div class="text-muted small mt-1">Approval Levels</div>
          <div class="small mt-1">
            <span class="badge bg-primary-subtle text-primary me-1">L1 Branch Manager</span>
            <span class="badge bg-warning-subtle text-warning me-1">L2 Compliance</span>
            <span class="badge bg-danger-subtle text-danger">L3 Super Admin</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-3">
            <input v-model="form.search" type="text" class="form-control form-control-sm" placeholder="Reference, account, customer…" />
          </div>
          <div class="col-md-2">
            <select v-model="form.type" class="form-select form-select-sm">
              <option value="">All Types</option>
              <option value="deposit">Deposit</option>
              <option value="withdrawal">Withdrawal</option>
              <option value="transfer">Transfer</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.view" class="form-select form-select-sm">
              <option value="mine">My Queue</option>
              <option value="all">All Pending</option>
              <option value="escalated">Escalated</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Go</button>
            <Link :href="route('admin.approvals.index')" class="btn btn-light btn-sm"><i class="bi bi-x"></i></Link>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!pending.data.length" class="text-center text-muted py-5">
          <i class="bi bi-check2-circle d-block mb-2" style="font-size:2.5rem;opacity:.3;color:green"></i>
          <div v-if="form.view === 'mine'">No transactions require your approval right now.</div>
          <div v-else>No pending approvals found.</div>
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Reference</th>
                <th>Account</th>
                <th>Type</th>
                <th class="text-end">Amount</th>
                <th>Approval Level</th>
                <th>Initiated By</th>
                <th>Waiting Since</th>
                <th class="pe-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="txn in pending.data" :key="txn.id" :class="txn.escalated_at ? 'table-danger' : ''">
                <td class="ps-3">
                  <span class="font-monospace small fw-semibold">{{ txn.reference }}</span>
                  <span v-if="txn.escalated_at" class="badge bg-danger ms-1" style="font-size:.6rem">ESCALATED</span>
                  <span v-else-if="isStale(txn.created_at)" class="badge bg-warning text-dark ms-1" style="font-size:.6rem">STALE</span>
                </td>
                <td class="small">
                  <div class="fw-semibold">{{ txn.account?.account_number }}</div>
                  <div class="text-muted" style="font-size:.72rem">{{ txn.account?.customer?.name }}</div>
                </td>
                <td>
                  <span class="badge" :class="typeBadge(txn.type)">{{ typeLabel(txn.type) }}</span>
                </td>
                <td class="text-end fw-semibold small">{{ formatCurrency(txn.amount, txn.currency) }}</td>
                <td>
                  <div class="d-flex align-items-center gap-1">
                    <span v-for="l in txn.approval_level_required" :key="l"
                      class="badge"
                      :class="l <= txn.approval_level_reached ? 'bg-success' : 'bg-light text-dark border'"
                      style="font-size:.65rem;width:1.4rem;height:1.4rem;padding:.25rem">
                      {{ l }}
                    </span>
                    <span class="text-muted small ms-1">/ L{{ txn.approval_level_required }}</span>
                  </div>
                </td>
                <td class="small text-muted">{{ txn.processed_by?.name ?? '—' }}</td>
                <td class="small text-muted">{{ timeAgo(txn.created_at) }}</td>
                <td class="pe-3">
                  <Link :href="route('admin.approvals.show', txn.id)" class="btn btn-sm btn-warning">
                    <i class="bi bi-eye me-1"></i>Review
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div v-if="pending.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <span class="text-muted small">Showing {{ pending.from }}–{{ pending.to }} of {{ pending.total }}</span>
          <div class="d-flex gap-1">
            <Link v-for="link in pending.links" :key="link.label" :href="link.url ?? '#'"
              class="btn btn-sm" :class="link.active ? 'btn-primary' : 'btn-light'" :disabled="!link.url" v-html="link.label" />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  pending: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  stats:   { type: Object, default: () => ({}) },
});

const form = reactive({
  search: props.filters.search ?? '',
  type:   props.filters.type   ?? '',
  view:   props.filters.view   ?? 'mine',
});

const applyFilters = () => router.get(route('admin.approvals.index'), form, { preserveState: true });

const typeLabel = (t) => ({ deposit: 'Deposit', withdrawal: 'Withdrawal', transfer: 'Transfer' }[t] ?? t);
const typeBadge = (t) => ({ deposit: 'bg-success-subtle text-success', withdrawal: 'bg-danger-subtle text-danger', transfer: 'bg-primary-subtle text-primary' }[t] ?? 'bg-light text-muted');

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);

const timeAgo = (d) => {
  if (!d) return '—';
  const ms   = Date.now() - new Date(d).getTime();
  const mins = Math.floor(ms / 60000);
  if (mins < 60)  return `${mins}m ago`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h ago`;
  return `${Math.floor(hrs / 24)}d ago`;
};

const isStale = (createdAt) => createdAt && (Date.now() - new Date(createdAt).getTime()) > 24 * 60 * 60 * 1000;
</script>
