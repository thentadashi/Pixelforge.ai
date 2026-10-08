<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from "vue";
import { useRoute, useRouter } from "vue-router";
import { api, session, label, money, progress } from "../api";
import Feedback from "../components/Feedback.vue";
import ResourceEditor from "../components/ResourceEditor.vue";
import ContentEditor from "../components/ContentEditor.vue";
import SupportPanel from "../components/SupportPanel.vue";
const route = useRoute(),
    router = useRouter();
const admin = computed(() => route.path.startsWith("/admin"));
const base = computed(() => (admin.value ? "/admin" : "/portal"));
const tabs = computed(() =>
    admin.value
        ? [
              "Overview",
              "Bookings",
              "Clients",
              "Quotations",
              "Projects",
              "Milestones",
              "Updates",
              "Support",
              "Files",
              "Invoices",
              "Website",
          ]
        : [
              "Overview",
              "Quotations",
              "Milestones",
              "Approvals",
              "Support",
              "Files",
              "Invoices",
          ],
);
const tab = computed(
    () =>
        tabs.value.find(
            (t) =>
                t.toLowerCase() ===
                String(route.params.tab || "overview").toLowerCase(),
        ) || "Overview",
);
const data = reactive({
    projects: [],
    milestones: [],
    quotations: [],
    tickets: [],
    replies: [],
    files: [],
    invoices: [],
    updates: [],
    revisions: [],
});
const adminData = reactive({ clients: [], bookings: [] });
const projectId = ref(""),
    loading = ref(true),
    busy = ref(false),
    error = ref(null),
    message = ref(""),
    invitation = ref(null),
    showInvoice = ref(null);
const revision = reactive({}),
    fileInput = ref(null),
    fileProject = ref("");
const projects = computed(() => data.projects);
const selected = computed(() =>
    projects.value.find((p) => p.id == projectId.value),
);
const filtered = (key) =>
    projectId.value
        ? data[key].filter((r) => r.project_id == projectId.value)
        : data[key];
const milestones = computed(() => filtered("milestones"));
const stats = computed(() => ({
    progress: progress(milestones.value),
    tickets: filtered("tickets").filter((t) => t.status === "open").length,
    projects: projectId.value
        ? 1
        : projects.value.filter((p) => p.status !== "archived").length,
}));
async function load() {
    loading.value = true;
    try {
        const work = await api("workspace");
        Object.assign(data, work);
        if (admin.value) Object.assign(adminData, await api("admin/data"));
        if (
            projectId.value &&
            !projects.value.some((p) => p.id == projectId.value)
        )
            projectId.value = "";
    } catch (e) {
        error.value = e;
        if (e.status === 401) router.push("/login");
    } finally {
        loading.value = false;
    }
}
onMounted(load);
watch(admin, load);
watch(
    () => route.fullPath,
    () => {
        message.value = "";
        error.value = null;
        showInvoice.value = null;
    },
);
async function act(path, method, body, text = "Saved.") {
    if (busy.value) return;
    busy.value = true;
    error.value = null;
    message.value = "";
    try {
        const result = await api(path, method, body);
        message.value = result.message || text;
        await load();
        return result;
    } catch (e) {
        error.value = e;
    } finally {
        busy.value = false;
    }
}
async function invite(client) {
    if (busy.value) return;
    const send = confirm(
        "Send the invitation by email? Choose Cancel to create a copyable link only.",
    );
    const result = await act(
        "admin/clients/" + client.id + "/invitation",
        "POST",
        { send_email: send },
    );
    if (result) invitation.value = { ...result, client: client.name };
}
async function copyInvite() {
    try {
        await navigator.clipboard.writeText(invitation.value.url);
        message.value = "Invitation copied.";
    } catch {
        message.value = "Select the invitation link below and copy it.";
    }
}
async function decisionQuote(q, decision) {
    if (
        confirm(
            (decision === "accepted"
                ? "Accept this quotation and start the project"
                : "Decline this quotation") + "?",
        )
    )
        await act("quotations/" + q.id + "/decision", "POST", { decision });
}
async function approve(m) {
    if (confirm("Approve this deliverable?"))
        await act("milestones/" + m.id + "/decision", "POST", {
            decision: "approved",
        });
}
async function requestRevision(m) {
    const result = await act("milestones/" + m.id + "/decision", "POST", {
        decision: "revision_requested",
        note: revision[m.id],
    });
    if (result) revision[m.id] = "";
}
async function upload() {
    const file = fileInput.value?.files[0];
    if (!file) {
        error.value = new Error("Choose a file.");
        return;
    }
    const form = new FormData();
    form.append("project_id", projectId.value || fileProject.value);
    form.append("file", file);
    const result = await act("files", "POST", form, "File uploaded.");
    if (result) fileInput.value.value = "";
}
async function removeFile(file) {
    if (confirm("Permanently remove " + file.name + "?"))
        await act("files/" + file.id, "DELETE");
}
function nameFor(id) {
    return projects.value.find((p) => p.id === id)?.name || "#" + id;
}
async function printInvoice(invoice) {
    showInvoice.value = invoice;
    await nextTick();
    window.print();
}
</script>
<template>
    <div class="shell">
        <aside class="sidebar" aria-label="Workspace navigation">
            <RouterLink
                v-for="t in tabs"
                :key="t"
                :class="{ active: tab === t }"
                :to="base + '/' + t.toLowerCase()"
                >{{ t
                }}<span
                    v-if="
                        t === 'Bookings' &&
                        adminData.bookings.filter(
                            (b) => b.status === 'requested',
                        ).length
                    "
                    class="count"
                    >{{
                        adminData.bookings.filter(
                            (b) => b.status === "requested",
                        ).length
                    }}</span
                ></RouterLink
            >
        </aside>
        <main class="workspace">
            <div class="toolbar">
                <div>
                    <span class="eyebrow">{{
                        admin
                            ? "PixelForge team workspace"
                            : (session.user.organization || session.user.name) +
                              " · Client workspace"
                    }}</span>
                    <h1>{{ tab }}</h1>
                </div>
                <label
                    v-if="projects.length && tab !== 'Website'"
                    class="project-select"
                    >Project<select v-model="projectId">
                        <option value="">All projects</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select></label
                >
            </div>
            <Feedback :error="error" :message="message" />
            <p v-if="loading" role="status">Loading workspace…</p>
            <section v-if="invitation" class="panel form">
                <h3>Invitation for {{ invitation.client }}</h3>
                <p>
                    {{ invitation.message }}
                    {{
                        invitation.email_dispatched
                            ? "Submitted to the " +
                              invitation.mailer +
                              " mailer."
                            : ""
                    }}
                </p>
                <label
                    >Invitation link (valid for seven days)<input
                        :value="invitation.url"
                        readonly
                        @focus="$event.target.select()"
                /></label>
                <div class="actions">
                    <button @click="copyInvite">Copy link</button
                    ><button @click="invitation = null">Dismiss</button>
                </div>
                <p v-if="invitation.mailer === 'log'" class="muted">
                    Email is in local log mode. Configure SMTP before sending
                    invitations to clients.
                </p>
            </section>
            <template v-if="tab === 'Overview'"
                ><div class="stats">
                    <div class="stat">
                        <small>Projects</small
                        ><strong>{{ stats.projects }}</strong>
                    </div>
                    <div class="stat">
                        <small>Approved milestones</small
                        ><strong>{{ stats.progress }}%</strong>
                    </div>
                    <div class="stat">
                        <small>Open tickets</small
                        ><strong>{{ stats.tickets }}</strong>
                    </div>
                </div>
                <p v-if="!projects.length && !loading" class="panel">
                    {{
                        admin
                            ? "Start by creating a client and sending an invitation. You can then prepare a quotation or create a project."
                            : "Your workspace is ready. Quotations and project updates will appear here when PixelForge adds them."
                    }}
                </p>
                <article
                    v-for="p in projectId
                        ? projects.filter((p) => p.id == projectId)
                        : projects"
                    :key="p.id"
                    class="panel"
                >
                    <span class="pill">{{ label(p.status) }}</span>
                    <h3 class="spaced">{{ p.name }}</h3>
                    <p class="preserve">{{ p.description }}</p>
                    <div
                        class="progress"
                        :aria-label="
                            progress(
                                data.milestones.filter(
                                    (m) => m.project_id === p.id,
                                ),
                            ) + ' percent approved'
                        "
                    >
                        <i
                            :style="{
                                width:
                                    progress(
                                        data.milestones.filter(
                                            (m) => m.project_id === p.id,
                                        ),
                                    ) + '%',
                            }"
                        ></i>
                    </div>
                    <div class="toolbar">
                        <span class="muted"
                            >{{
                                progress(
                                    data.milestones.filter(
                                        (m) => m.project_id === p.id,
                                    ),
                                )
                            }}% milestones approved · Target:
                            {{ p.target_date || "To be agreed" }}</span
                        ><RouterLink
                            class="link"
                            :to="base + '/milestones'"
                            @click="projectId = p.id"
                            >View milestones ↗</RouterLink
                        >
                    </div>
                </article>
                <section class="panel">
                    <h3>Latest project updates</h3>
                    <article
                        v-for="u in filtered('updates').slice(0, 10)"
                        :key="u.id"
                        class="reply"
                    >
                        <b>{{ nameFor(u.project_id) }}</b>
                        <p class="preserve">{{ u.body }}</p>
                    </article>
                    <p v-if="!filtered('updates').length">No updates yet.</p>
                </section></template
            >
            <template v-else-if="tab === 'Bookings' && admin"
                ><section class="panel">
                    <h3>Discovery requests</h3>
                    <p>
                        Review each request, contact the client, then confirm
                        the appointment. Cancelling releases the reserved time.
                    </p>
                    <article
                        v-for="b in adminData.bookings"
                        :key="b.id"
                        class="booking-row"
                    >
                        <div class="toolbar">
                            <h3>{{ b.organization }} · {{ b.name }}</h3>
                            <span class="pill"
                                >{{ b.date }} · {{ b.slot }} Philippine
                                time</span
                            >
                        </div>
                        <p>
                            <a :href="'mailto:' + b.email">{{ b.email }}</a> ·
                            {{ b.sector }}
                        </p>
                        <p class="preserve">{{ b.requirements }}</p>
                        <small>Reference: {{ b.reference }}</small>
                        <form
                            class="form spaced"
                            @submit.prevent="
                                act('admin/bookings/' + b.id, 'PATCH', {
                                    status: b.status,
                                    notes: b.notes,
                                })
                            "
                        >
                            <label
                                >Status<select v-model="b.status">
                                    <option
                                        v-for="s in [
                                            'requested',
                                            'confirmed',
                                            'completed',
                                            'cancelled',
                                        ]"
                                        :key="s"
                                        :value="s"
                                    >
                                        {{ label(s) }}
                                    </option>
                                </select></label
                            ><label
                                >Internal notes<textarea
                                    v-model="b.notes"
                                    maxlength="10000"
                                ></textarea></label
                            ><button :disabled="busy">
                                {{ busy ? "Saving…" : "Save booking" }}
                            </button>
                        </form>
                    </article>
                    <p v-if="!adminData.bookings.length && !loading">
                        No discovery requests yet.
                    </p>
                </section></template
            >
            <ResourceEditor
                v-else-if="
                    admin &&
                    [
                        'Clients',
                        'Projects',
                        'Quotations',
                        'Milestones',
                        'Updates',
                        'Invoices',
                    ].includes(tab)
                "
                :key="tab"
                :type="tab.toLowerCase()"
                :records="
                    tab === 'Clients'
                        ? adminData.clients
                        : tab === 'Projects'
                          ? projects
                          : tab === 'Quotations'
                            ? data.quotations
                            : filtered(tab.toLowerCase())
                "
                :clients="adminData.clients"
                :projects="projects"
                :project-id="projectId"
                @saved="load"
                @invite="invite"
            />
            <ContentEditor
                v-else-if="admin && tab === 'Website'"
                @saved="load"
            />
            <template v-else-if="tab === 'Quotations'"
                ><article
                    v-for="q in data.quotations"
                    :key="q.id"
                    class="panel"
                >
                    <div class="toolbar">
                        <h3>{{ q.title }}</h3>
                        <span class="pill">{{ label(q.status) }}</span>
                    </div>
                    <p class="preserve">{{ q.scope }}</p>
                    <strong>{{ money(q.amount) }}</strong>
                    <p>Valid until {{ q.valid_until }}</p>
                    <div v-if="q.status === 'sent'" class="actions">
                        <button
                            class="primary"
                            :disabled="busy"
                            @click="decisionQuote(q, 'accepted')"
                        >
                            {{
                                busy ? "Processing…" : "Accept quotation"
                            }}</button
                        ><button
                            :disabled="busy"
                            @click="decisionQuote(q, 'declined')"
                        >
                            Decline
                        </button>
                    </div>
                </article>
                <p v-if="!data.quotations.length && !loading" class="panel">
                    No quotations available yet.
                </p></template
            >
            <template v-else-if="['Milestones', 'Approvals'].includes(tab)"
                ><article v-for="m in milestones" :key="m.id" class="panel">
                    <div class="toolbar">
                        <h3>{{ m.title }}</h3>
                        <span class="pill">{{ label(m.status) }}</span>
                    </div>
                    <p class="muted">
                        {{ nameFor(m.project_id) }} · Target:
                        {{ m.target_date || "To be agreed" }}
                    </p>
                    <p class="preserve">{{ m.description }}</p>
                    <p v-if="m.approved_at" class="green compact">
                        Approved on {{ m.approved_at }}
                    </p>
                    <div v-if="m.status === 'ready_for_review'" class="actions">
                        <button
                            class="primary"
                            :disabled="busy"
                            @click="approve(m)"
                        >
                            {{ busy ? "Saving…" : "Approve deliverable" }}
                        </button>
                    </div>
                    <form
                        v-if="m.status === 'ready_for_review'"
                        class="form spaced"
                        @submit.prevent="requestRevision(m)"
                    >
                        <label
                            >Request a revision<textarea
                                v-model="revision[m.id]"
                                required
                                maxlength="10000"
                                placeholder="Describe what should change."
                            ></textarea></label
                        ><button :disabled="busy">
                            {{ busy ? "Saving…" : "Submit revision request" }}
                        </button>
                    </form>
                    <div
                        v-for="r in data.revisions.filter(
                            (r) => r.milestone_id === m.id,
                        )"
                        :key="r.id"
                        class="reply"
                    >
                        <b>Revision requested</b>
                        <p class="preserve">{{ r.note }}</p>
                    </div>
                </article>
                <p v-if="!milestones.length && !loading" class="panel">
                    No deliverables yet.
                </p></template
            >
            <SupportPanel
                v-else-if="tab === 'Support'"
                :key="projectId"
                :tickets="data.tickets"
                :replies="data.replies"
                :projects="projects"
                :project-id="projectId"
                @saved="load"
            />
            <template v-else-if="tab === 'Files'"
                ><form class="panel form" @submit.prevent="upload">
                    <h3>Share a project file</h3>
                    <label v-if="!projectId"
                        >Project<select v-model="fileProject" required>
                            <option value="" disabled>Choose a project</option>
                            <option
                                v-for="p in projects"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.name }}
                            </option>
                        </select></label
                    ><label
                        >File (maximum 10 MB)<input
                            ref="fileInput"
                            type="file"
                            required
                            accept=".pdf,.docx,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.zip"
                    /></label>
                    <p class="muted">
                        Project files are private. Only your client account and
                        the PixelForge team can download them.
                    </p>
                    <button
                        class="primary"
                        :disabled="busy || !projects.length"
                    >
                        {{ busy ? "Uploading…" : "Upload file" }}
                    </button>
                </form>
                <section class="panel">
                    <h3>Project files</h3>
                    <div class="tablewrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Project</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="f in filtered('files')" :key="f.id">
                                    <td>{{ f.name }}</td>
                                    <td>{{ nameFor(f.project_id) }}</td>
                                    <td>{{ (f.size / 1024).toFixed(1) }} KB</td>
                                    <td>
                                        <a
                                            class="btn secondary small"
                                            :href="
                                                '/api/files/' +
                                                f.id +
                                                '/download'
                                            "
                                            >Download</a
                                        >
                                        <button
                                            v-if="
                                                admin ||
                                                f.user_id === session.user.id
                                            "
                                            :disabled="busy"
                                            @click="removeFile(f)"
                                        >
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!filtered('files').length">
                                    <td colspan="4">No files uploaded yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section></template
            >
            <template v-else-if="tab === 'Invoices'"
                ><article
                    v-for="i in filtered('invoices')"
                    :key="i.id"
                    class="panel"
                >
                    <div class="toolbar">
                        <h3>{{ i.reference }}</h3>
                        <span class="pill">{{ label(i.status) }}</span>
                    </div>
                    <p>{{ i.description }} · {{ nameFor(i.project_id) }}</p>
                    <strong>{{ money(i.amount) }}</strong>
                    <p>Due: {{ i.due_date }}</p>
                    <button @click="printInvoice(i)">Print / save PDF</button>
                </article>
                <p
                    v-if="!filtered('invoices').length && !loading"
                    class="panel"
                >
                    No invoices issued yet.
                </p>
                <p class="muted">
                    Payments are arranged directly with PixelForge. This portal
                    does not collect card payments.
                </p></template
            >
            <section v-if="showInvoice" class="panel invoice-viewer">
                <div class="toolbar">
                    <h2>PixelForge.ai</h2>
                    <button class="no-print" @click="showInvoice = null">
                        Close
                    </button>
                </div>
                <h3 class="spaced">Invoice {{ showInvoice.reference }}</h3>
                <p>{{ nameFor(showInvoice.project_id) }}</p>
                <p>
                    Client:
                    {{
                        admin
                            ? adminData.clients.find(
                                  (c) =>
                                      c.id ===
                                      projects.find(
                                          (p) =>
                                              p.id === showInvoice.project_id,
                                      )?.client_id,
                              )?.name
                            : session.user.name
                    }}
                </p>
                <p>{{ showInvoice.description }}</p>
                <h2>{{ money(showInvoice.amount) }}</h2>
                <p>
                    Due date: {{ showInvoice.due_date }} ·
                    {{ label(showInvoice.status) }}
                </p>
            </section>
        </main>
    </div>
</template>
