<template>
  <PortalLayout title="New Standing Order" subtitle="Set up a recurring automatic transfer">
    <template #actions>
      <Link :href="route('customer.standing-orders.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <!-- Source account -->
                <div class="col-12">
                  <label class="form-label fw-semibold">From Account <span class="text-danger">*</span></label>
                  <select v-model="form.source_account_id" class="form-select"
                          :class="form.errors.source_account_id ? 'is-invalid' : ''" required>
                    <option value="">Select your account</option>
                    <option v-for="acc in my_accounts" :key="acc.id" :value="acc.id">
                      {{ acc.account_number }} — Balance: {{ fmt(acc.balance) }}
                    </option>
                  </select>
                  <div class="invalid-feedback">{{ form.errors.source_account_id }}</div>
                </div>

                <!-- Beneficiary search -->
                <div class="col-12">
                  <label class="form-label fw-semibold">To Account <span class="text-danger">*</span></label>
                  <input v-model="benSearch" type="text" class="form-control"
                         :class="form.errors.beneficiary_account_id ? 'is-invalid' : ''"
                         placeholder="Search by account number or name…">
                  <div class="invalid-feedback">{{ form.errors.beneficiary_account_id }}</div>
                  <div v-if="benSearch.length >= 2 && benResults.length"
                       class="list-group mt-1 shadow-sm" style="max-height:180px;overflow-y:auto;z-index:999;position:relative">
                    <button v-for="acc in benResults" :key="acc.id" type="button"
                            class="list-group-item list-group-item-action d-flex justify-content-between small"
                            @click="selectBen(acc)">
                      <span class="font-monospace">{{ acc.account_number }}</span>
                      <span class="text-muted">{{ acc.customer?.name }}</span>
                    </button>
                  </div>
                  <div v-if="selectedBen" class="alert alert-info py-2 small mt-2">
                    <i class="bi bi-check-circle me-1"></i>
                    <strong class="font-monospace">{{ selectedBen.account_number }}</strong>
                    — {{ selectedBen.customer?.name }}
                  </div>
                </div>

                <!-- Amount + frequency -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Amount (USD) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="form.amount" type="number" step="0.01" min="1"
                           class="form-control" :class="form.errors.amount ? 'is-invalid' : ''" required>
                    <div class="invalid-feedback">{{ form.errors.amount }}</div>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Frequency <span class="text-danger">*</span></label>
                  <select v-model="form.frequency" class="form-select" required>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                  </select>
                </div>

                <!-- Dates -->
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Start Date <span class="text-danger">*</span></label>
                  <input v-model="form.start_date" type="date" class="form-control"
                         :class="form.errors.start_date ? 'is-invalid' : ''" :min="today" required>
                  <div class="invalid-feedback">{{ form.errors.start_date }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">End Date (optional)</label>
                  <input v-model="form.end_date" type="date" class="form-control"
                         :min="form.start_date">
                </div>

                <!-- Description -->
                <div class="col-12">
                  <label class="form-label fw-semibold">Description (optional)</label>
                  <input v-model="form.description" type="text" class="form-control" maxlength="200"
                         placeholder="e.g. Rent payment, savings transfer…">
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-4" :disabled="form.processing || !isFormValid">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                Create Standing Order
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>

  </PortalLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  my_accounts:  { type: Array, default: () => [] },
  all_accounts: { type: Array, default: () => [] },
  preselected_source_account_id: { type: [Number, String], default: null },
})

const today = new Date().toISOString().split('T')[0]

const form = useForm({
  source_account_id:      props.preselected_source_account_id ?? '',
  beneficiary_account_id: '',
  amount:                 '',
  frequency:              'monthly',
  start_date:             today,
  end_date:               '',
  description:            '',
})

const benSearch  = ref('')
const selectedBen = ref(null)

const benResults = computed(() => {
  if (benSearch.value.length < 2) return []
  const s = benSearch.value.toLowerCase()
  return props.all_accounts
    .filter(a => !props.my_accounts.find(m => m.id === a.id))
    .filter(a => a.account_number.toLowerCase().includes(s) || a.customer?.name?.toLowerCase().includes(s))
    .slice(0, 20)
})

function selectBen(acc) {
  selectedBen.value = acc
  form.beneficiary_account_id = acc.id
  benSearch.value = acc.account_number
}

// Selecting a new "To Account" match invalidates any previously confirmed one
// until re-selected from the list — typing alone never sets beneficiary_account_id.
watch(benSearch, (value) => {
  if (selectedBen.value && value !== selectedBen.value.account_number) {
    selectedBen.value = null
    form.beneficiary_account_id = ''
  }
})

const isFormValid = computed(() =>
  !!form.source_account_id &&
  !!form.beneficiary_account_id &&
  Number(form.amount) > 0 &&
  !!form.frequency &&
  !!form.start_date
)

const submit = () => form.post(route('customer.standing-orders.store'))
const fmt    = (v) => new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(v ?? 0)
</script>
