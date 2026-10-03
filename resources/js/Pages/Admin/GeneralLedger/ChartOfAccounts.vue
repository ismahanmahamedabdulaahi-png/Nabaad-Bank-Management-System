<template>
  <AdminLayout title="Chart of Accounts" subtitle="General Ledger accounts and their current balances">

    <div class="card border-0 shadow-sm mb-4">
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Code</th>
              <th>Name</th>
              <th>Type</th>
              <th>Normal Balance</th>
              <th class="text-end">Balance</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="account in accounts" :key="account.id">
              <td class="font-monospace">{{ account.code }}</td>
              <td>
                {{ account.name }}
                <span v-if="!account.is_system" class="badge bg-secondary-subtle text-secondary ms-1">custom</span>
              </td>
              <td class="text-capitalize">{{ account.type }}</td>
              <td class="text-capitalize text-muted">{{ account.normal_balance }}</td>
              <td class="text-end font-monospace fw-semibold">{{ formatMoney(account.balance) }}</td>
              <td class="text-muted small">{{ account.description }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="can('gl.manage-accounts')" class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Add a Custom GL Account</div>
      <div class="card-body">
        <form @submit.prevent="submit" class="row g-3">
          <div class="col-md-2">
            <label class="form-label fw-semibold small">Code <span class="text-danger">*</span></label>
            <input v-model="form.code" type="text" class="form-control form-control-sm" maxlength="10" required>
            <div v-if="form.errors.code" class="text-danger small">{{ form.errors.code }}</div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Name <span class="text-danger">*</span></label>
            <input v-model="form.name" type="text" class="form-control form-control-sm" required>
            <div v-if="form.errors.name" class="text-danger small">{{ form.errors.name }}</div>
          </div>
          <div class="col-md-2">
            <label class="form-label fw-semibold small">Type <span class="text-danger">*</span></label>
            <select v-model="form.type" class="form-select form-select-sm" required>
              <option value="asset">Asset</option>
              <option value="liability">Liability</option>
              <option value="equity">Equity</option>
              <option value="income">Income</option>
              <option value="expense">Expense</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small">Description</label>
            <input v-model="form.description" type="text" class="form-control form-control-sm">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-sm btn-primary" :disabled="form.processing">
              <i class="bi bi-plus-lg me-1"></i>Add Account
            </button>
          </div>
        </form>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  accounts: { type: Array, default: () => [] },
})

const page = usePage()
function can(permission) {
  return page.props.auth?.permissions?.includes(permission) ?? false
}

const form = useForm({
  code: '',
  name: '',
  type: 'expense',
  description: '',
})

function submit() {
  form.post(route('admin.general-ledger.chart-of-accounts.store'), {
    onSuccess: () => form.reset(),
  })
}

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
