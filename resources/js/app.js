import "../css/app.css";
import { createApp } from "vue";
import { createRouter, createWebHistory } from "vue-router";
import App from "./App.vue";
import PublicPage from "./pages/PublicPage.vue";
import BookingPage from "./pages/BookingPage.vue";
import AuthPage from "./pages/AuthPage.vue";
import DemoPage from "./pages/DemoPage.vue";
import WorkspacePage from "./pages/WorkspacePage.vue";
import { api, session } from "./api";
export const router = createRouter({
    history: createWebHistory(),
    scrollBehavior: (to, from) =>
        to.path !== from.path ? { top: 0 } : undefined,
    routes: [
        { path: "/", component: PublicPage },
        { path: "/services", component: PublicPage },
        { path: "/about", component: PublicPage },
        { path: "/demos", component: PublicPage },
        { path: "/book", component: BookingPage },
        { path: "/demo/:type", component: DemoPage },
        { path: "/login", component: AuthPage },
        { path: "/forgot-password", component: AuthPage },
        { path: "/reset-password/:token", component: AuthPage },
        { path: "/invite/:token", component: AuthPage },
        {
            path: "/portal/:tab?",
            component: WorkspacePage,
            meta: { auth: true },
        },
        {
            path: "/admin/:tab?",
            component: WorkspacePage,
            meta: { auth: true, admin: true },
        },
        {
            path: "/:pathMatch(.*)*",
            component: PublicPage,
            props: { missing: true },
        },
    ],
});
router.beforeEach(async (to) => {
    if (!session.loaded) {
        const result = await api("me");
        session.user = result.user;
        session.loaded = true;
    }
    if (to.meta.auth && !session.user)
        return { path: "/login", query: { next: to.fullPath } };
    if (to.meta.admin && session.user.role !== "admin") return "/portal";
});
createApp(App).use(router).mount("#app");
