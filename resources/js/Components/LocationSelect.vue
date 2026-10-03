<template>
  <div class="row g-3">
    <!-- Country -->
    <div class="col-md-4">
      <label class="form-label fw-semibold" v-if="showLabels">Country<span v-if="required" class="text-danger"> *</span></label>
      <select v-model="country" class="form-select" :class="{ 'is-invalid': errors?.country }" :required="required">
        <option value="">Select country</option>
        <option v-for="c in COUNTRIES" :key="c" :value="c">{{ c }}</option>
      </select>
      <div v-if="errors?.country" class="invalid-feedback">{{ errors.country }}</div>
    </div>

    <!-- Region / Gobolka -->
    <div class="col-md-4">
      <label class="form-label fw-semibold" v-if="showLabels">Region / Gobolka<span v-if="required" class="text-danger"> *</span></label>
      <select v-if="regionOptions.length" v-model="region" class="form-select" :class="{ 'is-invalid': errors?.region }" :required="required" :disabled="!country">
        <option value="">Select region</option>
        <option v-for="r in regionOptions" :key="r" :value="r">{{ r }}</option>
      </select>
      <input v-else v-model="region" type="text" class="form-control" :class="{ 'is-invalid': errors?.region }"
             :disabled="!country" placeholder="Region / State / Province" :required="required">
      <div v-if="errors?.region" class="invalid-feedback">{{ errors.region }}</div>
    </div>

    <!-- City / Magaalada -->
    <div class="col-md-4">
      <label class="form-label fw-semibold" v-if="showLabels">City / Magaalada<span v-if="required" class="text-danger"> *</span></label>
      <select v-if="cityOptions.length" v-model="city" class="form-select" :class="{ 'is-invalid': errors?.city }" :required="required" :disabled="!region">
        <option value="">Select city</option>
        <option v-for="ct in cityOptions" :key="ct" :value="ct">{{ ct }}</option>
      </select>
      <input v-else v-model="city" type="text" class="form-control" :class="{ 'is-invalid': errors?.city }"
             :disabled="!country || (regionOptions.length > 0 && !region)" placeholder="City" :required="required">
      <div v-if="errors?.city" class="invalid-feedback">{{ errors.city }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { COUNTRIES, regionsForCountry, citiesForRegion } from '@/data/somaliaLocations'

defineProps({
  errors:     { type: Object, default: () => ({}) },
  required:   { type: Boolean, default: true },
  showLabels: { type: Boolean, default: true },
})

const country = defineModel('country', { type: String, default: '' })
const region  = defineModel('region',  { type: String, default: '' })
const city    = defineModel('city',    { type: String, default: '' })

const regionOptions = computed(() => regionsForCountry(country.value))
const cityOptions   = computed(() => citiesForRegion(country.value, region.value))

// Reset dependent fields when an ancestor changes to a value that no longer supports them
watch(country, (val, oldVal) => {
  if (val !== oldVal) { region.value = ''; city.value = '' }
})
watch(region, (val, oldVal) => {
  if (val !== oldVal) { city.value = '' }
})
</script>
