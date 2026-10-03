<template>
  <div :class="{ 'sidebar-collapsed': collapsed, 'dark': darkMode, 'sidebar-mobile-open': mobileOpen }">
    <!-- Mobile drawer backdrop -->
    <div v-if="mobileOpen" class="sidebar-backdrop d-lg-none" @click="mobileOpen = false"></div>

    <!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
    <nav class="sidebar d-flex flex-column">
      <!-- Brand -->
      <div class="sidebar-brand d-flex align-items-center gap-2">
        <img src="/images/logo.png" alt="NABAAD Bank"
             style="height:38px;width:auto;filter:brightness(0) invert(1);"
             @error="$event.target.style.display='none'">
        <div class="brand-text">
          <div class="brand-name">NABAAD Bank</div>
          <div style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">Garowe Branch</div>
        </div>
        <button type="button" class="sidebar-toggle-btn ms-auto d-none d-lg-flex" @click="toggleSidebar"
                :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'">
          <i class="bi" :class="collapsed ? 'bi-chevron-right' : 'bi-chevron-left'"></i>
        </button>
        <button type="button" class="sidebar-toggle-btn ms-auto d-lg-none" @click="mobileOpen = false" title="Close menu">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>

      <!-- Navigation -->
      <ul class="nav flex-column pt-2 flex-grow-1" style="overflow-y: auto; overflow-x: hidden; min-height: 0; flex-wrap: nowrap;"
          @click="mobileOpen = false">
        <span class="sidebar-section-title">Main</span>

        <li class="nav-item">
          <Link :href="route('admin.dashboard')" class="nav-link" :class="{ active: isActive('admin.dashboard') }">
            <i class="bi bi-speedometer2"></i> <span class="nav-label">Dashboard</span>
          </Link>
        </li>

        <span class="sidebar-section-title mt-2">Banking</span>

        <li v-if="can('customers.view')" class="nav-item">
          <Link :href="route('admin.customers.index')" class="nav-link" :class="{ active: isActive('admin.customers') }">
            <i class="bi bi-people"></i> <span class="nav-label">Customers</span>
          </Link>
        </li>

        <li v-if="can('accounts.view')" class="nav-item">
          <Link
            :href="route('admin.accounts.index')"
            class="nav-link"
            :class="{ active: isActive('admin.accounts') }"
          >
            <i class="bi bi-wallet2"></i> <span class="nav-label">Accounts</span>
          </Link>
        </li>

        <li v-if="can('transactions.view')" class="nav-item">
          <Link :href="route('admin.transactions.index')" class="nav-link" :class="{ active: isActive('admin.transactions') }">
            <i class="bi bi-arrow-left-right"></i> <span class="nav-label">Transactions</span>
          </Link>
        </li>

        <li v-if="can('transactions.transfer')" class="nav-item">
          <Link :href="route('admin.standing-orders.index')" class="nav-link" :class="{ active: isActive('admin.standing-orders') }">
            <i class="bi bi-repeat"></i> <span class="nav-label">Standing Orders</span>
          </Link>
        </li>

        <li v-if="can('loans.view')" class="nav-item">
          <Link :href="route('admin.loans.index')" class="nav-link" :class="{ active: isActive('admin.loans') }">
            <i class="bi bi-cash-coin"></i> <span class="nav-label">Loans</span>
          </Link>
        </li>

        <li v-if="can('cheques.view')" class="nav-item">
          <Link :href="route('admin.cheques.index')" class="nav-link" :class="{ active: isActive('admin.cheques') }">
            <i class="bi bi-file-earmark-text"></i> <span class="nav-label">Cheques</span>
          </Link>
        </li>

        <span class="sidebar-section-title mt-2">Operations</span>

        <li class="nav-item">
          <Link :href="route('admin.business-day.index')" class="nav-link" :class="{ active: isActive('admin.business-day') }">
            <i class="bi bi-calendar-check"></i> <span class="nav-label">Business Day</span>
          </Link>
        </li>

        <li v-if="can('teller.open-till') || can('teller.close-till')" class="nav-item">
          <Link :href="route('admin.tellers.index')" class="nav-link" :class="{ active: isActive('admin.tellers') }">
            <i class="bi bi-cash-stack"></i> <span class="nav-label">Teller Operations</span>
          </Link>
        </li>

        <li v-if="can('vault.view')" class="nav-item">
          <Link :href="route('admin.vault.show')" class="nav-link" :class="{ active: isActive('admin.vault') }">
            <i class="bi bi-safe"></i> <span class="nav-label">Vault</span>
          </Link>
        </li>

        <li v-if="can('service-codes.redeem')" class="nav-item">
          <Link :href="route('admin.service-codes.create')" class="nav-link" :class="{ active: isActive('admin.service-codes') }">
            <i class="bi bi-qr-code"></i> <span class="nav-label">Redeem Code</span>
          </Link>
        </li>

        <li v-if="can('complaints.view')" class="nav-item">
          <Link :href="route('admin.complaints.index')" class="nav-link" :class="{ active: isActive('admin.complaints') }">
            <i class="bi bi-headset"></i> <span class="nav-label">Complaints</span>
          </Link>
        </li>

        <li v-if="can('compliance.view')" class="nav-item">
          <Link :href="route('admin.compliance.index')" class="nav-link" :class="{ active: isActive('admin.compliance') }">
            <i class="bi bi-shield-exclamation"></i> <span class="nav-label">Compliance</span>
          </Link>
        </li>

        <li v-if="can('approvals.view')" class="nav-item">
          <Link :href="route('admin.approvals.index')" class="nav-link" :class="{ active: isActive('admin.approvals') }">
            <i class="bi bi-check2-circle"></i> <span class="nav-label">Approvals</span>
            <span v-if="$page.props.pending_approvals > 0"
              class="badge rounded-pill bg-danger ms-auto"
              style="font-size:.65rem">{{ $page.props.pending_approvals }}</span>
          </Link>
        </li>

        <li v-if="can('kyc.view')" class="nav-item">
          <Link :href="route('admin.kyc.index')" class="nav-link" :class="{ active: isActive('admin.kyc') }">
            <i class="bi bi-person-check"></i> <span class="nav-label">KYC</span>
          </Link>
        </li>

        <span class="sidebar-section-title mt-2">Management</span>

        <li v-if="can('reports.view')" class="nav-item">
          <Link :href="route('admin.reports.index')" class="nav-link" :class="{ active: isActive('admin.reports') }">
            <i class="bi bi-bar-chart-line"></i> <span class="nav-label">Reports</span>
          </Link>
        </li>

        <li v-if="can('gl.view')" class="nav-item">
          <Link :href="route('admin.general-ledger.index')" class="nav-link" :class="{ active: isActive('admin.general-ledger') }">
            <i class="bi bi-journal-bookmark"></i> <span class="nav-label">General Ledger</span>
          </Link>
        </li>

        <li v-if="can('audit.view')" class="nav-item">
          <Link :href="route('admin.audit-logs.index')" class="nav-link" :class="{ active: isActive('admin.audit-logs') }">
            <i class="bi bi-journal-text"></i> <span class="nav-label">Audit Logs</span>
          </Link>
        </li>

        <li v-if="can('users.view')" class="nav-item">
          <Link :href="route('admin.users.index')" class="nav-link" :class="{ active: isActive('admin.users') }">
            <i class="bi bi-person-gear"></i> <span class="nav-label">Staff Management</span>
          </Link>
        </li>

        <li v-if="can('roles.manage')" class="nav-item">
          <Link :href="route('admin.roles.index')" class="nav-link" :class="{ active: isActive('admin.roles') }">
            <i class="bi bi-shield-lock"></i> <span class="nav-label">Roles &amp; Permissions</span>
          </Link>
        </li>

        <li v-if="can('settings.view')" class="nav-item">
          <Link :href="route('admin.settings.index')" class="nav-link" :class="{ active: isActive('admin.settings') }">
            <i class="bi bi-gear"></i> <span class="nav-label">Settings</span>
          </Link>
        </li>

      </ul>

      <!-- User info at bottom — clicking goes to profile -->
      <Link :href="route('admin.profile')"
            class="p-3 border-top d-flex align-items-center gap-2 user-info-row text-decoration-none sidebar-profile-btn"
            style="border-color: rgba(255,255,255,0.1) !important;"
            :class="{ active: isActive('admin.profile') }">
        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center"
             style="width:34px;height:34px;flex-shrink:0;">
          <i class="bi bi-person-fill" style="color:#0B2447;"></i>
        </div>
        <div class="overflow-hidden user-info-text">
          <div class="text-white small fw-semibold text-truncate">{{ auth.name }}</div>
          <div style="color:rgba(255,255,255,0.5);font-size:0.7rem;" class="text-truncate">
            {{ auth.role }} &nbsp;·&nbsp; My Profile
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
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item">
              <Link :href="route('admin.dashboard')" class="text-decoration-none section-heading-brand">
                <i class="bi bi-house-door"></i>
              </Link>
            </li>
            <li
              v-for="(crumb, i) in breadcrumbs"
              :key="i"
              class="breadcrumb-item"
              :class="{ active: i === breadcrumbs.length - 1 }"
            >
              <Link v-if="crumb.href" :href="crumb.href" class="text-decoration-none section-heading-brand">
                {{ crumb.label }}
              </Link>
              <span v-else>{{ crumb.label }}</span>
            </li>
          </ol>
        </nav>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Clock -->
        <span class="text-muted small d-none d-md-block">
          <i class="bi bi-clock me-1"></i>{{ currentTime }}
        </span>

        <!-- Dark / Light toggle -->
        <button type="button" class="btn btn-light btn-sm" @click="toggleDark" :title="darkMode ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
          <i class="bi" :class="darkMode ? 'bi-sun-fill text-warning' : 'bi-moon-fill text-secondary'"></i>
        </button>

        <!-- General notifications bell -->
        <NotificationBell
          :notifications="page.props.notifications?.recent ?? []"
          :unread-count="page.props.notifications?.unread_count ?? 0"
          :index-route="route('admin.notifications.index')"
          :mark-read-route="(id) => route('admin.notifications.read', id)"
          :mark-all-route="route('admin.notifications.read-all')"
        />

        <!-- User dropdown -->
        <div class="dropdown">
          <button class="btn btn-light btn-sm dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-md-inline">{{ auth.name }}</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><h6 class="dropdown-header">{{ auth.role }}</h6></li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <Link :href="route('admin.profile')" class="dropdown-item">
                <i class="bi bi-person me-2"></i>Profile
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
        <!-- Page Header -->
        <div v-if="title" class="d-flex align-items-center justify-content-between mb-4">
          <div>
            <h5 class="fw-bold mb-0 section-heading-brand">{{ title }}</h5>
            <p v-if="subtitle" class="text-muted small mb-0">{{ subtitle }}</p>
          </div>
          <slot name="actions" />
        </div>

        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import NotificationBell from '@/Components/NotificationBell.vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  title: String,
  subtitle: String,
  breadcrumbs: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();

// Sidebar collapse — persisted across visits
const collapsed = ref(localStorage.getItem('nabaad_sidebar_collapsed') === '1');
const toggleSidebar = () => {
  collapsed.value = !collapsed.value;
  localStorage.setItem('nabaad_sidebar_collapsed', collapsed.value ? '1' : '0');
};

// Mobile off-canvas drawer — never persisted, always starts closed. Locks
// background scroll while open and closes on Escape, same as any standard
// drawer/modal pattern.
const mobileOpen = ref(false);
watch(mobileOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : '';
});
const onKeydown = (e) => { if (e.key === 'Escape') mobileOpen.value = false; };

// Dark / Light mode — persisted across visits
const darkMode = ref(localStorage.getItem('nabaad_dark_mode') === '1');
const toggleDark = () => {
  darkMode.value = !darkMode.value;
  localStorage.setItem('nabaad_dark_mode', darkMode.value ? '1' : '0');
  // Canvas-based charts (Chart.js) can't react to the `.dark` CSS class —
  // pages that render one listen for this to rebuild with theme-correct colors.
  window.dispatchEvent(new CustomEvent('nabaad-theme-change', { detail: darkMode.value }));
};

const auth = computed(() => ({
  name: page.props.auth?.user?.name ?? 'Staff',
  role: page.props.auth?.user?.roles?.[0]?.name ?? '',
}));

const { can } = usePermissions();

// Active route helper — supports exact name or prefix (e.g. 'admin.customers')
const isActive = (prefix) => {
  const current = route().current() ?? '';
  return current === prefix || current.startsWith(prefix + '.');
};

// Clock
const currentTime = ref('');
let clockInterval;

const updateClock = () => {
  currentTime.value = new Date().toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZone: 'Africa/Mogadishu',
  });
};

// Session keep-alive — pings the server every 5 minutes so a session doesn't
// expire (419 on submit) while staff spend a long time filling out a form.
let pingInterval;
const ping = () => axios.get(route('admin.ping')).catch(() => {});

onMounted(() => {
  updateClock();
  clockInterval = setInterval(updateClock, 1000);
  pingInterval = setInterval(ping, 5 * 60 * 1000);
  window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  clearInterval(clockInterval);
  clearInterval(pingInterval);
  window.removeEventListener('keydown', onKeydown);
  document.body.style.overflow = '';
});

const logout = () => {
  router.post(route('logout'));
};
</script>
