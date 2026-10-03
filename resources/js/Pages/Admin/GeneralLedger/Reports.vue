<template>
  <AdminLayout title="Financial Reports" subtitle="Trial balance, balance sheet, and income statement">

    <ul class="nav nav-tabs mb-4">
      <li class="nav-item"><button class="nav-link" :class="{ active: tab === 'trial-balance' }" @click="tab = 'trial-balance'">Trial Balance</button></li>
      <li class="nav-item"><button class="nav-link" :class="{ active: tab === 'balance-sheet' }" @click="tab = 'balance-sheet'">Balance Sheet</button></li>
      <li class="nav-item"><button class="nav-link" :class="{ active: tab === 'income-statement' }" @click="tab = 'income-statement'">Income Statement</button></li>
    </ul>

    <!-- ── Trial Balance ────────────────────────────────────────────────── -->
    <div v-if="tab === 'trial-balance'">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <form @submit.prevent="reload" class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small fw-semibold mb-0">As Of</label>
              <input v-model="filters.as_of" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">Go</button>
            </div>
            <div class="col-md-3 ms-auto text-end">
              <a :href="exportUrl('trial-balance')" target="_blank" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
              </a>
            </div>
          </form>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
              <tr><th>Code</th><th>Account</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>
            </thead>
            <tbody>
              <tr v-for="row in trial_balance" :key="row.code">
                <td class="font-monospace">{{ row.code }}</td>
                <td>{{ row.name }}</td>
                <td class="text-capitalize text-muted">{{ row.type }}</td>
                <td class="text-end font-monospace">{{ formatMoney(row.debit_total) }}</td>
                <td class="text-end font-monospace">{{ formatMoney(row.credit_total) }}</td>
                <td class="text-end font-monospace fw-semibold">{{ formatMoney(row.balance) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="table-light fw-semibold">
                <td colspan="3">TOTALS</td>
                <td class="text-end font-monospace">{{ formatMoney(sum(trial_balance, 'debit_total')) }}</td>
                <td class="text-end font-monospace">{{ formatMoney(sum(trial_balance, 'credit_total')) }}</td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Balance Sheet ────────────────────────────────────────────────── -->
    <div v-else-if="tab === 'balance-sheet'">
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <form @submit.prevent="reload" class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small fw-semibold mb-0">As Of</label>
              <input v-model="filters.as_of" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">Go</button>
            </div>
            <div class="col-md-3 ms-auto text-end">
              <a :href="exportUrl('balance-sheet')" target="_blank" class="btn btn-sm btn-outline-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i>Export PDF
              </a>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5">{{ formatMoney(balance_sheet.total_assets) }}</div><div class="text-muted small">Total Assets</div></div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5">{{ formatMoney(balance_sheet.total_liabilities) }}</div><div class="text-muted small">Total Liabilities</div></div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5">{{ formatMoney(balance_sheet.total_equity) }}</div><div class="text-muted small">Total Equity</div></div></div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Assets</div>
            <ul class="list-group list-group-flush">
              <li v-for="row in balance_sheet.assets" :key="row.code" class="list-group-item d-flex justify-content-between">
                <span>{{ row.code }} — {{ row.name }}</span><span class="font-monospace">{{ formatMoney(row.balance) }}</span>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Liabilities</div>
            <ul class="list-group list-group-flush">
              <li v-for="row in balance_sheet.liabilities" :key="row.code" class="list-group-item d-flex justify-content-between">
                <span>{{ row.code }} — {{ row.name }}</span><span class="font-monospace">{{ formatMoney(row.balance) }}</span>
              </li>
            </ul>
          </div>
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Equity</div>
            <ul class="list-group list-group-flush">
              <li v-for="row in balance_sheet.equity" :key="row.code" class="list-group-item d-flex justify-content-between">
                <span>{{ row.code }} — {{ row.name }}</span><span class="font-monospace">{{ formatMoney(row.balance) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Income Statement ─────────────────────────────────────────────── -->
    <div v-else>
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
          <form @submit.prevent="reload" class="row g-2 align-items-end">
            <div class="col-md-3">
              <label class="form-label small fw-semibold mb-0">From</label>
              <input v-model="filters.date_from" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold mb-0">To</label>
              <input v-model="filters.date_to" type="date" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
              <button type="submit" class="btn btn-primary btn-sm w-100">Go</button>
            </div>
          </form>
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5 text-success">{{ formatMoney(income_statement.total_income) }}</div><div class="text-muted small">Total Income</div></div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5 text-danger">{{ formatMoney(income_statement.total_expense) }}</div><div class="text-muted small">Total Expense</div></div></div>
        <div class="col-md-4"><div class="card shadow-sm text-center p-3"><div class="fw-bold fs-5">{{ formatMoney(income_statement.net_income) }}</div><div class="text-muted small">Net Income</div></div></div>
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Income</div>
            <ul class="list-group list-group-flush">
              <li v-for="row in income_statement.income" :key="row.code" class="list-group-item d-flex justify-content-between">
                <span>{{ row.code }} — {{ row.name }}</span><span class="font-monospace">{{ formatMoney(row.balance) }}</span>
              </li>
            </ul>
          </div>
        </div>
        <div class="col-md-6">
          <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Expense</div>
            <ul class="list-group list-group-flush">
              <li v-for="row in income_statement.expense" :key="row.code" class="list-group-item d-flex justify-content-between">
                <span>{{ row.code }} — {{ row.name }}</span><span class="font-monospace">{{ formatMoney(row.balance) }}</span>
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  as_of: { type: String, required: true },
  date_from: { type: String, required: true },
  date_to: { type: String, required: true },
  trial_balance: { type: Array, default: () => [] },
  balance_sheet: { type: Object, default: () => ({ assets: [], liabilities: [], equity: [] }) },
  income_statement: { type: Object, default: () => ({ income: [], expense: [] }) },
})

const tab = ref('trial-balance')
const filters = reactive({
  as_of: props.as_of,
  date_from: props.date_from,
  date_to: props.date_to,
})

function reload() {
  router.get(route('admin.general-ledger.reports'), filters, { preserveState: true })
}

function exportUrl(report) {
  const params = new URLSearchParams({ as_of: filters.as_of })
  return route(`admin.general-ledger.reports.${report}.export`) + '?' + params.toString()
}

function sum(rows, key) {
  return rows.reduce((total, r) => total + Number(r[key] ?? 0), 0)
}

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
