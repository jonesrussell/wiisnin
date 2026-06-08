<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
  app: { type: Object, required: true },
  communities: { type: Array, required: true },
})
</script>

<template>
  <Head :title="`${app.name} — North Shore food ordering`" />

  <main class="page">
    <header class="hero">
      <p class="kicker">Wiisnin · “eat” (Nishnaabemwin, Sagamok dialect)</p>
      <h1>{{ app.name }}</h1>
      <p class="tagline">{{ app.tagline }}</p>
    </header>

    <section aria-labelledby="communities-heading">
      <h2 id="communities-heading">Choose your community</h2>
      <ul class="communities">
        <li v-for="community in communities" :key="community.name" class="community">
          <h3>{{ community.name }}</h3>
          <p>{{ community.blurb }}</p>
        </li>
      </ul>
    </section>

    <footer class="foot">
      <p>Pay cash or e-transfer on pickup or delivery. No card processing — order-taking only.</p>
    </footer>
  </main>
</template>

<style scoped>
:global(body) {
  margin: 0;
  font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  background: #faf7f2;
  color: #2a2118;
}

.page {
  max-width: 64rem;
  margin: 0 auto;
  padding: 2rem 1.25rem 4rem;
}

.hero {
  text-align: center;
  padding: 2rem 0 1rem;
}

.kicker {
  font-size: 0.85rem;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #9a6b3f;
  margin: 0 0 0.5rem;
}

.hero h1 {
  font-size: clamp(2.5rem, 8vw, 4rem);
  margin: 0;
  color: #7a3b12;
}

.tagline {
  font-size: 1.15rem;
  color: #5a4a38;
  margin: 0.5rem 0 0;
}

h2 {
  text-align: center;
  font-size: 1.4rem;
  margin: 2.5rem 0 1.5rem;
}

.communities {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
}

.community {
  background: #fff;
  border: 1px solid #ece2d4;
  border-radius: 0.9rem;
  padding: 1.25rem 1.25rem 1.5rem;
  box-shadow: 0 1px 3px rgba(122, 59, 18, 0.06);
}

.community h3 {
  margin: 0 0 0.4rem;
  font-size: 1.3rem;
  color: #7a3b12;
}

.community p {
  margin: 0;
  color: #5a4a38;
  line-height: 1.45;
}

.foot {
  margin-top: 3rem;
  text-align: center;
  color: #8a7a66;
  font-size: 0.95rem;
}
</style>
