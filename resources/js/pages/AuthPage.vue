<script setup>
import { ref, reactive, computed, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, session } from "../api";
import Feedback from "../components/Feedback.vue";
const route = useRoute(),
    router = useRouter();
const form = reactive({
    email: "",
    password: "",
    password_confirmation: "",
    remember: false,
});
const busy = ref(false),
    error = ref(null),
    message = ref("");
const mode = computed(() =>
    route.path.startsWith("/invite/")
        ? "invite"
        : route.path.startsWith("/reset-password/")
          ? "reset"
          : route.path === "/forgot-password"
            ? "forgot"
            : "login",
);
const title = computed(
    () =>
        ({
            login: "Welcome to your workspace.",
            forgot: "Reset your password.",
            reset: "Choose a new password.",
            invite: "Activate your client portal.",
        })[mode.value],
);
watch(
    () => route.fullPath,
    () => {
        error.value = null;
        message.value = "";
        form.password = "";
        form.password_confirmation = "";
    },
);
async function submit() {
    busy.value = true;
    error.value = null;
    message.value = "";
    try {
        const endpoint = {
            login: "login",
            forgot: "forgot-password",
            reset: "reset-password",
            invite: "accept-invitation",
        }[mode.value];
        const data = {
            ...form,
            email:
                mode.value === "reset"
                    ? String(route.query.email || form.email)
                    : form.email,
            token: route.params.token,
        };
        const result = await api(endpoint, "POST", data);
        if (result.user) {
            session.user = result.user;
            const next = String(route.query.next || "");
            await router.push(
                next.startsWith("/admin") && result.user.role === "admin"
                    ? next
                    : next.startsWith("/portal")
                      ? next
                      : result.user.role === "admin"
                        ? "/admin"
                        : "/portal",
            );
        } else message.value = result.message;
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <main class="auth-page">
        <span class="eyebrow">PixelForge workspace</span>
        <h1 class="page-title">{{ title }}</h1>
        <p>
            {{
                mode === "invite"
                    ? "Set your own password to access your project, approvals, and support."
                    : "A private space for your project and the people building it."
            }}
        </p>
        <Feedback :error="error" :message="message" />
        <form class="panel form" @submit.prevent="submit">
            <label v-if="mode !== 'invite'"
                >Email<input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    :required="mode !== 'reset' || !route.query.email"
                    :placeholder="String(route.query.email || '')"
            /></label>
            <label v-if="mode !== 'forgot'"
                >{{
                    mode === "login"
                        ? "Password"
                        : "New password (12+ characters, with letters and numbers)"
                }}<input
                    v-model="form.password"
                    type="password"
                    :minlength="mode === 'login' ? 1 : 12"
                    :autocomplete="
                        mode === 'login' ? 'current-password' : 'new-password'
                    "
                    required
            /></label>
            <label v-if="['invite', 'reset'].includes(mode)"
                >Confirm password<input
                    v-model="form.password_confirmation"
                    type="password"
                    minlength="12"
                    autocomplete="new-password"
                    required
            /></label>
            <label v-if="mode === 'login'" class="check"
                ><input v-model="form.remember" type="checkbox" /> Remember
                me</label
            >
            <button class="primary" :disabled="busy">
                {{
                    busy
                        ? "Processing…"
                        : {
                              login: "Sign in",
                              forgot: "Send reset link",
                              reset: "Save password",
                              invite: "Activate account",
                          }[mode]
                }}
            </button>
            <RouterLink
                v-if="mode === 'login'"
                class="link"
                to="/forgot-password"
                >Forgot password?</RouterLink
            ><RouterLink v-else class="link" to="/login"
                >Back to sign in</RouterLink
            >
        </form>
        <p v-if="mode === 'login'" class="muted">
            Client accounts are created by invitation after your project is
            accepted.
        </p>
    </main>
</template>
