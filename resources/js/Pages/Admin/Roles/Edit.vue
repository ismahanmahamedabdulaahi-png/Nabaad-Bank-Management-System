<template>
  <AdminLayout
    :title="`Edit — ${role.name}`"
    subtitle="Update role name and permissions"
    :breadcrumbs="[
      { label: 'Roles & Permissions', href: route('admin.roles.index') },
      { label: role.name, href: route('admin.roles.edit', role.id) },
    ]"
  >
    <div v-if="role.is_super_admin" class="alert alert-warning d-flex align-items-center gap-2 mb-4">
      <i class="bi bi-shield-fill-exclamation fs-5"></i>
      <div>
        <strong>Super Admin</strong> always has full access to every permission and cannot be edited.
        This page is read-only for this role.
      </div>
    </div>

    <form @submit.prevent="submit">
      <div class="card shadow-sm mb-4">
        <div class="card-body">
          <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
          <input v-model="form.name" type="text" class="form-control" style="max-width:360px"
                 :class="{ 'is-invalid': form.errors.name }" :disabled="role.is_super_admin" />
          <div v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</div>
          <div class="form-text">{{ role.users_count }} staff member(s) currently assigned to this role.</div>
        </div>
      </div>

      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-shield-lock me-1 text-primary"></i>Permissions</span>
          <span class="text-muted small">{{ form.permissions.length }} selected</span>
        </div>
        <div class="card-body">
          <fieldset :disabled="role.is_super_admin">
            <div class="row g-4">
              <div v-for="(perms, group) in permission_groups" :key="group" class="col-md-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <span class="fw-semibold small text-uppercase" style="letter-spacing:.5px;color:var(--nabaad-muted)">
                    {{ groupLabel(group) }}
                  </span>
                  <button type="button" class="btn btn-link btn-sm p-0" @click="toggleGroup(perms)">
                    {{ allChecked(perms) ? 'Clear' : 'All' }}
                  </button>
                </div>
                <div v-for="p in perms" :key="p" class="form-check">
                  <input :id="p" type="checkbox" class="form-check-input" :value="p" v-model="form.permissions" />
                  <label :for="p" class="form-check-label small">{{ permLabel(p) }}</label>
                </div>
              </div>
            </div>
          </fieldset>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button v-if="!role.is_super_admin" type="submit" class="btn btn-primary px-4" :disabled="form.processing">
          <span v-if="form.processing" class="spinner-border spinner-border-sm me-1"></span>
          <i v-else class="bi bi-floppy me-1"></i>Save Changes
        </button>
        <Link :href="route('admin.roles.index')" class="btn btn-light">{{ role.is_super_admin ? 'Back' : 'Cancel' }}</Link>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  role:               { type: Object, required: true },
  permission_groups:  { type: Object, default: () => ({}) },
});

const form = useForm({
  name:        props.role.name,
  permissions: [...props.role.permissions],
});

const groupLabel = (g) => g.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
const permLabel  = (p) => p.split('.')[1]?.replace(/-/g, ' ').replace(/\b\w/g, c => c.toUpperCase()) ?? p;

const allChecked = (perms) => perms.every(p => form.permissions.includes(p));

const toggleGroup = (perms) => {
  if (allChecked(perms)) {
    form.permissions = form.permissions.filter(p => !perms.includes(p));
  } else {
    form.permissions = [...new Set([...form.permissions, ...perms])];
  }
};

const submit = () => form.put(route('admin.roles.update', props.role.id));
</script>
