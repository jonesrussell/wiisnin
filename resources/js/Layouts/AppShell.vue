<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  app: { type: Object, required: true },
})
</script>

<template>
  <div class="wsn-app">
    <header class="wsn-header">
      <Link href="/" class="wsn-brand">
        {{ app.name }}
        <small>wiisnin · let's eat · North Shore</small>
      </Link>
      <slot name="header-right" />
    </header>

    <main class="wsn-wrap">
      <slot />
    </main>

    <footer class="wsn-foot">
      <slot name="footer">Pay cash or e-transfer on pickup or delivery · order-taking only</slot>
    </footer>
  </div>
</template>
