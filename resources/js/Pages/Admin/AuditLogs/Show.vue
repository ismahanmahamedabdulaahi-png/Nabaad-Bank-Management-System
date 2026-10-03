<template>
  <AdminLayout :title="'Audit: ' + log.action" :subtitle="log.description">
    <template #actions>
      <Link :href="route('admin.audit-logs.index')" class="btn btn-sm btn-light">
        <i class="bi bi-arrow-left me-1"></i>Back
      </Link>
    </template>

    <div class="row g-4">
      <!-- Meta -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-info-circle me-2 text-primary"></i>Event Details
          </div>
          <div class="card-body">
            <div class="detail-row">
              <span>Action</span>
              <span class="badge" :class="actionBadge(log.action)">{{ log.action }}</span>
            </div>
            <div class="detail-row"><span>Module</span><strong class="text-capitalize">{{ log.module }}</strong></div>
            <div class="detail-row"><span>User</span><strong>{{ log.user_name ?? 'System' }}</strong></div>
            <div v-if="log.user_type" class="detail-row">
              <span>User Type</span>
              <span class="badge" :class="log.user_type.includes('Customer') ? 'bg-info' : 'bg-primary'">
                {{ log.user_type.includes('Customer') ? 'Customer' : 'Staff' }}
              </span>
            </div>
            <div class="detail-row"><span>Date &amp; Time</span><strong>{{ fmtDt(log.created_at) }}</strong></div>
            <div class="detail-row"><span>IP Address</span><strong class="font-monospace small">{{ log.ip_address }}</strong></div>
            <div v-if="log.url" class="detail-row">
              <span>URL</span>
              <span class="small text-muted text-truncate" style="max-width:160px" :title="log.url">{{ log.url }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Diff -->
      <div class="col-lg-8">
        <div v-if="!log.old_values && !log.new_values" class="card border-0 shadow-sm">
          <div class="card-body text-center text-muted py-5">
            <i class="bi bi-check-circle fs-1 mb-2 d-block text-success"></i>
            No value changes recorded for this event.
          </div>
        </div>

        <div v-else class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-arrow-left-right me-2 text-warning"></i>Value Changes
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Field</th>
                  <th class="text-danger">Before</th>
                  <th class="text-success">After</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="key in allKeys" :key="key" :class="{ 'table-warning': changed(key) }">
                  <td class="fw-semibold small text-capitalize">{{ key.replace(/_/g, ' ') }}</td>
                  <td class="small text-danger font-monospace">
                    <span v-if="log.old_values?.[key] !== undefined">
                      {{ formatVal(log.old_values[key]) }}
                    </span>
                    <span v-else class="text-muted fst-italic">—</span>
                  </td>
                  <td class="small text-success font-monospace">
                    <span v-if="log.new_values?.[key] !== undefined">
                      {{ formatVal(log.new_values[key]) }}
                    </span>
                    <span v-else class="text-muted fst-italic">—</span>
                  </td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ log: Object })

const allKeys = computed(() => {
  const keys = new Set([
    ...Object.keys(props.log.old_values ?? {}),
    ...Object.keys(props.log.new_values ?? {}),
  ])
  return [...keys].sort()
})

const changed   = (key) => JSON.stringify(props.log.old_values?.[key]) !== JSON.stringify(props.log.new_values?.[key])
const formatVal = (v) => v === null ? 'null' : typeof v === 'object' ? JSON.stringify(v) : String(v)
const fmtDt     = (d) => d ? new Date(d).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', second:'2-digit' }) : ''
const actionBadge = (a) => ({ created: 'bg-success', updated: 'bg-warning text-dark', deleted: 'bg-danger', login: 'bg-primary', logout: 'bg-secondary' }[a] ?? 'bg-secondary')
</script>

<style scoped>
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: .45rem 0; border-bottom: 1px solid var(--nabaad-border); font-size: .875rem; gap: .5rem; }
.detail-row:last-child { border-bottom: none; }
.detail-row > span:first-child { color: var(--nabaad-muted); flex-shrink: 0; }
</style>
