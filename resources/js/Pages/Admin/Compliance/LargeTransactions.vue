<template>
  <AdminLayout title="Large Transaction Report" subtitle="Completed transactions at or above the reporting threshold">
    <template #actions>
      <Link :href="route('admin.compliance.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Compliance
      </Link>
    </template>

    <div class="card shadow-sm mb-4">
      <div class="card-body">
        <form @submit.prevent="apply" class="row g-3 align-items-end">
          <div class="col-md-3">
            <label class="form-label fw-semibold small">From</label>
            <input v-model="f.date_from" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold small">To</label>
            <input v-model="f.date_to" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary w-100">
              <i class="bi bi-funnel me-1"></i>Apply
            </button>
          </div>
          <div class="col-md-3">
            <a :href="exportUrl" class="btn btn-sm btn-outline-success w-100">
              <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
          </div>
        </form>
        <p class="text-muted small mt-3 mb-0">
          Reporting threshold: <strong>{{ fmt(threshold) }}</strong> — configurable in Settings.
        </p>
      </div>
    </div>

    <div class="card shadow-sm">
      <div v-if="!transactions.length" class="text-center text-muted py-5">
        <i class="bi bi-file-earmark-bar-graph fs-1 mb-2 d-block"></i>
        No transactions at or above the threshold in this range.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Date</th>
              <th>Reference</th>
              <th>Customer</th>
              <th>Account</th>
              <th>Type</th>
              <th class="text-end">Amount</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transactions" :key="t.id">
              <td class="small text-muted">{{ fmtDate(t.created_at) }}</td>
              <td class="font-monospace small">{{ t.reference }}</td>
              <td>{{ t.account?.customer?.name }}</td>
              <td class="font-monospace small">{{ t.account?.account_number }}</td>
              <td><span class="badge bg-secondary">{{ ucfirst(t.type) }}</span></td>
              <td class="text-end fw-semibold tabular-nums">{{ fmt(t.amount) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  transactions: { type: Array, default: () => [] },
  threshold: { type: Number, default: 0 },
  date_from: String,
  date_to: String,
})

const f = ref({ date_from: props.date_from, date_to: props.date_to })

const apply = () => router.get(route('admin.compliance.large-transactions'), f.value, { preserveState: true })
const exportUrl = computed(() => route('admin.compliance.large-transactions', { ...f.value, format: 'excel' }))

const fmt      = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate  = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
const ucfirst  = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
</script>
