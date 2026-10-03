<template>
  <PortalLayout title="Budgets" subtitle="Track your monthly spending and get alerted before you overspend">
    <template #actions>
      <Link :href="route('customer.budgets.create')" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Budget
      </Link>
    </template>

    <div v-if="budgets.length === 0" class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-piggy-bank fs-1 d-block mb-2 opacity-25"></i>
        No budgets yet.
        <Link :href="route('customer.budgets.create')" class="d-block mt-2 small text-primary">Set up your first budget →</Link>
      </div>
    </div>

    <div v-else class="row g-3">
      <div v-for="budget in budgets" :key="budget.id" class="col-md-6">
        <div class="card border-0 shadow-sm h-100" :class="{ 'opacity-75': !budget.is_active }">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-1">
              <div>
                <div class="fw-semibold">{{ budget.name }}</div>
                <div class="text-muted small">
                  {{ budget.account ? budget.account.account_number : 'All accounts' }}
                  <span v-if="!budget.is_active" class="badge bg-secondary ms-1">Paused</span>
                </div>
              </div>
              <div class="text-end">
                <div class="fw-bold" :style="{ color: barColor(budget.percent_used, budget.threshold_percent) }">{{ budget.percent_used }}%</div>
                <div class="text-muted" style="font-size:.7rem">of {{ fmt(budget.limit_amount) }}</div>
              </div>
            </div>

            <div class="progress mt-2" style="height:8px">
              <div class="progress-bar" role="progressbar"
                   :style="{ width: Math.min(100, budget.percent_used) + '%', backgroundColor: barColor(budget.percent_used, budget.threshold_percent) }">
              </div>
            </div>

            <div class="d-flex justify-content-between mt-2 small text-muted">
              <span>Spent: <strong :class="textClass(budget.percent_used, budget.threshold_percent)">{{ fmt(budget.spent) }}</strong></span>
              <span>Since {{ budget.period_start }}</span>
            </div>

            <div v-if="budget.percent_used >= 100" class="alert alert-danger py-1 px-2 small mt-2 mb-0">
              <i class="bi bi-exclamation-octagon me-1"></i>Over budget by {{ fmt(budget.spent - budget.limit_amount) }}
            </div>
            <div v-else-if="budget.percent_used >= budget.threshold_percent" class="alert alert-warning py-1 px-2 small mt-2 mb-0">
              <i class="bi bi-exclamation-triangle me-1"></i>Approaching your {{ budget.threshold_percent }}% alert threshold
            </div>

            <div class="d-flex gap-2 mt-3">
              <form @submit.prevent="toggle(budget.id)">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                  <i class="bi" :class="budget.is_active ? 'bi-pause' : 'bi-play'"></i>
                  {{ budget.is_active ? 'Pause' : 'Resume' }}
                </button>
              </form>
              <button type="button" class="btn btn-sm btn-outline-danger" @click="confirmDelete(budget)">
                <i class="bi bi-trash me-1"></i>Delete
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Delete confirmation -->
    <ConfirmModal
      id="budgetDeleteModal"
      title="Delete Budget"
      :message="deleteTarget ? `Delete the '${deleteTarget.name}' budget? This cannot be undone.` : ''"
      variant="danger"
      icon="bi-trash"
      confirm-label="Delete"
      @confirmed="doDelete"
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
  budgets:  { type: Array, default: () => [] },
  accounts: { type: Array, default: () => [] },
})

const toggle = (id) => router.post(route('customer.budgets.toggle', id), {}, { preserveScroll: true })

const deleteTarget = ref(null)
const confirmDelete = (budget) => { deleteTarget.value = budget; new Modal(document.getElementById('budgetDeleteModal')).show() }
const doDelete = () => router.delete(route('customer.budgets.destroy', deleteTarget.value.id), { preserveScroll: true })

const fmt = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0)

const barColor = (pct, threshold) => pct >= 100 ? '#ef4444' : pct >= threshold ? '#f59e0b' : '#10b981'
const textClass = (pct, threshold) => pct >= 100 ? 'text-danger' : pct >= threshold ? 'text-warning' : 'text-success'
</script>
