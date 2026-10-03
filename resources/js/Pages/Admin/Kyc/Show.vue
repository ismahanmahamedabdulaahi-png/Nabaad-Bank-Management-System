<template>
  <AdminLayout
    :title="`KYC — ${kyc.customer?.name}`"
    subtitle="Identity verification review"
    :breadcrumbs="[
      { label: 'KYC', href: route('admin.kyc.index') },
      { label: kyc.customer?.name }
    ]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <Link :href="route('admin.customers.show', kyc.customer_id)" class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-person me-1"></i> View Customer
        </Link>
        <button
          v-if="kyc.status !== 'pending' && $page.props.auth.permissions.includes('kyc.approve')"
          class="btn btn-outline-warning btn-sm"
          @click="confirmReopen"
        >
          <i class="bi bi-arrow-counterclockwise me-1"></i> Reopen
        </button>
      </div>
    </template>

    <div class="row g-4">
      <!-- Left: Customer info + Status card -->
      <div class="col-lg-4">
        <!-- KYC Status card -->
        <div class="card shadow-sm mb-4">
          <div class="card-body text-center p-4">
            <div class="mb-3">
              <span class="badge rounded-pill px-3 py-2 fs-6" :class="statusBadge(kyc.status)">
                <i :class="statusIcon(kyc.status)" class="me-1"></i>
                {{ statusLabel(kyc.status) }}
              </span>
            </div>

            <div v-if="kyc.status === 'approved'" class="alert alert-success py-2 text-start small mb-0">
              <div><strong>Approved by:</strong> {{ kyc.verified_by?.name }}</div>
              <div><strong>Date:</strong> {{ formatDateTime(kyc.verified_at) }}</div>
              <div v-if="kyc.notes"><strong>Notes:</strong> {{ kyc.notes }}</div>
            </div>

            <div v-else-if="kyc.status === 'rejected'" class="alert alert-danger py-2 text-start small mb-0">
              <div><strong>Rejected by:</strong> {{ kyc.verified_by?.name }}</div>
              <div><strong>Date:</strong> {{ formatDateTime(kyc.verified_at) }}</div>
              <div><strong>Reason:</strong> {{ kyc.rejection_reason }}</div>
              <div v-if="kyc.notes"><strong>Notes:</strong> {{ kyc.notes }}</div>
            </div>
          </div>
        </div>

        <!-- Customer info -->
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold small">
            <i class="bi bi-person me-1 text-primary"></i> Customer Details
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush small">
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Name</span>
                <span class="fw-semibold">{{ kyc.customer?.name }}</span>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Customer #</span>
                <span class="font-monospace">{{ kyc.customer?.customer_number }}</span>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Email</span>
                <span>{{ kyc.customer?.email }}</span>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Phone</span>
                <span>{{ kyc.customer?.phone }}</span>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Date of Birth</span>
                <span>{{ kyc.customer?.date_of_birth ? formatDate(kyc.customer.date_of_birth) : '—' }}</span>
              </div>
              <div class="list-group-item d-flex justify-content-between">
                <span class="text-muted">Occupation</span>
                <span>{{ kyc.customer?.occupation || '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Documents + Actions -->
      <div class="col-lg-8">
        <!-- Document list -->
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span class="fw-semibold"><i class="bi bi-files me-1 text-primary"></i> Uploaded Documents</span>
            <span class="badge bg-light text-dark border">{{ kyc.documents?.length ?? 0 }}</span>
          </div>
          <div class="card-body">
            <div v-if="!kyc.documents?.length" class="text-center text-muted py-4">
              <i class="bi bi-file-earmark-x fs-1 d-block mb-2 opacity-25"></i>
              No documents uploaded yet.
            </div>

            <div v-else>
              <div v-for="doc in kyc.documents" :key="doc.id" class="border rounded p-3 mb-3">
                <!-- Document header -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-person text-primary" style="font-size:1.25rem"></i>
                    <div>
                      <div class="fw-semibold small">{{ doc.type_label }}</div>
                      <div class="text-muted" style="font-size:.72rem">
                        <span v-if="doc.document_number">№ {{ doc.document_number }}</span>
                        <span v-if="doc.expiry_date"> · Expires: {{ formatDate(doc.expiry_date) }}</span>
                      </div>
                    </div>
                  </div>
                  <span class="badge" :class="doc.is_complete ? 'bg-success' : 'bg-warning text-dark'">
                    <i :class="doc.is_complete ? 'bi bi-check-circle' : 'bi bi-exclamation-circle'" class="me-1"></i>
                    {{ doc.is_complete ? 'Complete' : 'Incomplete' }}
                  </span>
                </div>

                <!-- Image thumbnails / links -->
                <div class="row g-2">
                  <div v-if="doc.view_url_front" class="col-auto">
                    <a :href="doc.view_url_front" target="_blank" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-image me-1"></i>
                      {{ doc.required_sides.includes('back') ? 'Front' : (doc.document_type === 'passport' ? 'Photo Page' : 'Document') }}
                    </a>
                  </div>
                  <div v-else-if="doc.required_sides.includes('front')" class="col-auto">
                    <span class="btn btn-outline-secondary btn-sm disabled opacity-50">
                      <i class="bi bi-image me-1"></i> Front — missing
                    </span>
                  </div>

                  <div v-if="doc.required_sides.includes('back')" class="col-auto">
                    <a v-if="doc.view_url_back" :href="doc.view_url_back" target="_blank" class="btn btn-outline-primary btn-sm">
                      <i class="bi bi-image me-1"></i> Back
                    </a>
                    <span v-else class="btn btn-outline-secondary btn-sm disabled opacity-50">
                      <i class="bi bi-image me-1"></i> Back — missing
                    </span>
                  </div>

                  <div v-if="doc.required_sides.includes('selfie')" class="col-auto">
                    <a v-if="doc.view_url_selfie" :href="doc.view_url_selfie" target="_blank" class="btn btn-outline-info btn-sm">
                      <i class="bi bi-person-bounding-box me-1"></i> Customer Photo
                    </a>
                    <span v-else class="btn btn-outline-secondary btn-sm disabled opacity-50">
                      <i class="bi bi-person-bounding-box me-1"></i> Photo — missing
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload document (only if pending) -->
        <div class="card shadow-sm mb-4" v-if="kyc.status === 'pending' && $page.props.auth.permissions.includes('kyc.view')">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-upload me-1 text-primary"></i> Upload Document
          </div>
          <div class="card-body">
            <form @submit.prevent="submitUpload">
              <div class="row g-3">
                <!-- Type + Number + Expiry -->
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Document Type <span class="text-danger">*</span></label>
                  <select v-model="uploadForm.document_type" class="form-select form-select-sm" :class="{ 'is-invalid': uploadErrors.document_type }" @change="resetFiles">
                    <option value="">— Select Type —</option>
                    <option value="national_id">National ID</option>
                    <option value="passport">Passport</option>
                    <option value="driving_license">Driving License</option>
                    <option value="state_id">State ID</option>
                  </select>
                  <div v-if="uploadErrors.document_type" class="invalid-feedback">{{ uploadErrors.document_type }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Document Number</label>
                  <input v-model="uploadForm.document_number" type="text" class="form-control form-control-sm" placeholder="e.g. A12345678" />
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold small">Expiry Date</label>
                  <input v-model="uploadForm.expiry_date" type="date" class="form-control form-control-sm" />
                </div>
              </div>

              <!-- Dynamic file fields based on document type -->
              <div v-if="uploadForm.document_type" class="mt-3">
                <div class="alert alert-info py-2 small mb-3">
                  <i class="bi bi-info-circle me-1"></i>
                  <strong>{{ docTypeLabel(uploadForm.document_type) }}</strong> requires:
                  <span v-for="(side, i) in requiredSides(uploadForm.document_type)" :key="side">
                    {{ sideLabel(side) }}<span v-if="i < requiredSides(uploadForm.document_type).length - 1">, </span>
                  </span>
                </div>

                <div class="row g-3">
                  <!-- Front image (all types) -->
                  <div class="col-md-4">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-image me-1"></i>
                      {{ uploadForm.document_type === 'passport' ? 'Photo Page' : 'Front Image' }}
                      <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control form-control-sm" :class="{ 'is-invalid': uploadErrors.file_front }" accept=".jpg,.jpeg,.png,.pdf" @change="e => onFile(e, 'file_front')" />
                    <div v-if="uploadErrors.file_front" class="invalid-feedback">{{ uploadErrors.file_front }}</div>
                    <div class="form-text">JPG, PNG, or PDF · max 5 MB</div>
                  </div>

                  <!-- Back image (national_id, driving_license, state_id) -->
                  <div v-if="requiredSides(uploadForm.document_type).includes('back')" class="col-md-4">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-image me-1"></i> Back Image <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control form-control-sm" :class="{ 'is-invalid': uploadErrors.file_back }" accept=".jpg,.jpeg,.png,.pdf" @change="e => onFile(e, 'file_back')" />
                    <div v-if="uploadErrors.file_back" class="invalid-feedback">{{ uploadErrors.file_back }}</div>
                    <div class="form-text">JPG, PNG, or PDF · max 5 MB</div>
                  </div>

                  <!-- Selfie (state_id only) -->
                  <div v-if="requiredSides(uploadForm.document_type).includes('selfie')" class="col-md-4">
                    <label class="form-label fw-semibold small">
                      <i class="bi bi-person-bounding-box me-1"></i> Customer Photo <span class="text-danger">*</span>
                    </label>
                    <input type="file" class="form-control form-control-sm" :class="{ 'is-invalid': uploadErrors.file_selfie }" accept=".jpg,.jpeg,.png" @change="e => onFile(e, 'file_selfie')" />
                    <div v-if="uploadErrors.file_selfie" class="invalid-feedback">{{ uploadErrors.file_selfie }}</div>
                    <div class="form-text">JPG or PNG only · max 5 MB</div>
                  </div>
                </div>

                <div class="mt-3">
                  <button type="submit" class="btn btn-primary btn-sm" :disabled="uploadForm.processing">
                    <span v-if="uploadForm.processing" class="spinner-border spinner-border-sm me-1"></span>
                    <i v-else class="bi bi-cloud-upload me-1"></i>
                    Upload Document
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>

        <!-- Review action (only if pending) -->
        <div class="card shadow-sm border-primary" v-if="kyc.status === 'pending'">
          <div class="card-header text-white" style="background:#0B2447">
            <i class="bi bi-clipboard2-check me-1"></i> Review Decision
          </div>
          <div class="card-body">
            <form @submit.prevent="submitReview">
              <!-- Notes -->
              <div class="mb-3">
                <label class="form-label fw-semibold small">Notes (Optional)</label>
                <textarea v-model="reviewForm.notes" rows="2" class="form-control form-control-sm" placeholder="Internal notes visible only to staff…"></textarea>
              </div>

              <!-- Rejection reason (shown when Reject selected) -->
              <div class="mb-3" v-if="reviewForm.action === 'reject'">
                <label class="form-label fw-semibold small">Rejection Reason <span class="text-danger">*</span></label>
                <textarea
                  v-model="reviewForm.rejection_reason"
                  rows="2"
                  class="form-control form-control-sm"
                  :class="{ 'is-invalid': reviewErrors.rejection_reason }"
                  placeholder="Explain why this KYC is being rejected…"
                ></textarea>
                <div v-if="reviewErrors.rejection_reason" class="invalid-feedback">{{ reviewErrors.rejection_reason }}</div>
              </div>

              <div class="d-flex gap-2">
                <button
                  v-if="$page.props.auth.permissions.includes('kyc.approve')"
                  type="button"
                  class="btn btn-success btn-sm"
                  :disabled="reviewForm.processing"
                  @click="reviewForm.action = 'approve'; submitReview()"
                >
                  <i class="bi bi-check-circle me-1"></i> Approve KYC
                </button>
                <button
                  v-if="$page.props.auth.permissions.includes('kyc.reject')"
                  type="button"
                  class="btn btn-danger btn-sm"
                  :disabled="reviewForm.processing"
                  @click="reviewForm.action = 'reject'; reviewForm.action === 'reject' && submitReview()"
                >
                  <i class="bi bi-x-circle me-1"></i>
                  {{ reviewForm.action === 'reject' ? 'Confirm Rejection' : 'Reject KYC' }}
                </button>
                <button
                  v-if="reviewForm.action === 'reject'"
                  type="button"
                  class="btn btn-light btn-sm"
                  @click="reviewForm.action = null"
                >Cancel</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Reopen modal -->
    <ConfirmModal
      id="kycReopenModal"
      title="Reopen KYC"
      :message="`Reset ${kyc.customer?.name}'s KYC back to Pending? The previous decision will be cleared.`"
      variant="warning"
      icon="bi-arrow-counterclockwise"
      confirm-label="Reopen"
      @confirmed="doReopen"
    />
  </AdminLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
  kyc: { type: Object, required: true },
});

// ── Upload form ──────────────────────────────────────────────────────────────
const uploadForm   = useForm({ document_type: '', document_number: '', expiry_date: '', file_front: null, file_back: null, file_selfie: null });
const uploadErrors = computed(() => uploadForm.errors);

const onFile = (e, field) => { uploadForm[field] = e.target.files[0] ?? null; };
const resetFiles = () => { uploadForm.file_front = null; uploadForm.file_back = null; uploadForm.file_selfie = null; };

const submitUpload = () => {
  uploadForm.post(route('admin.kyc.upload-document', props.kyc.id), {
    forceFormData: true,
    onSuccess: () => uploadForm.reset(),
  });
};

// ── Document type requirements (mirrors KycDocument::requiredSides) ───────────
const SIDES = {
  national_id:     ['front', 'back', 'selfie'],
  passport:        ['front', 'selfie'],
  driving_license: ['front', 'back', 'selfie'],
  state_id:        ['front', 'back', 'selfie'],
};
const requiredSides  = (type) => SIDES[type] ?? ['front', 'selfie'];
const docTypeLabel   = (type) => ({ national_id: 'National ID', passport: 'Passport', driving_license: 'Driving License', state_id: 'State ID' }[type] ?? type);
const sideLabel      = (side) => ({ front: 'Front image', back: 'Back image', selfie: 'Customer photo' }[side] ?? side);

// ── Review form ──────────────────────────────────────────────────────────────
const reviewForm   = useForm({ action: null, notes: '', rejection_reason: '' });
const reviewErrors = computed(() => reviewForm.errors);

const submitReview = () => {
  if (!reviewForm.action) return;
  if (reviewForm.action === 'reject' && !reviewForm.rejection_reason) return;
  reviewForm.post(route('admin.kyc.review', props.kyc.id));
};

// ── Reopen ───────────────────────────────────────────────────────────────────
const confirmReopen = () => new Modal(document.getElementById('kycReopenModal')).show();
const doReopen      = () => router.post(route('admin.kyc.reopen', props.kyc.id));

// ── Helpers ──────────────────────────────────────────────────────────────────
const statusBadge = (s) => ({ pending: 'bg-warning text-dark', approved: 'bg-success text-white', rejected: 'bg-danger text-white' }[s] ?? 'bg-secondary text-white');
const statusLabel = (s) => ({ pending: 'Pending Review', approved: 'Approved', rejected: 'Rejected' }[s] ?? s);
const statusIcon  = (s) => ({ pending: 'bi bi-hourglass-split', approved: 'bi bi-shield-check', rejected: 'bi bi-shield-x' }[s] ?? 'bi bi-circle');

const formatDate     = (d) => new Date(d).toLocaleDateString('en-US',  { year: 'numeric', month: 'short', day: 'numeric' });
const formatDateTime = (d) => new Date(d).toLocaleString('en-US',      { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
</script>
