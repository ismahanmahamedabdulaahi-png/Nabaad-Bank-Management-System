<template>
  <!-- Bootstrap's JS modal.show() appends the backdrop as a direct child of
       <body>. If the modal itself stays buried inside the page's own Vue
       component tree instead, it and the backdrop end up as unrelated
       branches under <body> — in Chromium's actual paint/hit-test behavior
       (confirmed via Playwright: "modal-backdrop intercepts pointer events")
       the backdrop wins the hit test over the modal's z-index-1055 content,
       even though it's only z-index 1050. Teleporting to <body> makes the
       modal a sibling of the backdrop, matching how Bootstrap expects it to
       be positioned and eliminating the nested-stacking-context mismatch. -->
  <Teleport to="body">
    <div class="modal fade" :id="id" tabindex="-1" :aria-labelledby="`${id}Label`">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content confirm-modal-content">
          <div class="modal-body text-center pt-4 pb-2">
            <div class="confirm-modal-icon mx-auto mb-3" :class="`confirm-modal-icon-${variant}`">
              <i :class="`bi ${icon}`"></i>
            </div>
            <h6 class="fw-bold mb-2" :id="`${id}Label`">{{ title }}</h6>
            <p class="text-muted small mb-0">{{ message }}</p>
          </div>
          <div class="modal-footer border-0 pt-2 pb-4 px-4 justify-content-center">
            <button type="button" class="btn btn-light btn-sm px-3" data-bs-dismiss="modal">Cancel</button>
            <button
              type="button"
              class="btn btn-sm px-3"
              :class="`btn-${variant}`"
              data-bs-dismiss="modal"
              @click="$emit('confirmed')"
            >
              {{ confirmLabel }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
defineProps({
  id:           { type: String, required: true },
  title:        { type: String, default: 'Confirm Action' },
  message:      { type: String, default: 'Are you sure you want to proceed?' },
  confirmLabel: { type: String, default: 'Confirm' },
  variant:      { type: String, default: 'danger' },
  icon:         { type: String, default: 'bi-exclamation-triangle' },
});
defineEmits(['confirmed']);
</script>

<style scoped>
.confirm-modal-content { border: none; border-radius: 1rem; }
.confirm-modal-icon {
  width: 56px; height: 56px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 1.5rem;
}
.confirm-modal-icon-danger  { background: rgba(220,38,38,.1);  color: #dc2626; }
.confirm-modal-icon-warning { background: rgba(217,119,6,.12); color: #d97706; }
.confirm-modal-icon-success { background: rgba(22,163,74,.1);  color: #16a34a; }
.confirm-modal-icon-secondary,
.confirm-modal-icon-info    { background: rgba(11,36,71,.08);  color: #0B2447; }
</style>
