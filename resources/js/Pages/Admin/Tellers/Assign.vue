<template>
  <AdminLayout
    title="Open Till"
    subtitle="Assign a till to a teller for today"
    :breadcrumbs="[
      { label: 'Teller Operations', href: route('admin.tellers.index') },
      { label: 'Open Till' }
    ]"
  >
    <div class="row justify-content-center">
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-cash-stack me-1 text-success"></i>Till Assignment
          </div>
          <div class="card-body">
            <form @submit.prevent="submit">

              <!-- Teller -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Teller <span class="text-danger">*</span></label>
                <div v-if="!tellers.length" class="alert alert-info small">
                  <i class="bi bi-info-circle me-1"></i>All active tellers already have an open till today.
                </div>
                <select v-else v-model="form.teller_id" class="form-select" :class="form.errors.teller_id ? 'is-invalid' : ''">
                  <option value="">— Select Teller —</option>
                  <option v-for="t in tellers" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div v-if="form.errors.teller_id" class="invalid-feedback">{{ form.errors.teller_id }}</div>
              </div>

              <!-- Till Name -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Till Name <span class="text-muted small">(optional)</span></label>
                <input v-model="form.till_name" type="text" class="form-control" placeholder="e.g. Counter 1, Till A" maxlength="50" />
                <div class="form-text">Leave blank to auto-name from teller name.</div>
              </div>

              <!-- Opening Balance -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Opening Balance <span class="text-danger">*</span></label>
                <div class="input-group">
                  <span class="input-group-text">USD</span>
                  <input v-model="form.opening_balance" type="number" min="0" step="0.01"
                    class="form-control form-control-lg"
                    :class="form.errors.opening_balance ? 'is-invalid' : ''"
                    placeholder="0.00" />
                  <div v-if="form.errors.opening_balance" class="invalid-feedback">{{ form.errors.opening_balance }}</div>
                </div>
                <div class="form-text">Cash issued to this teller from vault.</div>
              </div>

              <!-- Business Date -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Business Date</label>
                <input v-model="form.business_date" type="date" class="form-control" />
              </div>

              <!-- Notes -->
              <div class="mb-4">
                <label class="form-label fw-semibold">Notes <span class="text-muted small">(optional)</span></label>
                <textarea v-model="form.notes" class="form-control" rows="2" placeholder="Optional notes"></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success px-4" :disabled="form.processing || !tellers.length">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-check-lg me-1"></i>Open Till
                </button>
                <Link :href="route('admin.tellers.index')" class="btn btn-light">Cancel</Link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  tellers:       { type: Array,  required: true },
  business_date: { type: String, required: true },
});

const form = useForm({
  teller_id:       '',
  till_name:       '',
  opening_balance: '',
  business_date:   props.business_date,
  notes:           '',
});

const submit = () => form.post(route('admin.tellers.store'));
</script>
