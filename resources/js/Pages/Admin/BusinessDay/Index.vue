<template>
  <AdminLayout title="Business Day">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-start mb-4">
      <div>
        <h2 class="mb-0 fw-bold">Business Day</h2>
        <small class="text-muted">{{ today_label }} &mdash; Cash Management Workflow</small>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <!-- Status badge -->
        <span v-if="today?.status === 'open'" class="badge bg-success fs-6 px-3 py-2">
          <i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle"></i>Open
        </span>
        <span v-else-if="today?.status === 'closed'" class="badge bg-secondary fs-6 px-3 py-2">
          <i class="bi bi-lock-fill me-1"></i>Closed
        </span>
        <span v-else class="badge bg-warning text-dark fs-6 px-3 py-2">
          <i class="bi bi-dash-circle me-1"></i>Not Started
        </span>

        <!-- Open / Close Day -->
        <button v-if="(!today || today.status !== 'open' && today.status !== 'closed') && can('business-day.manage')"
                class="btn btn-success px-4" @click="openDay">
          <i class="bi bi-play-fill me-1"></i>Open Business Day
        </button>
        <button v-if="today?.status === 'open' && can('business-day.manage')" class="btn btn-danger px-4" @click="confirmClose = true">
          <i class="bi bi-stop-fill me-1"></i>Close Business Day
        </button>
      </div>
    </div>


    <div class="row g-4">
      <!-- Left: Workflow Checklist -->
      <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-list-check me-2 text-primary"></i>Daily Workflow
          </div>
          <div class="card-body p-0">
            <ul class="list-group list-group-flush">
              <li v-for="step in workflow" :key="step.step"
                  class="list-group-item d-flex align-items-center gap-3 py-3">
                <!-- Step number / check -->
                <div class="flex-shrink-0" style="width:32px;height:32px">
                  <div v-if="step.done"
                       class="rounded-circle bg-success d-flex align-items-center justify-content-center text-white"
                       style="width:32px;height:32px;font-size:.8rem">
                    <i class="bi bi-check-lg"></i>
                  </div>
                  <div v-else
                       class="rounded-circle border d-flex align-items-center justify-content-center text-muted"
                       style="width:32px;height:32px;font-size:.75rem;font-weight:600">
                    {{ step.step }}
                  </div>
                </div>
                <div class="flex-grow-1">
                  <div :class="step.done ? 'text-muted' : 'fw-semibold'" style="font-size:.9rem">
                    {{ step.action }}
                  </div>
                  <small class="text-muted">{{ step.by }}</small>
                </div>
                <span v-if="step.done" class="badge bg-success-subtle text-success border-0">Done</span>
                <span v-else class="badge bg-light text-muted border">Pending</span>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Right: Status Cards + Tables -->
      <div class="col-lg-7">

        <!-- Vault + Stats row -->
        <div class="row g-3 mb-4">
          <!-- Vault Card -->
          <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100"
                 :class="vault?.status === 'open' ? 'border-start border-success border-3' : 'border-start border-secondary border-3'">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <div class="text-muted small fw-semibold text-uppercase">Vault</div>
                  <span :class="vault?.status === 'open' ? 'badge bg-success' : 'badge bg-secondary'">
                    {{ vault?.status ?? 'N/A' }}
                  </span>
                </div>
                <div class="fw-bold fs-4">
                  USD {{ vault ? Number(vault.balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) : '—' }}
                </div>
                <div class="mt-3 d-flex gap-2">
                  <form v-if="vault?.status !== 'open' && can('vault.cash-in')" @submit.prevent="openVault">
                    <button type="submit" class="btn btn-sm btn-success">
                      <i class="bi bi-unlock me-1"></i>Open Vault
                    </button>
                  </form>
                  <form v-else-if="vault?.status === 'open' && can('vault.cash-out')" @submit.prevent="closeVault">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                      <i class="bi bi-lock me-1"></i>Close Vault
                    </button>
                  </form>
                  <Link :href="route('admin.vault.show')" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-eye me-1"></i>View
                  </Link>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick Stats -->
          <div class="col-md-6">
            <div class="row g-2 h-100">
              <div class="col-6">
                <div class="card border-0 bg-light text-center p-2 h-100">
                  <div class="fw-bold fs-5 text-success">{{ stats.open_tills }}</div>
                  <div class="text-muted" style="font-size:.75rem">Open Tills</div>
                </div>
              </div>
              <div class="col-6">
                <div class="card border-0 bg-light text-center p-2 h-100">
                  <div class="fw-bold fs-5 text-secondary">{{ stats.closed_tills }}</div>
                  <div class="text-muted" style="font-size:.75rem">Closed Tills</div>
                </div>
              </div>
              <div class="col-6">
                <div class="card border-0 bg-light text-center p-2 h-100">
                  <div class="fw-bold fs-5 text-primary">{{ stats.txn_count }}</div>
                  <div class="text-muted" style="font-size:.75rem">Transactions</div>
                </div>
              </div>
              <div class="col-6">
                <div class="card border-0 bg-light text-center p-2 h-100">
                  <div class="fw-bold fs-5" :class="stats.pending_requests > 0 ? 'text-warning' : 'text-muted'">
                    {{ stats.pending_requests }}
                  </div>
                  <div class="text-muted" style="font-size:.75rem">Pending Requests</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tills Status -->
        <div class="card border-0 shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-cash-stack me-2 text-primary"></i>Teller Tills Today</span>
            <Link :href="route('admin.tellers.index')" class="btn btn-sm btn-outline-primary">Manage</Link>
          </div>
          <div v-if="tills.length === 0" class="card-body text-center text-muted py-4">
            No tills opened today.
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Teller</th>
                  <th>Till</th>
                  <th class="text-end">Balance</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="till in tills" :key="till.id">
                  <td>{{ till.teller?.name }}</td>
                  <td class="text-muted small">{{ till.till_name }}</td>
                  <td class="text-end font-monospace">
                    {{ Number(till.current_balance).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                  </td>
                  <td>
                    <span :class="till.status === 'open' ? 'badge bg-success' : 'badge bg-secondary'">
                      {{ till.status }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pending Replenishment Requests -->
        <div v-if="pending_requests.length" class="card border-0 shadow-sm border-warning border-start border-3">
          <div class="card-header bg-white fw-semibold text-warning">
            <i class="bi bi-exclamation-triangle me-2"></i>Pending Replenishment Requests
          </div>
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Teller</th>
                  <th>Till</th>
                  <th class="text-end">Amount</th>
                  <th>Reason</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="req in pending_requests" :key="req.id">
                  <td>{{ req.requested_by?.name }}</td>
                  <td class="text-muted small">{{ req.till?.till_name }}</td>
                  <td class="text-end font-monospace fw-bold">
                    USD {{ Number(req.amount).toLocaleString('en-US', { minimumFractionDigits: 2 }) }}
                  </td>
                  <td class="text-muted small">{{ req.reason ?? '—' }}</td>
                  <td>
                    <Link :href="route('admin.tellers.show', req.till_id)" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:.75rem">
                      Review
                    </Link>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- Close Business Day Confirm Modal -->
    <div v-if="confirmClose" class="modal fade show d-block" tabindex="-1" style="background:rgba(0,0,0,.4)">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger"><i class="bi bi-stop-fill me-2"></i>Close Business Day</h5>
            <button type="button" class="btn-close" @click="confirmClose = false"></button>
          </div>
          <form @submit.prevent="closeDay">
            <div class="modal-body">
              <div class="alert alert-warning py-2 small">
                Ensure all tills are closed and cash has been returned to the vault before closing the business day.
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                <textarea v-model="closeNotes" class="form-control" rows="2" placeholder="End-of-day remarks…"></textarea>
              </div>
            </div>
            <div class="modal-footer border-0">
              <button type="button" class="btn btn-outline-secondary" @click="confirmClose = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="closing">
                <span v-if="closing" class="spinner-border spinner-border-sm me-2"></span>
                Confirm Close
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
import { Link, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
  today:            Object,
  vault:            Object,
  tills:            Array,
  pending_requests: Array,
  workflow:         Array,
  stats:            Object,
})

const confirmClose = ref(false)
const closeNotes   = ref('')
const closing      = ref(false)

const today_label = computed(() => {
  const d = new Date()
  return d.toLocaleDateString('en-GB', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' })
})

function openDay() {
  router.post(route('admin.business-day.open'), {}, { preserveScroll: true })
}

function closeDay() {
  closing.value = true
  router.post(route('admin.business-day.close'), { notes: closeNotes.value }, {
    onFinish: () => { closing.value = false; confirmClose.value = false },
  })
}

function openVault() {
  router.post(route('admin.vault.open'), {}, { preserveScroll: true })
}

function closeVault() {
  router.post(route('admin.vault.close'), {}, { preserveScroll: true })
}
</script>
