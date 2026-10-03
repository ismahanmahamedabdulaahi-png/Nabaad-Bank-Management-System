<template>
  <AdminLayout title="Dashboard" subtitle="Welcome back to NABAAD Bank">

    <!-- System Alerts -->
    <div v-if="alerts.length" class="alert-stack mb-4">
      <Link v-for="(alert, i) in alerts" :key="i" :href="alert.href"
            class="alert-item" :class="`alert-item-${alert.level}`">
        <i class="bi" :class="alert.icon"></i>
        <span>{{ alert.label }}</span>
        <i class="bi bi-chevron-right ms-auto"></i>
      </Link>
    </div>

    <!-- KPI Row 1 — Core Banking -->
    <div class="row g-3 mb-3">
      <div v-for="tile in coreTiles" :key="tile.label" class="col-6 col-xl-3">
        <div class="card tile h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="tile-label">{{ tile.label }}</div>
                <div class="tile-value tabular-nums">{{ tile.value }}</div>
              </div>
              <div class="tile-icon" :style="{ background: tile.tint, color: tile.color }">
                <i :class="tile.icon"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- KPI Row 2 — Operational / Attention -->
    <div class="row g-3 mb-4">
      <div v-for="tile in opsTiles" :key="tile.label" class="col-6 col-xl-3">
        <div class="card tile h-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="tile-label">{{ tile.label }}</div>
                <div class="tile-value tabular-nums">{{ tile.value }}</div>
              </div>
              <div class="tile-icon" :style="{ background: tile.tint, color: tile.color }">
                <i :class="tile.icon"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Transaction Trend + Net Flow -->
    <div v-if="can('transactions.view')" class="row g-3 mb-4">
      <div class="col-lg-8">
        <div class="card chart-card h-100">
          <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span class="fw-semibold">Transaction Volume</span>
            <div class="btn-group btn-group-sm">
              <button v-for="r in [7, 14, 30, 90]" :key="r" type="button"
                      class="btn" :class="trendRange === r ? 'btn-primary' : 'btn-outline-secondary'"
                      @click="setRange(r)">
                {{ r }}D
              </button>
            </div>
          </div>
          <div class="card-body">
            <canvas ref="trendCanvas" height="110"></canvas>
            <p class="text-muted small mb-0 mt-2"><i class="bi bi-info-circle me-1"></i>Click a legend item to isolate a series.</p>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100">
          <div class="card-header bg-white fw-semibold">Transaction Overview</div>
          <div v-if="net_flow" class="card-body">
            <div class="flow-row">
              <span class="text-muted small">Deposits</span>
              <span class="fw-semibold text-success tabular-nums">+{{ fmt(net_flow.deposits) }}</span>
            </div>
            <div class="flow-row">
              <span class="text-muted small">Withdrawals</span>
              <span class="fw-semibold text-danger tabular-nums">-{{ fmt(net_flow.withdrawals) }}</span>
            </div>
            <div class="flow-row">
              <span class="text-muted small">Transfers</span>
              <span class="fw-semibold tabular-nums" style="color:#0B2447">{{ fmt(net_flow.transfers) }}</span>
            </div>
            <hr>
            <div class="flow-row">
              <span class="fw-semibold">Net Flow</span>
              <span class="fw-bold fs-5 tabular-nums" :class="net_flow.net_flow >= 0 ? 'text-success' : 'text-danger'">
                {{ net_flow.net_flow >= 0 ? '+' : '' }}{{ fmt(net_flow.net_flow) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Account Mix + Loan Portfolio + Quick Actions -->
    <div v-if="can('accounts.view') || can('loans.view') || hasAnyQuickAction" class="row g-3 mb-4">
      <div v-if="can('accounts.view')" class="col-lg-4">
        <div class="card chart-card h-100">
          <div class="card-header bg-white fw-semibold">Account Mix</div>
          <div class="card-body d-flex align-items-center">
            <canvas ref="mixCanvas" height="180"></canvas>
          </div>
        </div>
      </div>

      <div v-if="can('loans.view')" class="col-lg-4">
        <div class="card chart-card h-100">
          <div class="card-header bg-white fw-semibold">Loan Portfolio by Status</div>
          <div class="card-body">
            <canvas ref="loanCanvas" height="180"></canvas>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div v-if="hasAnyQuickAction" class="col-lg-4">
        <div class="card h-100">
          <div class="card-header bg-white">
            <i class="bi bi-lightning me-2 text-primary"></i>Quick Actions
          </div>
          <div class="card-body">
            <div class="d-grid gap-2">
              <Link v-if="can('customers.create')" :href="route('admin.customers.create')" class="btn btn-outline-primary btn-sm text-start">
                <i class="bi bi-person-plus me-2"></i>New Customer
              </Link>
              <Link v-if="can('accounts.create')" :href="route('admin.accounts.create')" class="btn btn-outline-primary btn-sm text-start">
                <i class="bi bi-wallet-plus me-2"></i>Open Account
              </Link>
              <Link v-if="can('transactions.deposit')" :href="route('admin.transactions.deposit.form')" class="btn btn-outline-success btn-sm text-start">
                <i class="bi bi-arrow-down-circle me-2"></i>Deposit Cash
              </Link>
              <Link v-if="can('transactions.withdraw')" :href="route('admin.transactions.withdrawal.form')" class="btn btn-outline-danger btn-sm text-start">
                <i class="bi bi-arrow-up-circle me-2"></i>Cash Withdrawal
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Recent Transactions + Pending Approvals -->
    <div class="row g-3">
      <div v-if="can('transactions.view')" :class="can('approvals.view') ? 'col-lg-6' : 'col-12'">
        <div class="card h-100">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span><i class="bi bi-arrow-left-right me-2 text-primary"></i>Recent Transactions</span>
            <Link :href="route('admin.transactions.index')" class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.15rem .5rem">
              View all
            </Link>
          </div>
          <div v-if="!recent_transactions.length" class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
            No transactions yet
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <tbody>
                <tr v-for="txn in recent_transactions" :key="txn.id">
                  <td>
                    <span class="badge" :class="typeBadge(txn.type)">{{ txn.type.replace(/_/g,' ') }}</span>
                  </td>
                  <td class="font-monospace small text-muted">{{ txn.account?.account_number ?? '—' }}</td>
                  <td class="small text-muted">{{ age(txn.created_at) }}</td>
                  <td class="font-monospace small text-end fw-semibold">{{ fmt(txn.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div v-if="can('approvals.view')" :class="can('transactions.view') ? 'col-lg-6' : 'col-12'">
        <div class="card h-100">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span><i class="bi bi-check2-circle me-2 text-warning"></i>Pending Approvals</span>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-warning text-dark">{{ pending_approvals_count }}</span>
              <Link :href="route('admin.approvals.index')" class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.15rem .5rem">
                View all
              </Link>
            </div>
          </div>
          <div v-if="pending_approvals.length === 0" class="card-body text-center text-muted py-5">
            <i class="bi bi-inbox fs-1 d-block mb-2 opacity-25"></i>
            No pending approvals
          </div>
          <div v-else class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Reference</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Age</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="txn in pending_approvals" :key="txn.id">
                  <td class="font-monospace small">{{ txn.reference }}</td>
                  <td>
                    <span class="badge" :class="typeBadge(txn.type)">{{ txn.type.replace(/_/g,' ') }}</span>
                  </td>
                  <td class="font-monospace small">{{ fmt(txn.amount) }}</td>
                  <td class="small text-muted">{{ age(txn.created_at) }}</td>
                  <td>
                    <Link :href="route('admin.approvals.show', txn.id)"
                          class="btn btn-xs btn-warning py-0 px-2" style="font-size:.72rem">
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
  </AdminLayout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import {
  Chart, BarController, DoughnutController,
  CategoryScale, LinearScale,
  BarElement, ArcElement,
  Legend, Tooltip,
} from 'chart.js'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePermissions } from '@/composables/usePermissions'

Chart.register(BarController, DoughnutController, CategoryScale, LinearScale, BarElement, ArcElement, Legend, Tooltip)

const { can } = usePermissions()

// Each stat/chart the controller computes is gated server-side by the same
// permission its own admin page requires — DashboardController omits (null)
// anything the viewer can't see rather than computing-and-hiding, so these
// props are all nullable in practice.
const props = defineProps({
  stats: {
    type: Object,
    default: () => ({
      customers: null, active_accounts: null, today_transactions: null, active_loans: null,
      total_deposits: null, pending_approvals: null, pending_cheques: null, today_withdrawals: null,
    }),
  },
  pending_approvals:       { type: Array,  default: () => [] },
  pending_approvals_count: { type: Number, default: 0 },
  recent_transactions:     { type: Array,  default: () => [] },
  charts: {
    type: Object,
    default: () => ({
      transaction_trend: null,
      account_mix:       null,
      loan_status:       null,
    }),
  },
  net_flow:    { type: Object, default: null },
  trend_range: { type: Number, default: 14 },
  alerts:      { type: Array,  default: () => [] },
})

const hasAnyQuickAction = computed(() =>
  can('customers.create') || can('accounts.create') || can('transactions.deposit') || can('transactions.withdraw')
)

// Validated categorical palette (fixed order — see dataviz skill palette.md).
// Chart.js paints on canvas, so it can't pick up CSS custom properties or the
// `.dark` class — these have to be resolved to a concrete light/dark set in JS,
// and the charts rebuilt when the theme toggle fires.
const PALETTE = {
  light: {
    seriesBlue: '#2a78d6', seriesOrange: '#eb6834', seriesAqua: '#1baf7a',
    inkSecondary: '#52514e', inkMuted: '#898781', grid: '#e1e0d9',
  },
  dark: {
    seriesBlue: '#3987e5', seriesOrange: '#d95926', seriesAqua: '#199e70',
    inkSecondary: '#c3c2b7', inkMuted: '#898781', grid: '#2c2c2a',
  },
}
// Status colors are fixed — validated against both light and dark surfaces.
const STATUS_BASE = {
  active:    '#0ca30c',
  overdue:   '#fab219',
  defaulted: '#d03b3b',
}

const isDark = ref(localStorage.getItem('nabaad_dark_mode') === '1')
const colors = computed(() => {
  const p = isDark.value ? PALETTE.dark : PALETTE.light
  return { ...p, closed: p.inkMuted }
})

// Row 1 — Core Banking. Row 2 — Operational/Attention (what needs eyes today).
const coreTiles = computed(() => [
  { permission: 'customers.view',      label: 'Active Customers',     value: (props.stats.customers ?? 0).toLocaleString(),          icon: 'bi bi-people-fill',       color: '#0B2447', tint: 'rgba(11,36,71,.08)' },
  { permission: 'accounts.view',       label: 'Active Accounts',      value: (props.stats.active_accounts ?? 0).toLocaleString(),     icon: 'bi bi-wallet2',           color: '#10b981', tint: 'rgba(16,185,129,.1)' },
  { permission: 'transactions.view',   label: "Today's Transactions", value: (props.stats.today_transactions ?? 0).toLocaleString(),  icon: 'bi bi-arrow-left-right',  color: '#f59e0b', tint: 'rgba(245,158,11,.1)' },
  { permission: 'accounts.view',       label: 'Total Deposits',       value: fmtCompact(props.stats.total_deposits ?? 0),             icon: 'bi bi-bank2',             color: '#0B2447', tint: 'rgba(11,36,71,.08)' },
].filter((tile) => can(tile.permission)))

const opsTiles = computed(() => [
  { permission: 'approvals.view',      label: 'Pending Approvals',    value: (props.stats.pending_approvals ?? 0).toLocaleString(),   icon: 'bi bi-hourglass-split',   color: '#dc2626', tint: 'rgba(220,38,38,.08)' },
  { permission: 'cheques.view',        label: 'Pending Transactions', value: (props.stats.pending_cheques ?? 0).toLocaleString(),     icon: 'bi bi-hourglass',         color: '#f59e0b', tint: 'rgba(245,158,11,.1)' },
  { permission: 'loans.view',          label: 'Active Loans',         value: (props.stats.active_loans ?? 0).toLocaleString(),        icon: 'bi bi-cash-coin',         color: '#6366f1', tint: 'rgba(99,102,241,.1)' },
  { permission: 'transactions.view',   label: "Today's Withdrawals",  value: fmtCompact(props.stats.today_withdrawals ?? 0),          icon: 'bi bi-arrow-up-circle',   color: '#dc2626', tint: 'rgba(220,38,38,.08)' },
].filter((tile) => can(tile.permission)))

const trendRange = ref(props.trend_range)

function setRange(r) {
  trendRange.value = r
  router.reload({
    only: ['charts', 'net_flow', 'trend_range'],
    data: { range: r },
    preserveScroll: true,
    preserveState: true,
  })
}

// Draws the total account count in the donut's empty center.
const centerLabelPlugin = {
  id: 'centerLabel',
  afterDraw(chart) {
    if (chart.config.type !== 'doughnut' || !chart.$centerText) return
    const { ctx, chartArea: { left, right, top, bottom } } = chart
    const x = (left + right) / 2
    const y = (top + bottom) / 2
    ctx.save()
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.font = '700 1.4rem system-ui, sans-serif'
    ctx.fillStyle = chart.$centerColor ?? '#0B2447'
    ctx.fillText(chart.$centerText, x, y - 10)
    ctx.font = '600 .7rem system-ui, sans-serif'
    ctx.fillStyle = chart.$centerMuted ?? '#898781'
    ctx.fillText('Accounts', x, y + 12)
    ctx.restore()
  },
}
Chart.register(centerLabelPlugin)

const trendCanvas = ref(null)
const mixCanvas   = ref(null)
const loanCanvas  = ref(null)
let trendChart = null
let mixChart   = null
let loanChart  = null

function buildCharts() {
  const c = colors.value
  const borderColor = isDark.value ? '#1e2433' : '#fff'

  trendChart?.destroy()
  mixChart?.destroy()
  loanChart?.destroy()
  trendChart = mixChart = loanChart = null

  // Each canvas only exists in the DOM (and each `charts.*` prop is only
  // populated) when the viewer holds the matching permission — skip building
  // whatever wasn't rendered/sent for this role.
  if (can('transactions.view') && trendCanvas.value && props.charts.transaction_trend) {
    trendChart = new Chart(trendCanvas.value, {
      type: 'bar',
      data: {
        labels: props.charts.transaction_trend.labels,
        datasets: [
          {
            label: 'Deposits',
            data: props.charts.transaction_trend.deposits,
            backgroundColor: c.seriesBlue,
            borderRadius: 4,
            maxBarThickness: 18,
          },
          {
            label: 'Withdrawals',
            data: props.charts.transaction_trend.withdrawals,
            backgroundColor: c.seriesOrange,
            borderRadius: 4,
            maxBarThickness: 18,
          },
          {
            label: 'Transfers',
            data: props.charts.transaction_trend.transfers,
            backgroundColor: c.seriesAqua,
            borderRadius: 4,
            maxBarThickness: 18,
          },
        ],
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { position: 'top', align: 'end', labels: { color: c.inkSecondary, boxWidth: 12, usePointStyle: true } },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${fmtMoney(ctx.parsed.y)}`,
            },
          },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: c.inkMuted } },
          y: {
            grid: { color: c.grid },
            ticks: { color: c.inkMuted, callback: (v) => fmtCompact(v) },
            beginAtZero: true,
          },
        },
      },
    })
  }

  if (can('accounts.view') && mixCanvas.value && props.charts.account_mix) {
    mixChart = new Chart(mixCanvas.value, {
      type: 'doughnut',
      data: {
        labels: props.charts.account_mix.labels,
        datasets: [{
          data: props.charts.account_mix.values,
          backgroundColor: [c.seriesBlue, c.seriesOrange, c.seriesAqua],
          borderColor,
          borderWidth: 2,
        }],
      },
      options: {
        responsive: true,
        cutout: '62%',
        plugins: {
          legend: { position: 'bottom', labels: { color: c.inkSecondary, boxWidth: 12, usePointStyle: true } },
        },
      },
    })
    mixChart.$centerText  = props.charts.account_mix.values.reduce((a, b) => a + b, 0).toLocaleString()
    mixChart.$centerColor = isDark.value ? '#e8e6de' : '#0B2447'
    mixChart.$centerMuted = c.inkMuted
  }

  if (can('loans.view') && loanCanvas.value && props.charts.loan_status) {
    const loanLabels = props.charts.loan_status.labels
    const status = { ...STATUS_BASE, closed: c.closed }
    loanChart = new Chart(loanCanvas.value, {
      type: 'bar',
      data: {
        labels: loanLabels,
        datasets: [{
          data: props.charts.loan_status.values,
          backgroundColor: loanLabels.map((l) => status[l.toLowerCase()] ?? c.seriesBlue),
          borderRadius: 4,
          maxBarThickness: 20,
        }],
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: c.grid }, ticks: { color: c.inkMuted, precision: 0 }, beginAtZero: true },
          y: { grid: { display: false }, ticks: { color: c.inkSecondary } },
        },
      },
    })
  }
}

function onThemeChange(e) {
  isDark.value = e.detail
  buildCharts()
}

// After a range-filter partial reload, the server may clamp/echo back the
// range and the chart props change — rebuild the trend/mix/loan charts with
// the new data. (No `immediate`: onMounted already builds the first pass.)
watch(() => props.charts, () => {
  trendRange.value = props.trend_range
  buildCharts()
}, { deep: true })

onMounted(() => {
  buildCharts()
  window.addEventListener('nabaad-theme-change', onThemeChange)
})

onBeforeUnmount(() => {
  window.removeEventListener('nabaad-theme-change', onThemeChange)
  trendChart?.destroy()
  mixChart?.destroy()
  loanChart?.destroy()
})

const fmt = (v) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 2 }).format(v ?? 0)

function fmtMoney(v) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(v ?? 0)
}

function fmtCompact(v) {
  return new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 }).format(v ?? 0)
}

const age = (d) => {
  const mins = Math.floor((Date.now() - new Date(d)) / 60000)
  if (mins < 60)   return `${mins}m ago`
  if (mins < 1440) return `${Math.floor(mins / 60)}h ago`
  return `${Math.floor(mins / 1440)}d ago`
}

const typeBadge = (t) => ({
  deposit:        'bg-success',
  withdrawal:     'bg-danger',
  transfer:       'bg-primary',
  loan_repayment: 'bg-info text-dark',
}[t] ?? 'bg-secondary')
</script>

<style scoped>
.tile {
  border: 1px solid rgba(11,11,11,.06);
  border-radius: .75rem;
  transition: box-shadow .15s ease, transform .15s ease;
}
.tile:hover { box-shadow: 0 .5rem 1.25rem rgba(11,36,71,.08); transform: translateY(-1px); }
.tile-label { font-size: .78rem; color: var(--nabaad-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
.tile-value { font-size: 1.6rem; font-weight: 700; color: var(--nabaad-text); margin-top: .15rem; }
.tile-icon {
  width: 44px; height: 44px; border-radius: .65rem;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.15rem; flex-shrink: 0;
}
.chart-card .card-header { border-bottom: 1px solid rgba(11,11,11,.06); }

.alert-stack { display: flex; flex-direction: column; gap: .5rem; }
.alert-item {
  display: flex; align-items: center; gap: .6rem;
  padding: .6rem .9rem; border-radius: .6rem;
  font-size: .85rem; font-weight: 600; text-decoration: none;
  border: 1px solid transparent;
}
.alert-item-critical { background: rgba(220,38,38,.08); color: #b91c1c; border-color: rgba(220,38,38,.18); }
.alert-item-warning  { background: rgba(245,158,11,.1); color: #92620a; border-color: rgba(245,158,11,.2); }
.alert-item:hover { filter: brightness(.96); }
.dark .alert-item-critical { background: rgba(220,38,38,.15); color: #fca5a5; border-color: rgba(220,38,38,.3); }
.dark .alert-item-warning  { background: rgba(245,158,11,.15); color: #fcd34d; border-color: rgba(245,158,11,.3); }

.flow-row { display: flex; align-items: center; justify-content: space-between; padding: .35rem 0; }
</style>
