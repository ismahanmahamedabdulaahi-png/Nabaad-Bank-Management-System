<template>
  <span class="badge rounded-pill" :class="cls">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, required: true },
  map: {
    type: Object,
    default: () => ({
      active:    { label: 'Active',    cls: 'badge-active' },
      inactive:  { label: 'Inactive',  cls: 'badge-inactive' },
      suspended: { label: 'Suspended', cls: 'badge-rejected' },
      pending:   { label: 'Pending',   cls: 'badge-pending' },
      approved:  { label: 'Approved',  cls: 'badge-approved' },
      rejected:  { label: 'Rejected',  cls: 'badge-rejected' },
      open:      { label: 'Open',      cls: 'badge-approved' },
      closed:    { label: 'Closed',    cls: 'badge-inactive' },
      frozen:    { label: 'Frozen',    cls: 'badge-pending' },
      dormant:   { label: 'Dormant',   cls: 'badge-inactive' },
      overdue:   { label: 'Overdue',   cls: 'badge-rejected' },
      disbursed: { label: 'Disbursed', cls: 'badge-active' },
      blacklisted: { label: 'Blacklisted', cls: 'badge-rejected' },
      deceased:    { label: 'Deceased',    cls: 'badge-inactive' },
      matured:     { label: 'Matured',     cls: 'badge-approved' },
      cancelled:   { label: 'Cancelled',   cls: 'badge-inactive' },
      under_review:{ label: 'Under Review',cls: 'badge-pending' },
      completed:   { label: 'Completed',   cls: 'badge-approved' },
      // Complaints
      in_progress: { label: 'In Progress', cls: 'badge-pending' },
      resolved:    { label: 'Resolved',    cls: 'badge-approved' },
      // Compliance cases
      reviewing:   { label: 'Reviewing',   cls: 'badge-pending' },
      cleared:     { label: 'Cleared',     cls: 'badge-approved' },
      reported:    { label: 'Reported',    cls: 'badge-inactive' },
      // Service request codes
      redeemed:    { label: 'Redeemed',    cls: 'badge-approved' },
      expired:     { label: 'Expired',     cls: 'badge-inactive' },
    }),
  },
});

const entry = computed(() => props.map[props.status] ?? { label: props.status, cls: 'badge-inactive' });
const label = computed(() => entry.value.label);
const cls   = computed(() => entry.value.cls);
</script>
