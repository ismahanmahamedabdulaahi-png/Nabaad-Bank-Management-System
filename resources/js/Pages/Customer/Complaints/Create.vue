<template>
  <PortalLayout title="New Complaint" subtitle="Tell us what happened">
    <template #actions>
      <Link :href="route('customer.complaints.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <form @submit.prevent="submit">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                  <select v-model="form.category" class="form-select" :class="form.errors.category ? 'is-invalid' : ''" required>
                    <option value="">Select category</option>
                    <option value="transaction_dispute">Transaction Dispute</option>
                    <option value="service_quality">Service Quality</option>
                    <option value="account_issue">Account Issue</option>
                    <option value="fraud_report">Fraud Report</option>
                    <option value="other">Other</option>
                  </select>
                  <div class="invalid-feedback">{{ form.errors.category }}</div>
                </div>

                <div class="col-md-6" v-if="form.category === 'transaction_dispute' || form.category === 'fraud_report'">
                  <label class="form-label fw-semibold">Related Transaction (optional)</label>
                  <select v-model="form.related_transaction_id" class="form-select">
                    <option value="">None</option>
                    <option v-for="t in transactions" :key="t.id" :value="t.id">
                      {{ t.reference }} — {{ ucfirst(t.type) }} — {{ fmt(t.amount) }}
                    </option>
                  </select>
                </div>

                <div class="col-md-6" v-else>
                  <label class="form-label fw-semibold">Related Account (optional)</label>
                  <select v-model="form.related_account_id" class="form-select">
                    <option value="">None</option>
                    <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.account_number }}</option>
                  </select>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                  <input v-model="form.subject" type="text" class="form-control" maxlength="150"
                         :class="form.errors.subject ? 'is-invalid' : ''" required>
                  <div class="invalid-feedback">{{ form.errors.subject }}</div>
                </div>

                <div class="col-12">
                  <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                  <textarea v-model="form.description" class="form-control" rows="5" maxlength="3000"
                            :class="form.errors.description ? 'is-invalid' : ''" required></textarea>
                  <div class="invalid-feedback">{{ form.errors.description }}</div>
                </div>
              </div>

              <button type="submit" class="btn btn-primary w-100 mt-4" :disabled="form.processing || !isValid">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                Submit Complaint
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  accounts:     { type: Array, default: () => [] },
  transactions: { type: Array, default: () => [] },
})

const form = useForm({
  category: '',
  subject: '',
  description: '',
  related_account_id: '',
  related_transaction_id: '',
})

const isValid = computed(() => !!form.category && !!form.subject && !!form.description)
const submit = () => form.post(route('customer.complaints.store'))

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
</script>
