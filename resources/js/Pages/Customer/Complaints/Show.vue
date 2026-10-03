<template>
  <PortalLayout :title="complaint.complaint_number" subtitle="Complaint Details">
    <template #actions>
      <Link :href="route('customer.complaints.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Complaints
      </Link>
    </template>

    <div class="row justify-content-center">
      <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">{{ complaint.subject }}</span>
            <span class="badge" :class="statusBadge(complaint.status)">{{ ucfirst(complaint.status) }}</span>
          </div>
          <div class="card-body">
            <div class="detail-row"><span>Reference</span><strong class="font-monospace">{{ complaint.complaint_number }}</strong></div>
            <div class="detail-row"><span>Category</span><strong>{{ ucfirst(complaint.category) }}</strong></div>
            <div v-if="complaint.related_account" class="detail-row"><span>Account</span><strong class="font-monospace">{{ complaint.related_account.account_number }}</strong></div>
            <div v-if="complaint.related_transaction" class="detail-row"><span>Transaction</span><strong class="font-monospace">{{ complaint.related_transaction.reference }}</strong></div>
            <div class="detail-row"><span>Filed</span><strong>{{ fmtDate(complaint.created_at) }}</strong></div>

            <div class="mt-3">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Description</div>
              <p class="mb-0">{{ complaint.description }}</p>
            </div>

            <div v-if="complaint.resolution_notes" class="alert alert-success mt-3 mb-0">
              <div class="fw-semibold small mb-1"><i class="bi bi-check-circle me-1"></i>Response from NABAAD Bank</div>
              {{ complaint.resolution_notes }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineProps({ complaint: Object })

const fmtDate = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ open: 'bg-warning text-dark', in_progress: 'bg-info', resolved: 'bg-success', closed: 'bg-secondary' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid #f0f4f8; font-size: .875rem; gap: 1rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row span { color: #64748b; flex-shrink: 0; }
</style>
