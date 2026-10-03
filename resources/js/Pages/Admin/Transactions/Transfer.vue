<template>
  <AdminLayout
    title="Transfer"
    subtitle="Move funds between two accounts"
    :breadcrumbs="[
      { label: 'Transactions', href: route('admin.transactions.index') },
      { label: 'Transfer' }
    ]"
  >
    <div class="row g-4">
      <!-- Form -->
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-arrow-left-right me-1 text-primary"></i>Transfer Details
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">

              <!-- FROM account -->
              <div class="mb-4">
                <label class="form-label fw-semibold">From Account <span class="text-danger">*</span></label>
                <!-- selected state -->
                <div v-if="fromAccount" class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold font-monospace">{{ fromAccount.account_number }}</div>
                    <div class="small text-muted">{{ fromAccount.customer?.name }} &bull; {{ typeLabel(fromAccount.account_type) }}</div>
                    <div class="small text-success fw-semibold">Balance: {{ fmt(fromAccount.balance, fromAccount.currency) }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearFrom">Change</button>
                </div>
                <!-- search state -->
                <div v-else>
                  <input v-model="fromSearch" type="text" class="form-control"
                         :class="form.errors.from_account_id ? 'is-invalid' : ''"
                         placeholder="Search by account number or customer…"
                         @input="onFromInput">
                  <div v-if="form.errors.from_account_id" class="invalid-feedback">{{ form.errors.from_account_id }}</div>
                  <div v-if="fromResults.length" class="border rounded mt-1 shadow-sm" style="max-height:200px;overflow-y:auto">
                    <div v-for="acc in fromResults" :key="acc.id"
                         class="px-3 py-2 d-flex justify-content-between align-items-center picker-row"
                         :class="fromHover === acc.id ? 'bg-primary text-white' : ''"
                         style="cursor:pointer"
                         @mouseenter="fromHover = acc.id"
                         @mouseleave="fromHover = null"
                         @mousedown.prevent="selectFrom(acc)">
                      <div>
                        <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                        <div class="small" :class="fromHover === acc.id ? 'text-white-50' : 'text-muted'">{{ acc.customer?.name }}</div>
                      </div>
                      <div class="small" :class="fromHover === acc.id ? 'text-white-50' : 'text-muted'">{{ fmt(acc.balance, acc.currency) }}</div>
                    </div>
                  </div>
                  <div v-if="fromSearch && !fromResults.length" class="text-muted small mt-1">No accounts found.</div>
                </div>
              </div>

              <!-- TO account -->
              <div class="mb-4">
                <label class="form-label fw-semibold">To Account <span class="text-danger">*</span></label>
                <div v-if="toAccount" class="border rounded p-3 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold font-monospace">{{ toAccount.account_number }}</div>
                    <div class="small text-muted">{{ toAccount.customer?.name }} &bull; {{ typeLabel(toAccount.account_type) }}</div>
                    <div class="small text-success fw-semibold">Balance: {{ fmt(toAccount.balance, toAccount.currency) }}</div>
                  </div>
                  <button type="button" class="btn btn-sm btn-outline-secondary" @click="clearTo">Change</button>
                </div>
                <div v-else>
                  <input v-model="toSearch" type="text" class="form-control"
                         :class="form.errors.to_account_id ? 'is-invalid' : ''"
                         placeholder="Search by account number or customer…"
                         @input="onToInput">
                  <div v-if="form.errors.to_account_id" class="invalid-feedback">{{ form.errors.to_account_id }}</div>
                  <div v-if="toResults.length" class="border rounded mt-1 shadow-sm" style="max-height:200px;overflow-y:auto">
                    <div v-for="acc in toResults" :key="acc.id"
                         class="px-3 py-2 d-flex justify-content-between align-items-center"
                         :class="toHover === acc.id ? 'bg-primary text-white' : ''"
                         style="cursor:pointer"
                         @mouseenter="toHover = acc.id"
                         @mouseleave="toHover = null"
                         @mousedown.prevent="selectTo(acc)">
                      <div>
                        <div class="fw-semibold font-monospace small">{{ acc.account_number }}</div>
                        <div class="small" :class="toHover === acc.id ? 'text-white-50' : 'text-muted'">{{ acc.customer?.name }}</div>
                      </div>
                      <div class="small" :class="toHover === acc.id ? 'text-white-50' : 'text-muted'">{{ fmt(acc.balance, acc.currency) }}</div>
                    </div>
                  </div>
                  <div v-if="toSearch && !toResults.length" class="text-muted small mt-1">No accounts found.</div>
                </div>
              </div>

              <!-- Amount -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">{{ fromAccount?.currency ?? 'USD' }}</span>
                  <input v-model="form.amount" type="number" min="0.01" step="0.01"
                         class="form-control form-control-lg"
                         :class="[form.errors.amount ? 'is-invalid' : '', insufficientFunds ? 'border-danger' : '']"
                         placeholder="0.00">
                  <div v-if="form.errors.amount" class="invalid-feedback">{{ form.errors.amount }}</div>
                </div>
                <div v-if="insufficientFunds" class="text-danger small mt-1">
                  <i class="bi bi-exclamation-triangle me-1"></i>Insufficient funds in source account.
                </div>
              </div>

              <!-- Description -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Description</label>
                <input v-model="form.description" type="text" class="form-control"
                       placeholder="e.g. Monthly rent transfer" maxlength="255">
              </div>

              <!-- Notes -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Internal Notes</label>
                <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Optional"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4"
                        :disabled="form.processing || !form.from_account_id || !form.to_account_id || insufficientFunds">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-check-lg me-1"></i>Execute Transfer
                </button>
                <Link :href="route('admin.transactions.index')" class="btn btn-light">Cancel</Link>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Preview -->
      <div class="col-lg-5">
        <div class="card shadow-sm border-primary">
          <div class="card-header bg-primary text-white fw-semibold">
            <i class="bi bi-arrow-left-right me-1"></i>Preview
          </div>
          <div class="card-body">
            <div v-if="!fromAccount && !toAccount" class="text-center text-muted py-3">
              Select accounts to preview
            </div>
            <div v-else>
              <!-- From -->
              <div class="mb-3 pb-3 border-bottom">
                <div class="text-muted small">From</div>
                <div v-if="fromAccount">
                  <div class="fw-bold font-monospace">{{ fromAccount.account_number }}</div>
                  <div class="small text-muted">{{ fromAccount.customer?.name }}</div>
                  <div class="small text-muted">Balance: {{ fmt(fromAccount.balance, fromAccount.currency) }}</div>
                  <div v-if="form.amount" class="small text-danger fw-semibold">
                    After: {{ fmt(parseFloat(fromAccount.balance) - parseFloat(form.amount), fromAccount.currency) }}
                  </div>
                </div>
                <div v-else class="text-muted small">—</div>
              </div>

              <div class="text-center text-primary my-2 fs-4"><i class="bi bi-arrow-down"></i></div>

              <!-- To -->
              <div class="mb-3 pb-3 border-bottom">
                <div class="text-muted small">To</div>
                <div v-if="toAccount">
                  <div class="fw-bold font-monospace">{{ toAccount.account_number }}</div>
                  <div class="small text-muted">{{ toAccount.customer?.name }}</div>
                  <div class="small text-muted">Balance: {{ fmt(toAccount.balance, toAccount.currency) }}</div>
                  <div v-if="form.amount" class="small text-success fw-semibold">
                    After: {{ fmt(parseFloat(toAccount.balance) + parseFloat(form.amount), toAccount.currency) }}
                  </div>
                </div>
                <div v-else class="text-muted small">—</div>
              </div>

              <!-- Amount -->
              <div>
                <div class="text-muted small">Transfer Amount</div>
                <div class="fw-bold fs-5 text-primary">
                  {{ form.amount ? fmt(form.amount, fromAccount?.currency ?? 'USD') : '—' }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  accounts: { type: Array, required: true },
})

const fromAccount = ref(null)
const toAccount   = ref(null)
const fromSearch  = ref('')
const toSearch    = ref('')
const fromHover   = ref(null)
const toHover     = ref(null)

const form = useForm({
  from_account_id: '',
  to_account_id:   '',
  amount:          '',
  description:     '',
  notes:           '',
})

const insufficientFunds = computed(() =>
  fromAccount.value && form.amount &&
  parseFloat(form.amount) > parseFloat(fromAccount.value.balance)
)

function filterAccounts(q, excludeId) {
  if (!q) return []
  const lq = q.toLowerCase()
  return props.accounts
    .filter(a => a.id !== excludeId &&
      (a.account_number.toLowerCase().includes(lq) || a.customer?.name?.toLowerCase().includes(lq)))
    .slice(0, 8)
}

const fromResults = computed(() => filterAccounts(fromSearch.value, toAccount.value?.id))
const toResults   = computed(() => filterAccounts(toSearch.value,   fromAccount.value?.id))

function selectFrom(acc) { fromAccount.value = acc; form.from_account_id = acc.id; fromSearch.value = '' }
function clearFrom()     { fromAccount.value = null; form.from_account_id = ''; fromSearch.value = '' }
function onFromInput()   { if (!fromSearch.value) clearFrom() }

function selectTo(acc)   { toAccount.value = acc; form.to_account_id = acc.id; toSearch.value = '' }
function clearTo()       { toAccount.value = null; form.to_account_id = ''; toSearch.value = '' }
function onToInput()     { if (!toSearch.value) clearTo() }

const submit = () => form.post(route('admin.transactions.transfer'))

const typeLabel = (t) => ({ savings: 'Savings', current: 'Current', fixed_deposit: 'Fixed Deposit' }[t] ?? t)
const fmt = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0)
</script>
