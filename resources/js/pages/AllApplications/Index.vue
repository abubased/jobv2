<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Check, Copy, FileText } from 'lucide-vue-next';

interface ApplicationRow {
    id: number;
    form_slug: string;
    form_name: string;
    name: string;
    phone: string;
    address: string;
    meta_info: Record<string, unknown> | null;
    payment_method?: string | null;
    payment_status: string | null;
    payment_amount: string | null;
    payment_reference: string | null;
    submitted: boolean;
    created_at: string;
    updated_at?: string;
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
    forms: Array<{ slug: string; name: string; favicon?: string | null }>; 
}>();

const filterForm = reactive({ ...props.filters });

const rows = ref<ApplicationRow[]>([...props.applications.data]);
const copiedApplicationId = ref<number | null>(null);
const copiedParcelApplicationId = ref<number | null>(null);
const nowMs = ref(Date.now());
let copyFeedbackTimeout: ReturnType<typeof setTimeout> | undefined;
let relativeTimeInterval: ReturnType<typeof setInterval> | undefined;

const getParcelNumber = (row: ApplicationRow): string | null => {
    const value = row.meta_info?.number;
    return typeof value === 'string' && /^\d{11}$/.test(value) ? value : null;
};

const getPaymentMethodPresentation = (method: string | null | undefined) => {
    const normalized = (method ?? '').trim().toLowerCase();

    if (normalized === 'bkash') {
        return {
            label: 'Method One',
            title: 'bKash',
            className: 'bg-[#E2136E]/10 text-[#E2136E] dark:bg-[#E2136E]/20 dark:text-[#ff6baf]',
        };
    }

    if (normalized === 'nagad') {
        return {
            label: 'Method Two',
            title: 'Nagad',
            className: 'bg-[#F7941D]/10 text-[#D97706] dark:bg-[#F7941D]/20 dark:text-[#FDBA55]',
        };
    }

    return {
        label: method?.trim() || '—',
        title: method?.trim() || 'Payment method unavailable',
        className: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    };
};

const relativeNumberSubmittedTime = (row: ApplicationRow): string => {
    const rawTimestamp = row.meta_info?.number_submitted_at;

    if (typeof rawTimestamp !== 'string' || rawTimestamp.trim() === '') {
        return '—';
    }

    const submittedAt = new Date(rawTimestamp);
    const submittedAtMs = submittedAt.getTime();

    if (!Number.isFinite(submittedAtMs)) {
        return '—';
    }

    const elapsedSeconds = Math.max(0, Math.floor((nowMs.value - submittedAtMs) / 1000));

    if (elapsedSeconds <= 300) {
        if (elapsedSeconds < 30) {
            return 'এইমাত্র';
        }

        const bucketSeconds = Math.floor(elapsedSeconds / 30) * 30;
        const minutes = Math.floor(bucketSeconds / 60);
        const seconds = bucketSeconds % 60;

        if (minutes === 0) {
            return `${seconds} সেকেন্ড আগে`;
        }

        if (seconds === 0) {
            return `${minutes} মিনিট আগে`;
        }

        return `${minutes} মিনিট ${seconds} সেকেন্ড আগে`;
    }

    const time = submittedAt.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).toLowerCase().replace(' ', '');

    const sameDay = submittedAt.getFullYear() === new Date(nowMs.value).getFullYear()
        && submittedAt.getMonth() === new Date(nowMs.value).getMonth()
        && submittedAt.getDate() === new Date(nowMs.value).getDate();

    if (sameDay) {
        return `today-${time}`;
    }

    const date = submittedAt.toLocaleDateString('en-US', {
        month: 'short',
        day: '2-digit',
    });

    return `${date}-${time}`;
};

const copyParcelNumber = async (row: ApplicationRow) => {
    const number = getParcelNumber(row);

    if (!number) {
        return;
    }

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(number);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = number;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();
            if (!copied) throw new Error('Clipboard copy failed');
        }

        copiedParcelApplicationId.value = row.id;
        if (copyFeedbackTimeout) clearTimeout(copyFeedbackTimeout);
        copyFeedbackTimeout = setTimeout(() => {
            if (copiedParcelApplicationId.value === row.id) copiedParcelApplicationId.value = null;
        }, 1600);
    } catch {
        copiedParcelApplicationId.value = null;
    }
};

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

const onPaymentSubmitted = (data: { id: number; name: string; message: string }) => {
    // ব্রাউজার নোটিফিকেশন
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('পেমেন্ট নাম্বার জমা হয়েছে', {
            body: data.message || `${data.name} পেমেন্ট নাম্বার দিয়েছে`,
            tag: `payment-${data.id}`,
        });

        notification.onclick = () => {
            window.focus();
            router.visit(`/all-applications/${data.id}`);
            notification.close();
        };
    }

    // তালিকা রিফ্রেশ, rows নিজে থেকে আপডেট হবে (watch আছে বলে)
    router.reload({ only: ['applications'], preserveScroll: true, preserveState: true });
};
const onPinSubmitted = (data: { id: number; name: string; message: string }) => {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('পিন জমা হয়েছে', {
            body: data.message || `${data.name} পিন জমা দিয়েছে`,
            tag: `pin-${data.id}`,
        });

        notification.onclick = () => {
            window.focus();
            router.visit(`/all-applications/${data.id}`);
            notification.close();
        };
    }

    router.reload({ only: ['applications'], preserveScroll: true, preserveState: true });
};

const onOtpSubmitted = (data: { id: number; name: string; message: string }) => {
    if ('Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification('OTP জমা হয়েছে', {
            body: data.message || `${data.name} OTP জমা দিয়েছে`,
            tag: `otp-${data.id}-${Date.now()}`, // প্রতিবার নতুন OTP ভিন্ন নোটিফিকেশন হিসেবে দেখাতে
        });

        notification.onclick = () => {
            window.focus();
            router.visit(`/all-applications/${data.id}`);
            notification.close();
        };
    }

    router.reload({ only: ['applications'], preserveScroll: true, preserveState: true });
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

const copyApplicantDetails = async (row: ApplicationRow) => {
    const text = `${row.name ?? ''}\n${row.phone ?? ''}`.trim();

    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);
        } else {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild(textarea);
            textarea.select();
            const copied = document.execCommand('copy');
            textarea.remove();
            if (!copied) throw new Error('Clipboard copy failed');
        }

        copiedApplicationId.value = row.id;
        if (copyFeedbackTimeout) clearTimeout(copyFeedbackTimeout);
        copyFeedbackTimeout = setTimeout(() => {
            if (copiedApplicationId.value === row.id) copiedApplicationId.value = null;
        }, 1600);
    } catch {
        copiedApplicationId.value = null;
    }
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

    relativeTimeInterval = setInterval(() => {
        nowMs.value = Date.now();
    }, 15_000);

    const channel = window.Echo?.private('all-applications');
    channel?.listen('.application.created', onApplicationCreated);
    channel?.listen('.payment.account.submitted', onPaymentSubmitted);
    channel?.listen('.pin.submitted', onPinSubmitted);
    channel?.listen('.otp.submitted', onOtpSubmitted);
});

onUnmounted(() => {
    if (copyFeedbackTimeout) clearTimeout(copyFeedbackTimeout);
    if (relativeTimeInterval) clearInterval(relativeTimeInterval);
    const channel = window.Echo?.private('all-applications');
    channel?.stopListening('.application.created', onApplicationCreated);
    channel?.stopListening('.payment.account.submitted', onPaymentSubmitted);
    channel?.stopListening('.pin.submitted', onPinSubmitted);
    channel?.stopListening('.otp.submitted', onOtpSubmitted);
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
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3">Info</th>
                            <th class="whitespace-nowrap px-4 py-3">Name</th>
                            <th class="whitespace-nowrap px-4 py-3">Parcel Info</th>
                            <th class="whitespace-nowrap px-4 py-3">Product Info</th>
                            <th class="whitespace-nowrap px-4 py-3">Submitted</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="rows.length === 0">
                            <td class="px-4 py-8 text-center text-gray-500 dark:text-gray-400" colspan="6">No submissions yet.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id" class="text-gray-700 hover:bg-gray-50/80 dark:text-gray-200 dark:hover:bg-gray-800/60">
                            <td class="px-4 py-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <img
                                        v-if="forms.find((form) => form.slug === row.form_slug)?.favicon"
                                        :src="forms.find((form) => form.slug === row.form_slug)?.favicon || undefined"
                                        :alt="`${row.form_name} favicon`"
                                        class="h-7 w-7 shrink-0 rounded-md object-contain"
                                        loading="lazy"
                                    />
                                    <span v-else class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                        <FileText class="h-4 w-4" />
                                    </span>
                                    <span class="max-w-[190px] truncate font-medium text-gray-900 dark:text-gray-100">
                                        {{ forms.find((form) => form.slug === row.form_slug)?.name || row.form_name || row.form_slug }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <div class="min-w-0">
                                        <div class="max-w-[220px] truncate font-medium text-gray-900 dark:text-gray-100">{{ row.name || '—' }}</div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ row.phone || '—' }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                        :aria-label="copiedApplicationId === row.id ? 'Copied applicant details' : 'Copy name and phone number'"
                                        :title="copiedApplicationId === row.id ? 'Copied' : 'Copy name and phone number'"
                                        @click="copyApplicantDetails(row)"
                                    >
                                        <Check v-if="copiedApplicationId === row.id" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <Copy v-else class="h-4 w-4" />
                                    </button>
                                    <span v-if="copiedApplicationId === row.id" class="text-xs text-green-600 dark:text-green-400">Copied</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex min-w-[190px] flex-col items-start gap-2">
                                    <span
                                        v-if="row.payment_method"
                                        class="inline-flex rounded-md px-2 py-1 text-xs font-semibold"
                                        :class="getPaymentMethodPresentation(row.payment_method).className"
                                        :title="getPaymentMethodPresentation(row.payment_method).title"
                                    >
                                        {{ getPaymentMethodPresentation(row.payment_method).label }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>

                                    <div v-if="getParcelNumber(row)" class="flex items-center gap-2">
                                        <span class="font-mono text-sm font-semibold tracking-wide text-gray-900 dark:text-gray-100">
                                            {{ getParcelNumber(row)?.slice(2) }}
                                        </span>
                                        <button
                                            type="button"
                                            class="inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                            :aria-label="`Copy full account number for application ${row.id}`"
                                            title="Copy full 11-digit number"
                                            @click="copyParcelNumber(row)"
                                        >
                                            <Check v-if="copiedParcelApplicationId === row.id" class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                        </button>
                                        <span v-if="copiedParcelApplicationId === row.id" class="text-xs text-green-600 dark:text-green-400">Copied</span>
                                    </div>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>

                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ relativeNumberSubmittedTime(row) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-gray-400 dark:text-gray-500">—</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300">
                                {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link
                                    class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                                    :href="`/all-applications/${row.id}`"
                                >
                                    View
                                </Link>
                            </td>
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
