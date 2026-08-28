<template>
    <div class="admin-layout">
        <!-- Mobile hamburger -->
        <button
            v-if="isMobile"
            class="sidebar-toggle"
            @click="sidebarOpen = !sidebarOpen"
        >
            <span class="hamburger-icon">&#9776;</span>
        </button>

        <!-- Backdrop for mobile -->
        <div
            v-if="isMobile && sidebarOpen"
            class="sidebar-backdrop"
            @click="sidebarOpen = false"
        ></div>

        <!-- Sidebar -->
        <nav class="admin-sidebar" :class="{ open: sidebarOpen }">
            <router-link to="/admin" class="sidebar-brand">
                Admin Panel
            </router-link>

            <div class="nav-scroll">
                <div class="nav-section">
                    <router-link
                        to="/admin"
                        class="nav-item"
                        exact-active-class="active"
                    >
                        Dashboard
                    </router-link>
                </div>

                <div v-if="canSee('content')" class="nav-section">
                    <span class="nav-group-label">Content</span>
                    <router-link to="/admin/characters" class="nav-item" active-class="active">Characters</router-link>
                    <router-link to="/admin/cards" class="nav-item" active-class="active">Cards</router-link>
                    <router-link to="/admin/events" class="nav-item" active-class="active">Events</router-link>
                    <router-link to="/admin/items" class="nav-item" active-class="active">Items</router-link>
                    <router-link to="/admin/curses" class="nav-item" active-class="active">Curses</router-link>
                </div>

                <div v-if="canSee('content')" class="nav-section">
                    <span class="nav-group-label">Cosmetics</span>
                    <router-link to="/admin/dice" class="nav-item" active-class="active">Dice</router-link>
                    <router-link to="/admin/kingdom-styles" class="nav-item" active-class="active">Kingdom Styles</router-link>
                    <router-link to="/admin/addons" class="nav-item" active-class="active">Addons</router-link>
                </div>

                <div v-if="canSee('content')" class="nav-section">
                    <span class="nav-group-label">Site Functionality</span>
                    <router-link to="/admin/announcements" class="nav-item" active-class="active">Announcements</router-link>
                    <router-link to="/admin/sounds" class="nav-item" active-class="active">Sounds</router-link>
                    <router-link to="/admin/payments" class="nav-item" active-class="active">Payments</router-link>
                    <router-link to="/admin/rotating-events" class="nav-item" active-class="active">Rotating Events</router-link>
                    <router-link to="/admin/media" class="nav-item" active-class="active">Media Library</router-link>
                    <router-link to="/admin/icons" class="nav-item" active-class="active">Icons</router-link>
                </div>

                <div v-if="canSee('content')" class="nav-section">
                    <span class="nav-group-label">Progression</span>
                    <router-link to="/admin/seasons" class="nav-item" active-class="active">Seasons</router-link>
                    <router-link to="/admin/xp" class="nav-item" active-class="active">XP &amp; Levels</router-link>
                    <router-link to="/admin/achievements" class="nav-item" active-class="active">Achievements</router-link>
                    <router-link to="/admin/unlockables" class="nav-item" active-class="active">Unlockables</router-link>
                    <router-link to="/admin/cosmetics" class="nav-item" active-class="active">Cosmetics</router-link>
                    <router-link to="/admin/challenges" class="nav-item" active-class="active">Challenges</router-link>
                    <router-link to="/admin/advisor-levels" class="nav-item" active-class="active">Advisor Levels</router-link>
                </div>

                <div v-if="canSee('management')" class="nav-section">
                    <span class="nav-group-label">Users</span>
                    <router-link to="/admin/users" class="nav-item" active-class="active">Users</router-link>
                    <router-link v-if="canSee('system')" to="/admin/roles" class="nav-item" active-class="active">Roles</router-link>
                    <router-link to="/admin/gifts" class="nav-item" active-class="active">Gifts</router-link>
                </div>

                <div v-if="canSee('analytics')" class="nav-section">
                    <span class="nav-group-label">Analytics</span>
                    <router-link to="/admin/balance" class="nav-item" active-class="active">Balance</router-link>
                    <router-link to="/admin/retention" class="nav-item" active-class="active">Retention</router-link>
                    <router-link to="/admin/audit-log" class="nav-item" active-class="active">Audit Log</router-link>
                    <router-link to="/admin/seeders" class="nav-item" active-class="active">Seeders</router-link>
                    <router-link v-if="canSee('system')" to="/admin/schedule" class="nav-item" active-class="active">Scheduled Jobs</router-link>
                    <router-link v-if="canSee('system')" to="/admin/settings" class="nav-item" active-class="active">Settings</router-link>
                    <router-link to="/admin/games" class="nav-item" active-class="active">Games</router-link>
                    <router-link v-if="canSee('content')" to="/admin/bot-games" class="nav-item" active-class="active">Bot Games</router-link>
                </div>
            </div>
            <!-- end nav-scroll -->

            <div class="nav-section nav-bottom">
                <router-link to="/" class="nav-item nav-back">
                    Back to Game
                </router-link>
            </div>
        </nav>

        <!-- Main content -->
        <div class="admin-main">
            <router-view />
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "../../stores/auth";

const auth = useAuth();
const route = useRoute();

const sidebarOpen = ref(false);
const isMobile = ref(false);

const userRole = computed<string | undefined>(
    () => auth.state.user?.admin_role ?? undefined,
);

const mobileQuery = window.matchMedia("(max-width: 768px)");

function applyMobileState(isMobileWidth: boolean): void {
    isMobile.value = isMobileWidth;
    if (!isMobile.value) {
        sidebarOpen.value = false;
    }
}

function onMobileQueryChange(event: MediaQueryListEvent): void {
    applyMobileState(event.matches);
}

function canSee(section: string): boolean {
    const role = userRole.value;
    // If no role set (legacy admin without role), show everything
    if (!role) {
        return true;
    }
    if (role === "super_admin") {
        return true;
    }
    if (section === "content") {
        return role === "content_admin";
    }
    if (section === "management") {
        return ["content_admin", "moderator"].includes(role);
    }
    if (section === "analytics") {
        return ["content_admin", "moderator", "analyst"].includes(role);
    }
    // "system" is super_admin only, handled above; everything else denied
    return false;
}

watch(
    () => route.fullPath,
    () => {
        if (isMobile.value) {
            sidebarOpen.value = false;
        }
    },
);

onMounted(() => {
    applyMobileState(mobileQuery.matches);
    mobileQuery.addEventListener("change", onMobileQueryChange);
});

onBeforeUnmount(() => {
    mobileQuery.removeEventListener("change", onMobileQueryChange);
});
</script>

<style scoped>
.admin-layout {
    min-height: 100vh;
    display: flex;
    background: var(--admin-bg, #f4f0e6);
}

/* Sidebar */
.admin-sidebar {
    width: 268px;
    height: 100vh;
    position: fixed;
    top: 0;
    left: 0;
    z-index: 100;
    background: var(--admin-sidebar, #fbf9f3);
    border-right: 1px solid var(--admin-line, #e3ddce);
    display: flex;
    flex-direction: column;
    padding: 0;
}

.sidebar-brand {
    display: block;
    font-family: var(--admin-font);
    font-size: 1rem;
    color: var(--admin-ink, #23201a);
    text-decoration: none;
    font-weight: 700;
    letter-spacing: 0.2px;
    padding: 16px 18px 13px;
    border-bottom: 1px solid var(--admin-line, #e3ddce);
}

.nav-scroll {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
    padding: 4px 0;
}

.nav-section {
    padding: 4px 0;
}

.nav-section + .nav-section {
    border-top: 1px solid var(--admin-line, #e3ddce);
}

.nav-group-label {
    display: block;
    font-family: var(--admin-font);
    font-size: 0.62rem;
    font-weight: 600;
    color: var(--admin-mute, #7a7365);
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 9px 18px 3px;
}

.nav-item {
    display: block;
    padding: 5px 18px;
    color: var(--admin-mute, #7a7365);
    text-decoration: none;
    font-size: 0.85rem;
    transition:
        background 0.15s,
        color 0.15s;
    border-left: 3px solid transparent;
    white-space: nowrap;
}

.nav-item:hover {
    color: var(--admin-ink, #23201a);
    background: rgba(168, 121, 44, 0.07);
}

.nav-item.active {
    color: var(--admin-brass, #a8792c);
    background: rgba(168, 121, 44, 0.1);
    border-left-color: var(--admin-brass, #a8792c);
    font-weight: 500;
}

.nav-bottom {
    flex-shrink: 0;
    border-top: 1px solid var(--admin-line, #e3ddce);
    padding-top: 8px;
    padding-bottom: 12px;
}

.nav-back {
    font-size: 0.85rem;
}

/* Main content area */
.admin-main {
    margin-left: 268px;
    flex: 1;
    padding: 28px 32px;
    max-width: 1180px;
}

/* Mobile hamburger */
.sidebar-toggle {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 150;
    background: #ffffff;
    border: 1px solid var(--admin-line-strong, #d2c7ac);
    color: var(--admin-ink, #23201a);
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    box-shadow: 0 2px 8px rgba(35, 26, 12, 0.12);
}

.sidebar-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(35, 32, 26, 0.4);
    z-index: 90;
}

/* Mobile */
@media (max-width: 768px) {
    .admin-sidebar {
        transform: translateX(-100%);
        transition: transform 0.25s ease;
    }

    .admin-sidebar.open {
        transform: translateX(0);
    }

    .admin-main {
        margin-left: 0;
        padding: 60px 16px 24px;
    }
}
</style>
