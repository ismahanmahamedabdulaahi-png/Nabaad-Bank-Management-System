<template>
  <AdminLayout :title="`Journal Entry ${entry.entry_number}`" subtitle="Journal entry detail">
    <template #actions>
      <Link :href="route('admin.general-ledger.journal-entries.index')" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back to Journal Entries
      </Link>
    </template>

    <div class="card border-0 shadow-sm mb-4">
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-3">
            <div class="text-muted small">Entry #</div>
            <div class="font-monospace fw-semibold">{{ entry.entry_number }}</div>
          </div>
          <div class="col-md-2">
            <div class="text-muted small">Date</div>
            <div class="fw-semibold">{{ entry.entry_date }}</div>
          </div>
          <div class="col-md-3">
            <div class="text-muted small">Source</div>
            <div class="fw-semibold">{{ entry.source_type ?? '—' }}</div>
          </div>
          <div class="col-md-2">
            <div class="text-muted small">Posted By</div>
            <div class="fw-semibold">{{ entry.created_by?.name ?? 'System' }}</div>
          </div>
          <div class="col-md-2">
            <div class="text-muted small">Reversal?</div>
            <div class="fw-semibold">
              <span v-if="entry.is_reversal" class="badge bg-warning-subtle text-warning">
                Reverses {{ entry.reversed_entry?.entry_number }}
              </span>
              <span v-else class="text-muted">No</span>
            </div>
          </div>
          <div class="col-12">
            <div class="text-muted small">Description</div>
            <div>{{ entry.description }}</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold">Lines</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>GL Account</th>
              <th>Memo</th>
              <th class="text-end">Debit</th>
              <th class="text-end">Credit</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="line in entry.lines" :key="line.id">
              <td class="font-monospace">{{ line.gl_account.code }} — {{ line.gl_account.name }}</td>
              <td class="text-muted small">{{ line.memo ?? '—' }}</td>
              <td class="text-end font-monospace">{{ Number(line.debit) > 0 ? formatMoney(line.debit) : '' }}</td>
              <td class="text-end font-monospace">{{ Number(line.credit) > 0 ? formatMoney(line.credit) : '' }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="table-light fw-semibold">
              <td colspan="2">TOTALS</td>
              <td class="text-end font-monospace">{{ formatMoney(totalDebit) }}</td>
              <td class="text-end font-monospace">{{ formatMoney(totalCredit) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  entry: { type: Object, required: true },
})

const totalDebit = computed(() => props.entry.lines.reduce((sum, l) => sum + Number(l.debit), 0))
const totalCredit = computed(() => props.entry.lines.reduce((sum, l) => sum + Number(l.credit), 0))

function formatMoney(v) {
  return Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
</script>
