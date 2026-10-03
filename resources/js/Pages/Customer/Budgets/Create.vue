<template>
  <PortalLayout title="New Budget" subtitle="Set a spending limit and get alerted before you hit it">
    <template #actions>
      <Link :href="route('customer.budgets.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Budget Name <span class="text-danger">*</span></label>
                  <input v-model="form.name" type="text" class="form-control" maxlength="100"
                         :class="form.errors.name ? 'is-invalid' : ''"
                         placeholder="e.g. Monthly spending, Rent account…" required>
                  <div class="invalid-feedback">{{ form.errors.name }}</div>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Applies To</label>
                  <select v-model="form.account_id" class="form-select">
                    <option value="">All my accounts (combined)</option>
                    <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                      {{ acc.account_number }} — {{ ucfirst(acc.account_type) }}
                    </option>
                  </select>
                  <div class="form-text">Tracks withdrawals, transfers out, and loan repayments each calendar month.</div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Monthly Limit (USD) <span class="text-danger">*</span></label>
                  <div class="input-group">
                    <span class="input-group-text">USD</span>
                    <input v-model="form.limit_amount" type="number" step="0.01" min="1"
                           class="form-control" :class="form.errors.limit_amount ? 'is-invalid' : ''" required>
                    <div class="invalid-feedback">{{ form.errors.limit_amount }}</div>
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-semibold">Alert Threshold</label>
                  <div class="input-group">
                    <input v-model.number="form.threshold_percent" type="number" min="1" max="100"
                           class="form-control" :class="form.errors.threshold_percent ? 'is-invalid' : ''" required>
                    <span class="input-group-text">%</span>
                    <div class="invalid-feedback">{{ form.errors.threshold_percent }}</div>
                  </div>
                  <div class="form-text">We'll notify you once spending reaches this share of the limit.</div>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-4" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                Create Budget
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineProps({
  accounts: { type: Array, default: () => [] },
})

const form = useForm({
  name:               '',
  account_id:         '',
  limit_amount:       '',
  threshold_percent:  80,
})

const submit = () => form.post(route('customer.budgets.store'))
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
</script>
