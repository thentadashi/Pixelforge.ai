<script setup>
import { computed, reactive, ref, nextTick } from "vue";
import { api, label, money } from "../api";
import { schemas, newRecord } from "../resource-schema";
import Feedback from "./Feedback.vue";
const props = defineProps({
    type: String,
    records: Array,
    clients: Array,
    projects: Array,
    projectId: [String, Number],
});
const emit = defineEmits(["saved", "invite"]);
const fields = computed(() => schemas[props.type]);
const form = reactive({});
const editing = ref(false),
    editId = ref(null),
    busy = ref(false),
    error = ref(null),
    message = ref(""),
    formEl = ref(null);
const columns = computed(() =>
    fields.value.filter((f) => f.type !== "textarea").slice(0, 5),
);
function options(f) {
    return f.source
        ? (props[f.source] || []).map((x) => ({
              value: x.id,
              text: x.name + (x.organization ? " · " + x.organization : ""),
          }))
        : f.options.map((x) => ({ value: x, text: label(x) }));
}
function display(r, f) {
    if (f.source)
        return options(f).find((x) => x.value === r[f.key])?.text || r[f.key];
    if (f.key === "amount") return money(r[f.key]);
    if (f.key === "status") return label(r[f.key]);
    return r[f.key] ?? "—";
}
async function open(record = null) {
    Object.keys(form).forEach((k) => delete form[k]);
    Object.assign(
        form,
        newRecord(props.type, props.projectId),
        record
            ? Object.fromEntries(
                  fields.value.map((f) => [f.key, record[f.key] ?? ""]),
              )
            : {},
    );
    if (
        props.type === "milestones" &&
        ["approved", "revision_requested"].includes(form.status)
    )
        form.status = "ready_for_review";
    editId.value = record?.id || null;
    editing.value = true;
    error.value = null;
    message.value = "";
    await nextTick();
    formEl.value?.scrollIntoView({ block: "start", behavior: "smooth" });
    formEl.value?.querySelector("input,select,textarea")?.focus();
}
async function save() {
    busy.value = true;
    error.value = null;
    try {
        await api(
            "admin/" + props.type + (editId.value ? "/" + editId.value : ""),
            editId.value ? "PUT" : "POST",
            form,
        );
        editing.value = false;
        message.value = "Saved successfully.";
        emit("saved");
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}
const locked = (r) =>
    props.type === "quotations" && ["accepted", "declined"].includes(r.status);
</script>
<template>
    <section class="panel">
        <div class="toolbar">
            <h3>{{ label(type) }}</h3>
            <button class="primary" :disabled="busy" @click="open()">
                + Add
                {{
                    type === "clients"
                        ? "client"
                        : type === "updates"
                          ? "update"
                          : type.slice(0, -1)
                }}
            </button>
        </div>
        <Feedback :error="error" :message="message" />
        <form
            v-if="editing"
            ref="formEl"
            class="form editor spaced"
            @submit.prevent="save"
        >
            <h3>
                {{ editId ? "Edit" : "New" }}
                {{ type === "updates" ? "update" : type.slice(0, -1) }}
            </h3>
            <p v-if="type === 'milestones' && editId" class="muted">
                Saving a previously approved or revised deliverable makes it
                ready for the client to review again.
            </p>
            <div class="form-grid">
                <label
                    v-for="f in fields"
                    :key="f.key"
                    :class="{ 'full-width': f.type === 'textarea' }"
                    >{{ f.title
                    }}<textarea
                        v-if="f.type === 'textarea'"
                        v-model="form[f.key]"
                        :required="!f.optional"
                        maxlength="20000"
                    ></textarea
                    ><select
                        v-else-if="f.type === 'select'"
                        v-model="form[f.key]"
                        :required="!f.optional"
                    >
                        <option value="" disabled>
                            Choose {{ f.title.toLowerCase() }}
                        </option>
                        <option
                            v-for="o in options(f)"
                            :key="o.value"
                            :value="o.value"
                        >
                            {{ o.text }}
                        </option></select
                    ><input
                        v-else
                        v-model="form[f.key]"
                        :type="f.type"
                        :step="f.step"
                        :min="f.type === 'number' ? 0 : undefined"
                        :required="!f.optional"
                        maxlength="255"
                /></label>
            </div>
            <div class="actions">
                <button class="primary" :disabled="busy">
                    {{ busy ? "Saving…" : "Save" }}</button
                ><button
                    type="button"
                    :disabled="busy"
                    @click="
                        editing = false;
                        error = null;
                    "
                >
                    Cancel
                </button>
            </div>
        </form>
        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th v-for="f in columns" :key="f.key">{{ f.title }}</th>
                        <th v-if="type === 'updates'">Update</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in records" :key="r.id">
                        <td>#{{ r.id }}</td>
                        <td v-for="f in columns" :key="f.key">
                            {{ display(r, f) }}
                        </td>
                        <td v-if="type === 'updates'" class="wrap-cell">
                            {{ r.body }}
                        </td>
                        <td>
                            <button
                                :disabled="busy || locked(r)"
                                @click="open(r)"
                            >
                                {{
                                    locked(r) ? "Decision retained" : "Edit"
                                }}</button
                            ><button
                                v-if="
                                    type === 'clients' && !r.email_verified_at
                                "
                                :disabled="busy"
                                @click="emit('invite', r)"
                            >
                                Invite client
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!records?.length">
                        <td :colspan="columns.length + 3">
                            No {{ type }} yet. Add your first record above.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
