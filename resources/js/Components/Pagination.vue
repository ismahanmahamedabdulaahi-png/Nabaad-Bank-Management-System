<template>
  <div v-if="links.length > 3" class="d-flex align-items-center justify-content-between flex-wrap gap-2">
    <p class="text-muted small mb-0">
      Showing <strong>{{ from }}</strong> to <strong>{{ to }}</strong> of <strong>{{ total }}</strong> results
    </p>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <li
          v-for="link in links"
          :key="link.label"
          class="page-item"
          :class="{ active: link.active, disabled: !link.url }"
        >
          <button
            class="page-link"
            :style="link.active ? 'background:#0B2447;border-color:#0B2447;color:#fff' : ''"
            @click="link.url && $emit('navigate', link.url)"
            v-html="link.label"
          />
        </li>
      </ul>
    </nav>
  </div>
</template>

<script setup>
defineProps({
  links:  { type: Array,  default: () => [] },
  from:   { type: Number, default: 0 },
  to:     { type: Number, default: 0 },
  total:  { type: Number, default: 0 },
});
defineEmits(['navigate']);
</script>
