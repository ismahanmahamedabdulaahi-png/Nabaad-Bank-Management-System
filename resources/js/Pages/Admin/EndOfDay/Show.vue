<template>
  <AdminLayout :title="`EOD Run — ${fmtDate(run.run_date)}`"
               :breadcrumbs="[{ label: 'End of Day', href: route('admin.eod.index') }, { label: fmtDate(run.run_date) }]">

    <div class="row g-4">
      <!-- Left: Summary -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-4"
             :style="`border-left:4px solid ${run.status === 'completed' ? '#198754' : run.status === 'failed' ? '#dc3545' : '#ffc107'}`">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div class="text-muted small text-uppercase fw-semibold">Status</div>
                <span :class="statusBadge(run.status)" class="badge fs-6 px-3 py-2 mt-1">{{ run.status }}</span>
              </div>
              <span v-if="run.is_manual" class="badge bg-info text-dark">Manual</span>
              <span v-else class="badge bg-secondary">Scheduled</span>
            </div>

            <dl class="row g-0 small mb-0">
              <dt class="col-5 text-muted">Date</dt>
              <dd class="col-7 fw-semibold">{{ fmtDate(run.run_date) }}</dd>

              <dt class="col-5 text-muted">Started</dt>
              <dd class="col-7">{{ fmtDt(run.started_at) }}</dd>

              <dt class="col-5 text-muted">Completed</dt>
              <dd class="col-7">{{ run.completed_at ? fmtDt(run.completed_at) : '—' }}</dd>

              <dt class="col-5 text-muted">Duration</dt>
              <dd class="col-7">{{ run.completed_at ? diffSecs(run.started_at, run.completed_at) + 's' : '—' }}</dd>

              <dt class="col-5 text-muted">Triggered By</dt>
              <dd class="col-7">{{ run.triggered_by?.name ?? 'System (Cron)' }}</dd>
            </dl>
          </div>
        </div>

        <!-- Process Results -->
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold small">Process Results</div>
          <div class="list-group list-group-flush">
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-repeat me-2 text-primary"></i>Standing Orders
                <div class="text-muted" style="font-size:.72rem">Success / Failed / Skipped</div>
              </div>
              <div class="text-end">
                <span class="text-success fw-bold">{{ run.standing_orders_success }}</span>
                <span class="text-muted"> / </span>
                <span :class="run.standing_orders_failed > 0 ? 'text-danger fw-bold' : 'text-muted'">{{ run.standing_orders_failed }}</span>
                <span class="text-muted"> / {{ run.standing_orders_skipped }}</span>
              </div>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-file-earmark-check me-2 text-success"></i>Cheques Cleared
              </div>
              <span class="fw-bold">{{ run.cheques_cleared }}</span>
            </div>
            <div class="list-group-item d-flex justify-content-between align-items-center">
              <div>
                <i class="bi bi-cash-coin me-2 text-warning"></i>Loans Updated
              </div>
              <span class="fw-bold">{{ run.loans_marked_overdue }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Errors -->
      <div class="col-lg-8">
        <div v-if="run.errors && run.errors.length" class="card border-0 shadow-sm border-danger mb-4">
          <div class="card-header bg-danger text-white fw-semibold">
            <i class="bi bi-exclamation-triangle me-2"></i>Errors ({{ run.errors.length }})
          </div>
          <div class="list-group list-group-flush">
            <div v-for="(err, i) in run.errors" :key="i" class="list-group-item">
              <div class="d-flex gap-3">
                <span class="badge bg-secondary mt-1">{{ err.step }}</span>
                <div>
                  <div class="fw-semibold small text-danger">{{ err.message }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else class="card border-0 shadow-sm border-success mb-4">
          <div class="card-body text-center text-success py-4">
            <i class="bi bi-check-circle-fill d-block mb-2" style="font-size:2.5rem"></i>
            <div class="fw-semibold">All processes completed without errors.</div>
          </div>
        </div>

        <div v-if="run.notes" class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold small">Notes</div>
          <div class="card-body text-muted">{{ run.notes }}</div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({ run: Object })

const statusBadge = (s) => ({
  running:   'bg-warning text-dark',
  completed: 'bg-success',
  failed:    'bg-danger',
}[s] ?? 'bg-secondary')

const fmtDate  = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
const fmtDt    = (d) => d ? new Date(d).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '—'
const diffSecs = (a, b) => Math.round((new Date(b) - new Date(a)) / 1000)
</script>
