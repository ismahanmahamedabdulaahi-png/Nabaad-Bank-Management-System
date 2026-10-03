<template>
  <AdminLayout
    title="KYC Management"
    subtitle="Review customer identity verification submissions"
    :breadcrumbs="[{ label: 'KYC' }]"
  >
    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-warning bg-opacity-10" style="width:48px;height:48px;flex-shrink:0">
              <i class="bi bi-hourglass-split text-warning fs-5"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold">{{ stats.pending }}</div>
              <div class="text-muted small">Pending Review</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-success bg-opacity-10" style="width:48px;height:48px;flex-shrink:0">
              <i class="bi bi-shield-check text-success fs-5"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold">{{ stats.approved }}</div>
              <div class="text-muted small">Approved</div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body d-flex align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger bg-opacity-10" style="width:48px;height:48px;flex-shrink:0">
              <i class="bi bi-shield-x text-danger fs-5"></i>
            </div>
            <div>
              <div class="fs-4 fw-bold">{{ stats.rejected }}</div>
              <div class="text-muted small">Rejected</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-5">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
              <input v-model="form.search" type="text" class="form-control" placeholder="Search by customer name or number…" />
            </div>
          </div>
          <div class="col-md-3">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="pending">Pending</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <button type="button" class="btn btn-light btn-sm ms-1" @click="resetFilters">Reset</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th class="ps-3">#</th>
                <th>Customer</th>
                <th>Documents</th>
                <th>Status</th>
                <th>Reviewed By</th>
                <th>Reviewed At</th>
                <th>
                  <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--nabaad-muted)" @click="sort('created_at')">
                    Submitted <i :class="sortIcon('created_at')"></i>
                  </button>
                </th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="kycs.data.length === 0">
                <td colspan="8" class="text-center text-muted py-5">
                  <i class="bi bi-shield-check fs-1 d-block mb-2 opacity-25"></i>
                  No KYC records found.
                </td>
              </tr>
              <tr v-for="(kyc, i) in kycs.data" :key="kyc.id">
                <td class="ps-3 text-muted small">{{ kycs.from + i }}</td>
                <td>
                  <div class="fw-semibold small">{{ kyc.customer?.name }}</div>
                  <div class="font-monospace text-muted" style="font-size:.72rem">{{ kyc.customer?.customer_number }}</div>
                </td>
                <td class="small text-center">
                  <span class="badge" :class="kyc.documents_count > 0 ? 'bg-light text-dark border' : 'bg-danger-subtle text-danger'">
                    {{ kyc.documents_count }} doc{{ kyc.documents_count !== 1 ? 's' : '' }}
                  </span>
                </td>
                <td>
                  <span class="badge rounded-pill" :class="statusBadge(kyc.status)">
                    {{ statusLabel(kyc.status) }}
                  </span>
                </td>
                <td class="small text-muted">{{ kyc.verified_by ? kyc.verified_by.name : '—' }}</td>
                <td class="small text-muted">{{ kyc.verified_at ? formatDate(kyc.verified_at) : '—' }}</td>
                <td class="small text-muted">{{ formatDate(kyc.created_at) }}</td>
                <td class="text-end pe-3">
                  <Link :href="route('admin.kyc.show', kyc.id)" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye me-1"></i> Review
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-white border-top py-2 px-3">
        <Pagination
          :links="kycs.links"
          :from="kycs.from"
          :to="kycs.to"
          :total="kycs.total"
          @navigate="navigateTo"
        />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Pagination  from '@/Components/Pagination.vue';

const props = defineProps({
  kycs:    Object,
  filters: Object,
  stats:   Object,
});

const form = reactive({
  search:    props.filters?.search    ?? '',
  status:    props.filters?.status    ?? '',
  sort:      props.filters?.sort      ?? 'created_at',
  direction: props.filters?.direction ?? 'desc',
});

const applyFilters = () => router.get(route('admin.kyc.index'), form, { preserveState: true, replace: true });
const resetFilters = () => { Object.assign(form, { search: '', status: '', sort: 'created_at', direction: 'desc' }); applyFilters(); };

const sort = (col) => {
  form.direction = form.sort === col && form.direction === 'asc' ? 'desc' : 'asc';
  form.sort = col;
  applyFilters();
};
const sortIcon = (col) => form.sort !== col ? 'bi bi-arrow-down-up text-muted' : (form.direction === 'asc' ? 'bi bi-sort-alpha-down' : 'bi bi-sort-alpha-up');
const navigateTo = (url) => router.visit(url, { preserveState: true });

const statusBadge = (s) => ({ pending: 'bg-warning text-dark', approved: 'bg-success text-white', rejected: 'bg-danger text-white' }[s] ?? 'bg-secondary text-white');
const statusLabel = (s) => ({ pending: 'Pending Review', approved: 'Approved', rejected: 'Rejected' }[s] ?? s);
const formatDate  = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
</script>
