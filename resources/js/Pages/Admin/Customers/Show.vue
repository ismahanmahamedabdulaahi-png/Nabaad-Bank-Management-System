<template>
  <AdminLayout
    :title="customer.name"
    subtitle="Customer profile"
    :breadcrumbs="[{ label: 'Customers', href: route('admin.customers.index') }, { label: customer.name }]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <Link
          v-if="$page.props.auth.permissions.includes('customers.edit')"
          :href="route('admin.customers.edit', customer.id)"
          class="btn btn-primary btn-sm"
        >
          <i class="bi bi-pencil me-1"></i> Edit
        </Link>
        <button
          v-if="$page.props.auth.permissions.includes('customers.edit') && canToggle"
          class="btn btn-sm"
          :class="customer.status === 'active' ? 'btn-warning' : 'btn-success'"
          @click="confirmToggle"
        >
          <i :class="customer.status === 'active' ? 'bi bi-pause-circle me-1' : 'bi bi-play-circle me-1'"></i>
          {{ customer.status === 'active' ? 'Deactivate' : 'Activate' }}
        </button>
        <button
          v-if="$page.props.auth.permissions.includes('customers.edit')"
          class="btn btn-outline-secondary btn-sm"
          @click="confirmReset"
        >
          <i class="bi bi-key me-1"></i> Reset Password
        </button>
        <Link
          v-if="$page.props.auth.permissions.includes('kyc.view')"
          :href="customer.kyc ? route('admin.kyc.show', customer.kyc.id) : '#'"
          :class="customer.kyc ? 'btn btn-outline-info btn-sm' : 'btn btn-info btn-sm'"
          @click.prevent="!customer.kyc && initiateKyc()"
        >
          <i class="bi bi-shield-check me-1"></i>
          {{ customer.kyc ? 'View KYC' : 'Initiate KYC' }}
        </Link>
        <button
          v-if="$page.props.auth.permissions.includes('compliance.manage')"
          class="btn btn-outline-danger btn-sm"
          data-bs-toggle="modal" data-bs-target="#custFlagModal"
        >
          <i class="bi bi-shield-exclamation me-1"></i>Flag for Compliance
        </button>
      </div>
    </template>

    <div class="row g-4">
      <!-- Profile card -->
      <div class="col-lg-4">
        <div class="card shadow-sm text-center p-4">
          <!-- Profile photo or initials fallback -->
          <div class="mx-auto mb-3 overflow-hidden rounded-circle" style="width:80px;height:80px">
            <img
              v-if="customer.photo_path"
              :src="route('admin.customers.photo', customer.id)"
              class="w-100 h-100 object-fit-cover"
              :alt="customer.name"
            />
            <div
              v-else
              class="w-100 h-100 d-flex align-items-center justify-content-center fw-bold text-white"
              :style="`background:${avatarColor(customer.name)};font-size:1.5rem`"
            >
              {{ initials(customer.name) }}
            </div>
          </div>
          <h5 class="fw-bold mb-1">{{ customer.name }}</h5>
          <p class="text-muted small mb-1">{{ customer.email }}</p>
          <p class="text-muted small mb-3">
            <span class="font-monospace">{{ customer.customer_number }}</span>
          </p>
          <div class="mb-3"><StatusBadge :status="customer.status" /></div>

          <!-- KYC badge -->
          <div class="mb-3">
            <span v-if="customer.kyc" class="badge px-3 py-2" :class="kycBadge(customer.kyc.status)" style="font-size:.8rem">
              <i class="bi bi-shield-check me-1"></i>
              KYC {{ kycLabel(customer.kyc.status) }}
            </span>
            <span v-else class="badge bg-light text-muted border px-3 py-2" style="font-size:.8rem">
              <i class="bi bi-shield-x me-1"></i>KYC Not Submitted
            </span>
          </div>

          <hr />
          <div class="text-start small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Phone</span>
              <span class="fw-semibold">{{ customer.phone }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Date of Birth</span>
              <span class="fw-semibold">{{ customer.date_of_birth ? formatDate(customer.date_of_birth) : '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Gender</span>
              <span class="fw-semibold text-capitalize">{{ customer.gender || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Marital Status</span>
              <span class="fw-semibold text-capitalize">{{ customer.marital_status || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Country</span>
              <span class="fw-semibold">{{ customer.nationality || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Occupation</span>
              <span class="fw-semibold">{{ customer.occupation || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Region</span>
              <span class="fw-semibold">{{ customer.region || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">City</span>
              <span class="fw-semibold">{{ customer.city || '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Last Login</span>
              <span class="fw-semibold">{{ customer.last_login_at ? formatDate(customer.last_login_at) : 'Never' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Joined</span>
              <span class="fw-semibold">{{ formatDate(customer.created_at) }}</span>
            </div>
          </div>

          <div v-if="customer.address || customer.city" class="mt-2 text-start">
            <hr />
            <p class="text-muted small mb-0">
              <i class="bi bi-geo-alt me-1"></i>
              {{ [customer.address, customer.city, customer.region, customer.nationality].filter(Boolean).join(', ') }}
            </p>
          </div>

          <!-- Next of Kin -->
          <div v-if="customer.next_of_kin_name" class="mt-2 text-start">
            <hr />
            <div class="small">
              <div class="text-muted fw-semibold mb-1"><i class="bi bi-people me-1"></i>Next of Kin</div>
              <div class="fw-semibold">{{ customer.next_of_kin_name }}</div>
              <div class="text-muted">{{ customer.next_of_kin_relationship }} · {{ customer.next_of_kin_phone }}</div>
            </div>
          </div>

          <!-- Signature -->
          <div v-if="customer.signature_path" class="mt-2 text-start">
            <hr />
            <div class="text-muted fw-semibold small mb-2"><i class="bi bi-pen me-1"></i>Signature</div>
            <div class="border rounded bg-white p-1" style="height:60px">
              <img
                :src="route('admin.customers.signature', customer.id)"
                class="h-100 w-100 object-fit-contain"
                alt="Signature"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div class="col-lg-8">
        <!-- Accounts table -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-wallet2 me-1 text-primary"></i>Accounts</span>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border">{{ customer.accounts?.length ?? 0 }}</span>
              <Link
                v-if="$page.props.auth.permissions.includes('accounts.create') && customer.status === 'active'"
                :href="route('admin.accounts.create', { customer_id: customer.id })"
                class="btn btn-sm btn-outline-primary"
              >
                <i class="bi bi-plus-lg me-1"></i> Open Account
              </Link>
            </div>
          </div>
          <div class="card-body p-0">
            <div v-if="!customer.accounts?.length" class="text-center text-muted py-4 small">
              No accounts opened yet.
            </div>
            <div class="table-responsive" v-else>
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Account #</th>
                    <th>Type</th>
                    <th>Balance</th>
                    <th>Status</th>
                    <th>Opened</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="acc in customer.accounts" :key="acc.id">
                    <td class="ps-3 font-monospace small">
                      <Link :href="route('admin.accounts.show', acc.id)" class="text-decoration-none">{{ acc.account_number }}</Link>
                    </td>
                    <td class="small text-capitalize">{{ acc.account_type?.replace('_', ' ') }}</td>
                    <td class="small fw-semibold">{{ formatCurrency(acc.balance) }}</td>
                    <td><StatusBadge :status="acc.status" /></td>
                    <td class="small text-muted">{{ formatDate(acc.created_at) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Loans summary -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white d-flex align-items-center justify-content-between">
            <span class="fw-semibold"><i class="bi bi-cash-coin me-1 text-primary"></i>Loans</span>
            <span class="badge bg-light text-dark border">{{ customer.loans?.length ?? 0 }}</span>
          </div>
          <div class="card-body p-0">
            <div v-if="!customer.loans?.length" class="text-center text-muted py-4 small">
              No loans on record.
            </div>
            <div class="table-responsive" v-else>
              <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-3">Loan #</th>
                    <th>Amount</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="loan in customer.loans" :key="loan.id">
                    <td class="ps-3 font-monospace small">{{ loan.loan_number }}</td>
                    <td class="small">{{ formatCurrency(loan.amount) }}</td>
                    <td class="small fw-semibold">{{ formatCurrency(loan.outstanding_balance) }}</td>
                    <td><StatusBadge :status="loan.status" /></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent activity -->
        <div class="card shadow-sm" v-if="recentAuditLogs?.length">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-clock-history me-1 text-primary"></i>Recent Activity (Portal)
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush">
              <div v-for="log in recentAuditLogs" :key="log.id" class="list-group-item py-2 px-3">
                <div class="d-flex align-items-start gap-2">
                  <i class="bi bi-dot text-primary mt-1 flex-shrink-0" style="font-size:1.5rem"></i>
                  <div>
                    <div class="small fw-semibold">{{ log.description }}</div>
                    <div class="text-muted" style="font-size:.72rem">
                      {{ log.module }} · {{ formatDateTime(log.created_at) }} · {{ log.ip_address }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Toggle Modal -->
    <ConfirmModal
      id="custShowToggleModal"
      :title="customer.status === 'active' ? 'Deactivate Customer' : 'Activate Customer'"
      :message="customer.status === 'active'
        ? `Deactivate ${customer.name}? They will lose portal access.`
        : `Activate ${customer.name}? They will regain portal access.`"
      :variant="customer.status === 'active' ? 'warning' : 'success'"
      :icon="customer.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"
      :confirm-label="customer.status === 'active' ? 'Deactivate' : 'Activate'"
      @confirmed="doToggle"
    />

    <!-- Reset Password Modal -->
    <ConfirmModal
      id="custShowResetModal"
      title="Reset Customer Password"
      :message="`Generate a new temporary password for ${customer.name}?`"
      variant="secondary"
      icon="bi-key"
      confirm-label="Reset Password"
      @confirmed="doReset"
    />

    <!-- Flag for Compliance Modal -->
    <Teleport to="body">
      <div class="modal fade" id="custFlagModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header border-0 pb-0">
              <h6 class="modal-title fw-bold"><i class="bi bi-shield-exclamation me-2 text-danger"></i>Flag for Compliance</h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form @submit.prevent="submitFlag">
              <div class="modal-body">
                <p class="text-muted small">
                  Opens an internal compliance case for <strong>{{ customer.name }}</strong>. The customer is never notified.
                </p>
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Severity</label>
                  <select v-model="flagForm.severity" class="form-select form-select-sm">
                    <option value="low">Low</option>
                    <option value="medium">Medium</option>
                    <option value="high">High</option>
                  </select>
                </div>
                <div class="mb-3">
                  <label class="form-label fw-semibold small">Notes <span class="text-danger">*</span></label>
                  <textarea v-model="flagForm.notes" class="form-control form-control-sm" rows="3"
                            :class="flagForm.errors.notes ? 'is-invalid' : ''" required></textarea>
                  <div v-if="flagForm.errors.notes" class="invalid-feedback">{{ flagForm.errors.notes }}</div>
                </div>
              </div>
              <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" :disabled="flagForm.processing">
                  <span v-if="flagForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                  Flag for Review
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Teleport>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import StatusBadge  from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
  customer:         { type: Object, required: true },
  recentAuditLogs:  { type: Array,  default: () => [] },
});

const canToggle = computed(() => !['deceased', 'blacklisted', 'pending'].includes(props.customer.status));

const confirmToggle = () => new Modal(document.getElementById('custShowToggleModal')).show();
const confirmReset  = () => new Modal(document.getElementById('custShowResetModal')).show();

const doToggle = () => router.post(route('admin.customers.toggle-status', props.customer.id));
const doReset  = () => router.post(route('admin.customers.reset-password', props.customer.id));

const flagForm = useForm({ customer_id: props.customer.id, severity: 'medium', notes: '' });
const submitFlag = () => {
  flagForm.post(route('admin.compliance.flag'), {
    onSuccess: () => { window.bootstrap?.Modal.getInstance(document.getElementById('custFlagModal'))?.hide(); flagForm.reset('notes'); },
  });
};

const initiateKyc = () => router.post(route('admin.kyc.initiate', props.customer.id));

const initials    = (n) => n?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?';
const colors      = ['#0B2447','#14395B','#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6'];
const avatarColor = (n) => colors[n?.charCodeAt(0) % colors.length] ?? '#0B2447';

const formatCurrency = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const formatDate     = (d) => new Date(d).toLocaleDateString('en-US',  { year:'numeric', month:'short', day:'numeric' });
const formatDateTime = (d) => new Date(d).toLocaleString('en-US',      { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });

const kycBadge = (s) => ({ pending: 'bg-warning text-dark', approved: 'bg-success text-white', rejected: 'bg-danger text-white' }[s] ?? 'bg-light border text-muted');
const kycLabel = (s) => ({ pending: 'Pending', approved: 'Verified', rejected: 'Rejected' }[s] ?? s);
</script>
