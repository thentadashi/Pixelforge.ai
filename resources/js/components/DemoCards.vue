<script setup>
const demos = [
    {
        key: "business",
        title: "Business operations",
        tag: "Inventory, sales & reporting",
        description:
            "Track products, record sales, and see stock levels change.",
    },
    {
        key: "government",
        title: "Government services",
        tag: "Records & service requests",
        description:
            "Organize citizen requests and move applications through review.",
    },
    {
        key: "school",
        title: "School management",
        tag: "Enrollment & student records",
        description: "Register students and manage enrollment in one place.",
    },
    {
        key: "corporate",
        title: "Corporate workflows",
        tag: "Requests & approvals",
        description: "Review team requests and keep decisions moving.",
    },
];
</script>
<template>
    <div class="cards">
        <article class="card demo-card" v-for="(d, n) in demos" :key="d.key">
            <div class="demo-art" aria-hidden="true">
                <i
                    v-for="(h, i) in [35, 65, 48, 90, 75, 110]"
                    :key="i"
                    :style="{
                        height: h - n * 3 + 'px',
                        opacity: 0.3 + i * 0.13,
                    }"
                ></i>
            </div>
            <div class="inner">
                <span class="pill">{{ d.tag }}</span>
                <h3>{{ d.title }}</h3>
                <p>{{ d.description }}</p>
                <RouterLink class="link" :to="'/demo/' + d.key"
                    >Try interactive demo ↗</RouterLink
                >
            </div>
        </article>
    </div>
</template>
