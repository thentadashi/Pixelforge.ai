<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { session } from "../api";
import DemoCards from "../components/DemoCards.vue";
defineProps({ missing: Boolean });
const route = useRoute();
const c = computed(() => session.content);
</script>
<template>
    <main v-if="missing" class="wrap section">
        <h1>Page not found</h1>
        <p>This page is unavailable.</p>
        <RouterLink class="btn" to="/">Back to home</RouterLink>
    </main>
    <main v-else-if="route.path === '/'">
        <div class="wrap">
            <section class="hero">
                <div>
                    <span class="eyebrow">{{ c.home.eyebrow }}</span>
                    <h1>{{ c.home.title }}</h1>
                    <p>{{ c.home.description }}</p>
                    <div class="actions">
                        <RouterLink class="btn" to="/book"
                            >{{ c.home.cta }} ↗</RouterLink
                        ><RouterLink class="btn secondary" to="/demos"
                            >Explore live demos</RouterLink
                        >
                    </div>
                    <div class="micro">
                        <span>Built for your workflow</span
                        ><span>Supported beyond launch</span>
                    </div>
                </div>
                <div class="visual">
                    <div class="mock">
                        <div class="mocktop">
                            <b>▦ &nbsp; Operations overview</b
                            ><span>Illustrative workspace</span>
                        </div>
                        <div class="mockbody">
                            <h3>Everything moving. Together.</h3>
                            <div class="stats">
                                <div
                                    class="stat"
                                    v-for="s in [
                                        ['Requests', 128],
                                        ['Completed', 96],
                                        ['In review', 32],
                                    ]"
                                    :key="s[0]"
                                >
                                    <small>{{ s[0] }}</small
                                    ><strong>{{ s[1] }}</strong>
                                </div>
                            </div>
                            <div class="toolbar">
                                <small>Activity this week</small
                                ><span class="badge">Weekly overview</span>
                            </div>
                            <div
                                class="chart"
                                aria-label="Illustrative weekly activity"
                            >
                                <i
                                    v-for="(h, i) in [
                                        35, 55, 40, 80, 68, 95, 85,
                                    ]"
                                    :key="i"
                                    :style="{ height: h + '%' }"
                                ></i>
                            </div>
                            <p class="muted">Mon · Tue · Wed · Thu · Fri</p>
                        </div>
                    </div>
                    <div class="float">
                        ✓ Less paperwork. More progress.<small
                            >One connected place to manage your work.</small
                        >
                    </div>
                </div>
            </section>
            <div class="sectors">
                <span>BUILT FOR THE WAY<br />YOUR SECTOR WORKS</span
                ><span>▦ Small & medium businesses</span
                ><span>▥ Corporations</span><span>⌂ Government</span
                ><span>▤ Education</span>
            </div>
            <section class="section">
                <div class="sectionhead">
                    <div>
                        <span class="eyebrow">What we do</span>
                        <h2>{{ c.home.services_heading }}</h2>
                    </div>
                    <p>
                        From the first discovery call to ongoing support, we
                        build around the way your organization works.
                    </p>
                </div>
                <div class="cards">
                    <article class="card" v-for="(s, i) in c.services" :key="i">
                        <span class="icon">{{ s.icon }}</span>
                        <h3>{{ s.title }}</h3>
                        <p>{{ s.description }}</p>
                        <RouterLink class="link" to="/book"
                            >Discuss your project ↗</RouterLink
                        >
                    </article>
                </div>
            </section>
        </div>
        <section class="tint">
            <div class="wrap section">
                <div class="sectionhead">
                    <div>
                        <span class="eyebrow">Explore the possibilities</span>
                        <h2>{{ c.home.demo_heading }}</h2>
                    </div>
                    <p>
                        Try sample workflows with fictional data. Your final
                        system will be tailored to your requirements.
                    </p>
                </div>
                <DemoCards />
            </div>
        </section>
        <div class="wrap section">
            <div class="split">
                <div>
                    <span class="eyebrow"
                        >A clear path from idea to launch</span
                    >
                    <h2>{{ c.home.process_heading }}</h2>
                    <p>
                        Stay involved with visible milestones, deliverable
                        approvals, and a dedicated client workspace.
                    </p>
                    <RouterLink class="btn secondary" to="/about"
                        >Meet PixelForge</RouterLink
                    >
                </div>
                <div class="steps">
                    <div
                        class="step"
                        v-for="(s, i) in [
                            [
                                'Discover',
                                'Book a call so we can understand your workflow and goals.',
                            ],
                            [
                                'Define & build',
                                'Review a tailored quotation, then follow your project in the client portal.',
                            ],
                            [
                                'Launch & support',
                                'Bring your system into daily use, with optional hosting and maintenance.',
                            ],
                        ]"
                        :key="i"
                    >
                        <b>0{{ i + 1 }}</b>
                        <div>
                            <h3>{{ s[0] }}</h3>
                            <p>{{ s[1] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="wrap section">
            <div class="cta">
                <div>
                    <h2>{{ c.home.closing_heading }}</h2>
                    <p>{{ c.home.closing_description }}</p>
                </div>
                <RouterLink class="btn" to="/book"
                    >Book a discovery call ↗</RouterLink
                >
            </div>
        </div>
    </main>
    <main v-else-if="route.path === '/demos'" class="wrap section">
        <div class="sectionhead">
            <div>
                <span class="eyebrow">Solutions by sector</span>
                <h1 class="page-title">Try a better way to work.</h1>
            </div>
            <p>
                Four working mini-apps. Fictional records. A starting point for
                your custom solution.
            </p>
        </div>
        <DemoCards />
    </main>
    <main v-else-if="route.path === '/services'" class="wrap section">
        <span class="eyebrow">Our services</span>
        <h1 class="page-title">Built around your operations.</h1>
        <p>
            From your first custom application to the systems that connect your
            organization.
        </p>
        <div class="cards">
            <article class="card" v-for="(s, i) in c.services" :key="i">
                <span class="icon">{{ s.icon }}</span>
                <h3>{{ s.title }}</h3>
                <p>{{ s.description }}</p>
                <RouterLink class="btn secondary" to="/book"
                    >Discuss requirements</RouterLink
                >
            </article>
        </div>
        <div class="panel">
            <h2>{{ c.maintenance.title }}</h2>
            <p>{{ c.maintenance.description }}</p>
        </div>
    </main>
    <main v-else class="wrap section">
        <div class="split">
            <div>
                <span class="eyebrow">About PixelForge.ai</span>
                <h1 class="page-title">{{ c.about.title }}</h1>
                <p>{{ c.about.mission }}</p>
                <p>{{ c.about.description }}</p>
            </div>
            <div class="panel">
                <img
                    class="founder-photo"
                    :src="c.about.founder_image"
                    :alt="c.about.founder_name + ' profile'"
                    width="160"
                    height="160"
                />
                <h2 class="spaced">{{ c.about.founder_name }}</h2>
                <p>{{ c.about.founder_role }}</p>
                <p>{{ c.about.founder_bio }}</p>
                <RouterLink class="btn" to="/book"
                    >Talk about your project</RouterLink
                >
            </div>
        </div>
        <section class="panel">
            <h3>Project case studies</h3>
            <div v-if="c.case_studies.length" class="cards spaced">
                <article class="card" v-for="(s, i) in c.case_studies" :key="i">
                    <h3>{{ s.title }}</h3>
                    <p>{{ s.description }}</p>
                </article>
            </div>
            <p v-else>
                Client stories will be added when approved for publication.
                Explore our fictional-data demos to review the workflows we can
                tailor to your organization.
            </p>
            <RouterLink class="btn secondary" to="/demos"
                >Explore demos</RouterLink
            >
        </section>
    </main>
</template>
