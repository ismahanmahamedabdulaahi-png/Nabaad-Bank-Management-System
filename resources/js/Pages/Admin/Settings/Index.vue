<template>
  <AdminLayout title="System Settings" subtitle="Configure bank-wide parameters">

    <form @submit.prevent="save">
      <!-- Tab nav -->
      <ul class="nav nav-tabs mb-0" style="border-bottom:none">
        <li v-for="g in groups" :key="g" class="nav-item">
          <button type="button" class="nav-link text-capitalize"
                  :class="{ active: activeTab === g }"
                  @click="activeTab = g">
            <i class="bi me-1" :class="groupIcon(g)"></i>{{ g }}
          </button>
        </li>
      </ul>

      <div class="card border-0 shadow-sm" style="border-top-left-radius:0">
        <div class="card-body">

          <!-- General -->
          <div v-show="activeTab === 'general'">
            <h6 class="section-title">Bank Information</h6>
            <div class="row g-3">
              <SettingField v-for="key in ['bank_name','bank_branch','bank_currency','bank_timezone','dormancy_months']"
                :key="key" :setting="getSetting('general', key)" v-model="form[key]" />
            </div>
          </div>

          <!-- Loan -->
          <div v-show="activeTab === 'loan'">
            <h6 class="section-title">Loan Parameters</h6>
            <div class="row g-3">
              <SettingField v-for="key in ['loan_interest_rate','loan_penalty_rate','loan_grace_period_days','loan_min_account_age_months','loan_min_transaction_volume']"
                :key="key" :setting="getSetting('loan', key)" v-model="form[key]" />
            </div>
          </div>

          <!-- Transaction -->
          <div v-show="activeTab === 'transaction'">
            <h6 class="section-title">Transaction Limits</h6>
            <div class="row g-3">
              <SettingField v-for="key in ['txn_limit_teller','txn_limit_cso','txn_no_approval_max','txn_approval_level1_max','txn_approval_level2_max']"
                :key="key" :setting="getSetting('transaction', key)" v-model="form[key]" />
            </div>
          </div>

          <!-- Cheque -->
          <div v-show="activeTab === 'cheque'">
            <h6 class="section-title">Cheque Settings</h6>
            <div class="row g-3">
              <SettingField v-for="key in ['cheque_clearing_days','cheque_expiry_months','cheque_book_leaves']"
                :key="key" :setting="getSetting('cheque', key)" v-model="form[key]" />
            </div>
          </div>

          <!-- Session -->
          <div v-show="activeTab === 'session'">
            <h6 class="section-title">Session & Security</h6>
            <div class="row g-3">
              <SettingField :setting="getSetting('session', 'session_timeout_minutes')"
                v-model="form['session_timeout_minutes']" />
            </div>
            <div class="alert alert-info mt-3 small">
              <i class="bi bi-info-circle me-2"></i>
              Session timeout is enforced server-side. Changes take effect on next login.
            </div>
          </div>

          <!-- Email -->
          <div v-show="activeTab === 'email'">
            <h6 class="section-title">Email Configuration</h6>
            <div class="row g-3">
              <SettingField v-for="key in ['email_from_name','email_from_address']"
                :key="key" :setting="getSetting('email', key)" v-model="form[key]" />
            </div>
          </div>

        </div>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
          <span class="text-muted small">
            <i class="bi bi-shield-lock me-1"></i>Only Super Admin and Branch Manager can edit settings.
          </span>
          <button type="submit" class="btn btn-primary px-4"
                  :disabled="saving">
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            <i v-else class="bi bi-floppy me-2"></i>Save Changes
          </button>
        </div>
      </div>
    </form>

  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed, defineComponent, h } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  settings: { type: Object, default: () => ({}) },
  groups:   { type: Array,  default: () => [] },
})

const activeTab = ref(props.groups[0] ?? 'general')
const saving    = ref(false)

// Flatten all settings into a key→value map for the form
const form = reactive({})
Object.values(props.settings).forEach(group => {
  Object.values(group).forEach(s => { form[s.key] = s.value ?? '' })
})

const getSetting = (group, key) => props.settings[group]?.[key] ?? { key, label: key, type: 'string' }

const groupIcon = (g) => ({
  general: 'bi-building', loan: 'bi-cash-coin', transaction: 'bi-arrow-left-right',
  cheque: 'bi-file-earmark-text', session: 'bi-shield-lock', email: 'bi-envelope',
}[g] ?? 'bi-gear')

const save = () => {
  saving.value = true
  const payload = Object.entries(form).map(([key, value]) => ({ key, value: String(value) }))
  router.put(route('admin.settings.update'), { settings: payload }, {
    onFinish: () => { saving.value = false },
    preserveScroll: true,
  })
}

// ── Inline SettingField component ────────────────────────────────────────────
const SettingField = defineComponent({
  props: { setting: Object, modelValue: String },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    return () => h('div', { class: 'col-md-6' }, [
      h('label', { class: 'form-label fw-semibold small' }, props.setting?.label ?? props.setting?.key),
      h('div', { class: 'input-group' }, [
        props.setting?.type === 'decimal' || props.setting?.type === 'integer'
          ? h('span', { class: 'input-group-text bg-white text-muted small' },
              props.setting?.key?.includes('rate') ? '%' :
              props.setting?.key?.includes('_max') || props.setting?.key?.includes('volume') || props.setting?.key?.includes('limit') ? '$' :
              '#')
          : null,
        h('input', {
          type: props.setting?.type === 'decimal' || props.setting?.type === 'integer' ? 'number' : 'text',
          step: props.setting?.type === 'decimal' ? '0.01' : '1',
          class: 'form-control form-control-sm',
          value: props.modelValue,
          onInput: (e) => emit('update:modelValue', e.target.value),
        }),
      ]),
    ])
  }
})
</script>

<style scoped>
.section-title { color: var(--nabaad-primary); font-size: .85rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 1rem; padding-bottom: .5rem; border-bottom: 2px solid var(--nabaad-border); }
.dark .section-title { color: var(--nabaad-accent); }
.nav-tabs .nav-link { color: var(--nabaad-muted); font-size: .875rem; }
.nav-tabs .nav-link.active { color: var(--nabaad-primary); font-weight: 600; border-bottom-color: var(--nabaad-bg); }
.dark .nav-tabs .nav-link.active { color: var(--nabaad-accent); }
</style>
