<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface FieldRow {
    path: string;
    text: string;
    source: string | null;
}

const props = defineProps<{
    application: {
        id: number;
        form_slug: string;
        form_name: string;
        reference_no: string | null;
        name: string;
        phone: string;
        address: string;
        submitted: boolean;
        submitted_at: string | null;
        payment_status: string | null;
        payment_amount: string | null;
        payment_method: string | null;
        merchant_name: string | null;
        form_data: Record<string, unknown>;
        field_sources: Record<string, string> | null;
    };
}>();

const flatten = (value: unknown, prefix = ''): Array<{ path: string; text: string }> => {
    if (Array.isArray(value)) {
        return value.flatMap((item, index) => flatten(item, `${prefix}[${index}]`));
    }

    if (value !== null && typeof value === 'object') {
        return Object.entries(value as Record<string, unknown>).flatMap(([key, item]) => flatten(item, prefix === '' ? key : `${prefix}.${key}`));
    }

    return [{ path: prefix || 'value', text: value === null || value === undefined ? '—' : String(value) }];
};

const rows: FieldRow[] = flatten(props.application.form_data).map((row) => {
    const field = row.path.split(/[.\[]/)[0];

    return {
        ...row,
        source: props.application.field_sources?.[field] ?? null,
    };
});

const sourceLabel = (source: string | null): string => {
    if (props.application.field_sources === null) {
        return 'Origin was not recorded';
    }

    if (source === 'applicant') {
        return 'Entered by the applicant';
    }

    if (source === 'backend') {
        return 'Supplied by the system';
    }

    return 'Origin was not recorded';
};
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'All applications', href: '/all-applications' }, { title: application.reference_no || 'Application', href: `/all-applications/${application.id}` }]">
        <Head :title="application.reference_no || 'Application'" />

        <div class="flex flex-col gap-4 p-4">
            <Link href="/all-applications" class="text-sm underline">Back to applications</Link>
            <h1 class="text-xl font-semibold">{{ application.form_name }}</h1>
            <p class="text-sm text-[#6b7280]">{{ application.form_slug }}</p>

            <dl class="grid gap-3 rounded-xl border border-[#e5e7eb] bg-white p-4 md:grid-cols-2">
                <div><dt class="text-xs text-[#6b7280]">Reference</dt><dd>{{ application.reference_no || '—' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Submitted</dt><dd>{{ application.submitted_at ? new Date(application.submitted_at).toLocaleString() : '—' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Name</dt><dd>{{ application.name }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Phone</dt><dd>{{ application.phone }}</dd></div>
                <div class="md:col-span-2"><dt class="text-xs text-[#6b7280]">Address</dt><dd>{{ application.address }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Application</dt><dd>{{ application.submitted ? 'Submitted' : 'Not submitted' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Payment status</dt><dd>{{ application.payment_status || '—' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Payment amount</dt><dd>{{ application.payment_amount || '—' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Payment method</dt><dd>{{ application.payment_method || '—' }}</dd></div>
                <div><dt class="text-xs text-[#6b7280]">Merchant</dt><dd>{{ application.merchant_name || '—' }}</dd></div>
            </dl>

            <section class="rounded-xl border border-[#e5e7eb] bg-white p-4">
                <h2 class="mb-3 font-semibold">Form details</h2>
                <p v-if="application.field_sources === null" class="mb-3 text-sm text-[#6b7280]">The origin of these values was not recorded.</p>
                <div v-if="rows.length === 0" class="text-sm text-[#6b7280]">No form-specific details were stored.</div>
                <div v-for="row in rows" :key="row.path" class="border-b py-2">
                    <div class="text-xs text-[#6b7280]">{{ row.path }}</div>
                    <div class="whitespace-pre-wrap">{{ row.text }}</div>
                    <div class="text-xs text-[#6b7280]">{{ sourceLabel(row.source) }}</div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
