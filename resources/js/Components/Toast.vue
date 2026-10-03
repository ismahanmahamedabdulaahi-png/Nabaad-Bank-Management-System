<template>
  <teleport to="body">
    <div class="app-toast-container" :class="{ 'app-toast-dark': isDark }">
      <transition-group name="app-toast">
        <div v-for="t in toasts" :key="t.id" class="app-toast" :class="`app-toast-${t.type}`" role="alert">
          <div class="app-toast-icon"><i class="bi" :class="icon(t.type)"></i></div>
          <div class="app-toast-body">{{ t.message }}</div>
          <button type="button" class="app-toast-close" aria-label="Dismiss" @click="dismiss(t.id)">
            <i class="bi bi-x-lg"></i>
          </button>
          <div class="app-toast-progress" :style="{ animationDuration: t.duration + 'ms' }"></div>
        </div>
      </transition-group>
    </div>
  </teleport>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

// Teleported to <body>, outside AdminLayout's own `.dark`-classed wrapper —
// ancestor CSS selectors like `.dark .app-toast` can never match here, so
// dark mode is tracked directly off the same localStorage flag + custom
// event AdminLayout's own toggle already uses, independent of DOM nesting.
const isDark = ref(localStorage.getItem('nabaad_dark_mode') === '1')
function onThemeChange(e) { isDark.value = e.detail }
onMounted(() => window.addEventListener('nabaad-theme-change', onThemeChange))
onBeforeUnmount(() => window.removeEventListener('nabaad-theme-change', onThemeChange))

// Mounted once at the true app root (see app.js) rather than per-layout, so
// flash messages surface on every page — including bare auth screens like
// Login/Register that render outside AdminLayout/PortalLayout entirely.
// Vue-native (no Bootstrap Toast JS instance) so auto-dismiss and stacking
// are just component state, not fighting Bootstrap's own imperative DOM edits.
const page = usePage()
const toasts = reactive([])
let seq = 0

function push(type, message, duration = 5000) {
  const id = ++seq
  toasts.push({ id, type, message, duration })
  setTimeout(() => dismiss(id), duration)
}

function dismiss(id) {
  const i = toasts.findIndex((t) => t.id === id)
  if (i !== -1) toasts.splice(i, 1)
}

function icon(type) {
  return {
    success: 'bi-check-circle-fill',
    error:   'bi-x-octagon-fill',
    warning: 'bi-exclamation-triangle-fill',
    info:    'bi-info-circle-fill',
  }[type] ?? 'bi-info-circle-fill'
}

watch(() => page.props.flash?.success, (v) => { if (v) push('success', v) })
watch(() => page.props.flash?.error,   (v) => { if (v) push('error', v, 7000) })
watch(() => page.props.flash?.warning, (v) => { if (v) push('warning', v, 6000) })
watch(() => page.props.flash?.info,    (v) => { if (v) push('info', v) })
</script>

<style scoped>
.app-toast-container {
  position: fixed;
  top: 1.25rem;
  right: 1.25rem;
  z-index: 10000;
  display: flex;
  flex-direction: column;
  gap: .6rem;
  max-width: min(380px, calc(100vw - 2.5rem));
}

.app-toast {
  position: relative;
  overflow: hidden;
  display: flex;
  align-items: flex-start;
  gap: .65rem;
  padding: .85rem 2.25rem .85rem 1rem;
  border-radius: .75rem;
  background: #fff;
  color: #1f2937;
  box-shadow: 0 12px 32px rgba(0,0,0,.16), 0 2px 8px rgba(0,0,0,.08);
  border-left: 4px solid transparent;
  font-size: .875rem;
  line-height: 1.4;
}

.app-toast-icon { flex-shrink: 0; font-size: 1.15rem; margin-top: .05rem; }
.app-toast-body { flex: 1; padding-top: .1rem; }
.app-toast-close {
  position: absolute; top: .5rem; right: .5rem;
  background: none; border: none; color: #9ca3af; font-size: .7rem;
  width: 22px; height: 22px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background .15s ease, color .15s ease;
}
.app-toast-close:hover { background: rgba(0,0,0,.06); color: #4b5563; }

.app-toast-progress {
  position: absolute; left: 0; bottom: 0; height: 3px;
  background: currentColor; opacity: .35;
  animation: app-toast-shrink linear forwards;
}
@keyframes app-toast-shrink { from { width: 100%; } to { width: 0%; } }

.app-toast-success { border-left-color: #16a34a; }
.app-toast-success .app-toast-icon, .app-toast-success .app-toast-progress { color: #16a34a; }

.app-toast-error { border-left-color: #dc2626; }
.app-toast-error .app-toast-icon, .app-toast-error .app-toast-progress { color: #dc2626; }

.app-toast-warning { border-left-color: #d97706; }
.app-toast-warning .app-toast-icon, .app-toast-warning .app-toast-progress { color: #d97706; }

.app-toast-info { border-left-color: #0B2447; }
.app-toast-info .app-toast-icon, .app-toast-info .app-toast-progress { color: #0B2447; }

/* Dark mode: the toast is a floating overlay, not a themed page surface —
   but pure white still clashes hard against a dark UI, so give it a
   surface color there rather than leaving it unstyled. Scoped to a class
   on the container (set from AdminLayout's own dark-mode flag/event, see
   script) rather than an ancestor selector, since teleporting to <body>
   takes this out of AdminLayout's `.dark`-classed subtree entirely. */
.app-toast-dark .app-toast { background: #1e2433; color: #e8e6de; box-shadow: 0 12px 32px rgba(0,0,0,.5); }
.app-toast-dark .app-toast-close:hover { background: rgba(255,255,255,.08); color: #e8e6de; }

.app-toast-enter-active { transition: transform .28s cubic-bezier(.34,1.56,.64,1), opacity .22s ease; }
.app-toast-leave-active { transition: transform .2s ease, opacity .2s ease; position: absolute; width: 100%; }
.app-toast-enter-from { transform: translateX(24px); opacity: 0; }
.app-toast-leave-to   { transform: translateX(24px); opacity: 0; }
.app-toast-move       { transition: transform .22s ease; }
</style>
