<template>
  <div class="dropdown">
    <button
      type="button"
      class="btn btn-light btn-sm position-relative"
      data-bs-toggle="dropdown"
      aria-expanded="false"
      title="Notifications"
    >
      <i class="bi bi-bell"></i>
      <span v-if="unreadCount > 0"
            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            style="font-size:0.6rem;">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow-sm notification-menu">
      <li>
        <h6 class="dropdown-header d-flex align-items-center justify-content-between">
          Notifications
          <button v-if="unreadCount > 0" type="button" class="btn btn-link btn-sm p-0 small"
                  @click="markAllRead">Mark all read</button>
        </h6>
      </li>
      <li><hr class="dropdown-divider my-1"></li>

      <li v-if="!notifications.length">
        <span class="dropdown-item-text text-muted small py-3 d-flex align-items-center gap-2 justify-content-center">
          <i class="bi bi-check2-circle text-success"></i>
          You're all caught up.
        </span>
      </li>

      <li v-for="n in notifications" :key="n.id" class="notification-item" :class="{ unread: !n.read }">
        <component :is="n.url ? Link : 'div'" :href="n.url" class="dropdown-item small py-2"
                   @click="onClick(n)">
          <div class="d-flex gap-2">
            <i class="bi mt-1" :class="[n.icon, levelClass(n.level)]"></i>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-semibold text-truncate">{{ n.title }}</div>
              <div class="text-muted" style="font-size:.75rem;white-space:normal;">{{ n.message }}</div>
              <div class="text-muted" style="font-size:.68rem;">{{ n.created_at }}</div>
            </div>
            <span v-if="!n.read" class="unread-dot mt-1"></span>
          </div>
        </component>
      </li>

      <li><hr class="dropdown-divider my-1"></li>
      <li>
        <Link :href="indexRoute" class="dropdown-item small">
          <i class="bi bi-arrow-right-circle me-2 text-primary"></i>View All Notifications
        </Link>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3'

const props = defineProps({
  notifications: { type: Array, default: () => [] },
  unreadCount:   { type: Number, default: 0 },
  indexRoute:    { type: String, required: true },
  markReadRoute: { type: Function, required: true },   // (id) => url
  markAllRoute:  { type: String, required: true },
})

const onClick = (n) => {
  if (!n.read) {
    router.post(props.markReadRoute(n.id), {}, { preserveScroll: true, preserveState: true })
  }
}

const markAllRead = () => {
  router.post(props.markAllRoute, {}, { preserveScroll: true, preserveState: true })
}

const levelClass = (level) => ({
  info:    'text-primary',
  success: 'text-success',
  warning: 'text-warning',
  danger:  'text-danger',
}[level] ?? 'text-primary')
</script>

<style scoped>
.notification-menu { min-width: 340px; max-width: 380px; max-height: 420px; overflow-y: auto; padding-bottom: 0; }
.notification-item.unread { background: #f0f6ff; }
.notification-item :deep(.dropdown-item) { white-space: normal; }
.unread-dot { width: 8px; height: 8px; border-radius: 50%; background: #0B2447; flex-shrink: 0; }
.min-w-0 { min-width: 0; }
</style>
