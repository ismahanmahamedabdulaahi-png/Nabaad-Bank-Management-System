<template>
  <AdminLayout title="Audit Logs" subtitle="Complete record of all system actions">

    <!-- Stats row -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
          <div class="fs-4 fw-bold section-heading-brand">{{ logs.total }}</div>
          <div class="text-muted small">Total Entries</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
          <div class="fs-4 fw-bold text-danger">{{ logs.data.filter(l => l.action === 'deleted').length }}</div>
          <div class="text-muted small">Deletes (this page)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
          <div class="fs-4 fw-bold text-success">{{ logs.data.filter(l => l.action === 'created').length }}</div>
          <div class="text-muted small">Creates (this page)</div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center py-3">
          <div class="fs-4 fw-bold text-warning">{{ logs.data.filter(l => l.action === 'updated').length }}</div>
          <div class="text-muted small">Updates (this page)</div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <form @submit.prevent="apply" class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Search description</label>
            <input v-model="f.search" type="text" class="form-control form-control-sm" placeholder="Type to search…">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold small">User</label>
            <input v-model="f.user_name" type="text" class="form-control form-control-sm" placeholder="Name…">
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold small">Module</label>
            <select v-model="f.module" class="form-select form-select-sm">
              <option value="">All Modules</option>
              <option v-for="m in modules" :key="m" :value="m">{{ ucfirst(m) }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold small">Action</label>
            <select v-model="f.action" class="form-select form-select-sm">
              <option value="">All Actions</option>
              <option v-for="a in actions" :key="a" :value="a">{{ ucfirst(a) }}</option>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label fw-semibold small">From</label>
            <input v-model="f.date_from" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-1">
            <label class="form-label fw-semibold small">To</label>
            <input v-model="f.date_to" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">Go</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="clear">✕</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
      <div class="card-body p-0">
        <div v-if="!logs.data.length" class="text-center text-muted py-5">
          <i class="bi bi-journal-text fs-1 mb-2 d-block"></i>
          No audit logs found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-sm table-hover mb-0">
            <thead class="table-dark">
              <tr>
                <th>Date &amp; Time</th>
                <th>User</th>
                <th>Module</th>
                <th>Action</th>
                <th>Description</th>
                <th>IP</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="log in logs.data" :key="log.id">
                <td class="small text-muted text-nowrap">{{ fmtDt(log.created_at) }}</td>
                <td class="small">
                  <span class="fw-semibold">{{ log.user_name ?? 'System' }}</span>
                  <span v-if="log.user_type?.includes('Customer')" class="badge bg-info ms-1" style="font-size:.6rem">Customer</span>
                </td>
                <td>
                  <span class="badge bg-secondary-subtle text-secondary-emphasis border text-capitalize">{{ log.module }}</span>
                </td>
                <td>
                  <span class="badge" :class="actionBadge(log.action)">{{ log.action }}</span>
                </td>
                <td class="small" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                    :title="log.description">{{ log.description }}</td>
                <td class="small text-muted font-monospace">{{ log.ip_address }}</td>
                <td>
                  <Link v-if="log.old_values || log.new_values"
                        :href="route('admin.audit-logs.show', log.id)"
                        class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:.75rem">
                    Diff
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <!-- Pagination -->
      <div v-if="logs.last_page > 1" class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">
          Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
        </span>
        <div class="d-flex gap-2">
          <Link v-if="logs.prev_page_url" :href="logs.prev_page_url" class="btn btn-sm btn-outline-secondary">Prev</Link>
          <Link v-if="logs.next_page_url" :href="logs.next_page_url" class="btn btn-sm btn-outline-primary">Next</Link>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  logs:    Object,
  modules: { type: Array, default: () => [] },
  actions: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const f = ref({
  search: props.filters.search ?? '', user_name: props.filters.user_name ?? '',
  module: props.filters.module ?? '', action: props.filters.action ?? '',
  date_from: props.filters.date_from ?? '', date_to: props.filters.date_to ?? '',
})

const apply = () => {
  router.get(route('admin.audit-logs.index'), Object.fromEntries(Object.entries(f.value).filter(([,v]) => v)), { preserveState: true })
}
const clear = () => {
  f.value = { search: '', user_name: '', module: '', action: '', date_from: '', date_to: '' }
  router.get(route('admin.audit-logs.index'))
}

const fmtDt   = (d) => d ? new Date(d).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const actionBadge = (a) => ({
  created: 'bg-success', updated: 'bg-warning text-dark', deleted: 'bg-danger',
  login: 'bg-primary', logout: 'bg-secondary', approved: 'bg-info', rejected: 'bg-danger',
  viewed: 'bg-light text-dark border',
}[a] ?? 'bg-secondary')
</script>
