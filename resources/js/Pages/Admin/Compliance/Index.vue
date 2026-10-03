<template>
  <AdminLayout title="Compliance" subtitle="AML case review and large-transaction monitoring">
    <template #actions>
      <Link :href="route('admin.compliance.large-transactions')" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-file-earmark-bar-graph me-1"></i>Large Transaction Report
      </Link>
    </template>

    <!-- Filters -->
    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="applyFilters" class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Status</label>
            <select v-model="f.status" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="open">Open</option>
              <option value="reviewing">Reviewing</option>
              <option value="cleared">Cleared</option>
              <option value="reported">Reported</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Type</label>
            <select v-model="f.type" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="large_transaction">Large Transaction</option>
              <option value="structuring">Structuring</option>
              <option value="manual_flag">Manual Flag</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">Severity</label>
            <select v-model="f.severity" class="form-select form-select-sm">
              <option value="">All</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
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
      <div v-if="!cases.data.length" class="text-center text-muted py-5">
        <i class="bi bi-shield-check fs-1 mb-2 d-block"></i>
        No compliance cases.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Customer</th>
              <th>Type</th>
              <th>Severity</th>
              <th>Status</th>
              <th>Flagged By</th>
              <th>Opened</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cases.data" :key="c.id" class="cursor-pointer" @click="open(c)">
              <td class="font-monospace small">#{{ c.id }}</td>
              <td>{{ c.customer?.name }}</td>
              <td><span class="badge bg-secondary">{{ typeLabel(c.type) }}</span></td>
              <td><span class="badge" :class="severityBadge(c.severity)">{{ ucfirst(c.severity) }}</span></td>
              <td><span class="badge" :class="statusBadge(c.status)">{{ ucfirst(c.status) }}</span></td>
              <td class="small text-muted">{{ c.flagged_by ? c.flagged_by.name : 'System' }}</td>
              <td class="small text-muted">{{ fmtDate(c.created_at) }}</td>
              <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white border-top py-2 px-3">
        <Pagination :links="cases.links" :from="cases.from" :to="cases.to"
                    :total="cases.total" @navigate="(url) => router.get(url, {}, { preserveState: true })" />
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
  cases: Object,
  filters: { type: Object, default: () => ({}) },
})

const f = ref({ status: props.filters.status ?? '', type: props.filters.type ?? '', severity: props.filters.severity ?? '' })

const applyFilters = () => router.get(route('admin.compliance.index'),
  Object.fromEntries(Object.entries(f.value).filter(([, v]) => v)), { preserveState: true })
const clear = () => { f.value = { status: '', type: '', severity: '' }; router.get(route('admin.compliance.index')) }
const open = (c) => router.visit(route('admin.compliance.show', c.id))

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const typeLabel = (t) => ({ large_transaction: 'Large Transaction', structuring: 'Structuring', manual_flag: 'Manual Flag' }[t] ?? t)
const statusBadge = (s) => ({ open: 'bg-warning text-dark', reviewing: 'bg-info', cleared: 'bg-success', reported: 'bg-dark' }[s] ?? 'bg-secondary')
const severityBadge = (s) => ({ low: 'bg-secondary', medium: 'bg-primary', high: 'bg-danger' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
