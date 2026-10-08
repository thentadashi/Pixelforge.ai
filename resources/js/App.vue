<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { api, session } from "./api";
const router = useRouter();
const error = ref("");
const busy = ref(false);
onMounted(async () => {
    try {
        session.content = await api("content");
    } catch (e) {
        error.value = e.message;
    }
});
async function logout() {
    busy.value = true;
    try {
        await api("logout", "POST");
        session.user = null;
        location.assign("/");
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
function reload() {
    location.reload();
}
router.onError((e) => {
    error.value = e.message;
});
</script>
<template>
    <header>
        <RouterLink class="brand" to="/"
            ><span class="logo" aria-hidden="true"
                ><i v-for="n in 9" :key="n"></i></span
            >PixelForge<em>.ai</em></RouterLink
        >
        <nav aria-label="Main navigation">
            <RouterLink to="/services">Services</RouterLink
            ><RouterLink to="/demos">Solutions & demos</RouterLink
            ><RouterLink to="/about">About us</RouterLink
            ><RouterLink
                :to="session.user?.role === 'admin' ? '/admin' : '/portal'"
                >{{
                    session.user?.role === "admin"
                        ? "Admin workspace"
                        : "Client portal"
                }}</RouterLink
            ><RouterLink class="btn" to="/book"
                >Book a discovery call ↗</RouterLink
            ><button v-if="session.user" :disabled="busy" @click="logout">
                {{ busy ? "Signing out…" : "Sign out" }}
            </button>
        </nav>
    </header>
    <div v-if="error" class="error global-error" role="alert">
        {{ error }} <button @click="reload">Retry</button>
    </div>
    <RouterView v-if="session.content" />
    <div v-else-if="!error" class="wrap section" role="status">
        Loading PixelForge…
    </div>
    <footer>
        <span>© {{ new Date().getFullYear() }} PixelForge.ai</span
        ><span>Custom software. Connected operations.</span
        ><RouterLink to="/login">Team & client sign in ↗</RouterLink>
    </footer>
</template>
