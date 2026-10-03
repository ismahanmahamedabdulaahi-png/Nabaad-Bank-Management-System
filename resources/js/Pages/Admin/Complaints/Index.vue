<template>
  <AdminLayout title="Complaints" subtitle="Customer complaints and disputes">

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="applyFilters" class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Status</label>
            <select v-model="f.status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="open">Open</option>
              <option value="in_progress">In Progress</option>
              <option value="resolved">Resolved</option>
              <option value="closed">Closed</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Priority</label>
            <select v-model="f.priority" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Category</label>
            <select v-model="f.category" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="transaction_dispute">Transaction Dispute</option>
              <option value="service_quality">Service Quality</option>
              <option value="account_issue">Account Issue</option>
              <option value="fraud_report">Fraud Report</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
              <i class="bi bi-funnel me-1"></i>Filter
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" @click="clear">Clear</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div v-if="!complaints.data.length" class="text-center text-muted py-5">
        <i class="bi bi-headset fs-1 mb-2 d-block"></i>
        No complaints found.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Reference</th>
              <th>Customer</th>
              <th>Subject</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Assigned To</th>
              <th>Filed</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in complaints.data" :key="c.id" class="cursor-pointer" @click="open(c)">
              <td class="font-monospace small">{{ c.complaint_number }}</td>
              <td>{{ c.customer?.name }}</td>
              <td>{{ c.subject }}</td>
              <td><span class="badge" :class="priorityBadge(c.priority)">{{ ucfirst(c.priority) }}</span></td>
              <td><span class="badge" :class="statusBadge(c.status)">{{ ucfirst(c.status) }}</span></td>
              <td class="small text-muted">{{ c.assigned_to?.name ?? '—' }}</td>
              <td class="small text-muted">{{ fmtDate(c.created_at) }}</td>
              <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white border-top py-2 px-3">
        <Pagination :links="complaints.links" :from="complaints.from" :to="complaints.to"
                    :total="complaints.total" @navigate="(url) => router.get(url, {}, { preserveState: true })" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  complaints: Object,
  filters: { type: Object, default: () => ({}) },
})

const f = ref({ status: props.filters.status ?? '', priority: props.filters.priority ?? '', category: props.filters.category ?? '' })

const applyFilters = () => router.get(route('admin.complaints.index'),
  Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true })
const clear = () => { f.value = { status: '', priority: '', category: '' }; router.get(route('admin.complaints.index')) }
const open = (c) => router.visit(route('admin.complaints.show', c.id))

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ open: 'bg-warning text-dark', in_progress: 'bg-info', resolved: 'bg-success', closed: 'bg-secondary' }[s] ?? 'bg-secondary')
const priorityBadge = (p) => ({ low: 'bg-secondary', medium: 'bg-primary', high: 'bg-danger' }[p] ?? 'bg-secondary')
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
