<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface ApplicationRow {
    id: number;
    form_slug: string;
    form_name: string;
    name: string;
    phone: string;
    address: string;
    payment_status: string | null;
    payment_amount: string | null;
    payment_reference: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    applications: {
        data: ApplicationRow[];
        links: PaginationLink[];
        total: number;
    };
}>();
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'All applications', href: '/all-applications' }]">
        <Head title="All applications" />

        <div class="flex flex-col gap-4 p-4">
            <h1 class="text-xl font-semibold">All applications</h1>
            <p class="text-sm text-[#6b7280]">{{ applications.total }} submissions in all_applications.</p>

            <div class="overflow-x-auto rounded-xl border border-[#e5e7eb] bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b bg-[#f9fafb] text-[#4b5563]">
                        <tr>
                            <th class="px-3 py-2">ID</th>
                            <th class="px-3 py-2">Form</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Fee</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="applications.data.length === 0">
                            <td class="px-3 py-6 text-[#6b7280]" colspan="7">No submissions yet.</td>
                        </tr>
                        <tr v-for="row in applications.data" :key="row.id" class="border-b">
                            <td class="px-3 py-2">{{ row.id }}</td>
                            <td class="px-3 py-2">
                                <div>{{ row.form_name }}</div>
                                <div class="text-xs text-[#6b7280]">{{ row.form_slug }}</div>
                            </td>
                            <td class="px-3 py-2">{{ row.name }}</td>
                            <td class="px-3 py-2">{{ row.phone }}</td>
                            <td class="px-3 py-2">{{ row.payment_amount || '—' }}</td>
                            <td class="px-3 py-2">{{ row.payment_status || '—' }}</td>
                            <td class="px-3 py-2">{{ row.payment_reference || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav class="flex flex-wrap gap-2">
                <Link
                    v-for="(link, index) in applications.links"
                    :key="index"
                    :href="link.url || ''"
                    class="rounded border px-3 py-1 text-sm"
                    :class="link.active ? 'bg-[#1b2c40] text-white' : 'bg-white'"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AppLayout>
</template>
