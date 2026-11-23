<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';

interface ApplicationRow {
    id: number;
    form_slug: string;
    form_name: string;
    name: string;
    phone: string;
    address: string;
    meta_info: string | null;
    payment_status: string | null;
    payment_amount: string | null;
    payment_reference: string | null;
    submitted: boolean;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface CreatedPayload {
    id: number;
    reference_no: string | null;
    form_slug: string;
    form_name: string;
    submitted_at: string | null;
    name: string;
    phone: string;
}

interface Filters {
    reference: string;
    name: string;
    phone: string;
    form: string;
    payment_status: string;
    submitted_from: string;
    submitted_to: string;
}

const props = defineProps<{
    applications: {
        data: ApplicationRow[];
        links: PaginationLink[];
        current_page: number;
        total: number;
    };
    filters: Filters;
    forms: Array<{ slug: string; name: string }>;
}>();

const filterForm = reactive({ ...props.filters });

const rows = ref<ApplicationRow[]>([...props.applications.data]);

watch(() => props.applications, (applications) => {
    rows.value = [...applications.data];
});

const matchesFilters = (payload: CreatedPayload): boolean => {
    const filters = props.filters;
    const includes = (field: string, value: string | null) => field === '' || (value ?? '').toLowerCase().includes(field.toLowerCase());

    if (!includes(filters.reference, payload.reference_no) || !includes(filters.name, payload.name) || !includes(filters.phone, payload.phone)) {
        return false;
    }

    if (filters.form !== '' && payload.form_slug !== filters.form && !payload.form_name.toLowerCase().includes(filters.form.toLowerCase())) {
        return false;
    }

    if (filters.payment_status !== '') {
        return false;
    }

    const submittedOn = (payload.submitted_at ?? '').slice(0, 10);

    return (filters.submitted_from === '' || submittedOn >= filters.submitted_from)
        && (filters.submitted_to === '' || submittedOn <= filters.submitted_to);
};

const showBrowserNotification = (payload: CreatedPayload) => {
    if (!('Notification' in window) || Notification.permission !== 'granted') {
        return;
    }

    const notification = new Notification('নতুন অ্যাপ্লিকেশন', {
        body: `${payload.name} (${payload.phone}) — ${payload.form_name}`,
        tag: `application-${payload.id}`, // একই আইডির নোটিফিকেশন দুইবার দেখাবে না
    });

    notification.onclick = () => {
        window.focus();
        router.visit(`/all-applications/${payload.id}`);
        notification.close();
    };
};

const onApplicationCreated = (payload: CreatedPayload) => {
    showBrowserNotification(payload); // ফিল্টার/পেজ যাই হোক, আগে নোটিফিকেশন
    prependApplication(payload);      // তারপর শর্ত মিললে টেবিলে যোগ
};

const prependApplication = (payload: CreatedPayload) => {
    if (rows.value.some((row) => row.id === payload.id) || !matchesFilters(payload)) {
        return;
    }

    if (props.applications.current_page !== 1) {
        return;
    }

    rows.value.unshift({
        id: payload.id,
        form_slug: payload.form_slug,
        form_name: payload.form_name,
        name: payload.name,
        phone: payload.phone,
        address: '',
        meta_info: null,
        payment_status: null,
        payment_amount: null,
        payment_reference: payload.reference_no,
        submitted: true,
        created_at: payload.submitted_at ?? '',
    });
};

const refreshList = () => {
    router.reload({ only: ['applications'], preserveScroll: true });
};

const applyFilters = () => {
    router.get('/all-applications', { ...filterForm }, { preserveState: true, preserveScroll: true, replace: true });
};

const browserNotificationPermission = ref<NotificationPermission>(
    'default'
);

const requestBrowserNotificationPermission = async () => {
    if (!('Notification' in window)) {
        return;
    }

    if (Notification.permission === 'default') {
        browserNotificationPermission.value =
            await Notification.requestPermission();
    } else {
        browserNotificationPermission.value =
            Notification.permission;
    }
};


onMounted(() => {
    browserNotificationPermission.value =
        'Notification' in window ? Notification.permission : 'denied';

    window.Echo?.private('all-applications').listen('.application.created', onApplicationCreated);
});

onUnmounted(() => {
    window.Echo?.private('all-applications').stopListening('.application.created', onApplicationCreated);
    window.Echo?.leave('all-applications');
});
</script>

<template>
    <AppLayout :breadcrumbs="[{ title: 'All applications', href: '/all-applications' }]">
        <Head title="All applications" />

        <div class="flex flex-col gap-4 p-4">
            <div
                v-if="browserNotificationPermission === 'default'"
                class="flex items-center justify-between rounded-xl border border-[#e5e7eb] bg-white p-3 text-sm"
            >
                <span>নতুন অ্যাপ্লিকেশন এলে ব্রাউজারে নোটিফিকেশন পেতে অনুমতি দিন।</span>
                <button
                    type="button"
                    class="rounded-lg bg-black px-3 py-1.5 text-white"
                    @click="requestBrowserNotificationPermission"
                >
                    নোটিফিকেশন চালু করুন
                </button>
            </div>
            <div v-else-if="browserNotificationPermission === 'denied'" class="text-sm text-[#b91c1c]">
                নোটিফিকেশন ব্লক করা আছে। Chrome-এর ঠিকানার বারের বাঁ পাশে 🔒 আইকনে ক্লিক করে "Notifications" Allow করুন।
            </div>
            <div class="overflow-x-auto rounded-xl border border-[#e5e7eb] bg-white">
                <table class="min-w-full text-left text-sm">
                    <thead class="border-b bg-[#f9fafb] text-[#4b5563]">
                        <tr>
                            <th class="px-3 py-2">Info</th>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Phone</th>
                            <th class="px-3 py-2">Delivery Info</th>
                            <th class="px-3 py-2">Submitted</th>
                            <th class="px-3 py-2">Payment</th>
                            <th class="px-3 py-2">Amount</th>
                            <th class="px-3 py-2">Application</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="rows.length === 0">
                            <td class="px-3 py-6 text-[#6b7280]" colspan="8">No submissions yet.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" class="border-b">
                            <td class="px-3 py-2">
                                <div>{{ forms.find((form) => form.slug === row.form_slug)?.name || row.form_name }}</div>
                                <div class="text-xs text-[#6b7280]">
                                    <Link class="underline" :href="`/all-applications/${row.id}`">{{ row.payment_reference || 'View' }}</Link>
                                </div>
                            </td>
                            <td class="px-3 py-2">{{ row.name }}</td>
                            <td class="px-3 py-2">{{ row.phone }}</td>
                            <td class="px-3 py-2">
                                {{ row.meta_info || '—' }}
                            </td>
                            <td class="px-3 py-2">{{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}</td>
                            <td class="px-3 py-2">{{ row.payment_status || '—' }}</td>
                            <td class="px-3 py-2">{{ row.payment_amount || '—' }}</td>
                            <td class="px-3 py-2">{{ row.submitted ? 'Submitted' : 'Not submitted' }}</td>
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

            <form class="grid gap-3 rounded-xl border border-[#e5e7eb] bg-white p-3 md:grid-cols-4" @submit.prevent="applyFilters">
                <input v-model="filterForm.reference" class="rounded border px-3 py-2" placeholder="Reference" />
                <input v-model="filterForm.name" class="rounded border px-3 py-2" placeholder="Applicant name" />
                <input v-model="filterForm.phone" class="rounded border px-3 py-2" placeholder="Phone" />
                <select v-model="filterForm.form" class="rounded border px-3 py-2">
                    <option value="">All forms</option>
                    <option v-for="form in forms" :key="form.slug" :value="form.slug">{{ form.name }}</option>
                </select>
                <input v-model="filterForm.payment_status" class="rounded border px-3 py-2" placeholder="Payment status" />
                <input v-model="filterForm.submitted_from" class="rounded border px-3 py-2" type="date" />
                <input v-model="filterForm.submitted_to" class="rounded border px-3 py-2" type="date" />
                <div class="flex gap-2">
                    <button type="submit" class="rounded bg-[#1b2c40] px-3 py-2 text-sm text-white">Apply</button>
                    <button type="button" class="rounded border px-3 py-2 text-sm" @click="refreshList">Refresh</button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
