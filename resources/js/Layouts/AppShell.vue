<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  app: { type: Object, required: true },
})
</script>

<template>
  <div class="wsn-app">
    <header class="wsn-header">
      <Link href="/" class="wsn-brand">{{ app.name }}</Link>
      <span class="wsn-sub">North Shore food ordering</span>
    </header>

    <main class="wsn-wrap">
      <slot />
    </main>

    <footer class="wsn-foot">
      <slot name="footer">
        Pay cash or e-transfer on pickup or delivery · order-taking only
      </slot>
    </footer>
  </div>
</template>
