<template>
  <AdminLayout title="Journal Entries" subtitle="Every posting made to the General Ledger">
    <template #actions>
      <Link v-if="can('gl.post')" :href="route('admin.general-ledger.journal-entries.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-plus-lg me-1"></i>New Entry
      </Link>
    </template>

    <div class="card shadow-sm mb-4">
      <div class="card-body py-2">
        <form @submit.prevent="applyFilters" class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-0">From</label>
            <input v-model="form.date_from" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-0">To</label>
            <input v-model="form.date_to" type="date" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label small fw-semibold mb-0">GL Account</label>
            <select v-model="form.gl_account_id" class="form-select form-select-sm">
              <option value="">All Accounts</option>
              <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label small fw-semibold mb-0">Source</label>
            <select v-model="form.source_type" class="form-select form-select-sm">
              <option value="">All Sources</option>
              <option value="transaction">Transaction</option>
              <option value="cheque">Cheque</option>
              <option value="vault_transaction">Vault</option>
              <option value="till_cash_movement">Till Transfer</option>
              <option value="opening_balance">Opening Balance</option>
              <option value="manual">Manual</option>
            </select>
          </div>
          <div class="col-md-2 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Go</button>
            <Link :href="route('admin.general-ledger.journal-entries.index')" class="btn btn-light btn-sm"><i class="bi bi-x"></i></Link>
          </div>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div v-if="!entries.data.length" class="text-center text-muted py-5">
          <i class="bi bi-journal-text d-block mb-2" style="font-size:2.5rem;opacity:.3"></i>
          No journal entries found.
        </div>
        <div class="table-responsive" v-else>
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Entry #</th>
                <th>Date</th>
                <th>Description</th>
                <th>Source</th>
                <th class="text-end">Amount</th>
                <th>Posted By</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="entry in entries.data" :key="entry.id">
                <td class="ps-3 font-monospace small fw-semibold">
                  {{ entry.entry_number }}
                  <span v-if="entry.is_reversal" class="badge bg-warning-subtle text-warning ms-1">reversal</span>
                </td>
                <td class="small">{{ entry.entry_date }}</td>
                <td class="small">{{ entry.description }}</td>
                <td class="small text-muted">{{ entry.source_type ?? '—' }}</td>
                <td class="text-end font-monospace small">{{ formatMoney(entryTotal(entry)) }}</td>
                <td class="small text-muted">{{ entry.created_by?.name ?? 'System' }}</td>
                <td class="pe-3">
                  <Link :href="route('admin.general-ledger.journal-entries.show', entry.id)" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye"></i>
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="entries.last_page > 1" class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
          <span class="text-muted small">Showing {{ entries.from }}–{{ entries.to }} of {{ entries.total }}</span>
          <div class="d-flex gap-1">
            <Link v-for="link in entries.links" :key="link.label" :href="link.url ?? '#'"
              class="btn btn-sm" :class="link.active ? 'btn-primary' : 'btn-light'" :disabled="!link.url" v-html="link.label" />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  entries: { type: Object, required: true },
  accounts: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
function can(permission) {
  return page.props.auth?.permissions?.includes(permission) ?? false
}

const form = reactive({
  date_from: props.filters.date_from ?? '',
  date_to: props.filters.date_to ?? '',
  gl_account_id: props.filters.gl_account_id ?? '',
  source_type: props.filters.source_type ?? '',
})

function applyFilters() {
  router.get(route('admin.general-ledger.journal-entries.index'), form, { preserveState: true })
}

function entryTotal(entry) {
  return (entry.lines ?? []).reduce((sum, l) => sum + Number(l.debit), 0)
}

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
