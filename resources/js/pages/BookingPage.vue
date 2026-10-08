<script setup>
import { ref, reactive, watch, computed } from "vue";
import { api, session, today } from "../api";
import Feedback from "../components/Feedback.vue";
const form = reactive({
    name: "",
    email: "",
    organization: "",
    sector: "Business",
    requirements: "",
    date: "",
    slot: "",
});
const busy = ref(false),
    loading = ref(false),
    error = ref(null),
    confirmation = ref(null),
    slots = ref([]);
let availabilityRequest = 0;
const minDate = today();
const maxDate = computed(() => {
    const d = new Date(minDate + "T00:00:00+08:00");
    d.setUTCMonth(d.getUTCMonth() + 3);
    return new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Manila",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(d);
});
const time = (s) =>
    new Intl.DateTimeFormat("en-PH", {
        hour: "numeric",
        minute: "2-digit",
        timeZone: "Asia/Manila",
    }).format(new Date("2000-01-01T" + s + ":00+08:00"));
async function availability() {
    const req = ++availabilityRequest;
    slots.value = [];
    form.slot = "";
    if (!form.date) return;
    loading.value = true;
    error.value = null;
    try {
        const r = await api("availability?date=" + form.date);
        if (req === availabilityRequest) slots.value = r.slots;
    } catch (e) {
        if (req === availabilityRequest) error.value = e;
    } finally {
        if (req === availabilityRequest) loading.value = false;
    }
}
watch(() => form.date, availability);
async function submit() {
    busy.value = true;
    error.value = null;
    try {
        if (!form.slot) throw new Error("Choose an available call time.");
        confirmation.value = await api("bookings", "POST", form);
    } catch (e) {
        error.value = e;
        if (e.errors?.slot) await availability();
        error.value = e;
    } finally {
        busy.value = false;
    }
}
</script>
<template>
    <main class="booking">
        <span class="eyebrow">Start with a conversation</span>
        <h1>{{ session.content.booking.title }}</h1>
        <p>{{ session.content.booking.description }}</p>
        <Feedback :error="error" />
        <section v-if="confirmation" class="success">
            <h2>Request received.</h2>
            <p>{{ confirmation.message }}</p>
            <p>
                {{ form.name }} · {{ form.date }} ·
                {{ time(form.slot) }} Philippine time
            </p>
            <p class="muted">Reference: {{ confirmation.reference }}</p>
            <RouterLink class="btn secondary" to="/">Back to home</RouterLink>
        </section>
        <div v-else class="split">
            <form class="form panel" @submit.prevent="submit">
                <label
                    >Your name<input
                        v-model="form.name"
                        required
                        maxlength="255"
                        autocomplete="name" /></label
                ><label
                    >Work email<input
                        v-model="form.email"
                        type="email"
                        required
                        maxlength="255"
                        autocomplete="email" /></label
                ><label
                    >Organization<input
                        v-model="form.organization"
                        required
                        maxlength="255"
                        autocomplete="organization" /></label
                ><label
                    >Sector<select v-model="form.sector">
                        <option
                            v-for="s in [
                                'Business',
                                'Corporation',
                                'Government',
                                'Education',
                            ]"
                            :key="s"
                        >
                            {{ s }}
                        </option>
                    </select></label
                ><label
                    >What would you like to improve?<textarea
                        v-model="form.requirements"
                        required
                        maxlength="10000"
                        placeholder="Describe your current process and what you want to change."
                    ></textarea></label
                ><label
                    >Preferred date<input
                        v-model="form.date"
                        type="date"
                        :min="minDate"
                        :max="maxDate"
                        required
                /></label>
                <div>
                    <b class="field-label">Available times · Philippine time</b>
                    <p v-if="!form.date" class="muted">
                        Choose a date to see available times.
                    </p>
                    <p v-else-if="loading" role="status">
                        Checking availability…
                    </p>
                    <p v-else-if="!slots.length">
                        No available times for this date. Please choose another
                        date.
                    </p>
                    <div class="slots">
                        <button
                            v-for="s in slots"
                            :key="s"
                            type="button"
                            :class="{ selected: form.slot === s }"
                            :aria-pressed="form.slot === s"
                            @click="form.slot = s"
                        >
                            {{ time(s) }}
                        </button>
                    </div>
                </div>
                <button
                    class="primary"
                    :disabled="busy || loading || !form.slot"
                >
                    {{ busy ? "Saving request…" : "Request discovery call" }}
                </button>
                <p class="muted">
                    Your details are used to respond to this request and discuss
                    your project.
                </p>
            </form>
            <aside class="panel">
                <h3>What we’ll discuss</h3>
                <p>
                    Your current workflow, the people who will use the system,
                    the features you need, and your target timeline.
                </p>
                <h3>No one-size-fits-all pricing.</h3>
                <p>
                    Your quotation reflects your project’s scope, integrations,
                    and support needs.
                </p>
                <span class="pill">30-minute discovery call</span>
            </aside>
        </div>
    </main>
</template>
