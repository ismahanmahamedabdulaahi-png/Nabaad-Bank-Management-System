<template>
  <div class="row g-3">
    <div :class="showParentChoice || showOtherInput ? 'col-md-6' : 'col-12'">
      <select v-model="category" class="form-select" :class="{ 'is-invalid': error }">
        <option value="">— Select —</option>
        <option v-for="opt in CATEGORIES" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
      </select>
    </div>

    <!-- Parent → Father or Mother -->
    <div v-if="showParentChoice" class="col-md-6">
      <select v-model="parentChoice" class="form-select" :class="{ 'is-invalid': error }">
        <option value="">— Aabbe mise Hooyo? —</option>
        <option value="Father">Aabbe (Father)</option>
        <option value="Mother">Hooyo (Mother)</option>
      </select>
    </div>

    <!-- Other → free text -->
    <div v-if="showOtherInput" class="col-md-6">
      <input v-model="otherText" type="text" class="form-control" :class="{ 'is-invalid': error }" placeholder="Specify relationship…">
    </div>

    <div v-if="error" class="col-12"><div class="invalid-feedback d-block">{{ error }}</div></div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  error: { type: String, default: '' },
})

const relationship = defineModel({ type: String, default: '' })

const CATEGORIES = [
  { value: 'Spouse',  label: 'Xaas (Spouse)' },
  { value: 'Parent',  label: 'Waalid (Parent)' },
  { value: 'Sibling', label: 'Walaal (Sibling)' },
  { value: 'Child',   label: 'Ilmo (Child)' },
  { value: 'Other',   label: 'Qaraabo kale (Other)' },
]
const PARENT_VALUES = ['Father', 'Mother']
const DIRECT_VALUES = ['Spouse', 'Sibling', 'Child']

// Derive initial UI state from whatever value the form already has
// (existing records store the specific final value, e.g. "Mother").
const initial = relationship.value
const category = ref(
  PARENT_VALUES.includes(initial) ? 'Parent'
    : DIRECT_VALUES.includes(initial) ? initial
    : initial ? 'Other'
    : ''
)
const parentChoice = ref(PARENT_VALUES.includes(initial) ? initial : '')
const otherText = ref(category.value === 'Other' ? initial : '')

const showParentChoice = computed(() => category.value === 'Parent')
const showOtherInput   = computed(() => category.value === 'Other')

// Reset the sub-choice when the category itself changes
watch(category, (val, oldVal) => {
  if (val === oldVal) return
  if (val !== 'Parent') parentChoice.value = ''
  if (val !== 'Other') otherText.value = ''
})

// Keep the single stored value in sync with whichever UI is active
watch([category, parentChoice, otherText], () => {
  if (category.value === 'Parent') {
    relationship.value = parentChoice.value
  } else if (category.value === 'Other') {
    relationship.value = otherText.value
  } else {
    relationship.value = category.value
  }
})
</script>
