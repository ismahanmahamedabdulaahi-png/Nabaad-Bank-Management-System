<template>
  <AdminLayout title="End of Day" subtitle="Automated nightly processing">


    <!-- Today Status + Trigger -->
    <div class="row g-4 mb-4">
      <!-- Today card -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100"
             :style="stats.ran_today
               ? 'border-left:4px solid #198754'
               : 'border-left:4px solid #dc3545'">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <div class="text-muted small fw-semibold text-uppercase">Today's EOD</div>
                <div class="fs-6 fw-bold text-muted">{{ todayLabel }}</div>
              </div>
              <span v-if="stats.ran_today" class="badge bg-success fs-6 px-3 py-2">
                <i class="bi bi-check-circle-fill me-1"></i>Completed
              </span>
              <span v-else-if="!is_working_day" class="badge bg-secondary fs-6 px-3 py-2">
                <i class="bi bi-calendar-x me-1"></i>Non-Working Day
              </span>
              <span v-else class="badge bg-warning text-dark fs-6 px-3 py-2">
                <i class="bi bi-clock me-1"></i>Pending
              </span>
            </div>

            <!-- Today run summary -->
            <div v-if="today_run" class="row g-2 mb-3">
              <div class="col-6">
                <div class="bg-light rounded p-2 text-center">
                  <div class="fw-bold fs-5 text-success">{{ today_run.standing_orders_success }}</div>
                  <div class="text-muted" style="font-size:.72rem">Standing Orders</div>
                </div>
              </div>
              <div class="col-6">
                <div class="bg-light rounded p-2 text-center">
                  <div class="fw-bold fs-5 text-primary">{{ today_run.cheques_cleared }}</div>
                  <div class="text-muted" style="font-size:.72rem">Cheques Cleared</div>
                </div>
              </div>
              <div class="col-6">
                <div class="bg-light rounded p-2 text-center">
                  <div class="fw-bold fs-5 text-warning">{{ today_run.loans_marked_overdue }}</div>
                  <div class="text-muted" style="font-size:.72rem">Loans Updated</div>
                </div>
              </div>
              <div class="col-6">
                <div class="bg-light rounded p-2 text-center">
                  <div class="fw-bold fs-5" :class="today_run.standing_orders_failed > 0 ? 'text-danger' : 'text-muted'">
                    {{ today_run.standing_orders_failed }}
                  </div>
                  <div class="text-muted" style="font-size:.72rem">SO Failed</div>
                </div>
              </div>
            </div>

            <div v-if="today_run" class="text-muted small mb-3">
              <i class="bi bi-clock me-1"></i>
              Run at {{ fmtTime(today_run.started_at) }}
              <span v-if="today_run.triggered_by"> by {{ today_run.triggered_by?.name }}</span>
              <span v-else> (scheduled)</span>
              <span v-if="today_run.errors?.length" class="text-danger ms-2">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ today_run.errors.length }} error(s)
              </span>
            </div>

            <div v-if="!stats.ran_today" class="text-muted small mb-3">
              <i class="bi bi-info-circle me-1"></i>
              EOD runs automatically at midnight. You can also trigger it manually.
            </div>

            <!-- Manual trigger -->
            <div class="d-flex gap-2">
              <button v-if="!stats.ran_today" class="btn btn-primary"
                      @click="showConfirm = true" :disabled="running">
                <span v-if="running" class="spinner-border spinner-border-sm me-1"></span>
                <i v-else class="bi bi-play-fill me-1"></i>Run EOD Now
              </button>
              <button v-if="stats.ran_today && can('eod.run')" class="btn btn-outline-warning btn-sm"
                      @click="showForceConfirm = true">
                <i class="bi bi-arrow-repeat me-1"></i>Re-run
              </button>
              <Link v-if="today_run" :href="route('admin.eod.show', today_run.id)"
                    class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>View Log
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Stats cards -->
      <div class="col-lg-7">
        <div class="row g-3">
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
              <div class="fw-bold fs-4 text-primary">{{ stats.total_runs }}</div>
              <div class="text-muted small">Total Runs</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
              <div class="fw-bold fs-4 text-success">{{ stats.completed_runs }}</div>
              <div class="text-muted small">Completed</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
              <div class="fw-bold fs-4 text-danger">{{ stats.failed_runs }}</div>
              <div class="text-muted small">Failed</div>
            </div>
          </div>
          <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm text-center p-3">
              <div class="fw-bold fs-4 text-info">
                {{ latest_run ? fmtDate(latest_run.run_date) : '—' }}
              </div>
              <div class="text-muted small">Last Run</div>
            </div>
          </div>

          <!-- Quick links -->
          <div class="col-12">
            <div class="card border-0 bg-light p-3">
              <div class="fw-semibold small text-uppercase text-muted mb-2">Related</div>
              <div class="d-flex gap-2 flex-wrap">
                <Link :href="route('admin.public-holidays.index')" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-calendar-event me-1"></i>Public Holidays
                </Link>
                <Link :href="route('admin.business-day.index')" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-calendar-check me-1"></i>Business Day
                </Link>
                <Link :href="route('admin.standing-orders.index')" class="btn btn-sm btn-outline-secondary">
                  <i class="bi bi-repeat me-1"></i>Standing Orders
                </Link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Run History -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history me-2 text-primary"></i>Run History</span>
      </div>
      <div v-if="!runs.data.length" class="card-body text-center text-muted py-5">
        <i class="bi bi-calendar3 d-block mb-2" style="font-size:2rem;opacity:.3"></i>No EOD runs recorded yet.
      </div>
      <div v-else class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">Date</th>
              <th>Status</th>
              <th class="text-center">SO Success</th>
              <th class="text-center">SO Failed</th>
              <th class="text-center">Cheques</th>
              <th class="text-center">Loans</th>
              <th>Duration</th>
              <th>Triggered By</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in runs.data" :key="run.id">
              <td class="ps-3 fw-semibold">{{ fmtDate(run.run_date) }}</td>
              <td>
                <span :class="statusBadge(run.status)" class="badge">{{ run.status }}</span>
                <i v-if="run.errors?.length" class="bi bi-exclamation-triangle text-danger ms-1"
                   :title="`${run.errors.length} error(s)`"></i>
              </td>
              <td class="text-center">{{ run.standing_orders_success }}</td>
              <td class="text-center" :class="run.standing_orders_failed > 0 ? 'text-danger fw-bold' : 'text-muted'">
                {{ run.standing_orders_failed }}
              </td>
              <td class="text-center">{{ run.cheques_cleared }}</td>
              <td class="text-center">{{ run.loans_marked_overdue }}</td>
              <td class="text-muted small">
                {{ run.completed_at ? diffSecs(run.started_at, run.completed_at) + 's' : '—' }}
              </td>
              <td class="small text-muted">
                {{ run.triggered_by?.name ?? 'Scheduled' }}
                <span v-if="run.is_manual" class="badge bg-info text-dark ms-1" style="font-size:.65rem">Manual</span>
              </td>
              <td>
                <Link :href="route('admin.eod.show', run.id)" class="btn btn-xs btn-outline-secondary py-0 px-2"
                      style="font-size:.75rem">
                  Detail
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="runs.last_page > 1" class="d-flex justify-content-center py-3 gap-1">
        <button v-if="runs.prev_page_url" class="btn btn-sm btn-outline-secondary"
                @click="goPage(runs.current_page - 1)">
          <i class="bi bi-chevron-left"></i>
        </button>
        <span class="btn btn-sm btn-primary disabled">{{ runs.current_page }} / {{ runs.last_page }}</span>
        <button v-if="runs.next_page_url" class="btn btn-sm btn-outline-secondary"
                @click="goPage(runs.current_page + 1)">
          <i class="bi bi-chevron-right"></i>
        </button>
      </div>
    </div>

    <!-- Run Confirm Modal -->
    <div v-if="showConfirm" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-play-fill me-2 text-primary"></i>Run EOD Now</h5>
            <button type="button" class="btn-close" @click="showConfirm = false"></button>
          </div>
          <form @submit.prevent="submitRun(false)">
            <div class="modal-body">
              <div class="alert alert-info small py-2">
                This will run all end-of-day processes for <strong>{{ todayLabel }}</strong>:
                <ul class="mb-0 mt-1">
                  <li>Execute due standing orders</li>
                  <li>Clear cheques past their clearing date</li>
                  <li>Mark overdue loan installments</li>
                </ul>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                <textarea v-model="notes" class="form-control" rows="2"
                          placeholder="Reason for manual trigger…"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showConfirm = false">Cancel</button>
              <button type="submit" class="btn btn-primary" :disabled="running">
                <span v-if="running" class="spinner-border spinner-border-sm me-1"></span>
                Confirm & Run
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Force Re-run Confirm Modal -->
    <div v-if="showForceConfirm" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title text-warning"><i class="bi bi-arrow-repeat me-2"></i>Re-run Today's EOD</h5>
            <button type="button" class="btn-close" @click="showForceConfirm = false"></button>
          </div>
          <form @submit.prevent="submitRun(true)">
            <div class="modal-body">
              <div class="alert alert-warning small py-2">
                <i class="bi bi-exclamation-triangle me-1"></i>
                Today's EOD has already been completed. Re-running will delete the existing record
                and re-process all steps. This may cause duplicate transactions for standing orders.
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showForceConfirm = false">Cancel</button>
              <button type="submit" class="btn btn-warning" :disabled="running">
                <span v-if="running" class="spinner-border spinner-border-sm me-1"></span>
                Force Re-run
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  runs:          Object,
  today_run:     Object,
  latest_run:    Object,
  is_working_day:Boolean,
  stats:         Object,
})

const page = usePage()
const can  = (p) => page.props.auth?.permissions?.includes(p) ?? false

const showConfirm      = ref(false)
const showForceConfirm = ref(false)
const running          = ref(false)
const notes            = ref('')

const form = useForm({ force: false, notes: '' })

function submitRun(force) {
  running.value = true
  form.force = force
  form.notes = notes.value
  form.post(route('admin.eod.run'), {
    onFinish: () => { running.value = false; showConfirm.value = false; showForceConfirm.value = false },
  })
}

const todayLabel = computed(() => {
  return new Date().toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
})

const statusBadge = (s) => ({
  running:   'bg-warning text-dark',
  completed: 'bg-success',
  failed:    'bg-danger',
}[s] ?? 'bg-secondary')

const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'
const fmtTime = (d) => d ? new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '—'
const diffSecs = (a, b) => Math.round((new Date(b) - new Date(a)) / 1000)
const goPage   = (p) => router.get(route('admin.eod.index'), { page: p })
</script>
