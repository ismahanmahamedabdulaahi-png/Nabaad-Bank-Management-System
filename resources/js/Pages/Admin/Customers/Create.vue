<template>
  <AdminLayout
    title="Register Customer"
    subtitle="Create a new bank customer account"
    :breadcrumbs="[{ label: 'Customers', href: route('admin.customers.index') }, { label: 'Register Customer' }]"
  >
    <form @submit.prevent="submit">
      <div class="row g-4">

        <!-- ── Personal Information ─────────────────────────────────────── -->
        <div class="col-lg-8">
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-person me-1 text-primary"></i> Personal Information
            </div>
            <div class="card-body">
              <!-- Full Name -->
              <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': form.errors.name }" placeholder="Enter full name" />
                <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Date of Birth</label>
                  <input v-model="form.date_of_birth" type="date" class="form-control" :class="{ 'is-invalid': form.errors.date_of_birth }" :max="maxDob" />
                  <div v-if="form.errors.date_of_birth" class="invalid-feedback">{{ form.errors.date_of_birth }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Gender</label>
                  <select v-model="form.gender" class="form-select" :class="{ 'is-invalid': form.errors.gender }">
                    <option value="">— Select —</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                  <div v-if="form.errors.gender" class="invalid-feedback">{{ form.errors.gender }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Marital Status</label>
                  <select v-model="form.marital_status" class="form-select" :class="{ 'is-invalid': form.errors.marital_status }">
                    <option value="">— Select —</option>
                    <option value="single">Single</option>
                    <option value="married">Married</option>
                    <option value="divorced">Divorced</option>
                    <option value="widowed">Widowed</option>
                  </select>
                  <div v-if="form.errors.marital_status" class="invalid-feedback">{{ form.errors.marital_status }}</div>
                </div>
              </div>

              <div class="row g-3 mt-0">
                <div class="col-12">
                  <label class="form-label fw-semibold mb-2">Location</label>
                  <LocationSelect
                    v-model:country="form.nationality"
                    v-model:region="form.region"
                    v-model:city="form.city"
                    :required="false"
                    :show-labels="false"
                    :errors="{ country: form.errors.nationality, region: form.errors.region, city: form.errors.city }"
                  />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Occupation</label>
                  <input v-model="form.occupation" type="text" class="form-control" :class="{ 'is-invalid': form.errors.occupation }" placeholder="e.g. Business Owner" />
                  <div v-if="form.errors.occupation" class="invalid-feedback">{{ form.errors.occupation }}</div>
                </div>
              </div>

            </div>
          </div>

          <!-- ── Contact Information ──────────────────────────────────────── -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-telephone me-1 text-primary"></i> Contact Information
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                  <input v-model="form.phone" type="tel" class="form-control" :class="{ 'is-invalid': form.errors.phone }" placeholder="+252 61 XXXXXXX" />
                  <div v-if="form.errors.phone" class="invalid-feedback">{{ form.errors.phone }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                  <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" placeholder="customer@email.com" />
                  <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Address</label>
                  <textarea v-model="form.address" rows="2" class="form-control" :class="{ 'is-invalid': form.errors.address }" placeholder="Residential address…"></textarea>
                  <div v-if="form.errors.address" class="invalid-feedback">{{ form.errors.address }}</div>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Next of Kin ──────────────────────────────────────────────── -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-people me-1 text-primary"></i> Next of Kin
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Full Name</label>
                  <input v-model="form.next_of_kin_name" type="text" class="form-control" placeholder="Next of kin's full name" />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Phone Number</label>
                  <input v-model="form.next_of_kin_phone" type="tel" class="form-control" placeholder="+252 61 XXXXXXX" />
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Relationship</label>
                  <NextOfKinRelationship v-model="form.next_of_kin_relationship" :error="form.errors.next_of_kin_relationship" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Sidebar ─────────────────────────────────────────────────── -->
        <div class="col-lg-4">

          <!-- Photo & Signature -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-camera me-1 text-primary"></i> Photo & Signature
            </div>
            <div class="card-body">
              <!-- Customer Photo -->
              <div class="mb-4">
                <label class="form-label fw-semibold small">Customer Photo</label>
                <div
                  class="border rounded d-flex align-items-center justify-content-center mb-2 overflow-hidden bg-light"
                  style="height:140px;cursor:pointer"
                  @click="$refs.photoInput.click()"
                >
                  <img v-if="photoPreview" :src="photoPreview" class="h-100 w-100 object-fit-cover" alt="Preview" />
                  <div v-else class="text-center text-muted small p-2">
                    <i class="bi bi-person-bounding-box d-block mb-1" style="font-size:2rem"></i>
                    Click to upload photo
                  </div>
                </div>
                <input ref="photoInput" type="file" class="d-none" accept=".jpg,.jpeg,.png" @change="onPhoto" />
                <div v-if="form.errors.photo" class="text-danger small mt-1">{{ form.errors.photo }}</div>
                <div class="form-text">JPG or PNG · max 3 MB</div>
              </div>

              <!-- Signature -->
              <div>
                <label class="form-label fw-semibold small">Customer Signature</label>
                <div
                  class="border rounded d-flex align-items-center justify-content-center mb-2 overflow-hidden bg-light"
                  style="height:90px;cursor:pointer"
                  @click="$refs.signInput.click()"
                >
                  <img v-if="signPreview" :src="signPreview" class="h-100 w-100 object-fit-contain p-1" alt="Signature" />
                  <div v-else class="text-center text-muted small p-2">
                    <i class="bi bi-pen d-block mb-1" style="font-size:1.5rem"></i>
                    Click to upload signature
                  </div>
                </div>
                <input ref="signInput" type="file" class="d-none" accept=".jpg,.jpeg,.png" @change="onSign" />
                <div v-if="form.errors.signature" class="text-danger small mt-1">{{ form.errors.signature }}</div>
                <div class="form-text">JPG or PNG · max 2 MB</div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="card shadow-sm border-primary mb-4" style="border-color:#0B2447!important">
            <div class="card-header text-white" style="background:#0B2447">
              <i class="bi bi-info-circle me-1"></i> Registration Notes
            </div>
            <div class="card-body small">
              <ul class="ps-3 mb-0">
                <li class="mb-2">Customer will be created with <strong>Pending Approval</strong> status.</li>
                <li class="mb-2">A KYC record is auto-created — upload identity documents immediately after registration.</li>
                <li class="mb-2">Only <strong>KYC-approved</strong> customers can open bank accounts.</li>
                <li>An invitation email is sent — the customer confirms and receives a temporary password once they accept.</li>
              </ul>
            </div>
          </div>
          <div class="card shadow-sm">
            <div class="card-body">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-person-check me-1"></i>
                  Register Customer
                </button>
                <Link :href="route('admin.customers.index')" class="btn btn-light">Cancel</Link>
              </div>
            </div>
          </div>
        </div>

      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import LocationSelect from '@/Components/LocationSelect.vue';
import NextOfKinRelationship from '@/Components/NextOfKinRelationship.vue';

defineProps({
  errors: { type: Object, default: () => ({}) },
});

const today  = new Date();
const maxDob = `${today.getFullYear() - 18}-${String(today.getMonth() + 1).padStart(2,'0')}-${String(today.getDate()).padStart(2,'0')}`;

const photoPreview = ref(null);
const signPreview  = ref(null);

const form = useForm({
  name:                     '',
  date_of_birth:            '',
  gender:                   '',
  nationality:              '',
  region:                   '',
  occupation:               '',
  marital_status:           '',
  phone:                    '',
  email:                    '',
  address:                  '',
  city:                     '',
  next_of_kin_name:         '',
  next_of_kin_phone:        '',
  next_of_kin_relationship: '',
  photo:                    null,
  signature:                null,
});

const onPhoto = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.photo = file;
  photoPreview.value = URL.createObjectURL(file);
};

const onSign = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  form.signature = file;
  signPreview.value = URL.createObjectURL(file);
};

const submit = () => form.post(route('admin.customers.store'), { forceFormData: true });
</script>
