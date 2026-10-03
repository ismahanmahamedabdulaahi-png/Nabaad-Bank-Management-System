<template>
  <div :class="{ 'sidebar-collapsed': collapsed, 'sidebar-mobile-open': mobileOpen }">
    <!-- Mobile drawer backdrop -->
    <div v-if="mobileOpen" class="sidebar-backdrop d-lg-none" @click="mobileOpen = false"></div>

    <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
    <nav class="sidebar d-flex flex-column">
      <div class="sidebar-brand d-flex align-items-center gap-2">
        <img src="/images/logo.png" alt="NABAAD Bank"
             style="height:38px;width:auto;filter:brightness(0) invert(1);"
             @error="$event.target.style.display='none'">
        <div class="brand-text">
          <div class="brand-name">NABAAD Bank</div>
          <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">Customer Portal</div>
        </div>
        <button type="button" class="sidebar-toggle-btn ms-auto d-none d-lg-flex" @click="toggleSidebar"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
          <i class="bi" :class="collapsed ? 'bi-chevron-right' : 'bi-chevron-left'"></i>
        </button>
        <button type="button" class="sidebar-toggle-btn ms-auto d-lg-none" @click="mobileOpen = false" title="Close menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <ul class="nav flex-column pt-2 flex-grow-1" style="overflow-y: auto; overflow-x: hidden; min-height: 0;"
          @click="mobileOpen = false">
        <li class="nav-item">
          <Link :href="route('customer.dashboard')" class="nav-link" :class="{ active: isActive('customer.dashboard') }">
            <i class="bi bi-speedometer2"></i> <span class="nav-label">Dashboard</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.accounts.index')" class="nav-link" :class="{ active: isActive('customer.accounts') }">
            <i class="bi bi-wallet2"></i> <span class="nav-label">Accounts</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.transactions.index')" class="nav-link" :class="{ active: isActive('customer.transactions') }">
            <i class="bi bi-arrow-left-right"></i> <span class="nav-label">Transactions</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.transfer.create')" class="nav-link" :class="{ active: isActive('customer.transfer') }">
            <i class="bi bi-send"></i> <span class="nav-label">Transfer</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.service-codes.index')" class="nav-link" :class="{ active: isActive('customer.service-codes') }">
            <i class="bi bi-qr-code"></i> <span class="nav-label">Cardless Codes</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.complaints.index')" class="nav-link" :class="{ active: isActive('customer.complaints') }">
            <i class="bi bi-headset"></i> <span class="nav-label">Complaints</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.loans.index')" class="nav-link" :class="{ active: isActive('customer.loans') }">
            <i class="bi bi-cash-coin"></i> <span class="nav-label">Loans</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.cheques.index')" class="nav-link" :class="{ active: isActive('customer.cheques') }">
            <i class="bi bi-file-earmark-text"></i> <span class="nav-label">Cheques</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.standing-orders.index')" class="nav-link" :class="{ active: isActive('customer.standing-orders') }">
            <i class="bi bi-arrow-repeat"></i> <span class="nav-label">Standing Orders</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.budgets.index')" class="nav-link" :class="{ active: isActive('customer.budgets') }">
            <i class="bi bi-piggy-bank"></i> <span class="nav-label">Budgets</span>
          </Link>
        </li>
        <li class="nav-item">
          <Link :href="route('customer.notifications.index')" class="nav-link" :class="{ active: isActive('customer.notifications') }">
            <i class="bi bi-bell"></i> <span class="nav-label">Notifications</span>
          </Link>
        </li>
      </ul>

      <Link :href="route('customer.profile.show')"
            class="p-3 border-top d-flex align-items-center gap-2 user-info-row text-decoration-none sidebar-profile-btn"
            style="border-color: rgba(255,255,255,0.1) !important;"
            :class="{ active: isActive('customer.profile') }">
        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
             style="width:34px;height:34px;flex-shrink:0;">
          <i class="bi bi-person-fill" style="color:#0B2447;"></i>
        </div>
        <div class="overflow-hidden user-info-text">
          <div class="text-white small fw-semibold text-truncate">{{ customer.name }}</div>
          <div style="color:rgba(255,255,255,0.5);font-size:0.7rem;" class="text-truncate">
            {{ customer.customer_number }} &nbsp;·&nbsp; My Profile
          </div>
        </div>
      </Link>
    </nav>

    <!-- ── Top Bar ──────────────────────────────────────────────────────────── -->
    <header class="topbar justify-content-between">
      <div class="d-flex align-items-center gap-2">
        <button type="button" class="sidebar-mobile-toggle d-lg-none" @click="mobileOpen = true" title="Open menu">
          <i class="bi bi-list"></i>
        </button>
        <span class="fw-semibold" style="color:#0B2447">Welcome, {{ customer.name?.split(' ')[0] }}</span>
      </div>

      <div class="d-flex align-items-center gap-3">
        <NotificationBell
          :notifications="page.props.notifications?.recent ?? []"
          :unread-count="page.props.notifications?.unread_count ?? 0"
          :index-route="route('customer.notifications.index')"
          :mark-read-route="(id) => route('customer.notifications.read', id)"
          :mark-all-route="route('customer.notifications.read-all')"
        />

        <div class="dropdown">
          <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-md-inline">{{ customer.name }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ customer.email }}</h6></li>
            <li v-if="customer.last_login_at">
              <span class="dropdown-item-text small text-muted">
                <i class="bi bi-clock me-1"></i>Last login: {{ customer.last_login_at }}
              </span>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <Link :href="route('customer.profile.show')" class="dropdown-item">
                <i class="bi bi-person me-2"></i>My Profile
              </Link>
            </li>
            <li>
              <form @submit.prevent="logout">
                <button type="submit" class="dropdown-item text-danger">
                  <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                </button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <!-- ── Page Content ─────────────────────────────────────────────────────── -->
    <main class="page-wrapper">
      <!-- Flash success/error/warning/info surfaces globally via the <Toast>
           mounted at the app root (see app.js) — no per-layout markup needed. -->

      <div class="page-content">
        <div v-if="title" class="d-flex align-items-center justify-content-between mb-4">
          <div>
            <h5 class="fw-bold mb-0" style="color:#0B2447">{{ title }}</h5>
            <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
          </div>
          <slot name="actions" />
        </div>

        <slot />
      </div>

      <footer class="text-center text-muted small py-3">
        &copy; {{ new Date().getFullYear() }} NABAAD Bank &middot; Trust &bull; Security &bull; Progress &middot; Garowe Branch, Somalia
      </footer>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import NotificationBell from '@/Components/NotificationBell.vue'

defineProps({ title: String, subtitle: String })

// Session keep-alive — pings the server every 5 minutes so a session doesn't
// expire (419 on submit) while a customer spends a long time filling out a form.
let pingInterval
const onKeydown = (e) => { if (e.key === 'Escape') mobileOpen.value = false }
onMounted(() => {
  pingInterval = setInterval(() => axios.get(route('customer.ping')).catch(() => {}), 5 * 60 * 1000)
  window.addEventListener('keydown', onKeydown)
})
onUnmounted(() => {
  clearInterval(pingInterval)
  window.removeEventListener('keydown', onKeydown)
  document.body.style.overflow = ''
})

const page     = usePage()
const customer = computed(() => page.props.customer_auth ?? {})

// Sidebar collapse — persisted across visits, same pattern as the admin panel.
const collapsed = ref(localStorage.getItem('nabaad_portal_sidebar_collapsed') === '1')
const toggleSidebar = () => {
  collapsed.value = !collapsed.value
  localStorage.setItem('nabaad_portal_sidebar_collapsed', collapsed.value ? '1' : '0')
}

// Mobile off-canvas drawer — never persisted, always starts closed.
const mobileOpen = ref(false)
watch(mobileOpen, (open) => { document.body.style.overflow = open ? 'hidden' : '' })

const isActive = (prefix) => {
  const current = route().current() ?? ''
  return current === prefix || current.startsWith(prefix + '.')
}

const logout = () => router.post(route('customer.logout'))
</script>
