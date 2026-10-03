<template>
  <AdminLayout
    title="Customers"
    subtitle="Manage bank customers"
    :breadcrumbs="[{ label: 'Customers' }]"
  >
    <template #actions>
      <Link v-if="$page.props.auth.permissions.includes('customers.create')" :href="route('admin.customers.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Add Customer
      </Link>
    </template>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-5">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
              <input v-model="form.search" type="text" class="form-control" placeholder="Search by name, email, phone, or customer number…" />
            </div>
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="pending">Pending Approval</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="blacklisted">Blacklisted</option>
              <option value="deceased">Deceased</option>
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
                <th>
                  <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--nabaad-muted)" @click="sort('name')">
                    Customer <i :class="sortIcon('name')"></i>
                  </button>
                </th>
                <th>
                  <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--nabaad-muted)" @click="sort('customer_number')">
                    Customer # <i :class="sortIcon('customer_number')"></i>
                  </button>
                </th>
                <th>Phone</th>
                <th>KYC</th>
                <th>Accounts</th>
                <th>Status</th>
                <th>
                  <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--nabaad-muted)" @click="sort('created_at')">
                    Joined <i :class="sortIcon('created_at')"></i>
                  </button>
                </th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="customers.data.length === 0">
                <td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                  No customers found.
                </td>
              </tr>
              <tr v-for="(customer, i) in customers.data" :key="customer.id">
                <td class="ps-3 text-muted small">{{ customers.from + i }}</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                         :style="`width:34px;height:34px;background:${avatarColor(customer.name)};flex-shrink:0;font-size:.8rem`">
                      {{ initials(customer.name) }}
                    </div>
                    <div>
                      <div class="fw-semibold small">{{ customer.name }}</div>
                      <div class="text-muted" style="font-size:.73rem">{{ customer.email }}</div>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="font-monospace small text-muted">{{ customer.customer_number }}</span>
                </td>
                <td class="small">{{ customer.phone }}</td>
                <td>
                  <span v-if="customer.kyc" class="badge rounded-pill" :class="kycBadge(customer.kyc.status)">
                    {{ kycLabel(customer.kyc.status) }}
                  </span>
                  <span v-else class="badge rounded-pill bg-light text-muted border">None</span>
                </td>
                <td class="small text-center">{{ customer.accounts_count ?? 0 }}</td>
                <td><StatusBadge :status="customer.status" /></td>
                <td class="small text-muted">{{ formatDate(customer.created_at) }}</td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <Link :href="route('admin.customers.show', customer.id)" class="btn btn-outline-secondary btn-sm" title="View">
                      <i class="bi bi-eye"></i>
                    </Link>
                    <Link
                      v-if="$page.props.auth.permissions.includes('customers.edit')"
                      :href="route('admin.customers.edit', customer.id)"
                      class="btn btn-outline-primary btn-sm"
                      title="Edit"
                    >
                      <i class="bi bi-pencil"></i>
                    </Link>
                    <button
                      v-if="$page.props.auth.permissions.includes('customers.edit') && !['deceased','blacklisted','pending'].includes(customer.status)"
                      class="btn btn-outline-warning btn-sm"
                      :title="customer.status === 'active' ? 'Deactivate' : 'Activate'"
                      @click="confirmToggle(customer)"
                    >
                      <i :class="customer.status === 'active' ? 'bi bi-pause-circle' : 'bi bi-play-circle'"></i>
                    </button>
                    <button
                      v-if="$page.props.auth.permissions.includes('customers.delete')"
                      class="btn btn-outline-danger btn-sm"
                      title="Delete"
                      @click="confirmDelete(customer)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer bg-white border-top py-2 px-3">
        <Pagination
          :links="customers.links"
          :from="customers.from"
          :to="customers.to"
          :total="customers.total"
          @navigate="navigateTo"
        />
      </div>
    </div>

    <!-- Toggle Modal -->
    <ConfirmModal
      id="customerToggleModal"
      :title="toggleTarget?.status === 'active' ? 'Deactivate Customer' : 'Activate Customer'"
      :message="toggleTarget?.status === 'active'
        ? `Deactivate ${toggleTarget?.name}? They will not be able to access the portal.`
        : `Activate ${toggleTarget?.name}? They will regain portal access.`"
      :variant="toggleTarget?.status === 'active' ? 'warning' : 'success'"
      :icon="toggleTarget?.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"
      :confirm-label="toggleTarget?.status === 'active' ? 'Deactivate' : 'Activate'"
      @confirmed="doToggle"
    />

    <!-- Delete Modal -->
    <ConfirmModal
      id="customerDeleteModal"
      title="Delete Customer"
      :message="`Permanently delete ${deleteTarget?.name}? All associated data will be removed. This cannot be undone.`"
      variant="danger"
      icon="bi-trash"
      confirm-label="Delete"
      @confirmed="doDelete"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import Pagination   from '@/Components/Pagination.vue';
import StatusBadge  from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
  customers: Object,
  filters:   Object,
});

const form = reactive({
  search:    props.filters?.search    ?? '',
  status:    props.filters?.status    ?? '',
  sort:      props.filters?.sort      ?? 'created_at',
  direction: props.filters?.direction ?? 'desc',
});

const applyFilters = () => router.get(route('admin.customers.index'), form, { preserveState: true, replace: true });
const resetFilters = () => { Object.assign(form, { search: '', status: '', sort: 'created_at', direction: 'desc' }); applyFilters(); };

const sort = (col) => {
  form.direction = form.sort === col && form.direction === 'asc' ? 'desc' : 'asc';
  form.sort = col;
  applyFilters();
};

const sortIcon = (col) => {
  if (form.sort !== col) return 'bi bi-arrow-down-up text-muted';
  return form.direction === 'asc' ? 'bi bi-sort-alpha-down' : 'bi bi-sort-alpha-up';
};

const navigateTo = (url) => router.visit(url, { preserveState: true });

// Actions
const toggleTarget = ref(null);
const deleteTarget = ref(null);

const confirmToggle = (c) => { toggleTarget.value = c; new Modal(document.getElementById('customerToggleModal')).show(); };
const doToggle = () => router.post(route('admin.customers.toggle-status', toggleTarget.value.id), {}, { preserveState: false });

const confirmDelete = (c) => { deleteTarget.value = c; new Modal(document.getElementById('customerDeleteModal')).show(); };
const doDelete = () => router.delete(route('admin.customers.destroy', deleteTarget.value.id));

// Helpers
const initials    = (n) => n?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?';
const colors      = ['#0B2447','#14395B','#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6'];
const avatarColor = (n) => colors[n?.charCodeAt(0) % colors.length] ?? '#0B2447';
const formatDate  = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });

const kycBadge  = (s) => ({ pending: 'bg-warning text-dark', approved: 'bg-success text-white', rejected: 'bg-danger text-white' }[s] ?? 'bg-light text-muted border');
const kycLabel  = (s) => ({ pending: 'Pending', approved: 'Verified', rejected: 'Rejected' }[s] ?? s);
</script>
