<template>
  <PortalLayout title="My Cheques" subtitle="Cheque books and issued cheques">
    <div v-if="!books.length" class="text-center text-muted py-5">
      <i class="bi bi-file-earmark-text fs-1 mb-2 d-block"></i>
      No cheque books found.
    </div>

    <div v-for="book in books" :key="book.id" class="card border-0 shadow-sm mb-4">
      <!-- Book header -->
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
          <span class="fw-bold font-monospace me-2" style="color:#0B2447">{{ book.book_number }}</span>
          <span class="text-muted small">· {{ book.account_number }}</span>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="text-center">
            <div style="font-size:.65rem;color:#94a3b8;text-transform:uppercase">Used</div>
            <div class="fw-bold small">{{ book.used_leaves }} / {{ book.total_leaves }}</div>
          </div>
          <!-- Mini progress -->
          <div class="progress" style="width:80px;height:6px;border-radius:3px">
            <div class="progress-bar" :class="book.status === 'exhausted' ? 'bg-danger' : 'bg-success'"
                 :style="{ width: Math.round((book.used_leaves / book.total_leaves) * 100) + '%' }"></div>
          </div>
          <span class="badge" :class="bookBadge(book.status)">{{ book.status }}</span>
        </div>
      </div>

      <!-- Cheques table -->
      <div class="card-body p-0">
        <div v-if="!book.cheques.length" class="text-center text-muted py-3 small">
          No cheques issued from this book.
        </div>
        <div v-else class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Cheque No.</th>
              <th>Payee</th>
              <th class="text-end">Amount</th>
              <th>Settlement</th>
              <th>Issue Date</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in book.cheques" :key="c.cheque_number">
              <td class="font-monospace small">{{ c.cheque_number }}</td>
              <td class="small">{{ c.payee_name || '—' }}</td>
              <td class="text-end small">{{ c.amount ? '$' + fmt(c.amount) : '—' }}</td>
              <td>
                <span v-if="c.settlement_type" class="badge"
                      :class="c.settlement_type === 'cash' ? 'bg-success' : 'bg-info'">
                  {{ c.settlement_type === 'cash' ? 'Cash' : 'Account' }}
                </span>
                <span v-else class="text-muted small">—</span>
              </td>
              <td class="small text-muted">{{ c.issue_date ? fmtDate(c.issue_date) : '—' }}</td>
              <td>
                <span class="badge" :class="chequeBadge(c.status)">{{ ucfirst(c.status) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue'

defineProps({ books: { type: Array, default: () => [] } })

const fmt     = (v) => Number(v ?? 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtDate = (d) => d ? new Date(d).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : ''
const ucfirst = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1).replace(/_/g, ' ') : ''
const bookBadge   = (s) => ({ active: 'bg-success', exhausted: 'bg-danger', cancelled: 'bg-secondary' }[s] ?? 'bg-secondary')
const chequeBadge = (s) => ({ issued: 'bg-primary', paid: 'bg-success', deposited: 'bg-info', bounced: 'bg-danger', cancelled: 'bg-secondary', pending_clearance: 'bg-warning text-dark' }[s] ?? 'bg-secondary')
</script>
