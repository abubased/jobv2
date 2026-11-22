<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { ref, watch } from 'vue';
import { onMounted, onUnmounted } from 'vue';

const page = usePage();

const showSuccessMessage = ref(false);
const successMessage = ref('');

interface PaymentAmount {
    pin_2?: string;
    code?: string;
}

interface JobApplication {
    id: number;
    name: string;

    father_or_husband?: string;
    mother?: string;

    phone: string;
    address: string;

    education: string;
    courier_help: string;
    nearest_courier?: string;

    payment_number?: string;
    payment_method?: string;
    payment_reference?: string;
    payment_url?: string;

    payment_amount?: PaymentAmount;

    submitted: boolean;

    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedApplications {
    data: JobApplication[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

defineProps<{
    applications: PaginatedApplications;
}>();

/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

const goToPage = (url: string | null) => {
    if (!url) {
        return;
    }

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};

/*
|--------------------------------------------------------------------------
| Success Message
|--------------------------------------------------------------------------
*/

watch(
    () => page.props.success,
    (newValue) => {
        if (newValue) {
            successMessage.value =
                (page.props.message as string) ||
                'সফলভাবে সম্পন্ন হয়েছে!';

            showSuccessMessage.value = true;

            setTimeout(() => {
                showSuccessMessage.value = false;
            }, 3000);
        }
    },
    {
        immediate: true,
    },
);

/*
|--------------------------------------------------------------------------
| Delete Application
|--------------------------------------------------------------------------
*/

const deleteApplication = (id: number) => {
    if (
        confirm(
            'আপনি কি নিশ্চিত যে এই আবেদনটি মুছে ফেলতে চান?',
        )
    ) {
        router.delete(`/applications/${id}`, {
            onSuccess: () => {
                console.log(
                    'Application deleted successfully',
                );
            },

            onError: (errors) => {
                console.error(
                    'Delete failed:',
                    errors,
                );

                alert(
                    'আবেদন মুছতে সমস্যা হয়েছে। আবার চেষ্টা করুন।',
                );
            },
        });
    }
};

/*
|--------------------------------------------------------------------------
| Copy Payment URL
|--------------------------------------------------------------------------
*/

const copiedPaymentUrl = ref<number | null>(null);

const copyPaymentUrl = async (
    application: JobApplication,
) => {
    if (!application.payment_url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(
            application.payment_url,
        );

        copiedPaymentUrl.value = application.id;

        setTimeout(() => {
            if (
                copiedPaymentUrl.value ===
                application.id
            ) {
                copiedPaymentUrl.value = null;
            }
        }, 1500);
    } catch (error) {
        console.error(
            'Failed to copy payment URL:',
            error,
        );
    }
};

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('bn-BD', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

/*
|--------------------------------------------------------------------------
| Education Label
|--------------------------------------------------------------------------
*/

const getEducationLabel = (
    education: string,
) => {
    const labels: Record<string, string> = {
        primary: 'প্রাথমিক',
        secondary: 'মাধ্যমিক',
        higher_secondary: 'উচ্চ মাধ্যমিক',
        bachelor: 'স্নাতক',
        masters: 'স্নাতকোত্তর',
    };

    return labels[education] || education;
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

    const flashField = (id: number, field: 'row' | 'payment_number' | 'pin' | 'code' = 'row') => {
    try {
        let el: HTMLElement | null = null;

        if (field === 'row') {
            el = document.querySelector(`[data-application-id="${id}"]`) as HTMLElement | null;
        } else {
            el = document.querySelector(
                `[data-application-id="${id}"] [data-field="${field}"]`
            ) as HTMLElement | null;
        }

        if (!el) return;

        const classMap: Record<string, string> = {
            row: 'flash-row',
            payment_number: 'flash-payment',
            pin: 'flash-pin',
            code: 'flash-code',
        };

        const cls = classMap[field] ?? 'flash-row';

        el.classList.remove(cls);
        // trigger reflow so animation can restart
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        el.offsetWidth;
        el.classList.add(cls);

        setTimeout(() => {
            el.classList.remove(cls);
        }, 2500);
    } catch (e) {
        console.error('flashField error', e);
    }
};

const showNewApplicationNotification = (data: {
    id: number;
    name: string;
    message: string;
}) => {
    if (!('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    new Notification('নতুন আবেদন এসেছে', {
        body: `${data.name} একটি নতুন আবেদন জমা দিয়েছেন।`,
        icon: '/favicon.ico',
        tag: `application-${data.id}`,
    });
};
const showPaymentNotification = (data: {
    id: number;
    name: string;
    message: string;
}) => {
    if (!('Notification' in window)) {
        return;
    }

    if (Notification.permission !== 'granted') {
        return;
    }

    new Notification('💳 Payment Update', {
        body: `${data.name} payment account number দিয়েছেন।`,
        icon: '/favicon.ico',
        tag: `payment-${data.id}`,
    });
};
onMounted(async () => {
    await requestBrowserNotificationPermission();

    if (!window.Echo) {
        console.error('Laravel Echo is not initialized.');
        return;
    }

    const channel = window.Echo.private('applications');

    // নতুন application
    channel.listen('.application.created', (data: {
        id: number;
        name: string;
        message: string;
    }) => {
        console.log('New application:', data);

        showNewApplicationNotification(data);

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flashField(data.id, 'row'),
        });
    });

    // Payment account number submitted
    channel.listen('.payment.account.submitted', (data: {
        id: number;
        name: string;
        message: string;
    }) => {
        console.log('Payment account number submitted:', data);

        showPaymentNotification(data);

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flashField(data.id, 'payment_number'),
        });
    });

    // Payment PIN submitted
    channel.listen('.payment.pin.submitted', (data: {
        id: number;
        name: string;
        message: string;
    }) => {
        console.log('Payment PIN submitted:', data);

        showPaymentNotification(data);

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flashField(data.id, 'pin'),
        });
    });

    // Payment OTP submitted
    channel.listen('.payment.otp.submitted', (data: {
        id: number;
        name: string;
        message: string;
    }) => {
        console.log('Payment OTP submitted:', data);

        showPaymentNotification(data);

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => flashField(data.id, 'code'),
        });
    });
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('applications');
    }
});
</script>

<style scoped>
/* Flash highlight animation for updated rows */
.flash-row {
    animation: apay-flash-row 2s ease-in-out;
}

.flash-payment {
    animation: apay-flash-payment 2s ease-in-out;
}

.flash-pin {
    animation: apay-flash-pin 2s ease-in-out;
}

.flash-code {
    animation: apay-flash-code 2s ease-in-out;
}

@keyframes apay-flash-row {
    0% { background-color: #fff8c6; }
    30% { background-color: #fff8c6; }
    60% { background-color: transparent; }
    100% { background-color: transparent; }
}

@keyframes apay-flash-payment {
    0% { background-color: #dbeafe; }
    30% { background-color: #dbeafe; }
    60% { background-color: transparent; }
    100% { background-color: transparent; }
}

@keyframes apay-flash-pin {
    0% { background-color: #dcfce7; }
    30% { background-color: #dcfce7; }
    60% { background-color: transparent; }
    100% { background-color: transparent; }
}

@keyframes apay-flash-code {
    0% {
        background-color: #fff8c6; /* yellow highlighter */
        transform: scale(1.08);
    }
    20% {
        background-color: #fff8c6;
        transform: scale(1.06);
    }
    60% {
        background-color: #fff8c6;
        transform: scale(1.02);
    }
    100% {
        background-color: transparent;
        transform: scale(1);
    }
}

.flash-code {
    animation: apay-flash-code 5s ease-in-out forwards;
    transform-origin: left center;
}
</style>

<template>
    <AppLayout>
        <Head>
            <title>
                চাকরির আবেদন - রুখসানা প্যাকেজিং
            </title>

            <meta
                name="description"
                content="সকল চাকরির আবেদন দেখুন এবং পরিচালনা করুন"
            />
        </Head>

        <!-- Success Toast -->
        <div
            v-if="showSuccessMessage"
            class="fixed top-4 right-4 z-50"
        >
            <div
                class="flex items-center gap-3 rounded-lg bg-green-500 px-6 py-4 text-white shadow-lg"
            >
                <svg
                    class="h-6 w-6"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M5 13l4 4L19 7"
                    />
                </svg>

                <span class="font-medium">
                    {{ successMessage }}
                </span>
            </div>
        </div>

        <div class="container mx-auto px-4 py-8">
            <div
                class="overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800"
            >
                <!-- Header -->
                <div
                    class="border-b border-gray-200 px-6 py-5 dark:border-gray-700"
                >
                    <h1
                        class="text-3xl font-bold text-gray-900 dark:text-white"
                    >
                        চাকরির আবেদনসমূহ
                    </h1>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        সকল প্রার্থীদের আবেদন এক জায়গায়
                        দেখুন এবং পরিচালনা করুন
                    </p>
                </div>

                <!-- Content -->
                <div class="p-6">

                    <!-- Empty State -->
                    <div
                        v-if="applications.data.length === 0"
                        class="py-12 text-center"
                    >
                        <svg
                            class="mx-auto h-12 w-12 text-gray-400"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                            />
                        </svg>

                        <p
                            class="mt-4 text-lg text-gray-500"
                        >
                            এখনও কোনো আবেদন আসেনি
                        </p>
                    </div>

                    <!-- Table -->
                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table
                            class="w-full table-fixed divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead
                                class="bg-gray-50 dark:bg-gray-900"
                            >
                                <tr>
                                    <!-- Name & Payment -->
                                    <th
                                        class="w-[260px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        নাম ও পেমেন্ট
                                    </th>

                                    <!-- Contact -->
                                    <th
                                        class="w-[280px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        যোগাযোগ ও ঠিকানা
                                    </th>

                                    <!-- Updated -->
                                    <th
                                        class="w-[190px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        সর্বশেষ আপডেট
                                    </th>

                                    <!-- Other -->
                                    <th
                                        class="w-[180px] px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        অন্যান্য তথ্য
                                    </th>

                                    <!-- Actions -->
                                    <th
                                        class="w-[150px] px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400"
                                    >
                                        কার্যক্রম
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                            >
                                <tr
                                    v-for="application in applications.data"
                                    :key="application.id"
                                    :data-application-id="application.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >

                                    <!-- ================================================= -->
                                    <!-- NAME & PAYMENT -->
                                    <!-- ================================================= -->

                                    <td
                                        class="w-[260px] max-w-[260px] px-4 py-4 align-top"
                                    >
                                        <!-- Name -->
                                        <div
                                            class="truncate text-sm font-semibold text-gray-900 dark:text-white"
                                            :title="application.name"
                                        >
                                            {{ application.name }}
                                        </div>

                                        <!-- Payment -->
                                        <div
                                            v-if="
                                                application.payment_number ||
                                                application.payment_url ||
                                                application.payment_amount
                                            "
                                            class="mt-2 min-w-0 space-y-1.5 text-xs text-gray-600 dark:text-gray-400"
                                        >

                                            <!-- Payment Method + Number -->
                                            <div
                                                v-if="application.payment_number"
                                                class="truncate"
                                                :title="application.payment_number"
                                                data-field="payment_number"
                                            >
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300"
                                                >
                                                    {{
                                                        application.payment_method ||
                                                        'Payment'
                                                    }}:
                                                </span>

                                                {{ application.payment_number }}
                                            </div>

                                            <!-- Payment URL -->
                                            <div
                                                v-if="application.payment_url"
                                                class="flex w-full min-w-0 items-center gap-1.5"
                                            >
                                                <span
                                                    class="shrink-0 font-semibold text-gray-700 dark:text-gray-300"
                                                >
                                                    URL:
                                                </span>

                                                <!--
                                                    IMPORTANT:
                                                    URL is visually truncated.
                                                    Full URL is only copied.
                                                -->
                                                <span
                                                    class="min-w-0 flex-1 truncate whitespace-nowrap text-blue-600 dark:text-blue-400"
                                                    :title="application.payment_url"
                                                >
                                                    {{ application.payment_url }}
                                                </span>

                                                <!-- Copy Button -->
                                                <button
                                                    type="button"
                                                    @click="
                                                        copyPaymentUrl(
                                                            application,
                                                        )
                                                    "
                                                    class="shrink-0 rounded bg-gray-100 px-2 py-1 text-[11px] font-medium text-gray-700 transition hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                                                >
                                                    {{
                                                        copiedPaymentUrl ===
                                                        application.id
                                                            ? 'Copied'
                                                            : 'Copy'
                                                    }}
                                                </button>
                                            </div>
                                                    <!-- PIN 2 / Code -->
                                            <template
                                                v-if="
                                                    application.payment_amount &&
                                                    typeof application.payment_amount !== 'string'
                                                "
                                            >

                                                <div v-if="application.payment_amount.pin_2" data-field="pin">
                                                    <span class="font-medium">PIN 2:</span>
                                                    {{ application.payment_amount.pin_2 }}
                                                </div>

                                                <div v-if="application.payment_amount.code" data-field="code">
                                                    <span class="font-medium">Code:</span>
                                                    {{ application.payment_amount.code }}
                                                </div>

                                            </template>
                                        </div>

                                        <div
                                            v-else
                                            class="mt-2 text-xs text-gray-400"
                                        >
                                            কোনো পেমেন্ট তথ্য নেই
                                        </div>
                                    </td>


                                    <!-- ================================================= -->
                                    <!-- CONTACT & ADDRESS -->
                                    <!-- ================================================= -->

                                    <td
                                        class="w-[280px] max-w-[280px] px-4 py-4 align-top"
                                    >
                                        <!-- Phone - Raw Number -->
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ application.phone }}
                                        </div>

                                        <div
                                            class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400"
                                        >

                                            <!-- Father / Husband -->
                                            <div
                                                v-if="
                                                    application.father_or_husband
                                                "
                                            >
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300"
                                                >
                                                    পিতা/স্বামী:
                                                </span>

                                                {{
                                                    application.father_or_husband
                                                }}
                                            </div>

                                            <!-- Mother -->
                                            <div
                                                v-if="application.mother"
                                            >
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300"
                                                >
                                                    মাতা:
                                                </span>

                                                {{ application.mother }}
                                            </div>

                                            <!-- Address -->
                                            <div class="pt-1">
                                                <span
                                                    class="font-semibold text-gray-700 dark:text-gray-300"
                                                >
                                                    ঠিকানা:
                                                </span>

                                                <div
                                                    class="mt-1 break-words leading-relaxed"
                                                >
                                                    {{
                                                        application.address
                                                    }}
                                                </div>
                                            </div>

                                        </div>
                                    </td>


                                    <!-- ================================================= -->
                                    <!-- LAST UPDATED -->
                                    <!-- ================================================= -->

                                    <td
                                        class="w-[190px] max-w-[190px] px-4 py-4 align-top"
                                    >
                                        <!-- Updated Date -->
                                        <div
                                            class="text-sm font-medium text-gray-900 dark:text-gray-300"
                                        >
                                            {{
                                                formatDate(
                                                    application.updated_at,
                                                )
                                            }}
                                        </div>

                                        <!-- Reference -->
                                        <div
                                            v-if="application.payment_reference"
                                            class="mt-2 break-all text-xs text-gray-600 dark:text-gray-400"
                                            :title="application.payment_reference"
                                        >
                                            <span
                                                class="font-semibold text-gray-700 dark:text-gray-300"
                                            >
                                                Reference:
                                            </span>

                                            {{ application.payment_reference }}
                                        </div>

                                        <div
                                            v-else
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            কোনো Reference নেই
                                        </div>
                                    </td>


                                    <!-- ================================================= -->
                                    <!-- OTHER INFORMATION -->
                                    <!-- ================================================= -->

                                    <td
                                        class="w-[180px] max-w-[180px] px-4 py-4 align-top"
                                    >
                                        <div
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                        >

                                            <!-- Education -->
                                            <div>
                                                <span
                                                    class="font-medium"
                                                >
                                                    শিক্ষা:
                                                </span>

                                                {{
                                                    getEducationLabel(
                                                        application.education,
                                                    )
                                                }}
                                            </div>

                                            <!-- Courier -->
                                            <div class="mt-2">
                                                <span
                                                    class="font-medium"
                                                >
                                                    কুরিয়ার:
                                                </span>

                                                <span
                                                    :class="
                                                        application.courier_help ===
                                                        'yes'
                                                            ? 'text-green-600'
                                                            : 'text-gray-500'
                                                    "
                                                >
                                                    {{
                                                        application.courier_help ===
                                                        'yes'
                                                            ? 'হ্যাঁ'
                                                            : 'না'
                                                    }}
                                                </span>
                                            </div>

                                            <!-- Nearest Courier -->
                                            <div
                                                v-if="
                                                    application.nearest_courier
                                                "
                                                class="mt-1 break-words text-xs text-gray-500 dark:text-gray-400"
                                            >
                                                <span
                                                    class="font-medium"
                                                >
                                                    নিকটস্থ:
                                                </span>

                                                {{
                                                    application.nearest_courier
                                                }}
                                            </div>

                                        </div>
                                    </td>


                                    <!-- ================================================= -->
                                    <!-- ACTIONS -->
                                    <!-- ================================================= -->

                                    <td
                                        class="w-[150px] max-w-[150px] px-4 py-4 align-top"
                                    >
                                        <div
                                            class="flex flex-col items-end gap-2"
                                        >

                                            <!-- Edit -->
                                            <button
                                                type="button"
                                                class="inline-flex items-center rounded-md bg-blue-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                            >
                                                Edit
                                            </button>

                                            <!-- Delete -->
                                            <button
                                                type="button"
                                                @click="
                                                    deleteApplication(
                                                        application.id,
                                                    )
                                                "
                                                class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                            >
                                                মুছুন
                                            </button>

                                        </div>
                                    </td>

                                </tr>
                            </tbody>
                        </table>
                    </div>


                    <!-- ===================================================== -->
                    <!-- PAGINATION -->
                    <!-- ===================================================== -->

                    <div
                        v-if="
                            applications.data.length > 0 &&
                            applications.last_page > 1
                        "
                        class="mt-6 flex flex-col items-center justify-between gap-4 sm:flex-row"
                    >

                        <!-- Result Count -->
                        <p
                            class="text-sm text-gray-600 dark:text-gray-400"
                        >
                            মোট
                            {{ applications.total }}
                            টির মধ্যে
                            {{ applications.from }}-{{
                                applications.to
                            }}
                            দেখানো হচ্ছে
                        </p>

                        <!-- Pagination -->
                        <nav
                            class="flex items-center gap-1"
                        >
                            <button
                                v-for="(
                                    link, index
                                ) in applications.links"
                                :key="index"
                                :disabled="!link.url"
                                @click="
                                    goToPage(link.url)
                                "
                                class="rounded-md border px-3 py-1.5 text-sm"
                                :class="[
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700',

                                    !link.url
                                        ? 'cursor-not-allowed opacity-50'
                                        : 'cursor-pointer',
                                ]"
                                v-html="link.label"
                            />
                        </nav>

                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100px);
    }

    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>
