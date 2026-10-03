<template>
  <AdminLayout
    title="Standing Order"
    subtitle="Scheduled recurring transfer details"
    :breadcrumbs="[
      { label: 'Standing Orders', href: route('admin.standing-orders.index') },
      { label: order.source_account?.account_number }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <button v-if="order.status === 'active'" class="btn btn-warning btn-sm" @click="showPause = true">
          <i class="bi bi-pause-circle me-1"></i>Pause
        </button>
        <button v-if="order.status === 'paused'" class="btn btn-success btn-sm" @click="resumeOrder">
          <i class="bi bi-play-circle me-1"></i>Resume
        </button>
        <button v-if="order.status === 'active' || order.status === 'paused'" class="btn btn-outline-danger btn-sm" @click="showCancel = true">
          <i class="bi bi-x-circle me-1"></i>Cancel
        </button>
        <Link :href="route('admin.standing-orders.index')" class="btn btn-light btn-sm">
          <i class="bi bi-arrow-left me-1"></i>Back
        </Link>
      </div>
    </template>

    <div class="row g-4">
      <!-- Main details -->
      <div class="col-lg-8">
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white fw-semibold"><i class="bi bi-info-circle me-1 text-primary"></i>Order Details</div>
          <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-sm mb-0">
              <tbody>
                <tr>
                  <td class="text-muted ps-3" style="width:40%">Status</td>
                  <td class="pe-3"><StatusBadge :status="order.status" /></td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Amount</td>
                  <td class="pe-3 fw-semibold fs-6">{{ formatCurrency(order.amount, order.source_account?.currency) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Frequency</td>
                  <td class="pe-3 text-capitalize">{{ order.frequency }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Next Execution</td>
                  <td class="pe-3" :class="isDue ? 'text-warning fw-semibold' : ''">
                    <i v-if="isDue" class="bi bi-exclamation-circle me-1"></i>
                    {{ formatDate(order.next_execution_date) }}
                  </td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Start Date</td>
                  <td class="pe-3">{{ formatDate(order.start_date) }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">End Date</td>
                  <td class="pe-3">{{ order.end_date ? formatDate(order.end_date) : 'No end date' }}</td>
                </tr>
                <tr v-if="order.description">
                  <td class="text-muted ps-3">Description</td>
                  <td class="pe-3">{{ order.description }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Created By</td>
                  <td class="pe-3">{{ order.created_by_user?.name ?? '—' }}</td>
                </tr>
                <tr>
                  <td class="text-muted ps-3">Created At</td>
                  <td class="pe-3">{{ formatDate(order.created_at) }}</td>
                </tr>
              </tbody>
            </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Sidebar: accounts -->
      <div class="col-lg-4">
        <!-- Source -->
        <div class="card shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-arrow-up-circle me-1 text-danger"></i>Source Account</div>
          <div class="card-body">
            <div class="fw-bold font-monospace">{{ order.source_account?.account_number }}</div>
            <div class="text-muted small">{{ order.source_account?.customer?.name }}</div>
            <div class="text-success small fw-semibold mt-1">Balance: {{ formatCurrency(order.source_account?.balance, order.source_account?.currency) }}</div>
            <Link :href="route('admin.accounts.show', order.source_account?.id)" class="btn btn-sm btn-outline-primary mt-2 w-100">View Account</Link>
          </div>
        </div>

        <!-- Beneficiary -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small"><i class="bi bi-arrow-down-circle me-1 text-success"></i>Beneficiary Account</div>
          <div class="card-body">
            <div class="fw-bold font-monospace">{{ order.beneficiary_account?.account_number }}</div>
            <div class="text-muted small">{{ order.beneficiary_account?.customer?.name }}</div>
            <Link :href="route('admin.accounts.show', order.beneficiary_account?.id)" class="btn btn-sm btn-outline-primary mt-2 w-100">View Account</Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Pause Modal -->
    <div v-if="showPause" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pause-circle me-2 text-warning"></i>Pause Standing Order</h5>
            <button type="button" class="btn-close" @click="showPause = false"></button>
          </div>
          <div class="modal-body">
            The standing order will be paused. No transfers will be executed until it is resumed.
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="showPause = false">Cancel</button>
            <button class="btn btn-warning" @click="pauseOrder">Pause Order</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Modal -->
    <div v-if="showCancel" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title"><i class="bi bi-x-circle me-2 text-danger"></i>Cancel Standing Order</h5>
            <button type="button" class="btn-close" @click="showCancel = false"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-danger small">This will permanently cancel the standing order. This cannot be undone.</div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-light" @click="showCancel = false">Keep Order</button>
            <button class="btn btn-danger" @click="cancelOrder">Confirm Cancel</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
  order: { type: Object, required: true },
});

const showPause  = ref(false);
const showCancel = ref(false);

const isDue = computed(() =>
  props.order.status === 'active' && new Date(props.order.next_execution_date) <= new Date()
);

const pauseOrder = () => {
  router.post(route('admin.standing-orders.pause', props.order.id), {}, {
    onSuccess: () => { showPause.value = false; },
  });
};

const resumeOrder = () => router.post(route('admin.standing-orders.resume', props.order.id));

const cancelOrder = () => {
  router.post(route('admin.standing-orders.cancel', props.order.id), {}, {
    onSuccess: () => { showCancel.value = false; },
  });
};

const formatCurrency = (v, c = 'USD') => new Intl.NumberFormat('en-US', { style: 'currency', currency: c ?? 'USD' }).format(v ?? 0);
const formatDate     = (d) => d ? new Date(d).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '—';
</script>
