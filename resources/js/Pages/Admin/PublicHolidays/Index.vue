<template>
  <AdminLayout title="Public Holidays" subtitle="Bank non-working days — blocks business day opening">
    <template #actions>
      <div class="d-flex gap-2">
        <select :value="year" @change="changeYear($event.target.value)"
                class="form-select form-select-sm" style="width:100px">
          <option v-for="y in yearRange" :key="y" :value="y">{{ y }}</option>
        </select>
        <button v-if="can('public-holidays.manage')" class="btn btn-primary btn-sm" @click="showAdd = true">
          <i class="bi bi-plus-circle me-1"></i>Add Holiday
        </button>
      </div>
    </template>


    <div class="row g-4">
      <!-- Holiday List -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-calendar-event me-2 text-danger"></i>Holidays for {{ year }}</span>
            <span class="badge bg-danger rounded-pill">{{ holidays.length }}</span>
          </div>

          <div v-if="!holidays.length" class="card-body text-center text-muted py-5">
            <i class="bi bi-calendar-check d-block mb-2" style="font-size:2rem;opacity:.3"></i>
            No holidays defined for {{ year }}.
          </div>

          <div v-else class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th class="ps-3">Date</th>
                  <th>Day</th>
                  <th>Name</th>
                  <th>Description</th>
                  <th class="text-center">Recurring</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="h in sortedHolidays" :key="h.id"
                    :class="isWeekend(h.effective_date) ? 'table-secondary' : ''">
                  <td class="ps-3 font-monospace small fw-bold">{{ h.effective_date }}</td>
                  <td class="small" :class="isWeekend(h.effective_date) ? 'text-muted' : 'fw-semibold'">
                    {{ h.day_name }}
                  </td>
                  <td>
                    {{ h.name }}
                    <span v-if="isWeekend(h.effective_date)" class="badge bg-secondary ms-1" style="font-size:.65rem">Weekend</span>
                  </td>
                  <td class="text-muted small">{{ h.description ?? '—' }}</td>
                  <td class="text-center">
                    <i v-if="h.recurring_yearly" class="bi bi-arrow-repeat text-success" title="Repeats every year"></i>
                    <span v-else class="text-muted">—</span>
                  </td>
                  <td>
                    <button v-if="can('public-holidays.manage')" class="btn btn-xs btn-outline-danger py-0 px-2" style="font-size:.75rem"
                            @click="confirmDelete(h)">
                      <i class="bi bi-trash"></i>
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Info panel -->
      <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-info-circle me-2 text-primary"></i>How It Works
          </div>
          <div class="card-body small text-muted">
            <ul class="mb-0 ps-3">
              <li class="mb-2">Opening a <strong>Business Day</strong> is blocked on public holidays.</li>
              <li class="mb-2">Weekends (Sat/Sun) are automatically non-working regardless of this list.</li>
              <li class="mb-2"><strong>Recurring yearly</strong> holidays repeat every year on the same month/day.</li>
              <li>The <strong>EOD process</strong> still runs nightly even on holidays for automated clearing.</li>
            </ul>
          </div>
        </div>

        <div class="card border-0 shadow-sm">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-calendar3 me-2 text-secondary"></i>Summary for {{ year }}
          </div>
          <div class="list-group list-group-flush small">
            <div class="list-group-item d-flex justify-content-between">
              <span class="text-muted">Total Holidays</span>
              <strong>{{ holidays.length }}</strong>
            </div>
            <div class="list-group-item d-flex justify-content-between">
              <span class="text-muted">On Weekdays</span>
              <strong>{{ weekdayHolidays }}</strong>
            </div>
            <div class="list-group-item d-flex justify-content-between">
              <span class="text-muted">Recurring</span>
              <strong>{{ holidays.filter(h => h.recurring_yearly).length }}</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Holiday Modal -->
    <div v-if="showAdd" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-calendar-plus me-2 text-danger"></i>Add Public Holiday</h5>
            <button type="button" class="btn-close" @click="showAdd = false"></button>
          </div>
          <form @submit.prevent="submitAdd">
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label fw-semibold">Holiday Name <span class="text-danger">*</span></label>
                  <input v-model="addForm.name" type="text" class="form-control"
                         :class="addForm.errors.name ? 'is-invalid' : ''"
                         placeholder="e.g. Eid Al-Fitr" required>
                  <div class="invalid-feedback">{{ addForm.errors.name }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
                  <input v-model="addForm.date" type="date" class="form-control"
                         :class="addForm.errors.date ? 'is-invalid' : ''" required>
                  <div class="invalid-feedback">{{ addForm.errors.date }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Description</label>
                  <input v-model="addForm.description" type="text" class="form-control"
                         placeholder="Optional short description">
                </div>
                <div class="col-12">
                  <div class="form-check">
                    <input v-model="addForm.recurring_yearly" type="checkbox" class="form-check-input"
                           id="recurringCheck">
                    <label class="form-check-label" for="recurringCheck">
                      Repeat every year (same month & day)
                    </label>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-light" @click="showAdd = false">Cancel</button>
              <button type="submit" class="btn btn-danger" :disabled="addForm.processing">
                <span v-if="addForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                Add Holiday
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Delete Confirm -->
    <div v-if="deleteTarget" class="modal fade show d-block" style="background:rgba(0,0,0,.5)">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header border-0">
            <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Remove Holiday</h5>
            <button type="button" class="btn-close" @click="deleteTarget = null"></button>
          </div>
          <div class="modal-body">
            Remove <strong>{{ deleteTarget.name }}</strong> ({{ deleteTarget.date }}) from the holiday calendar?
          </div>
          <div class="modal-footer border-0">
            <button type="button" class="btn btn-light" @click="deleteTarget = null">Cancel</button>
            <button class="btn btn-danger" @click="submitDelete" :disabled="deleting">
              <span v-if="deleting" class="spinner-border spinner-border-sm me-1"></span>
              Remove
            </button>
          </div>
        </div>
      </div>
    </div>

  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePermissions } from '@/composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
  holidays: { type: Array, default: () => [] },
  year:     Number,
})

const showAdd     = ref(false)
const deleteTarget= ref(null)
const deleting    = ref(false)

const addForm = useForm({
  name:             '',
  date:             '',
  description:      '',
  recurring_yearly: false,
})

const yearRange = computed(() => {
  const y = new Date().getFullYear()
  return [y - 1, y, y + 1, y + 2]
})

const sortedHolidays = computed(() =>
  [...props.holidays].sort((a, b) => a.effective_date.localeCompare(b.effective_date))
)

const weekdayHolidays = computed(() =>
  props.holidays.filter(h => !isWeekend(h.effective_date)).length
)

function isWeekend(dateStr) {
  const d = new Date(dateStr)
  return d.getDay() === 0 || d.getDay() === 6
}

function changeYear(y) {
  router.get(route('admin.public-holidays.index'), { year: y })
}

function submitAdd() {
  addForm.post(route('admin.public-holidays.store'), {
    onSuccess: () => { showAdd.value = false; addForm.reset() },
  })
}

function confirmDelete(h) {
  deleteTarget.value = h
}

function submitDelete() {
  deleting.value = true
  router.delete(route('admin.public-holidays.destroy', deleteTarget.value.id), {
    onFinish: () => { deleting.value = false; deleteTarget.value = null },
  })
}
</script>
