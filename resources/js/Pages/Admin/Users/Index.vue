<template>
  <AdminLayout
    title="Staff Management"
    subtitle="Manage staff accounts and roles"
    :breadcrumbs="[{ label: 'Staff Management' }]"
  >
    <template #actions>
      <Link v-if="$page.props.auth.permissions.includes('users.create')" :href="route('admin.users.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-person-plus me-1"></i> Add Staff
      </Link>
    </template>

    <!-- Filters -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-4">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
              <input v-model="form.search" type="text" class="form-control" placeholder="Search by name, staff ID, or email…" />
            </div>
          </div>
          <div class="col-md-3">
            <select v-model="form.role" class="form-select form-select-sm">
              <option value="">All Roles</option>
              <option v-for="role in roles" :key="role" :value="role">{{ role }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select form-select-sm">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="suspended">Suspended</option>
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
                <th>Staff ID</th>
                <th>
                  <button class="btn btn-link btn-sm p-0 text-decoration-none fw-semibold text-uppercase" style="font-size:.8rem;letter-spacing:.5px;color:var(--nabaad-muted)" @click="sort('name')">
                    Name <i :class="sortIcon('name')"></i>
                  </button>
                </th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Txn Limit</th>
                <th>Last Login</th>
                <th class="text-end pe-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="users.data.length === 0">
                <td colspan="9" class="text-center text-muted py-5">
                  <i class="bi bi-people fs-1 d-block mb-2 opacity-25"></i>
                  No staff found.
                </td>
              </tr>
              <tr v-for="(user, i) in users.data" :key="user.id">
                <td class="ps-3 text-muted small">{{ users.from + i }}</td>
                <td class="small font-monospace fw-semibold">{{ user.staff_id }}</td>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                         :style="`width:34px;height:34px;background:${avatarColor(user.name)};flex-shrink:0;font-size:.8rem`">
                      {{ initials(user.name) }}
                    </div>
                    <div>
                      <div class="fw-semibold small">{{ user.name }}</div>
                      <div class="text-muted" style="font-size:.75rem">{{ user.phone ?? '—' }}</div>
                    </div>
                  </div>
                </td>
                <td class="small">{{ user.email }}</td>
                <td>
                  <span class="badge bg-light text-dark border" style="font-size:.75rem">
                    {{ user.roles?.[0]?.name ?? '—' }}
                  </span>
                </td>
                <td><StatusBadge :status="user.status" /></td>
                <td class="small">{{ formatCurrency(user.transaction_limit) }}</td>
                <td class="small text-muted">{{ user.last_login_at ? formatDate(user.last_login_at) : 'Never' }}</td>
                <td class="text-end pe-3">
                  <div class="btn-group btn-group-sm">
                    <Link :href="route('admin.users.show', user.id)" class="btn btn-outline-secondary btn-sm" title="View">
                      <i class="bi bi-eye"></i>
                    </Link>
                    <Link
                      v-if="$page.props.auth.permissions.includes('users.edit')"
                      :href="route('admin.users.edit', user.id)"
                      class="btn btn-outline-primary btn-sm"
                      title="Edit"
                    >
                      <i class="bi bi-pencil"></i>
                    </Link>
                    <button
                      v-if="$page.props.auth.permissions.includes('users.edit') && user.id !== $page.props.auth.user.id"
                      class="btn btn-outline-warning btn-sm"
                      :title="user.status === 'active' ? 'Suspend' : 'Activate'"
                      @click="confirmToggle(user)"
                    >
                      <i :class="user.status === 'active' ? 'bi bi-pause-circle' : 'bi bi-play-circle'"></i>
                    </button>
                    <button
                      v-if="$page.props.auth.permissions.includes('users.delete') && user.id !== $page.props.auth.user.id"
                      class="btn btn-outline-danger btn-sm"
                      title="Delete"
                      @click="confirmDelete(user)"
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
          :links="users.links"
          :from="users.from"
          :to="users.to"
          :total="users.total"
          @navigate="navigateTo"
        />
      </div>
    </div>

    <!-- Toggle Status Modal -->
    <ConfirmModal
      id="toggleModal"
      :title="toggleTarget?.status === 'active' ? 'Suspend Staff' : 'Activate Staff'"
      :message="toggleTarget?.status === 'active'
        ? `Suspend ${toggleTarget?.name}? They will no longer be able to log in.`
        : `Activate ${toggleTarget?.name}? They will be able to log in again.`"
      :variant="toggleTarget?.status === 'active' ? 'warning' : 'success'"
      :icon="toggleTarget?.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"
      :confirm-label="toggleTarget?.status === 'active' ? 'Suspend' : 'Activate'"
      @confirmed="doToggle"
    />

    <!-- Delete Modal -->
    <ConfirmModal
      id="deleteModal"
      title="Delete Staff"
      :message="`Permanently delete ${deleteTarget?.name}? This action cannot be undone.`"
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
import AdminLayout   from '@/Layouts/AdminLayout.vue';
import Pagination    from '@/Components/Pagination.vue';
import StatusBadge   from '@/Components/StatusBadge.vue';
import ConfirmModal  from '@/Components/ConfirmModal.vue';

const props = defineProps({
  users:   Object,
  roles:   Array,
  filters: Object,
});

const form = reactive({
  search:    props.filters?.search    ?? '',
  role:      props.filters?.role      ?? '',
  status:    props.filters?.status    ?? '',
  sort:      props.filters?.sort      ?? 'created_at',
  direction: props.filters?.direction ?? 'desc',
});

const applyFilters = () => {
  router.get(route('admin.users.index'), form, { preserveState: true, replace: true });
};

const resetFilters = () => {
  Object.assign(form, { search: '', role: '', status: '', sort: 'created_at', direction: 'desc' });
  applyFilters();
};

const sort = (column) => {
  form.direction = form.sort === column && form.direction === 'asc' ? 'desc' : 'asc';
  form.sort = column;
  applyFilters();
};

const sortIcon = (column) => {
  if (form.sort !== column) return 'bi bi-arrow-down-up text-muted';
  return form.direction === 'asc' ? 'bi bi-sort-alpha-down' : 'bi bi-sort-alpha-up';
};

const navigateTo = (url) => router.visit(url, { preserveState: true });

// ── Actions ───────────────────────────────────────────────────────────────────

const toggleTarget = ref(null);
const deleteTarget = ref(null);

const confirmToggle = (user) => {
  toggleTarget.value = user;
  new Modal(document.getElementById('toggleModal')).show();
};

const doToggle = () => {
  router.post(route('admin.users.toggle-status', toggleTarget.value.id), {}, { preserveState: false });
};

const confirmDelete = (user) => {
  deleteTarget.value = user;
  new Modal(document.getElementById('deleteModal')).show();
};

const doDelete = () => {
  router.delete(route('admin.users.destroy', deleteTarget.value.id));
};

// ── Helpers ───────────────────────────────────────────────────────────────────

const initials = (name) => name?.split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase() ?? '?';

const colors = ['#0B2447','#14395B','#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6'];
const avatarColor = (name) => colors[name?.charCodeAt(0) % colors.length] ?? '#0B2447';

const formatCurrency = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const formatDate = (d) => new Date(d).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
</script>
