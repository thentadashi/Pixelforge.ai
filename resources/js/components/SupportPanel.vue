<script setup>
import { reactive, ref, computed } from "vue";
import { api, label } from "../api";
import Feedback from "./Feedback.vue";
const props = defineProps({
    tickets: Array,
    replies: Array,
    projects: Array,
    projectId: [String, Number],
});
const emit = defineEmits(["saved"]);
const draft = reactive({
        project_id: props.projectId || "",
        subject: "",
        details: "",
    }),
    reply = reactive({});
const busy = ref(false),
    error = ref(null),
    message = ref("");
const visible = computed(() =>
    props.projectId
        ? props.tickets.filter((t) => t.project_id == props.projectId)
        : props.tickets,
);
async function act(path, method, data, onSuccess) {
    busy.value = true;
    error.value = null;
    message.value = "";
    try {
        await api(path, method, data);
        onSuccess?.();
        message.value = "Saved.";
        emit("saved");
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}
function create() {
    draft.project_id = props.projectId || draft.project_id;
    act("tickets", "POST", draft, () => {
        draft.subject = "";
        draft.details = "";
    });
}
</script>
<template>
    <Feedback :error="error" :message="message" />
    <form class="panel form" @submit.prevent="create">
        <h3>Create a support ticket</h3>
        <label v-if="!projectId"
            >Project<select v-model="draft.project_id" required>
                <option value="" disabled>Choose a project</option>
                <option v-for="p in projects" :key="p.id" :value="p.id">
                    {{ p.name }}
                </option>
            </select></label
        ><label
            >Subject<input
                v-model="draft.subject"
                required
                maxlength="255" /></label
        ><label
            >Details<textarea
                v-model="draft.details"
                required
                maxlength="10000"
            ></textarea></label
        ><button class="primary" :disabled="busy || !projects.length">
            {{ busy ? "Saving…" : "Submit ticket" }}
        </button>
    </form>
    <article v-for="t in visible" :key="t.id" class="panel">
        <div class="toolbar">
            <h3>#{{ t.id }} · {{ t.subject }}</h3>
            <span class="pill">{{ label(t.status) }}</span>
        </div>
        <p class="preserve">{{ t.details }}</p>
        <div
            class="reply"
            v-for="r in replies.filter((r) => r.ticket_id === t.id)"
            :key="r.id"
        >
            <b>{{ r.author }}</b
            ><small>
                ·
                {{
                    new Date(
                        r.created_at.replace(" ", "T") + "+08:00",
                    ).toLocaleString()
                }}</small
            >
            <p class="preserve">{{ r.body }}</p>
        </div>
        <form
            class="form spaced"
            @submit.prevent="
                act(
                    'tickets/' + t.id + '/replies',
                    'POST',
                    { body: reply[t.id] },
                    () => (reply[t.id] = ''),
                )
            "
        >
            <label
                >Reply to this ticket<textarea
                    v-model="reply[t.id]"
                    required
                    maxlength="10000"
                ></textarea>
            </label>
            <div class="actions">
                <button class="primary" :disabled="busy">
                    {{ busy ? "Saving…" : "Post reply" }}</button
                ><button
                    type="button"
                    :disabled="busy"
                    @click="
                        act('tickets/' + t.id + '/status', 'PATCH', {
                            status: t.status === 'open' ? 'resolved' : 'open',
                        })
                    "
                >
                    {{
                        t.status === "open" ? "Resolve ticket" : "Reopen ticket"
                    }}
                </button>
            </div>
        </form>
    </article>
    <p v-if="!visible.length" class="panel">No support requests yet.</p>
</template>
