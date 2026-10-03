<template>
  <AdminLayout :title="complaint.complaint_number" subtitle="Complaint Details">
    <template #actions>
      <Link :href="route('admin.complaints.index')" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Complaints
      </Link>
    </template>

    <div class="row g-4">
      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold">{{ complaint.subject }}</span>
            <span class="badge" :class="statusBadge(complaint.status)">{{ ucfirst(complaint.status) }}</span>
          </div>
          <div class="card-body">
            <div class="detail-row"><span>Customer</span><strong>{{ complaint.customer?.name }}</strong></div>
            <div class="detail-row"><span>Category</span><strong>{{ ucfirst(complaint.category) }}</strong></div>
            <div class="detail-row"><span>Priority</span><strong>{{ ucfirst(complaint.priority) }}</strong></div>
            <div v-if="complaint.related_account" class="detail-row"><span>Account</span><strong class="font-monospace">{{ complaint.related_account.account_number }}</strong></div>
            <div v-if="complaint.related_transaction" class="detail-row"><span>Transaction</span><strong class="font-monospace">{{ complaint.related_transaction.reference }}</strong></div>
            <div class="detail-row"><span>Filed</span><strong>{{ fmtDate(complaint.created_at) }}</strong></div>

            <div class="mt-3">
              <div class="text-muted small text-uppercase fw-semibold mb-1">Description</div>
              <p class="mb-0">{{ complaint.description }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <!-- Assign -->
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold small">Assign</div>
          <div class="card-body">
            <form @submit.prevent="assign" class="d-flex gap-2">
              <select v-model="assignForm.assigned_to" class="form-select form-select-sm">
                <option value="">Unassigned</option>
                <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap" :disabled="assignForm.processing">Assign</button>
            </form>
            <p v-if="complaint.assigned_to" class="text-muted small mt-2 mb-0">
              Currently assigned to <strong>{{ complaint.assigned_to.name }}</strong>
            </p>
          </div>
        </div>

        <!-- Update status -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small">Update Status</div>
          <div class="card-body">
            <form @submit.prevent="updateStatus">
              <div class="mb-3">
                <select v-model="statusForm.status" class="form-select form-select-sm">
                  <option value="open">Open</option>
                  <option value="in_progress">In Progress</option>
                  <option value="resolved">Resolved</option>
                  <option value="closed">Closed</option>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label small fw-semibold">Response to customer</label>
                <textarea v-model="statusForm.resolution_notes" class="form-control form-control-sm" rows="4"
                          placeholder="Visible to the customer when resolved/closed"></textarea>
              </div>
              <button type="submit" class="btn btn-sm btn-primary w-100" :disabled="statusForm.processing">
                <span v-if="statusForm.processing" class="spinner-border spinner-border-sm me-1"></span>
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

const props = defineProps({ complaint: Object, staff: { type: Array, default: () => [] } })

const assignForm = useForm({ assigned_to: props.complaint.assigned_to?.id ?? '' })
const statusForm = useForm({ status: props.complaint.status, resolution_notes: props.complaint.resolution_notes ?? '' })

const assign = () => assignForm.post(route('admin.complaints.assign', props.complaint.id), { preserveScroll: true })
const updateStatus = () => statusForm.post(route('admin.complaints.status', props.complaint.id), { preserveScroll: true })

const fmtDate = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ open: 'bg-warning text-dark', in_progress: 'bg-info', resolved: 'bg-success', closed: 'bg-secondary' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .5rem 0; border-bottom: 1px solid rgba(11,11,11,.06); font-size: .875rem; gap: 1rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row span { color: var(--nabaad-muted); flex-shrink: 0; }
</style>
