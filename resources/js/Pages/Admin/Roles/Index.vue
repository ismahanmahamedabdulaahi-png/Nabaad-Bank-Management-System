<template>
  <AdminLayout
    title="Roles & Permissions"
    subtitle="Define what each staff role can access and do"
    :breadcrumbs="[{ label: 'Roles & Permissions' }]"
  >
    <template #actions>
      <Link v-if="can('roles.manage')" :href="route('admin.roles.create')" class="btn btn-primary btn-sm">
        <i class="bi bi-shield-plus me-1"></i> Add Role
      </Link>
    </template>

    <div class="card shadow-sm">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Role</th>
                <th class="text-center">Staff Assigned</th>
                <th class="text-center">Permissions</th>
                <th class="pe-3 text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="role in roles" :key="role.id">
                <td class="ps-3">
                  <span class="fw-semibold">{{ role.name }}</span>
                  <span v-if="role.is_super_admin" class="badge bg-danger-subtle text-danger ms-2" style="font-size:.65rem">
                    Full Access
                  </span>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border">{{ role.users_count }}</span>
                </td>
                <td class="text-center">
                  <span class="badge bg-primary-subtle text-primary">
                    {{ role.is_super_admin ? total_permissions : role.permissions_count }} / {{ total_permissions }}
                  </span>
                </td>
                <td class="pe-3 text-end">
                  <div v-if="can('roles.manage')" class="btn-group btn-group-sm">
                    <Link :href="route('admin.roles.edit', role.id)" class="btn btn-outline-primary btn-sm" title="Edit">
                      <i class="bi" :class="role.is_super_admin ? 'bi-eye' : 'bi-pencil'"></i>
                    </Link>
                    <button
                      v-if="!role.is_super_admin"
                      class="btn btn-outline-danger btn-sm"
                      title="Delete"
                      :disabled="role.users_count > 0"
                      @click="confirmDelete(role)"
                    >
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <p class="text-muted small mt-3">
      <i class="bi bi-info-circle me-1"></i>
      The <strong>Super Admin</strong> role always has full access to every permission and cannot be edited or deleted.
      A role with staff still assigned to it cannot be deleted — reassign those staff first.
    </p>

    <!-- Delete Modal -->
    <ConfirmModal
      id="deleteRoleModal"
      title="Delete Role"
      :message="`Permanently delete the '${deleteTarget?.name}' role? This cannot be undone.`"
      variant="danger"
      icon="bi-trash"
      confirm-label="Delete"
      @confirmed="doDelete"
    />
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { usePermissions } from '@/composables/usePermissions';

const { can } = usePermissions();

defineProps({
  roles:             { type: Array,  default: () => [] },
  total_permissions: { type: Number, default: 0 },
});

const deleteTarget = ref(null);

const confirmDelete = (role) => {
  deleteTarget.value = role;
  new Modal(document.getElementById('deleteRoleModal')).show();
};

const doDelete = () => {
  router.delete(route('admin.roles.destroy', deleteTarget.value.id));
};
</script>
