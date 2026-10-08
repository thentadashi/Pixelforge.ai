<script setup>
import { reactive, ref, computed, watch } from "vue";
import { useRoute } from "vue-router";
import { seed, sell, loadDemo } from "../demo-store";
import { money } from "../api";
const route = useRoute();
const demos = {
    business: ["Business operations", "Inventory, sales & reporting"],
    government: ["Government services", "Records & service requests"],
    school: ["School management", "Enrollment & student records"],
    corporate: ["Corporate workflows", "Requests & approvals"],
};
const type = computed(() => route.params.type);
const title = computed(() => demos[type.value]);
let initial;
try {
    initial = loadDemo(localStorage);
} catch {
    initial = seed();
}
const state = reactive(initial);
const search = ref(""),
    message = ref(""),
    name = ref(""),
    kind = ref(""),
    subject = ref(""),
    department = ref(""),
    amount = ref(1),
    product = reactive({ sku: "", name: "", stock: 0, price: 0 });
watch(
    state,
    () => {
        try {
            localStorage.setItem("pixelforge.demo.v1", JSON.stringify(state));
        } catch {
            message.value =
                "Changes work for this session; browser storage is unavailable.";
        }
    },
    { deep: true },
);
watch(type, () => {
    name.value = "";
    kind.value = "";
    search.value = "";
    message.value = "";
});
const products = computed(() =>
    state.products
        .map((p, i) => ({ ...p, index: i }))
        .filter((p) =>
            (p.sku + p.name).toLowerCase().includes(search.value.toLowerCase()),
        ),
);
function reset() {
    if (confirm("Reset all fictional demo records in this browser?")) {
        Object.assign(state, seed());
        message.value = "Demo records reset.";
    }
}
function sale(i) {
    if (sell(state, i))
        message.value = "Sale recorded. Stock and sales totals updated.";
}
function addRecord() {
    const list = type.value === "government" ? state.requests : state.students;
    list.push({
        id: Math.max(0, ...list.map((r) => r.id)) + 1,
        name: name.value,
        kind: kind.value,
        status: "Pending",
    });
    name.value = "";
    message.value = "Fictional record added.";
}
function addApproval() {
    state.approvals.push({
        id: Math.max(0, ...state.approvals.map((r) => r.id)) + 1,
        subject: subject.value,
        department: department.value,
        amount: Number(amount.value),
        status: "Pending",
    });
    subject.value = "";
    department.value = "";
    amount.value = 1;
    message.value = "Fictional request submitted.";
}
function addProduct() {
    if (
        state.products.some(
            (p) => p.sku.toLowerCase() === product.sku.toLowerCase(),
        )
    ) {
        message.value = "Choose a unique SKU.";
        return;
    }
    state.products.push({
        ...product,
        stock: Number(product.stock),
        price: Number(product.price),
    });
    Object.assign(product, { sku: "", name: "", stock: 0, price: 0 });
    message.value = "Fictional product added.";
}
</script>
<template>
    <div class="notice">
        Interactive demo · Fictional data saved only in this browser · Separate
        from real client projects
    </div>
    <main class="wrap section">
        <template v-if="title"
            ><div class="toolbar">
                <div>
                    <span class="eyebrow">{{ title[1] }}</span>
                    <h1 class="page-title">{{ title[0] }}</h1>
                </div>
                <button @click="reset">Reset demo</button>
            </div>
            <div class="admin-links">
                <RouterLink
                    v-for="(d, key) in demos"
                    :key="key"
                    class="btn secondary"
                    :to="'/demo/' + key"
                    >{{ d[0] }}</RouterLink
                >
            </div>
            <p v-if="message" class="success compact" role="status">
                {{ message }}
            </p>
            <template v-if="type === 'business'"
                ><div class="stats">
                    <div class="stat">
                        <small>Products</small
                        ><strong>{{ state.products.length }}</strong>
                    </div>
                    <div class="stat">
                        <small>Units in stock</small
                        ><strong>{{
                            state.products.reduce((n, p) => n + p.stock, 0)
                        }}</strong>
                    </div>
                    <div class="stat">
                        <small>Demo sales</small
                        ><strong>{{ money(state.sales) }}</strong>
                    </div>
                </div>
                <section class="panel">
                    <div class="toolbar">
                        <h3>Product inventory</h3>
                        <input
                            v-model="search"
                            aria-label="Search products"
                            placeholder="Search products…"
                        />
                    </div>
                    <div class="tablewrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Product</th>
                                    <th>Stock</th>
                                    <th>Unit price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="p in products" :key="p.index">
                                    <td>{{ p.sku }}</td>
                                    <td>{{ p.name }}</td>
                                    <td>{{ p.stock }}</td>
                                    <td>{{ money(p.price) }}</td>
                                    <td>
                                        <button
                                            :disabled="p.stock < 1"
                                            @click="sale(p.index)"
                                        >
                                            Record 1 sale
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="!products.length">
                                    <td colspan="5">No matching products.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <form class="panel form" @submit.prevent="addProduct">
                    <h3>Add a demo product</h3>
                    <div class="form-grid">
                        <label
                            >SKU<input
                                v-model="product.sku"
                                required
                                maxlength="50" /></label
                        ><label
                            >Product name<input
                                v-model="product.name"
                                required
                                maxlength="255" /></label
                        ><label
                            >Stock<input
                                v-model="product.stock"
                                type="number"
                                min="0"
                                step="1"
                                required /></label
                        ><label
                            >Price (PHP)<input
                                v-model="product.price"
                                type="number"
                                min="0"
                                step=".01"
                                required
                        /></label>
                    </div>
                    <button class="primary">Add product</button>
                </form></template
            >
            <template v-else-if="['government', 'school'].includes(type)"
                ><section class="panel">
                    <h3>
                        {{
                            type === "government"
                                ? "Service requests"
                                : "Enrollment records"
                        }}
                    </h3>
                    <div class="tablewrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Name</th>
                                    <th>
                                        {{
                                            type === "government"
                                                ? "Service"
                                                : "Program"
                                        }}
                                    </th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="r in type === 'government'
                                        ? state.requests
                                        : state.students"
                                    :key="r.id"
                                >
                                    <td>{{ r.id }}</td>
                                    <td>{{ r.name }}</td>
                                    <td>{{ r.kind }}</td>
                                    <td>{{ r.status }}</td>
                                    <td>
                                        <button
                                            :disabled="
                                                [
                                                    'Completed',
                                                    'Enrolled',
                                                ].includes(r.status)
                                            "
                                            @click="
                                                r.status =
                                                    type === 'school'
                                                        ? 'Enrolled'
                                                        : r.status === 'Pending'
                                                          ? 'Under review'
                                                          : 'Completed'
                                            "
                                        >
                                            {{
                                                type === "school"
                                                    ? "Enroll"
                                                    : r.status === "Pending"
                                                      ? "Review"
                                                      : "Complete"
                                            }}
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <form class="panel form" @submit.prevent="addRecord">
                    <h3>
                        {{
                            type === "government"
                                ? "New service request"
                                : "Register a student"
                        }}
                    </h3>
                    <label
                        >Name<input
                            v-model="name"
                            required
                            maxlength="255" /></label
                    ><label
                        >{{ type === "government" ? "Service" : "Program"
                        }}<select v-model="kind" required>
                            <option disabled value="">Choose one</option>
                            <option
                                v-for="k in type === 'government'
                                    ? [
                                          'Barangay clearance',
                                          'Certificate of residency',
                                          'Business endorsement',
                                      ]
                                    : [
                                          'Information Technology',
                                          'Business Administration',
                                          'Teacher Education',
                                      ]"
                                :key="k"
                            >
                                {{ k }}
                            </option>
                        </select></label
                    ><button class="primary">Create fictional record</button>
                </form></template
            >
            <template v-else
                ><section class="panel">
                    <h3>Approval queue</h3>
                    <div class="tablewrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Request</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Decision</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in state.approvals" :key="r.id">
                                    <td>{{ r.id }}</td>
                                    <td>{{ r.subject }}</td>
                                    <td>{{ r.department }}</td>
                                    <td>{{ money(r.amount) }}</td>
                                    <td>{{ r.status }}</td>
                                    <td>
                                        <template v-if="r.status === 'Pending'"
                                            ><button
                                                @click="r.status = 'Approved'"
                                            >
                                                Approve
                                            </button>
                                            <button
                                                @click="r.status = 'Declined'"
                                            >
                                                Decline
                                            </button></template
                                        ><span v-else>Decision recorded</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
                <form class="panel form" @submit.prevent="addApproval">
                    <h3>Submit a team request</h3>
                    <label
                        >Request<input
                            v-model="subject"
                            required
                            maxlength="255" /></label
                    ><label
                        >Department<input
                            v-model="department"
                            required
                            maxlength="255" /></label
                    ><label
                        >Amount (PHP)<input
                            v-model="amount"
                            type="number"
                            min="1"
                            step=".01"
                            required /></label
                    ><button class="primary">Submit for approval</button>
                </form></template
            >
            <div class="panel toolbar">
                <div>
                    <h3>Make this fit your organization.</h3>
                    <p>Discuss your workflows, roles, and reporting needs.</p>
                </div>
                <RouterLink class="btn" to="/book"
                    >Book a discovery call</RouterLink
                >
            </div></template
        ><template v-else
            ><h1 class="page-title">Demo not found</h1>
            <RouterLink class="btn" to="/demos">All demos</RouterLink></template
        >
    </main>
</template>
