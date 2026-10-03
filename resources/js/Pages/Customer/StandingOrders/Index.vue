<template>
  <PortalLayout title="Standing Orders" subtitle="Automated recurring transfers">
    <template #actions>
      <Link :href="route('customer.standing-orders.create')" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Order
      </Link>
    </template>


    <div v-if="orders.length === 0" class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-arrow-repeat fs-1 d-block mb-2 opacity-25"></i>
        No standing orders yet.
        <Link :href="route('customer.standing-orders.create')" class="d-block mt-2 small text-primary">Set up your first standing order →</Link>
      </div>
    </div>

    <div v-else class="row g-3">
      <div v-for="order in orders" :key="order.id" class="col-md-6">
        <div class="card border-0 shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <span class="badge" :class="statusBadge(order.status)">{{ order.status }}</span>
                <span class="badge bg-light text-dark ms-1">{{ order.frequency }}</span>
              </div>
              <div class="fw-bold fs-5" style="color:#0B2447">{{ fmt(order.amount) }}</div>
            </div>

            <div class="small text-muted mb-1">
              <i class="bi bi-arrow-right me-1"></i>
              <span class="font-monospace">{{ order.source_account?.account_number }}</span>
              →
              <span class="font-monospace">{{ order.beneficiary_account?.account_number }}</span>
            </div>
            <div v-if="order.beneficiary_account?.customer" class="small text-muted">
              To: <strong>{{ order.beneficiary_account.customer.name }}</strong>
            </div>
            <div v-if="order.description" class="small text-muted mt-1">{{ order.description }}</div>

            <div class="d-flex justify-content-between mt-3 text-muted small">
              <span>Next: <strong>{{ fmtDate(order.next_execution_date) }}</strong></span>
              <span>Runs: <strong>{{ order.executions_count }}</strong></span>
            </div>

            <div v-if="order.status === 'active' || order.status === 'paused'" class="d-flex gap-2 mt-3">
              <form v-if="order.status === 'active'" @submit.prevent="pause(order.id)">
                <button type="submit" class="btn btn-sm btn-outline-warning">
                  <i class="bi bi-pause me-1"></i>Pause
                </button>
              </form>
              <form v-if="order.status === 'paused'" @submit.prevent="resume(order.id)">
                <button type="submit" class="btn btn-sm btn-outline-success">
                  <i class="bi bi-play me-1"></i>Resume
                </button>
              </form>
              <button type="button" class="btn btn-sm btn-outline-danger" @click="confirmCancel(order)">
                <i class="bi bi-x-circle me-1"></i>Cancel
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel confirmation -->
    <ConfirmModal
      id="orderCancelModal"
      title="Cancel Standing Order"
      :message="cancelTarget ? `Cancel the recurring ${fmt(cancelTarget.amount)} transfer? This cannot be undone.` : ''"
      variant="danger"
      icon="bi-x-circle"
      confirm-label="Cancel Order"
      @confirmed="doCancel"
    />
  </PortalLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { Modal } from 'bootstrap'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

defineProps({
  orders:   { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
})

const pause  = (id) => router.post(route('customer.standing-orders.pause',  id), {}, { preserveScroll: true })
const resume = (id) => router.post(route('customer.standing-orders.resume', id), {}, { preserveScroll: true })

const cancelTarget = ref(null)
const confirmCancel = (order) => { cancelTarget.value = order; new Modal(document.getElementById('orderCancelModal')).show() }
const doCancel = () => router.post(route('customer.standing-orders.cancel', cancelTarget.value.id), {}, { preserveScroll: true })

const statusBadge = (s) => ({
  active:    'bg-success',
  paused:    'bg-warning text-dark',
  cancelled: 'bg-secondary',
  expired:   'bg-secondary',
}[s] ?? 'bg-secondary')

const fmt     = (v) => new Intl.NumberFormat('en-US', { style:'currency', currency:'USD' }).format(v ?? 0)
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—'
</script>
