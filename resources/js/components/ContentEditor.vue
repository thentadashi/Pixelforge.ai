<script setup>
import { reactive, ref } from "vue";
import { api, session, label } from "../api";
import Feedback from "./Feedback.vue";
const emit = defineEmits(["saved"]);
const draft = reactive(JSON.parse(JSON.stringify(session.content)));
const busy = ref(false),
    error = ref(null),
    message = ref("");
async function save() {
    busy.value = true;
    error.value = null;
    message.value = "";
    try {
        await api("admin/content", "PUT", draft);
        session.content = JSON.parse(JSON.stringify(draft));
        message.value =
            "Website content saved. Visitors see these changes immediately.";
        emit("saved");
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}
async function photo(event) {
    const file = event.target.files[0];
    if (!file) return;
    const data = new FormData();
    data.append("image", file);
    busy.value = true;
    error.value = null;
    try {
        const result = await api("admin/founder-image", "POST", data);
        draft.about.founder_image = result.url;
        session.content.about.founder_image = result.url;
        message.value = "Founder photo updated.";
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
        event.target.value = "";
    }
}
</script>
<template>
    <form class="form" @submit.prevent="save">
        <Feedback :error="error" :message="message" />
        <div class="panel">
            <div class="toolbar">
                <div>
                    <h3>Website content</h3>
                    <p>
                        Manage the public pages, service cards, and approved
                        case studies.
                    </p>
                </div>
                <button class="primary" :disabled="busy">
                    {{ busy ? "Saving…" : "Publish content" }}
                </button>
            </div>
        </div>
        <section
            v-for="section in ['home', 'about', 'booking', 'maintenance']"
            :key="section"
            class="panel form"
        >
            <h3>{{ label(section) }}</h3>
            <div class="form-grid">
                <template v-for="(value, key) in draft[section]" :key="key"
                    ><label v-if="key !== 'founder_image'" class="full-width"
                        >{{ label(key)
                        }}<textarea
                            v-model="draft[section][key]"
                            required
                            maxlength="5000"
                        ></textarea></label></template
                ><label v-if="section === 'about'" class="full-width"
                    >Founder photo (JPG, PNG or WebP; 2 MB maximum)<img
                        class="founder-photo"
                        :src="draft.about.founder_image"
                        :alt="draft.about.founder_name" /><input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        :disabled="busy"
                        @change="photo"
                /></label>
            </div>
        </section>
        <section
            v-for="section in ['services', 'case_studies']"
            :key="section"
            class="panel form"
        >
            <div class="toolbar">
                <h3>{{ label(section) }}</h3>
                <button
                    type="button"
                    @click="
                        draft[section].push(
                            section === 'services'
                                ? { icon: '⌘', title: '', description: '' }
                                : { title: '', description: '' },
                        )
                    "
                >
                    + Add
                    {{ section === "services" ? "service" : "case study" }}
                </button>
            </div>
            <p v-if="section === 'case_studies'" class="muted">
                Publish client stories only after you have permission.
            </p>
            <fieldset v-for="(item, i) in draft[section]" :key="i" class="form">
                <legend>
                    {{ section === "services" ? "Service" : "Case study" }}
                    {{ i + 1 }}
                </legend>
                <label v-for="(value, key) in item" :key="key"
                    >{{ label(key)
                    }}<textarea
                        v-if="key === 'description'"
                        v-model="item[key]"
                        required
                        maxlength="5000"
                    ></textarea
                    ><input
                        v-else
                        v-model="item[key]"
                        required
                        maxlength="5000" /></label
                ><button type="button" @click="draft[section].splice(i, 1)">
                    Remove card
                </button>
            </fieldset>
            <p v-if="!draft[section].length">No cards published.</p>
        </section>
        <button class="primary" :disabled="busy">
            {{ busy ? "Saving…" : "Publish content" }}
        </button>
    </form>
</template>
