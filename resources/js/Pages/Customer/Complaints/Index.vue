<template>
  <PortalLayout title="Complaints" subtitle="Track issues you've raised with us">
    <template #actions>
      <Link :href="route('customer.complaints.create')" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Complaint
      </Link>
    </template>

    <div class="card border-0 shadow-sm">
      <div v-if="!complaints.length" class="text-center text-muted py-5">
        <i class="bi bi-headset fs-1 mb-2 d-block"></i>
        No complaints filed yet.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Reference</th>
              <th>Subject</th>
              <th>Category</th>
              <th>Status</th>
              <th>Filed</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in complaints" :key="c.id" class="cursor-pointer" @click="open(c)">
              <td class="font-monospace small">{{ c.complaint_number }}</td>
              <td>{{ c.subject }}</td>
              <td class="small text-muted">{{ ucfirst(c.category) }}</td>
              <td><span class="badge" :class="statusBadge(c.status)">{{ ucfirst(c.status) }}</span></td>
              <td class="small text-muted">{{ fmtDate(c.created_at) }}</td>
              <td class="text-end"><i class="bi bi-chevron-right text-muted"></i></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineProps({ complaints: { type: Array, default: () => [] } })

const open = (c) => router.visit(route('customer.complaints.show', c.id))
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ open: 'bg-warning text-dark', in_progress: 'bg-info', resolved: 'bg-success', closed: 'bg-secondary' }[s] ?? 'bg-secondary')
</script>

<style scoped>
.cursor-pointer { cursor: pointer; }
</style>
