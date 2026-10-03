<template>
  <AdminLayout title="New Journal Entry" subtitle="Post a manual, balanced entry to the General Ledger">
    <template #actions>
      <Link :href="route('admin.general-ledger.journal-entries.index')" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <form @submit.prevent="submit">
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label fw-semibold small">Date <span class="text-danger">*</span></label>
              <input v-model="form.entry_date" type="date" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-9">
              <label class="form-label fw-semibold small">Description <span class="text-danger">*</span></label>
              <input v-model="form.description" type="text" class="form-control form-control-sm" required>
            </div>
          </div>
          <div v-if="form.errors.lines" class="alert alert-danger py-2 small">{{ form.errors.lines }}</div>

          <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead class="table-light">
              <tr>
                <th>GL Account</th>
                <th style="width:160px" class="text-end">Debit</th>
                <th style="width:160px" class="text-end">Credit</th>
                <th>Memo</th>
                <th style="width:40px"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, i) in form.lines" :key="i">
                <td>
                  <select v-model="line.gl_account_id" class="form-select form-select-sm" required>
                    <option value="" disabled>Select account…</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">{{ acc.code }} — {{ acc.name }}</option>
                  </select>
                </td>
                <td><input v-model="line.debit" type="number" step="0.01" min="0" class="form-control form-control-sm text-end" @input="line.credit = ''"></td>
                <td><input v-model="line.credit" type="number" step="0.01" min="0" class="form-control form-control-sm text-end" @input="line.debit = ''"></td>
                <td><input v-model="line.memo" type="text" class="form-control form-control-sm"></td>
                <td>
                  <button type="button" class="btn btn-sm btn-outline-danger" :disabled="form.lines.length <= 2" @click="removeLine(i)">
                    <i class="bi bi-x"></i>
                  </button>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="table-light fw-semibold">
                <td>
                  <button type="button" class="btn btn-sm btn-outline-primary" @click="addLine">
                    <i class="bi bi-plus-lg me-1"></i>Add Line
                  </button>
                </td>
                <td class="text-end font-monospace">{{ formatMoney(totalDebit) }}</td>
                <td class="text-end font-monospace">{{ formatMoney(totalCredit) }}</td>
                <td colspan="2">
                  <span v-if="!isBalanced" class="text-danger small">
                    <i class="bi bi-exclamation-triangle me-1"></i>Debits and credits must match.
                  </span>
                  <span v-else class="text-success small"><i class="bi bi-check-circle me-1"></i>Balanced</span>
                </td>
              </tr>
            </tfoot>
          </table>
          </div>

          <button type="submit" class="btn btn-primary" :disabled="form.processing || !isBalanced">
            <i class="bi bi-check-lg me-1"></i>Post Journal Entry
          </button>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
  accounts: { type: Array, default: () => [] },
})

const form = useForm({
  entry_date: new Date().toISOString().slice(0, 10),
  description: '',
  lines: [
    { gl_account_id: '', debit: '', credit: '', memo: '' },
    { gl_account_id: '', debit: '', credit: '', memo: '' },
  ],
})

function addLine() {
  form.lines.push({ gl_account_id: '', debit: '', credit: '', memo: '' })
}

function removeLine(i) {
  form.lines.splice(i, 1)
}

const totalDebit = computed(() => form.lines.reduce((sum, l) => sum + Number(l.debit || 0), 0))
const totalCredit = computed(() => form.lines.reduce((sum, l) => sum + Number(l.credit || 0), 0))
const isBalanced = computed(() => totalDebit.value > 0 && Math.abs(totalDebit.value - totalCredit.value) < 0.005)

function submit() {
  form.post(route('admin.general-ledger.journal-entries.store'))
}

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
