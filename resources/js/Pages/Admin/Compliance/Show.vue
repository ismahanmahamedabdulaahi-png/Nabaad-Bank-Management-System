<template>
  <AdminLayout :title="`Case #${caseData.id}`" subtitle="Compliance case detail">
    <template #actions>
      <Link :href="route('admin.compliance.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Cases
      </Link>
    </template>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">{{ typeLabel(caseData.type) }}</span>
            <span class="badge" :class="statusBadge(caseData.status)">{{ ucfirst(caseData.status) }}</span>
          </div>
          <div class="card-body">
            <div class="detail-row"><span>Customer</span><strong>{{ caseData.customer?.name }}</strong></div>
            <div class="detail-row"><span>Severity</span><strong>{{ ucfirst(caseData.severity) }}</strong></div>
            <div class="detail-row"><span>Flagged By</span><strong>{{ caseData.flagged_by ? caseData.flagged_by.name : 'System (automated detection)' }}</strong></div>
            <div v-if="caseData.transaction" class="detail-row">
              <span>Transaction</span>
              <strong class="font-monospace">{{ caseData.transaction.reference }} — {{ fmt(caseData.transaction.amount) }}</strong>
            </div>
            <div class="detail-row"><span>Opened</span><strong>{{ fmtDate(caseData.created_at) }}</strong></div>

            <div class="mt-3">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Notes</div>
              <p class="mb-0" style="white-space:pre-wrap">{{ caseData.notes }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small">Update Case</div>
          <div class="card-body">
            <form @submit.prevent="update">
              <div class="mb-3">
                <select v-model="form.status" class="form-select form-select-sm">
                  <option value="open">Open</option>
                  <option value="reviewing">Reviewing</option>
                  <option value="cleared">Cleared</option>
                  <option value="reported">Reported (to regulator)</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Investigation notes</label>
                <textarea v-model="form.notes" class="form-control form-control-sm" rows="5"></textarea>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100" :disabled="form.processing">
                <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                Save
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ case: Object })
const caseData = props.case

const form = useForm({ status: caseData.status, notes: caseData.notes ?? '' })
const update = () => form.post(route('admin.compliance.update', caseData.id), { preserveScroll: true })

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)
const fmtDate = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : ''
const typeLabel = (t) => ({ large_transaction: 'Large Transaction', structuring: 'Possible Structuring', manual_flag: 'Manually Flagged Transaction' }[t] ?? t)
const statusBadge = (s) => ({ open: 'bg-warning text-dark', reviewing: 'bg-info', cleared: 'bg-success', reported: 'bg-dark' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid rgba(11,11,11,.06); font-size: .875rem; gap: 1rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row span { color: var(--nabaad-muted); flex-shrink: 0; }
</style>
