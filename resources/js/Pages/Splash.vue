<template>
  <Head title="NABAAD Bank" />
  <div class="splash-page">
    <div class="splash-glow"></div>
    <div class="splash-content">
      <img src="/images/logo.png" alt="NABAAD Bank" class="splash-logo" />
      <div class="splash-ring" aria-hidden="true">
        <svg viewBox="0 0 48 48">
          <circle class="splash-ring-track" cx="24" cy="24" r="20" />
          <circle class="splash-ring-arc" cx="24" cy="24" r="20" />
        </svg>
      </div>
      <p class="splash-text">Securing your session…</p>
    </div>
  </div>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';

const props = defineProps({
  redirect_to: { type: String, required: true },
});

onMounted(() => {
  const minDisplay = new Promise((resolve) => setTimeout(resolve, 650));
  minDisplay.then(() => router.visit(props.redirect_to, { replace: true }));
});
</script>

<style scoped>
.splash-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  position: relative;
  overflow: hidden;
  background: radial-gradient(circle at 30% 20%, #14395B 0%, #0B2447 55%, #081A33 100%);
}

.splash-glow {
  position: absolute;
  inset: -20%;
  background: radial-gradient(circle at 50% 40%, rgba(201, 151, 47, 0.18), transparent 60%);
  animation: splash-pulse 3.5s ease-in-out infinite;
}

@keyframes splash-pulse {
  0%, 100% { opacity: 0.6; transform: scale(1); }
  50%      { opacity: 1;   transform: scale(1.08); }
}

.splash-content {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.25rem;
  animation: splash-in 0.5s ease both;
}

@keyframes splash-in {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

.splash-logo {
  height: 56px;
  filter: drop-shadow(0 8px 24px rgba(0,0,0,0.35));
}

.splash-ring {
  width: 40px;
  height: 40px;
}

.splash-ring svg {
  width: 100%;
  height: 100%;
  animation: splash-spin 1.1s linear infinite;
}

@keyframes splash-spin {
  to { transform: rotate(360deg); }
}

.splash-ring-track {
  fill: none;
  stroke: rgba(255, 255, 255, 0.15);
  stroke-width: 4;
}

.splash-ring-arc {
  fill: none;
  stroke: #C9972F;
  stroke-width: 4;
  stroke-linecap: round;
  stroke-dasharray: 90;
  stroke-dashoffset: 60;
}

.splash-text {
  color: rgba(255, 255, 255, 0.7);
  font-size: 0.85rem;
  letter-spacing: 0.4px;
  margin: 0;
}
</style>
