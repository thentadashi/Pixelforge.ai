<script setup>
defineProps({ error: Object, message: String });
</script>
<template>
    <div v-if="error" class="error" role="alert">
        <b>{{ error.message }}</b>
        <ul v-if="Object.keys(error.errors || {}).length">
            <li v-for="(messages, field) in error.errors" :key="field">
                {{ messages.join(" ") }}
            </li>
        </ul>
    </div>
    <p v-if="message" class="success compact" role="status">{{ message }}</p>
</template>
