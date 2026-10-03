<template>
  <PortalLayout title="Notifications" subtitle="Alerts about your budgets, balances, and account activity">
    <template #actions>
      <button v-if="hasUnread" type="button" class="btn btn-sm btn-outline-primary" @click="markAllRead">
        <i class="bi bi-check2-all me-1"></i>Mark all read
      </button>
    </template>

    <div v-if="notifications.data.length === 0" class="card border-0 shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <i class="bi bi-bell-slash fs-1 d-block mb-2 opacity-25"></i>
        No notifications yet.
      </div>
    </div>

    <div v-else class="card border-0 shadow-sm">
      <div class="list-group list-group-flush">
        <div v-for="n in notifications.data" :key="n.id"
             class="list-group-item d-flex gap-3 py-3" :class="{ 'bg-light': !n.read_at }">
          <i class="bi fs-5 mt-1" :class="[n.data.icon || 'bi-bell', levelClass(n.data.level)]"></i>
          <div class="flex-grow-1">
            <div class="d-flex justify-content-between align-items-start">
              <div class="fw-semibold">{{ n.data.title }}</div>
              <span class="text-muted small text-nowrap ms-2">{{ timeAgo(n.created_at) }}</span>
            </div>
            <div class="text-muted small">{{ n.data.message }}</div>
            <div class="d-flex gap-2 mt-2">
              <Link v-if="n.data.url" :href="n.data.url" class="small text-primary text-decoration-none">View →</Link>
              <button v-if="!n.read_at" type="button" class="btn btn-link btn-sm p-0 small" @click="markRead(n.id)">
                Mark as read
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="notifications.last_page > 1" class="card-footer bg-white d-flex justify-content-between align-items-center">
        <span class="text-muted small">Page {{ notifications.current_page }} of {{ notifications.last_page }}</span>
        <div class="d-flex gap-2">
          <Link v-if="notifications.prev_page_url" :href="notifications.prev_page_url" class="btn btn-sm btn-outline-secondary">Prev</Link>
          <Link v-if="notifications.next_page_url" :href="notifications.next_page_url" class="btn btn-sm btn-outline-primary">Next</Link>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'

const props = defineProps({
  notifications: { type: Object, required: true },
})

const hasUnread = computed(() => props.notifications.data.some(n => !n.read_at))

const markRead = (id) => router.post(route('customer.notifications.read', id), {}, { preserveScroll: true })
const markAllRead = () => router.post(route('customer.notifications.read-all'), {}, { preserveScroll: true })

const timeAgo = (d) => {
  const diff = (Date.now() - new Date(d).getTime()) / 1000
  if (diff < 60) return 'just now'
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  return Math.floor(diff / 86400) + 'd ago'
}

const levelClass = (level) => ({
  info: 'text-primary', success: 'text-success', warning: 'text-warning', danger: 'text-danger',
}[level] ?? 'text-primary')
</script>
