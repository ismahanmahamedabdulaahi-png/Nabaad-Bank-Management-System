<template>
  <AdminLayout title="General Ledger" subtitle="Chart of accounts, journal entries, and financial reports">

    <div v-if="reconciliation.length" class="alert alert-warning d-flex align-items-start gap-2 mb-4">
      <i class="bi bi-exclamation-triangle-fill fs-5"></i>
      <div>
        <div class="fw-semibold">Reconciliation mismatch detected</div>
        <div class="small">
          <div v-for="row in reconciliation" :key="row.code">
            {{ row.code }} — {{ row.name }}: GL shows {{ formatMoney(row.gl_balance) }}, subsidiary ledger shows {{ formatMoney(row.expected) }}
            (difference {{ formatMoney(row.difference) }})
          </div>
        </div>
      </div>
    </div>
    <div v-else class="alert alert-success d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-check-circle-fill fs-5"></i>
      <div>All GL control accounts tie out to their subsidiary ledgers.</div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-lg-3">
        <Link :href="route('admin.general-ledger.chart-of-accounts')" class="card border-0 shadow-sm h-100 text-decoration-none quick-link">
          <div class="card-body text-center">
            <i class="bi bi-list-columns-reverse fs-2 text-primary"></i>
            <div class="fw-semibold mt-2">Chart of Accounts</div>
          </div>
        </Link>
      </div>
      <div class="col-lg-3">
        <Link :href="route('admin.general-ledger.journal-entries.index')" class="card border-0 shadow-sm h-100 text-decoration-none quick-link">
          <div class="card-body text-center">
            <i class="bi bi-journal-text fs-2 text-info"></i>
            <div class="fw-semibold mt-2">Journal Entries</div>
          </div>
        </Link>
      </div>
      <div class="col-lg-3">
        <Link v-if="can('gl.post')" :href="route('admin.general-ledger.journal-entries.create')" class="card border-0 shadow-sm h-100 text-decoration-none quick-link">
          <div class="card-body text-center">
            <i class="bi bi-plus-circle fs-2 text-success"></i>
            <div class="fw-semibold mt-2">New Journal Entry</div>
          </div>
        </Link>
      </div>
      <div class="col-lg-3">
        <Link :href="route('admin.general-ledger.reports')" class="card border-0 shadow-sm h-100 text-decoration-none quick-link">
          <div class="card-body text-center">
            <i class="bi bi-bar-chart-line fs-2 text-warning"></i>
            <div class="fw-semibold mt-2">Trial Balance &amp; Statements</div>
          </div>
        </Link>
      </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-header bg-white fw-semibold">Trial Balance (all-time)</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Code</th>
              <th>Account</th>
              <th>Type</th>
              <th class="text-end">Debit</th>
              <th class="text-end">Credit</th>
              <th class="text-end">Balance</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in trial_balance" :key="row.code">
              <td class="font-monospace">{{ row.code }}</td>
              <td>{{ row.name }}</td>
              <td class="text-muted text-capitalize">{{ row.type }}</td>
              <td class="text-end font-monospace">{{ formatMoney(row.debit_total) }}</td>
              <td class="text-end font-monospace">{{ formatMoney(row.credit_total) }}</td>
              <td class="text-end font-monospace fw-semibold">{{ formatMoney(row.balance) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="table-light fw-semibold">
              <td colspan="3">TOTALS</td>
              <td class="text-end font-monospace">{{ formatMoney(totalDebit) }}</td>
              <td class="text-end font-monospace">{{ formatMoney(totalCredit) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        Recent Journal Entries
        <Link :href="route('admin.general-ledger.journal-entries.index')" class="small">View all</Link>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Entry #</th>
              <th>Date</th>
              <th>Description</th>
              <th class="text-end">Amount</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="entry in recent_entries" :key="entry.id">
              <td class="font-monospace">{{ entry.entry_number }}</td>
              <td>{{ entry.entry_date }}</td>
              <td>{{ entry.description }}</td>
              <td class="text-end font-monospace">{{ formatMoney(entryTotal(entry)) }}</td>
              <td class="text-end">
                <Link :href="route('admin.general-ledger.journal-entries.show', entry.id)" class="btn btn-sm btn-outline-secondary">View</Link>
              </td>
            </tr>
            <tr v-if="!recent_entries.length">
              <td colspan="5" class="text-center text-muted py-4">No journal entries yet.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  trial_balance: { type: Array, default: () => [] },
  reconciliation: { type: Array, default: () => [] },
  recent_entries: { type: Array, default: () => [] },
})

const page = usePage()
function can(permission) {
  return page.props.auth?.permissions?.includes(permission) ?? false
}

const totalDebit = computed(() => props.trial_balance.reduce((sum, r) => sum + Number(r.debit_total), 0))
const totalCredit = computed(() => props.trial_balance.reduce((sum, r) => sum + Number(r.credit_total), 0))

function entryTotal(entry) {
  return (entry.lines ?? []).reduce((sum, l) => sum + Number(l.debit), 0)
}

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>

<style scoped>
.quick-link { transition: transform .1s ease; color: var(--nabaad-text); }
.quick-link:hover { transform: translateY(-2px); }
</style>
