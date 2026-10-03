import { usePage } from '@inertiajs/vue3'

// Shared permission check, reading from the `auth.permissions` array every
// Inertia response carries (see HandleInertiaRequests::share). Used anywhere
// the UI needs to show/hide something based on what the current staff member
// is allowed to do — the sidebar nav (AdminLayout) and the role-aware
// Dashboard both rely on this same check.
export function usePermissions() {
  const page = usePage()

  function can(permission) {
    return page.props.auth?.permissions?.includes(permission) ?? false
  }

  return { can }
}
