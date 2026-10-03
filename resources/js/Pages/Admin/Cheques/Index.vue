<template>
  <AdminLayout title="Cheque Management">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2 class="mb-0 fw-bold">Cheque Books</h2>
        <small class="text-muted">Issue and track cheque books</small>
      </div>
      <div class="d-flex gap-2">
        <Link :href="route('admin.cheques.verify')" class="btn btn-outline-primary">
          <i class="bi bi-search me-1"></i>Verify Cheque
        </Link>
        <Link v-if="can('cheques.issue')" :href="route('admin.cheques.create')" class="btn btn-primary">
          <i class="bi bi-plus-lg me-1"></i>Issue Book
        </Link>
      </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
          <div class="card-body">
            <div class="fs-3 fw-bold text-primary">{{ stats.total_books }}</div>
            <small class="text-muted">Total Books</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
          <div class="card-body">
            <div class="fs-3 fw-bold text-success">{{ stats.active_books }}</div>
            <small class="text-muted">Active Books</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
          <div class="card-body">
            <div class="fs-3 fw-bold text-warning">{{ stats.pending_clearance }}</div>
            <small class="text-muted">Pending Clearance</small>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center h-100">
          <div class="card-body">
            <div class="fs-3 fw-bold text-danger">{{ stats.bounced_total }}</div>
            <small class="text-muted">Bounced</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-4">
            <input v-model="form.search" type="text" class="form-control"
                   placeholder="Book #, series, or customer name…">
          </div>
          <div class="col-md-2">
            <select v-model="form.status" class="form-select">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="exhausted">Exhausted</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="col-md-2">
            <input v-model="form.date_from" type="date" class="form-control" placeholder="From">
          </div>
          <div class="col-md-2">
            <input v-model="form.date_to" type="date" class="form-control" placeholder="To">
          </div>
          <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
            <button v-if="hasFilters" type="button" @click="clearFilters" class="btn btn-outline-secondary">
              <i class="bi bi-x"></i>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Book #</th>
              <th>Customer</th>
              <th>Account</th>
              <th>Series</th>
              <th class="text-center">Leaves</th>
              <th>Status</th>
              <th>Issued</th>
              <th>Issued By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="books.data.length === 0">
              <td colspan="9" class="text-center text-muted py-5">No cheque books found.</td>
            </tr>
            <tr v-for="book in books.data" :key="book.id">
              <td class="font-monospace fw-bold">{{ book.book_number }}</td>
              <td>
                <div class="fw-semibold">{{ book.customer?.name }}</div>
                <small class="text-muted">{{ book.customer?.customer_number }}</small>
              </td>
              <td class="font-monospace">{{ book.account?.account_number }}</td>
              <td class="font-monospace">
                <small>{{ book.series_start }} – {{ book.series_end }}</small>
              </td>
              <td class="text-center">
                <span class="badge bg-light text-dark border">
                  {{ book.used_leaves }}/{{ book.total_leaves }}
                </span>
              </td>
              <td>
                <span :class="statusClass(book.status)" class="badge">
                  {{ book.status }}
                </span>
              </td>
              <td>
                <small>{{ formatDate(book.issued_at) }}</small>
              </td>
              <td>
                <small class="text-muted">{{ book.issued_by?.name }}</small>
              </td>
              <td>
                <Link :href="route('admin.cheques.show', book.id)" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="books.last_page > 1" class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
          Showing {{ books.from }}–{{ books.to }} of {{ books.total }}
        </small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <li v-for="link in books.links" :key="link.label"
                class="page-item" :class="{ active: link.active, disabled: !link.url }">
              <Link v-if="link.url" :href="link.url" class="page-link" v-html="link.label" />
              <span v-else class="page-link" v-html="link.label" />
            </li>
          </ul>
        </nav>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  books:   Object,
  filters: Object,
  stats:   Object,
})

const page = usePage()
const can  = (p) => page.props.auth?.permissions?.includes(p) ?? false

const form = reactive({
  search:    props.filters.search    ?? '',
  status:    props.filters.status    ?? '',
  date_from: props.filters.date_from ?? '',
  date_to:   props.filters.date_to   ?? '',
})

const hasFilters = computed(() => Object.values(form).some(v => v !== ''))

function applyFilters() {
  router.get(route('admin.cheques.index'), form, { preserveState: true, replace: true })
}

function clearFilters() {
  Object.assign(form, { search: '', status: '', date_from: '', date_to: '' })
  applyFilters()
}

function statusClass(status) {
  return {
    active:    'bg-success',
    exhausted: 'bg-secondary',
    cancelled: 'bg-danger',
  }[status] ?? 'bg-secondary'
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>
