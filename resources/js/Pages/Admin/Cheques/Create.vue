<template>
  <AdminLayout title="Issue Cheque Book">
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="d-flex align-items-center gap-3 mb-4">
          <Link :href="route('admin.cheques.index')" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
          </Link>
          <div>
            <h2 class="mb-0 fw-bold">Issue Cheque Book</h2>
            <small class="text-muted">Assign a new cheque book to a customer account</small>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">

              <!-- Customer Search -->
              <div class="mb-3 position-relative">
                <label class="form-label fw-semibold">Customer</label>
                <input v-model="search" type="text" class="form-control"
                       :class="{ 'is-invalid': form.errors.customer_id }"
                       placeholder="Type customer name or number…"
                       @input="filterCustomers"
                       @focus="showDropdown = search.length > 0"
                       @blur="onBlur"
                       autocomplete="off">
                <div v-if="showDropdown && filtered.length" class="dropdown-menu show w-100 shadow-sm" style="max-height:220px;overflow-y:auto">
                  <button v-for="c in filtered" :key="c.id" type="button"
                          class="dropdown-item" @mousedown.prevent="selectCustomer(c)">
                    <div class="fw-semibold">{{ c.name }}</div>
                    <small class="text-muted">{{ c.customer_number }}</small>
                  </button>
                </div>
                <div v-if="form.errors.customer_id" class="invalid-feedback">{{ form.errors.customer_id }}</div>
              </div>

              <!-- Account -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Account</label>
                <select v-model="form.account_id" class="form-select"
                        :class="{ 'is-invalid': form.errors.account_id }"
                        :disabled="!accounts.length">
                  <option value="">{{ accounts.length ? 'Select account' : 'Select customer first' }}</option>
                  <option v-for="a in accounts" :key="a.id" :value="a.id">
                    {{ a.account_number }} ({{ a.account_type }}) — Bal: {{ Number(a.balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                  </option>
                </select>
                <div v-if="form.errors.account_id" class="invalid-feedback">{{ form.errors.account_id }}</div>
              </div>

              <!-- Number of Leaves -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Number of Leaves</label>
                <input v-model.number="form.total_leaves" type="number" class="form-control"
                       :class="{ 'is-invalid': form.errors.total_leaves }"
                       min="5" max="200">
                <div class="form-text">Default: {{ defaultLeaves }} leaves per book</div>
                <div v-if="form.errors.total_leaves" class="invalid-feedback">{{ form.errors.total_leaves }}</div>
              </div>

              <!-- Notes -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                <textarea v-model="form.notes" class="form-control" rows="2"
                          :class="{ 'is-invalid': form.errors.notes }"
                          placeholder="Any special instructions…"></textarea>
                <div v-if="form.errors.notes" class="invalid-feedback">{{ form.errors.notes }}</div>
              </div>

              <!-- Info box -->
              <div class="alert alert-info d-flex gap-2 py-2 mb-4">
                <i class="bi bi-info-circle-fill mt-1"></i>
                <div>
                  <strong>{{ form.total_leaves }} leaves</strong> will be pre-assigned with sequential cheque numbers.
                  Each leaf expires 6 months from today.
                </div>
              </div>

              <div class="d-flex gap-2 justify-content-end">
                <Link :href="route('admin.cheques.index')" class="btn btn-outline-secondary">Cancel</Link>
                <button type="submit" class="btn btn-primary px-4" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-2"></span>
                  Issue Cheque Book
                </button>
              </div>
            </form>
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
  customers:      Array,
  default_leaves: Number,
})

const form = useForm({
  customer_id:  '',
  account_id:   '',
  total_leaves: props.default_leaves,
  notes:        '',
})

const search      = ref('')
const showDropdown= ref(false)
const accounts    = ref([])

const filtered = computed(() => {
  if (!search.value) return []
  const q = search.value.toLowerCase()
  return props.customers.filter(c =>
    c.name.toLowerCase().includes(q) || c.customer_number.toLowerCase().includes(q)
  ).slice(0, 8)
})

function filterCustomers() {
  showDropdown.value = true
  form.customer_id   = ''
  form.account_id    = ''
  accounts.value     = []
}

function onBlur() {
  setTimeout(() => { showDropdown.value = false }, 150)
}

async function selectCustomer(c) {
  search.value       = `${c.name} (${c.customer_number})`
  form.customer_id   = c.id
  form.account_id    = ''
  showDropdown.value = false

  const resp = await fetch(route('admin.cheques.accounts', c.id))
  accounts.value = await resp.json()
  if (accounts.value.length === 1) form.account_id = accounts.value[0].id
}

function submit() {
  form.post(route('admin.cheques.store'))
}
</script>
