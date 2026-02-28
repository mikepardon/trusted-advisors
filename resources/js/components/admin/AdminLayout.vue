<template>
  <div class="admin-layout">
    <nav class="admin-nav">
      <router-link to="/admin" class="nav-brand">Admin Panel</router-link>
      <div class="nav-links">
        <router-link to="/admin/characters">Characters</router-link>
        <router-link to="/admin/cards">Cards</router-link>
        <router-link to="/admin/events">Events</router-link>
        <router-link to="/admin/items">Items</router-link>
        <router-link to="/admin/sounds">Sounds</router-link>
        <router-link to="/admin/bot-games">Bot Games</router-link>
      </div>
      <router-link to="/" class="nav-back">Back to Game</router-link>
    </nav>
    <div class="admin-content">
      <router-view />
    </div>
  </div>
</template>

<script>
export default { name: 'AdminLayout' };
</script>

<style scoped>
.admin-layout {
  min-height: 100vh;
}

.admin-nav {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 12px 24px;
  background: var(--bg-secondary);
  border-bottom: 2px solid var(--border-gold);
}

.nav-brand {
  font-family: 'Cinzel', serif;
  font-size: 1.2rem;
  color: var(--accent-gold);
  text-decoration: none;
  font-weight: 700;
  margin-right: 10px;
}

.nav-links {
  display: flex;
  gap: 6px;
  flex: 1;
}

.nav-links a {
  color: var(--text-secondary);
  text-decoration: none;
  padding: 6px 14px;
  border-radius: 4px;
  font-size: 0.95rem;
  transition: all 0.2s;
}

.nav-links a:hover {
  color: var(--text-bright);
  background: rgba(212, 168, 67, 0.1);
}

.nav-links a.router-link-active {
  color: var(--accent-gold);
  background: rgba(212, 168, 67, 0.15);
}

.nav-back {
  color: var(--text-secondary);
  text-decoration: none;
  font-size: 0.85rem;
  padding: 6px 12px;
  border: 1px solid var(--text-secondary);
  border-radius: 4px;
  transition: all 0.2s;
}

.nav-back:hover {
  color: var(--text-bright);
  border-color: var(--text-bright);
}

.admin-content {
  max-width: 1100px;
  margin: 0 auto;
  padding: 24px 20px;
}
</style>
