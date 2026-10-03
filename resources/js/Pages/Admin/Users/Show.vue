<template>
  <AdminLayout
    :title="user.name"
    subtitle="Staff account profile"
    :breadcrumbs="[{ label: 'Staff Management', href: route('admin.users.index') }, { label: user.name }]"
  >
    <template #actions>
      <div class="d-flex gap-2">
        <Link
          v-if="$page.props.auth.permissions.includes('users.edit')"
          :href="route('admin.users.edit', user.id)"
          class="btn btn-primary btn-sm"
        >
          <i class="bi bi-pencil me-1"></i> Edit
        </Link>
        <button
          v-if="$page.props.auth.permissions.includes('users.edit') && user.id !== $page.props.auth.user.id"
          class="btn btn-sm"
          :class="user.status === 'active' ? 'btn-warning' : 'btn-success'"
          @click="confirmToggle"
        >
          <i :class="user.status === 'active' ? 'bi bi-pause-circle me-1' : 'bi bi-play-circle me-1'"></i>
          {{ user.status === 'active' ? 'Suspend' : 'Activate' }}
        </button>
        <button
          v-if="$page.props.auth.permissions.includes('users.edit')"
          class="btn btn-outline-secondary btn-sm"
          @click="confirmReset"
        >
          <i class="bi bi-key me-1"></i> Reset Password
        </button>
      </div>
    </template>

    <div class="row g-4">
      <!-- Profile Card -->
      <div class="col-lg-4">
        <div class="card shadow-sm text-center p-4">
          <div
            class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white mx-auto mb-3"
            :style="`width:80px;height:80px;background:${avatarColor(user.name)};font-size:1.5rem`"
          >
            {{ initials(user.name) }}
          </div>
          <h5 class="fw-bold mb-1">{{ user.name }}</h5>
          <p class="text-muted small mb-1 font-monospace">{{ user.staff_id }}</p>
          <p class="text-muted small mb-2">{{ user.email }}</p>
          <div class="mb-3"><StatusBadge :status="user.status" /></div>
          <div class="badge bg-light text-dark border px-3 py-2 mb-3">
            <i class="bi bi-shield-check me-1 text-primary"></i>
            {{ user.roles?.[0]?.name ?? 'No Role' }}
          </div>
          <hr />
          <div class="text-start small">
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Phone</span>
              <span class="fw-semibold">{{ user.phone ?? '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Txn Limit</span>
              <span class="fw-semibold">{{ formatCurrency(user.transaction_limit) }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Last Login</span>
              <span class="fw-semibold">{{ user.last_login_at ? formatDate(user.last_login_at) : 'Never' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1 border-bottom">
              <span class="text-muted">Last IP</span>
              <span class="fw-semibold">{{ user.last_login_ip ?? '—' }}</span>
            </div>
            <div class="d-flex justify-content-between py-1">
              <span class="text-muted">Joined</span>
              <span class="fw-semibold">{{ formatDate(user.created_at) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Permissions Card -->
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-shield-lock me-1 text-primary"></i>
            Role Permissions — {{ user.roles?.[0]?.name ?? 'No Role' }}
          </div>
          <div class="card-body">
            <div v-if="!user.permissions?.length" class="text-center text-muted py-4">
              <i class="bi bi-shield-x fs-1 d-block mb-2 opacity-25"></i>
              No permissions assigned.
            </div>
            <div v-else class="row g-2">
              <div
                v-for="permission in user.permissions"
                :key="permission"
                class="col-md-4 col-sm-6"
              >
                <div class="d-flex align-items-center gap-2 p-2 rounded border bg-light small">
                  <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                  <span>{{ formatPermission(permission) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Recent Audit Logs -->
        <div class="card shadow-sm mt-4" v-if="recentAuditLogs?.length">
          <div class="card-header bg-white fw-semibold">
            <i class="bi bi-clock-history me-1 text-primary"></i>
            Recent Activity
          </div>
          <div class="card-body p-0">
            <div class="list-group list-group-flush">
              <div
                v-for="log in recentAuditLogs"
                :key="log.id"
                class="list-group-item py-2 px-3"
              >
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
      id="showToggleModal"
      :title="user.status === 'active' ? 'Suspend User' : 'Activate User'"
      :message="user.status === 'active'
        ? `Suspend ${user.name}? They will no longer be able to log in.`
        : `Activate ${user.name}? They will be able to log in again.`"
      :variant="user.status === 'active' ? 'warning' : 'success'"
      :icon="user.status === 'active' ? 'bi-pause-circle' : 'bi-play-circle'"
      :confirm-label="user.status === 'active' ? 'Suspend' : 'Activate'"
      @confirmed="doToggle"
    />

    <!-- Reset Password Modal -->
    <ConfirmModal
      id="showResetModal"
      title="Reset Password"
      :message="`Generate a new temporary password for ${user.name}? They will need to change it on next login.`"
      variant="secondary"
      icon="bi-key"
      confirm-label="Reset Password"
      @confirmed="doReset"
    />
  </AdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Modal } from 'bootstrap';
import AdminLayout  from '@/Layouts/AdminLayout.vue';
import StatusBadge  from '@/Components/StatusBadge.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';

const props = defineProps({
  user:           { type: Object, required: true },
  recentAuditLogs:{ type: Array,  default: () => [] },
});

const confirmToggle = () => new Modal(document.getElementById('showToggleModal')).show();
const confirmReset  = () => new Modal(document.getElementById('showResetModal')).show();

const doToggle = () => router.post(route('admin.users.toggle-status', props.user.id));
const doReset  = () => router.post(route('admin.users.reset-password', props.user.id));

const initials    = (n) => n?.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() ?? '?';
const colors      = ['#0B2447','#14395B','#10b981','#f59e0b','#6366f1','#ef4444','#8b5cf6'];
const avatarColor = (n) => colors[n?.charCodeAt(0) % colors.length] ?? '#0B2447';

const formatCurrency   = (v) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v ?? 0);
const formatDate       = (d) => new Date(d).toLocaleDateString('en-US',  { year:'numeric', month:'short', day:'numeric' });
const formatDateTime   = (d) => new Date(d).toLocaleString('en-US',      { year:'numeric', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });
const formatPermission = (p) => p.replace('.', ' ').replace(/\b\w/g, c => c.toUpperCase());
</script>
