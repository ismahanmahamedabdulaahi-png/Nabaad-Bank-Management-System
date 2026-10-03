<template>
  <AdminLayout
    :title="`Edit — ${customer.name}`"
    subtitle="Update customer details"
    :breadcrumbs="[
      { label: 'Customers', href: route('admin.customers.index') },
      { label: customer.name, href: route('admin.customers.show', customer.id) },
      { label: 'Edit' }
    ]"
  >
    <form @submit.prevent="submit">
      <div class="row g-4">

        <!-- ── Main columns ─────────────────────────────────────────────── -->
        <div class="col-lg-8">

          <!-- Personal Information -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-person me-1 text-primary"></i> Personal Information
            </div>
            <div class="card-body">
              <div class="mb-3">
                <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                <input v-model="form.name" type="text" class="form-control" :class="{ 'is-invalid': form.errors.name }" />
                <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
              </div>

              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Date of Birth</label>
                  <input v-model="form.date_of_birth" type="date" class="form-control" :class="{ 'is-invalid': form.errors.date_of_birth }" />
                  <div v-if="form.errors.date_of_birth" class="invalid-feedback">{{ form.errors.date_of_birth }}</div>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Gender</label>
                  <select v-model="form.gender" class="form-select">
                    <option value="">— Select —</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-semibold">Marital Status</label>
                  <select v-model="form.marital_status" class="form-select">
                    <option value="">— Select —</option>
                    <option value="single">Single</option>
                    <option value="married">Married</option>
                    <option value="divorced">Divorced</option>
                    <option value="widowed">Widowed</option>
                  </select>
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
                  <input v-model="form.occupation" type="text" class="form-control" />
                </div>
              </div>

            </div>
          </div>

          <!-- Contact Information -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-telephone me-1 text-primary"></i> Contact Information
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Mobile Number <span class="text-danger">*</span></label>
                  <input v-model="form.phone" type="tel" class="form-control" :class="{ 'is-invalid': form.errors.phone }" />
                  <div v-if="form.errors.phone" class="invalid-feedback">{{ form.errors.phone }}</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                  <input v-model="form.email" type="email" class="form-control" :class="{ 'is-invalid': form.errors.email }" />
                  <div v-if="form.errors.email" class="invalid-feedback">{{ form.errors.email }}</div>
                </div>
                <div class="col-12">
                  <label class="form-label fw-semibold">Address</label>
                  <textarea v-model="form.address" rows="2" class="form-control"></textarea>
                </div>
              </div>
            </div>
          </div>

          <!-- Next of Kin -->
          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-people me-1 text-primary"></i> Next of Kin
            </div>
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Full Name</label>
                  <input v-model="form.next_of_kin_name" type="text" class="form-control" />
                </div>
                <div class="col-md-6">
                  <label class="form-label fw-semibold">Phone Number</label>
                  <input v-model="form.next_of_kin_phone" type="tel" class="form-control" />
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
                  <img v-if="photoPreview" :src="photoPreview" class="h-100 w-100 object-fit-cover" alt="Photo" />
                  <div v-else class="text-center text-muted small p-2">
                    <i class="bi bi-person-bounding-box d-block mb-1" style="font-size:2rem"></i>
                    Click to replace photo
                  </div>
                </div>
                <input ref="photoInput" type="file" class="d-none" accept=".jpg,.jpeg,.png" @change="onPhoto" />
                <div v-if="form.errors.photo" class="text-danger small mt-1">{{ form.errors.photo }}</div>
                <div class="form-text">Leave empty to keep current photo</div>
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
                    Click to replace signature
                  </div>
                </div>
                <input ref="signInput" type="file" class="d-none" accept=".jpg,.jpeg,.png" @change="onSign" />
                <div v-if="form.errors.signature" class="text-danger small mt-1">{{ form.errors.signature }}</div>
                <div class="form-text">Leave empty to keep current signature</div>
              </div>
            </div>
          </div>

          <div class="card shadow-sm mb-4">
            <div class="card-header bg-white fw-semibold">
              <i class="bi bi-toggle-on me-1 text-primary"></i> Account Status
            </div>
            <div class="card-body">
              <label class="form-label fw-semibold">Status</label>
              <select v-model="form.status" class="form-select" :class="{ 'is-invalid': form.errors.status }">
                <option value="pending">Pending Approval</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="blacklisted">Blacklisted</option>
                <option value="deceased">Deceased</option>
              </select>
              <div v-if="form.errors.status" class="invalid-feedback">{{ form.errors.status }}</div>
              <div class="form-text mt-2">
                <i class="bi bi-exclamation-triangle text-warning me-1"></i>
                Normally changed via KYC approval — only override if necessary.
              </div>
            </div>
          </div>

          <div class="card shadow-sm">
            <div class="card-body">
              <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary" :disabled="form.processing">
                  <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
                  <i v-else class="bi bi-floppy me-1"></i>
                  Save Changes
                </button>
                <Link :href="route('admin.customers.show', customer.id)" class="btn btn-light">Cancel</Link>
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

const props = defineProps({
  customer: { type: Object, required: true },
  errors:   { type: Object, default: () => ({}) },
});

// Initialise previews from existing server images
const photoPreview = ref(props.customer.photo_path ? route('admin.customers.photo', props.customer.id) : null);
const signPreview  = ref(props.customer.signature_path ? route('admin.customers.signature', props.customer.id) : null);

const form = useForm({
  name:                     props.customer.name,
  date_of_birth:            props.customer.date_of_birth ?? '',
  gender:                   props.customer.gender ?? '',
  nationality:              props.customer.nationality ?? '',
  region:                   props.customer.region ?? '',
  occupation:               props.customer.occupation ?? '',
  marital_status:           props.customer.marital_status ?? '',
  phone:                    props.customer.phone,
  email:                    props.customer.email,
  address:                  props.customer.address ?? '',
  city:                     props.customer.city ?? '',
  next_of_kin_name:         props.customer.next_of_kin_name ?? '',
  next_of_kin_phone:        props.customer.next_of_kin_phone ?? '',
  next_of_kin_relationship: props.customer.next_of_kin_relationship ?? '',
  status:                   props.customer.status,
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

// PHP never parses a multipart body on a real PUT/PATCH request (only on POST),
// so a literal form.put() with forceFormData silently loses every field. Send it
// as POST with a _method override instead — the long-standing Inertia/Laravel pattern.
const submit = () => {
  form.transform((data) => ({ ...data, _method: 'put' }))
      .post(route('admin.customers.update', props.customer.id), { forceFormData: true });
};
</script>
