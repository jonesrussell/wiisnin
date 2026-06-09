<script setup>
import { Head, router } from '@inertiajs/vue3'
import { reactive, ref } from 'vue'
import AppShell from '../Layouts/AppShell.vue'

defineProps({
  app: { type: Object, required: true },
  error: { type: String, default: null },
})

const form = reactive({ passphrase: '' })
const submitting = ref(false)
function submit() {
  submitting.value = true
  router.post('/vendor/login', { passphrase: form.passphrase }, { onFinish: () => { submitting.value = false } })
}
</script>

<template>
  <Head :title="`Vendor inbox — ${app.name}`" />
  <AppShell :app="app">
    <div class="perm">
      <div class="ring" aria-hidden="true">🔑</div>
      <h2>Order inbox</h2>
      <p>Enter the vendor passphrase to see incoming orders for Meedjims.</p>
    </div>
    <div v-if="error" class="error">{{ error }}</div>
    <div class="field"><label>Passphrase</label><input v-model="form.passphrase" type="password" placeholder="passphrase" @keyup.enter="submit" /></div>
    <button class="cta" :disabled="submitting" @click="submit">{{ submitting ? 'Checking…' : 'Open inbox' }}</button>
  </AppShell>
</template>
