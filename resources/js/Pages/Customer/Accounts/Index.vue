<template>
  <PortalLayout title="My Accounts" subtitle="All your bank accounts">

    <!-- Summary -->
    <div v-if="accounts.length" class="accounts-summary mb-4">
      <div>
        <span class="fw-bold">{{ accounts.length }}</span>
        <span class="text-muted"> Account{{ accounts.length === 1 ? '' : 's' }}</span>
      </div>
      <div v-if="totalBalance !== null" class="text-end">
        <div class="text-muted small">Total Balance</div>
        <div class="fw-bold tabular-nums" style="color:#0B2447">
          {{ accounts[0].currency ?? 'USD' }} {{ fmt(totalBalance) }}
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div v-for="acc in accounts" :key="acc.id" class="col-md-6 col-xl-4">
        <div class="account-card position-relative" :style="{ viewTransitionName: `account-card-${acc.id}` }">
          <div class="account-card-top">
            <div class="text-white-50 small text-uppercase letter-spacing-1">{{ ucfirst(acc.account_type) }} Account</div>
            <span class="status-pill" :class="statusBadge(acc.status)">
              <i class="bi bi-circle-fill"></i>{{ acc.status }}
            </span>
          </div>

          <div class="account-number-row">
            <span class="account-number">{{ acc.account_number }}</span>
            <button type="button" class="copy-btn" @click.stop.prevent="copyNumber(acc)"
                    :title="'Copy account number'">
              <i class="bi" :class="copiedId === acc.id ? 'bi-check2' : 'bi-copy'"></i>
              <span v-if="copiedId === acc.id" class="copy-feedback">Copied</span>
            </button>
          </div>
          <div class="account-subtitle">{{ typeDescription(acc.account_type) }}</div>

          <div class="account-balance" :style="{ viewTransitionName: `account-balance-${acc.id}` }">
            <div class="balance-label">Available Balance</div>
            <div class="balance-amount tabular-nums">{{ acc.currency ?? 'USD' }} {{ fmt(acc.balance) }}</div>
          </div>

          <div class="account-card-footer">
            <div>
              <div class="text-white-50 opened-label">Opened</div>
              <div class="text-white-50 small">{{ fmtDate(acc.created_at) }}</div>
            </div>
            <Link :href="route('customer.accounts.show', acc.id)" view-transition
                  class="stretched-link view-link fw-semibold">
              View Account <i class="bi bi-arrow-right ms-1"></i>
            </Link>
          </div>
        </div>
      </div>

      <div v-if="!accounts.length" class="col-12">
        <div class="text-center text-muted py-5">
          <i class="bi bi-wallet2 fs-1 mb-2 d-block"></i>
          No accounts found. Contact the bank to open an account.
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({ accounts: { type: Array, default: () => [] } })

// Summing balances across accounts is only meaningful when every account
// shares one currency — this bank is USD-only today, but the check keeps
// the total from silently becoming wrong the day a second currency exists.
const totalBalance = computed(() => {
  if (!props.accounts.length) return null
  const currency = props.accounts[0].currency ?? 'USD'
  const uniform = props.accounts.every((a) => (a.currency ?? 'USD') === currency)
  return uniform ? props.accounts.reduce((sum, a) => sum + Number(a.balance ?? 0), 0) : null
})

const copiedId = ref(null)
function copyNumber(acc) {
  navigator.clipboard?.writeText(acc.account_number)
  copiedId.value = acc.id
  setTimeout(() => { if (copiedId.value === acc.id) copiedId.value = null }, 1500)
}

const typeDescription = (t) => ({
  savings:       'Personal Savings Account',
  current:       'Personal Current Account',
  fixed_deposit: 'Fixed Deposit Account',
}[t] ?? 'Personal Account')

const fmt     = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const statusBadge = (s) => ({ active: 'status-active', frozen: 'status-frozen', closed: 'status-closed', dormant: 'status-dormant' }[s] ?? 'status-closed')
</script>

<style scoped>
.accounts-summary {
  display: flex; align-items: center; justify-content: space-between;
  padding: .85rem 1.1rem; background: var(--nabaad-section-bg, #f7f8fa);
  border: 1px solid var(--nabaad-border, #eceef1); border-radius: .75rem;
}
.account-card {
  background: linear-gradient(135deg, #0B2447 0%, #14395B 100%);
  border-radius: 16px; padding: 1.5rem; color: #fff;
  box-shadow: 0 4px 16px rgba(11,36,71,.25);
  border: 1px solid transparent;
  transition: transform .18s ease, box-shadow .18s ease;
}
.account-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 10px 28px rgba(11,36,71,.38);
  border-color: rgba(201,151,47,.4);
}
.account-card-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: .85rem; }
.status-pill {
  display: inline-flex; align-items: center; gap: .35rem;
  font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
  padding: .2rem .55rem; border-radius: 1rem;
}
.status-pill i { font-size: .45rem; }
.status-active  { background: rgba(16,185,129,.18); color: #6ee7b7; }
.status-frozen  { background: rgba(56,189,248,.18); color: #7dd3fc; }
.status-dormant { background: rgba(245,158,11,.18); color: #fcd34d; }
.status-closed  { background: rgba(148,163,184,.18); color: #cbd5e1; }

.account-number-row { display: flex; align-items: center; gap: .5rem; }
.account-number { font-size: 1.15rem; font-weight: 700; letter-spacing: 1px; font-family: monospace; }
.copy-btn {
  position: relative; z-index: 2;
  background: rgba(255,255,255,.12); border: none; color: #fff;
  width: 26px; height: 26px; border-radius: 6px; font-size: .8rem;
  display: inline-flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background .15s ease;
}
.copy-btn:hover { background: rgba(255,255,255,.22); }
.copy-feedback {
  position: absolute; top: -1.6rem; left: 50%; transform: translateX(-50%);
  background: #0B2447; color: #fff; font-size: .65rem; font-weight: 600;
  padding: .15rem .5rem; border-radius: .3rem; white-space: nowrap;
}
.account-subtitle { color: rgba(255,255,255,.55); font-size: .78rem; margin-top: .15rem; }

.balance-label { font-size: .75rem; color: rgba(255,255,255,.6); text-transform: uppercase; letter-spacing: .5px; }
.balance-amount { font-size: 1.85rem; font-weight: 800; }
.account-balance { margin: 1.25rem 0; }
.account-card-footer {
  display: flex; justify-content: space-between; align-items: center;
  border-top: 1px solid rgba(255,255,255,.15); padding-top: 1rem;
}
.opened-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; }
.view-link {
  color: #fff !important; text-decoration: none; font-size: .85rem;
  display: inline-flex; align-items: center;
}
.view-link i { transition: transform .15s ease; }
.account-card:hover .view-link i { transform: translateX(3px); }
</style>
