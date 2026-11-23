<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const page = usePage();

const submitted = ref(false);
const showStickyCta = ref(false);
const showRegistrationModal = ref(false);
const showPaymentModal = ref(false);
const showDuplicatePhoneModal = ref(false);
const duplicatePhoneMessage = ref('');
const selectedPaymentMethod = ref('');
const currentApplicationId = ref<number | null>(null);
const showPaymentFrame = ref(false);
const paymentFrameUrl = ref('');
const paymentMethods = [
    { key: 'bkash', label: 'bKash', logo: '/images/bkash.jfif' },
    { key: 'nagad', label: 'Nagad', logo: '/images/Nagad.webp' },
    { key: 'rocket', label: 'Rocket', logo: '/images/Rocket.webp' },
    { key: 'upay', label: 'Upay', logo: '/images/Upay.png' },
];
const isPaymentLoading = ref(false);
const isSubmittingApplication = ref(false);
const convertBengaliDigits = (value: string | number): string => {
    const bengaliDigits = '০১২৩৪৫৬৭৮৯';

    return String(value).replace(/[০-৯]/g, (digit) =>
        String(bengaliDigits.indexOf(digit))
    );
};
const normalizeRegistrationFee = (value: string | number): string => {
    const normalized = convertBengaliDigits(value).trim();

    // খালি বা ভুল ভ্যালু হলে ডিফল্ট ফি
    if (!/^\d+$/.test(normalized)) {
        return '20';
    }

    const amount = Number(normalized);

    if (!Number.isSafeInteger(amount) || amount <= 0) {
        return '20';
    }

    return String(amount);
};
const selectPaymentMethod = (method: string) => {
    selectedPaymentMethod.value = method;
};

const handleScroll = () => {
    const doc = document.documentElement;
    const scrollTop = window.scrollY || doc.scrollTop || 0;
    const scrollHeight = doc.scrollHeight - doc.clientHeight;

    if (scrollHeight <= 0) {
        showStickyCta.value = false;
        return;
    }

    const scrolledPercent = (scrollTop / scrollHeight) * 100;
    showStickyCta.value = scrolledPercent >= 5;
};

const form = ref({
    name: '',
    fatherOrHusband: '',
    mother: '',
    phone: '',
    address: '',
    education: 'primary',
    courierHelp: 'yes',
    nearestCourier: '20',
    paymentNumber: '',
    paymentAmount: '',
});

const errors = ref<Record<string, string>>({});

const getCsrfToken = () => {
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (metaToken) {
        return metaToken;
    }

    const cookieMatch = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (cookieMatch) {
        return decodeURIComponent(cookieMatch[1]);
    }

    return '';
};

// Watch for success message from backend
watch(() => page.props.success, (newValue) => {
    if (newValue === true) {
        submitted.value = true;
        showRegistrationModal.value = false;

        // Reset form
        form.value = {
            name: '',
            fatherOrHusband: '',
            mother: '',
            phone: '',
            address: '',
            education: 'primary',
            courierHelp: 'yes',
            nearestCourier: '',
            paymentNumber: '',
            paymentAmount: '',
        };

        // Scroll to top to show success message (multiple attempts for reliability)
        window.scrollTo({ top: 0, behavior: 'instant' });

        setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 50);

        setTimeout(() => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 200);
    }
}, { immediate: true });

const validateMainForm = () => {
    errors.value = {};
    if (!form.value.name) errors.value.name = 'নাম লিখুন';
    if (!form.value.phone) errors.value.phone = 'মোবাইল নাম্বার দিন';
    if (!form.value.address) errors.value.address = 'সম্পূর্ন ঠিকানা লিখুন';
    return Object.keys(errors.value).length === 0;
};

// const validatePaymentForm = () => {
//     // Clear existing payment-related errors
//     if (errors.value.paymentNumber) {
//         delete errors.value.paymentNumber;
//     }
//     if (errors.value.paymentAmount) {
//         delete errors.value.paymentAmount;
//     }

//     if (!form.value.paymentNumber) errors.value.paymentNumber = 'এখানে আপনি যে নাম্বার থেকে টাকা পাঠিয়েছেন সেই বিকাশ নাম্বারটি লিখুন';
//     if (!form.value.paymentAmount) errors.value.paymentAmount = 'কত টাকা পাঠিয়েছেন লিখুন';

//     return !errors.value.paymentNumber && !errors.value.paymentAmount;
// };

const scrollToFirstError = (context: 'main' | 'modal') => {
    // Determine first error field in order
    let fieldKey: 'name' | 'phone' | 'address' | null = null;
    if (errors.value.name) fieldKey = 'name';
    else if (errors.value.phone) fieldKey = 'phone';
    else if (errors.value.address) fieldKey = 'address';

    if (!fieldKey) return;

    const selector =
        context === 'main'
            ? `[data-error-field="main-${fieldKey}"]`
            : `[data-error-field="modal-${fieldKey}"]`;

    const el = document.querySelector(selector) as HTMLElement | null;
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if ('focus' in el && typeof (el as any).focus === 'function') {
            (el as any).focus();
        }
    }
};

const handleMainFormSubmit = () => {
    // Prevent duplicate submission
    if (isSubmittingApplication.value) {
        return;
    }

    if (!validateMainForm()) {
        scrollToFirstError('main');
        return;
    }

    submitFormDirect();
};

const handleRegistrationFormSubmit = () => {
    // Prevent duplicate submission
    if (isSubmittingApplication.value) {
        return;
    }

    if (!validateMainForm()) {
        scrollToFirstError('modal');
        return;
    }

    submitFormDirect();
};

const submitFormDirect = async () => {
    // Prevent duplicate requests
    if (isSubmittingApplication.value) {
        return;
    }

    isSubmittingApplication.value = true;

    const formData = {
        form_slug: 'job-application',
        name: form.value.name,
        phone: form.value.phone,
        address: form.value.address,
        form_data: {
            father_or_husband: form.value.fatherOrHusband,
            mother: form.value.mother,
            education: form.value.education,
            courier_help: form.value.courierHelp,
            nearest_courier: form.value.nearestCourier,
        },
    };

    const csrfToken = getCsrfToken();

    try {
        const response = await fetch('/submit-form', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(formData),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            const serverErrors = result.errors || {};
            const mappedErrors: Record<string, string> = {};

            Object.entries(serverErrors).forEach(([key, value]) => {
                const message = Array.isArray(value) ? String(value[0]) : String(value);
                mappedErrors[key.replace(/^form_data\./, '')] = message;
            });

            errors.value = mappedErrors;

            const phoneError = serverErrors.phone;

            if (
                phoneError &&
                phoneError.includes('আগেই ফরম পূরন হয়েছে')
            ) {
                duplicatePhoneMessage.value = phoneError;

                showRegistrationModal.value = false;
                showDuplicatePhoneModal.value = true;
            } else {
                alert(
                    result.message ||
                    'ফরম সাবমিট করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
                );
            }

            return;
        }

        // Application successfully created
        currentApplicationId.value = result.application_id ?? null;

        if (!currentApplicationId.value) {
            alert(result.message || 'আবেদন জমা হয়েছে, কিন্তু পেমেন্ট শুরু করা যায়নি।');
            return;
        }

        showRegistrationModal.value = false;

        // Open payment method selection
        showPaymentModal.value = true;

    } catch (error) {
        console.error('Submission error:', error);

        alert(
            'ফরম সাবমিট করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
        );

    } finally {
        // Always stop loading
        isSubmittingApplication.value = false;
    }
};

// const openPaymentFrame = async () => {
//     if (!selectedPaymentMethod.value || !currentApplicationId.value) {
//         return;
//     }

//     const csrfToken = getCsrfToken();

//     try {
//         const response = await fetch('/job-applications/init-payment', {
//             method: 'POST',
//             credentials: 'same-origin',
//             headers: {
//                 'Content-Type': 'application/json',
//                 'Accept': 'application/json',
//                 'X-CSRF-TOKEN': csrfToken,
//                 'X-XSRF-TOKEN': csrfToken,
//             },
//             body: JSON.stringify({
//                 application_id: currentApplicationId.value,
//                 method: selectedPaymentMethod.value,
//             }),
//         });

//         const result = await response.json();

//         if (!response.ok || !result.success) {
//             alert(result.message || 'পেমেন্ট শুরু করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
//             return;
//         }

//         paymentFrameUrl.value = result.url;
//         showPaymentModal.value = false;
//         showPaymentFrame.value = true;
//     } catch (error) {
//         console.error('Payment init error:', error);
//         alert('পেমেন্ট শুরু করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।');
//     }
// };

const openPaymentFrame = async () => {

    if (
        isPaymentLoading.value ||
        !selectedPaymentMethod.value ||
        !currentApplicationId.value
    ) {
        return;
    }

    isPaymentLoading.value = true;

    try {

        const csrfToken = getCsrfToken();

        const response = await fetch('/init-payment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-XSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                application_id: currentApplicationId.value,
                method: selectedPaymentMethod.value,
            }),
        });

        const result = await response.json();

        if (!response.ok || !result.success || !result.url || result.payment_status === 'paid') {
            alert(
                result.message ||
                'পেমেন্ট এখনো নিশ্চিত হয়নি। আবেদন জমা আছে। ফি ২০.০০ টাকা।'
            );
            return;
        }

        paymentFrameUrl.value = result.url;

        showPaymentModal.value = false;
        showPaymentFrame.value = true;

    } catch (error) {

        console.error('Payment init error:', error);

        alert(
            'পেমেন্ট শুরু করতে সমস্যা হয়েছে। আবার চেষ্টা করুন।'
        );

    } finally {

        isPaymentLoading.value = false;
    }
};

const handlePaymentModalMessage = (event: MessageEvent) => {
    if (
        event.origin !== window.location.origin ||
        event.data?.type !== 'close-payment-modal'
    ) {
        return;
    }

    showPaymentFrame.value = false;
    paymentFrameUrl.value = '';
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
    window.addEventListener(
        'message',
        handlePaymentModalMessage
    );
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', handleScroll);
    window.removeEventListener(
        'message',
        handlePaymentModalMessage
    );
});
</script>

<template>
    <Head title="কলম প্যাকেটিং জব" />

    <div class="min-h-screen bg-gradient-to-b from-[#1b2c40]/5 via-white to-[#ff6d4d]/5 text-[#111827] flex flex-col items-stretch">
        <!-- Top bar / brand -->
        <header
            v-show="!showStickyCta"
            class="sticky top-0 z-30 px-4 pt-4 pb-3 flex items-center justify-between border-b border-[#e5e7eb] bg-[#65cf7294] backdrop-blur"
        >
            <div class="flex items-center gap-2">
                <div
                    class="h-9 w-9 rounded-lg bg-white border border-gray-200 flex items-center justify-center overflow-hidden shadow-sm"
                >
                    <img
                        src="/images/logo.png"
                        alt="Rukhsana Packaging Logo"
                        class="h-8 w-8 object-contain"
                    />
                </div>
                <div>
                    <p class="text-sm font-semibold leading-tight text-[#10747a]">
                        Rukhsana Packaging
                    </p>
                    <p class="text-[11px] leading-tight text-[#ff370b]">
                        প্রতিটি প্যাকেটে আমাদের শেষ্ঠত্বের ছাপ
                    </p>
                </div>
            </div>
            <div class="text-right text-[11px] leading-tight text-[#ff370b]">
                <p>Chowk Bazar, Dhaka-1202</p>
                <p>House: 58/C, B-Block</p>
            </div>
        </header>

        <!-- SUCCESS MESSAGE - Top of Page -->
        <div
            v-if="submitted"
            class="px-4 pt-4"
        >
            <div
                class="max-w-2xl mx-auto rounded-2xl border-2 border-[#65cf72] bg-gradient-to-br from-[#65cf72]/20 to-[#3b9398]/10 p-6 text-center shadow-lg animate-[slideIn_0.5s_ease-out]"
            >
                <!-- Success Icon -->
                <div class="flex justify-center mb-4">
                    <div class="w-20 h-20 rounded-full bg-[#65cf72] flex items-center justify-center animate-[scaleIn_0.6s_ease-out]">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>

                <h3 class="text-3xl font-bold text-[#1b2c40] mb-3">
                    ✅ সফলভাবে সাবমিট হয়েছে!
                </h3>
                <p class="text-xl font-semibold text-[#3b9398] mb-5">
                    আপনার আবেদনটি আমরা পেয়েছি
                </p>

                <div class="bg-white rounded-xl p-5 mb-4 text-left shadow-sm">
                    <p class="text-[16px] text-gray-800 mb-3 font-semibold">
                        📋 পরবর্তী ধাপ:
                    </p>
                    <ol class="list-decimal list-inside space-y-3 text-[15px] text-gray-700">
                        <li>আমাদের এ্যাডমিন ম্যানেজার শীঘ্রই আপনার সাথে যোগাযোগ করবেন</li>
                        <li>কম্পানির ভেরিফাইড নাম্বারে রেজিস্ট্রেশন ফি প্রদান করুন</li>
                        <li>রেজিস্ট্রেশন কনফার্ম হলে ২৪ ঘন্টার মধ্যে ডেলিভারি শুরু হবে</li>
                    </ol>
                </div>

                <div class="bg-[#ff370b]/10 border-2 border-[#ff370b]/30 rounded-xl p-4 mb-4">
                    <p class="text-[14px] text-[#ff370b] font-bold mb-2">
                        📞 যোগাযোগ করুন
                    </p>
                    <p class="text-[15px] text-gray-800 font-semibold">
                        WhatsApp/IMO: <span class="text-[#ff370b]">01333 130 523</span>
                        অথবা: <span class="text-[#ff370b]">01956 272 986</span>
                    </p>
                </div>

                <p class="text-[13px] text-gray-600 italic mb-4">
                    নোট: কলম রিসিভ করার সময় ১ কপি NID ফটোকপি ও ১ কপি পাসপোর্ট সাইজ ছবি দিবেন
                </p>

                <button
                    @click="submitted = false"
                    class="px-8 py-3 bg-[#3b9398] text-white rounded-full text-[15px] font-bold hover:bg-[#1b2c40] transition-all hover:scale-105 shadow-md"
                >
                    আরেকটি আবেদন করুন
                </button>
            </div>
        </div>

        <!-- Cover / hero section -->
        <section class="px-4 pt-3 pb-4 bg-gradient-to-br from-[#1b2c40]/8 via-white to-[#ff6d4d]/10 border-b border-[#e5e7eb]">
            <div
                class="relative overflow-hidden rounded-2xl border border-white/60 bg-white transition-all duration-500 ease-out hover:-translate-y-0.5 hover:shadow-lg"
            >
                <div class="h-32 w-full overflow-hidden">
                    <img
                        src="/images/cover.jpg"
                        alt="কলম প্যাকেটিং কাজের কভার"
                        class="h-full w-full object-cover object-center"
                    />
                </div>
                <div class="absolute inset-0 pointer-events-none">
                    <div class="h-full w-full bg-gradient-to-t from-black/10 via-transparent to-transparent"></div>
                </div>
                <div class="absolute inset-0 flex items-end">
                    <div class="p-4 flex items-center gap-3 text-white drop-shadow">
                        <div
                            class="h-10 w-10 rounded-2xl bg-white shadow-md border border-white flex items-center justify-center"
                        >
                            <!-- simple pen box icon -->
                            <svg
                                class="h-6 w-6 text-[#3b9398]"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                                stroke-width="1.6"
                            >
                                <rect x="4" y="8" width="16" height="10" rx="2" />
                                <path d="M8 8V6l4-2 4 2v2" />
                                <path d="M10 13h4" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-white/80 font-medium">Home based job</p>
                            <p class="text-sm font-semibold">Registration-form Submit Page</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Scrollable content -->
        <main
            class="flex-1 pb-24 bg-gradient-to-b from-white via-[#f5f7fb] to-[#1b2c40]/5"
        >
            <section class="px-4 pt-4">
                <p class="inline-flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-[#3b9398]">
                    <span class="h-[1px] w-6 bg-[#3b9398]"></span>
                    Special Job Offer For Women!
                </p>
                <h1 class="mt-2 text-[22px] font-bold leading-snug text-[#1b2c40]">
                    বাসায় বসে কলম প্যাকেটিং এর কাজ
                </h1>
                <p class="mt-2 text-sm text-slate-700">

                    নিচের নিয়মগুলো ভালোভাবে পড়ে তারপর রেজিস্ট্রেশন করুন।
                </p>

                <!-- Salary highlight card -->
                <div
                    class="mt-4 rounded-2xl border border-[#3b9398]/20 bg-white p-4 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                >
                    <p class="flex items-center gap-1 text-xs font-medium text-[#3b9398] uppercase tracking-[0.15em]">
                        <svg
                            class="h-3 w-3"
                            viewBox="0 0 20 20"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path
                                d="M6 4h8M6 8h5.5a2.5 2.5 0 010 5H9m0 0H6m3 0v3"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                        <span>মাসিক বেতন</span>
                    </p>
                    <p class="mt-1 text-3xl font-extrabold">
                        ১৫,০০০<span class="text-base font-semibold"> টাকা*</span>
                    </p>
                    <p class="mt-1 text-[13px] text-gray-700">
                        প্রতি কলমে ১ টাকা + নাস্তা + বিদ্যুৎ/মোবাইল বিল।
                    </p>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-[12px]">
                        <div class="rounded-lg bg-white p-2 border border-gray-200">
                            <p class="text-gray-500">মূল বেতন</p>
                            <p class="text-sm font-semibold">১২,০০০ টাকা</p>
                        </div>
                        <div class="rounded-lg bg-[#f9fafb] p-2 border border-gray-200">
                            <p class="text-gray-500">নাস্তা + বিল</p>
                            <p class="text-sm font-semibold">৩,০০০ টাকা</p>
                        </div>
                        <div class="rounded-lg bg-[#f9fafb] p-2 border border-gray-200">
                            <p class="text-gray-500">মোট</p>
                            <p class="text-sm font-semibold text-[#3b9398]">
                                ১৫,০০০ টাকা
                            </p>
                        </div>
                    </div>
                    <p class="mt-2 text-[12px] text-gray-500">
                        *শর্ত প্রযোজ্য – নিয়ম অনুযায়ী কাজ সম্পন্ন করলে পূর্ণ বেতন।
                    </p>
                </div>
            </section>

            <!-- Rules section -->
            <section class="px-4 mt-6">
                <h2 class="text-xl font-semibold flex items-center gap-2 text-[#1b2c40]">
                    <span
                        class="inline-flex h-7 w-1 rounded-full bg-gradient-to-b from-[#3b9398] to-[#ff6d4d]"
                    ></span>
                    কাজের নিয়ম-কানুন
                </h2>
                <div class="mt-3 space-y-3 text-[14px] leading-relaxed">
                    <div
                        class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <p class="font-medium text-[#ff370b]">
                            ১ম মাসে ১২,০০০ পিস কলম
                        </p>
                        <p class="mt-1 text-gray-600">
                            কুরিয়ারের মাধ্যমে ১২,০০০ পিস কলম দেয়া হবে। সবগুলো প্যাকেটিং
                            করে কোম্পানির ঠিকানায় ফেরত পাঠাতে হবে। প্রতি কলমে বেতন ১
                            টাকা।
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-gradient-to-r from-[#3b9398]/5 via-white to-[#ff6d4d]/8 p-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <p class="font-medium text-[#ff370b]">
                            প্রথম সপ্তাহের কাজ
                        </p>
                        <p class="mt-1 text-slate-700">
                            শুরুতে ৭ দিনের জন্য ৩,০০০ পিস কলম, ৩টি হ্যান্ড গ্লোভস, ২৮০
                            টি প্যাকেট, ৩টি ছোট কার্টুন এবং ১টি সাদা টেপ দেয়া হবে।
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-gradient-to-r from-[#ff6d4d]/8 via-white to-[#3b9398]/5 p-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <p class="font-medium text-[#ff370b]">
                            ৭ দিন পর পেমেন্ট + পরবর্তী কাজ
                        </p>
                        <p class="mt-1 text-slate-700">
                            ডেলিভারি ম্যান ৭ দিন পর আপনার বাসা থেকে প্যাকেজ করা কলমগুলো নিয়ে আসবেন এবং প্রথম মাসের
                            অগ্রিম ৫,০০০ টাকা প্রদান করবেন। কাজ ঠিক থাকলে বাকি ৯,০০০ পিস
                            কলম ও প্রয়োজনীয় সব কিছু পাঠানো হবে।
                        </p>
                    </div>
                    <div
                        class="rounded-xl border border-gray-200 bg-gradient-to-r from-white via-[#3b9398]/5 to-[#ff6d4d]/8 p-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <p class="font-medium text-[#ff370b]">
                            প্রয়োজনীয় কাগজপত্র
                        </p>
                        <p class="mt-1 text-slate-700">
                            ডেলিভেরী ম্যান যখন, আপনাকে প্রথমবার কলম দিতে যাবেন, তখন আপনার এক কপি পাসপোর্ট সাইজের ছবি, এবং আপনার আইডি কার্ডের ফটোকপি ডেলীভেরি ম্যানকে দিতে হবে ।
                        </p>
                    </div>
                </div>
            </section>

            <!-- Registration fee & license -->
            <section class="px-4 mt-6">
            
                <!-- Trade license display (non-downloadable) -->
                <div class="mt-4">
                    <p class="text-sm font-semibold text-[#1b2c40]">আমাদের  ই-ট্রেড লাইসেন্স</p>
                    <p class="mt-1 text-[12px] text-gray-600">
                        TRAD/DSCC/298395/2019
                    </p>
                    <div
                        class="mt-2 inline-block rounded-2xl border border-gray-200 bg-gray-50 p-2 shadow-sm relative"
                    >
                        <div class="overflow-hidden rounded-xl max-w-full">
                            <img
                                :src="encodeURI('/images/Trade License.jpg')"
                                alt="আমাদের ই-ট্রেড লাইসেন্স"
                                class="max-h-64 w-auto object-contain select-none"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 rounded-2xl bg-gradient-to-t from-white/40 via-transparent to-transparent"
                        ></div>
                    </div>
                </div>
            </section>

            <!-- Contact -->
            <section class="px-4 mt-6">
                <h2 class="text-xl font-semibold flex items-center gap-1 text-[#1b2c40]">
                    <svg
                        class="h-4 w-4 text-[#3b9398]"
                        viewBox="0 0 20 20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M5 3h10a1 1 0 011 1v12l-3-3H5a1 1 0 01-1-1V4a1 1 0 011-1z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span>যোগাযোগ</span>
                </h2>
                <p class="mt-2 text-sm text-gray-700">
                    01333130523 এই নাম্বারে IMO / Whatsapp এ মেসেজ অথবা কল করে বিস্তারিত
                    জানতে পারবেন এবং রেজিস্ট্রেশন হয়েছে কিনা তা নিশ্চিত হতে পারবেন।
                </p>

                <div class="mt-3">
                    <a
                        href="https://wa.me/8801333130523?text=%E0%A6%86%E0%A6%AE%E0%A6%BF%20TikTok%20%E0%A6%A5%E0%A7%87%E0%A6%95%E0%A7%87%20%E0%A6%8F%E0%A6%B8%E0%A7%87%E0%A6%9B%E0%A6%BF%2C%20%E0%A6%95%E0%A6%B2%E0%A6%AE%20%E0%A6%AA%E0%A7%8D%E0%A6%AF%E0%A6%BE%E0%A6%95%E0%A7%87%E0%A6%9F%E0%A6%BF%E0%A6%82%20%E0%A6%9C%E0%A6%AC%E0%A7%87%20%E0%A6%86%E0%A6%AC%E0xA7%87%E0%A6%A6%E0%A6%A8%20%E0%A6%95%E0%A6%B0তে%"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex w-full items-center justify-between gap-3 rounded-full bg-gradient-to-r from-[#65cf72] to-[#3b9398] px-4 py-2 text-[13px] font-semibold text-white shadow-sm shadow-[#65cf72]/40 hover:from-[#3b9398] hover:to-[#1b2c40] active:scale-95 transition-transform duration-200 hover:scale-[1.01]"
                    >
                        <div class="flex items-center gap-2">
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    d="M12.04 3C7.605 3 4 6.505 4 10.83c0 1.58.47 3.04 1.28 4.27L4 21l6.06-1.94c1.15.38 2.11.54 3.03.54h.01c4.43 0 8.01-3.5 8.01-7.82C21.11 6.5 16.47 3 12.04 3z"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M9.75 9.5c0 .35-.2.7-.32.9-.16.24-.34.47-.39.54-.08.1-.16.21-.16.36 0 .15.09.29.17.4.22.3.7 1.07 1.65 1.72 1 .66 1.8.83 2.17.92.1.03.22.02.3.01.09-.01.28-.11.32-.22.04-.11.04-.2.03-.22-.01-.02-.04-.03-.09-.05-.05-.03-.28-.14-.64-.3-.33-.16-.62-.29-.71-.31-.11-.04-.2-.03-.29.08-.09.11-.33.39-.4.47-.07.08-.15.09-.27.03-.13-.06-.54-.2-1.03-.64-.38-.34-.64-.76-.72-.89-.08-.13-.01-.2.06-.27.06-.06.13-.15.2-.23.07-.08.09-.14.13-.23.04-.09.02-.17-.01-.24-.03-.06-.69-1.64-.94-2.24-.25-.6-.5-.52-.69-.53-.18-.01-.38-.01-.49-.01-.11 0-.3.04-.46.2-.16.16-.6.58-.6 1.41z"
                                    fill="currentColor"
                                />
                            </svg>
                            <span class="hidden xs:inline">WhatsApp</span>
                        </div>

                        <span class="text-[13px] font-semibold">IMO/Whatsapp এ মেসেজ করুন</span>

                        <div class="flex items-center gap-2">
                            <span class="hidden xs:inline">Imo</span>
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <circle
                                    cx="12"
                                    cy="12"
                                    r="9"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                />
                                <path
                                    d="M9 9.5C9 8.67 9.67 8 10.5 8h3c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5h-3A1.5 1.5 0 0 1 9 14.5v-5Z"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                />
                                <path
                                    d="M11 11h2"
                                    stroke="currentColor"
                                    stroke-width="1.5"
                                    stroke-linecap="round"
                                />
                            </svg>
                        </div>
                    </a>
                </div>
            </section>

            <!-- Registration form -->
            <section class="px-4 mt-6">
                <div
                    class="job-application-card rounded-2xl border-2 border-[#3b9398]/30 bg-white shadow-xl shadow-slate-300/50 px-4 py-5 sm:px-6 sm:py-6"
                >
                    <div class="job-application-heading mb-5 border-b-2 border-dashed border-[#3b9398]/25 pb-4">
                        <p class="inline-flex items-center rounded-full bg-[#3b9398]/10 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.12em] text-[#24777b]">
                            চাকরির আবেদন ফরম
                        </p>
                        <h2 class="mt-3 text-xl font-bold text-[#1b2c40] sm:text-2xl">
                            কলম প্যাকেটিং চাকরির আবেদন
                        </h2>
                        <p class="mt-1 text-sm text-gray-600">
                            নিচের সব ঘর সঠিকভাবে পূরণ করুন। শেষে রেজিস্ট্রেশন ফি পাঠানো
                            নাম্বার ও টাকার পরিমাণ দিন।
                        </p>
                    </div>

                    <form
                        class="job-application-form space-y-4 text-[13px]"
                        @submit.prevent="handleMainFormSubmit"
                    >
                    <div>
                        <label class="block mb-1 text-[13px]">নাম</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#3b9398] focus:ring-1 focus:ring-[#3b9398]/40"
                            :class="{ 'error-blink-border': errors.name }"
                            data-error-field="main-name"
                            placeholder="আপনার পুরো নাম"
                        />
                        <p
                            v-if="errors.name"
                            class="mt-1 text-[11px] text-[#ff3131]"
                            :class="{ 'error-blink-text': errors.name }"
                        >
                            {{ errors.name }}
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">পিতা/স্বামীর নাম</label>
                        <input
                            v-model="form.fatherOrHusband"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">মাতার নাম</label>
                        <input
                            v-model="form.mother"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">মোবাইল নাম্বার</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                            :class="{ 'error-blink-border': errors.phone }"
                            data-error-field="main-phone"
                            placeholder="01XXXXXXXXX"
                        />
                        <p
                            v-if="errors.phone"
                            class="mt-1 text-[11px] text-[#ff3131]"
                            :class="{ 'error-blink-text': errors.phone }"
                        >
                            {{ errors.phone }}
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">সম্পূর্ন ঠিকানা</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                            :class="{ 'error-blink-border': errors.address }"
                            data-error-field="main-address"
                        />
                        <p
                            v-if="errors.address"
                            class="mt-1 text-[11px] text-[#ff3131]"
                            :class="{ 'error-blink-text': errors.address }"
                        >
                            {{ errors.address }}
                        </p>
                    </div>

                    <div>
                        <label class="block mb-1 text-[13px]">শিক্ষাগত যোগ্যতা</label>
                        <select
                            v-model="form.education"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        >
                            <option value="primary">প্রাথমিক (৫ম শ্রেণী)</option>
                            <option value="secondary">মাধ্যমিক (এস.এস.সি)</option>
                            <option value="higher_secondary">উচ্চ মাধ্যমিক (এইচ.এস.সি)</option>
                            <option value="bachelor">স্নাতক (ব্যাচেলর)</option>
                            <option value="masters">স্নাতকোত্তর (মাস্টার্স)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1 text-[12px]">
                            রেজিষ্ট্রেশন ফী
                        </label>
                        <input
                            v-model="form.nearestCourier"
                            type="text"
                            inputmode="numeric"
                            @blur="form.nearestCourier = normalizeRegistrationFee(form.nearestCourier)"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>


                    <button
                        type="submit"
                        :disabled="isSubmittingApplication"
                        class="job-submit-button mt-3 w-full rounded-xl px-4 py-4 text-[16px] font-bold text-white shadow-lg transition-all duration-200 sm:text-lg"
                        :class="
                            isSubmittingApplication
                                ? 'cursor-not-allowed bg-gray-400 shadow-gray-300'
                                : 'bg-gradient-to-r from-[#24777b] via-[#2d9295] to-[#3b9398] shadow-[#24777b]/30 hover:-translate-y-0.5 hover:from-[#1b2c40] hover:to-[#24777b] hover:shadow-xl active:translate-y-0 active:scale-[0.99]'
                        "
                    >
                        <span
                            v-if="isSubmittingApplication"
                            class="inline-flex items-center justify-center gap-3"
                        >
                            <!-- Spinner -->
                            <svg
                                class="h-5 w-5 animate-spin text-[#1b2c40]"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>

                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                ></path>
                            </svg>

                            <span>আবেদন জমা হচ্ছে...</span>
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center justify-center gap-3"
                        >
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" /></svg>

                            <span>আবেদন সম্পন্ন করুন ও সাবমিট করুন</span>
                        </span>
                    </button>

                    <p class="mt-2 text-[12px] text-gray-600">
                        সাবমিটের পর আমাদের এ্যাডমিন ম্যানেজার আপনার সাথে ভেরিফাইড
                        নাম্বার থেকে যোগাযোগ করে রেজিস্ট্রেশন ফি কনফার্ম করবেন।
                    </p>
                    </form>
                </div>
                <!-- Government trust & hotline section -->
                <div class="mt-5 rounded-2xl border border-[#1b2c40]/10 bg-white p-3 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-[#3b9398]">
                        জরুরি হটলাইন
                    </p>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <!-- Bangladesh Government logo -->
                        <div class="flex items-center gap-2">
                            <div class="h-12 w-12 rounded-full bg-white border border-gray-200 flex items-center justify-center overflow-hidden">
                                <img
                                    src="/images/bangladesh_gov.png"
                                    alt="বাংলাদেশ সরকার লোগো"
                                    class="h-10 w-10 object-contain"
                                />
                            </div>
                            <div class="text-[12px] leading-snug">
                                <p class="font-semibold text-[#1b2c40]">
                                    বাংলাদেশ সরকার
                                </p>
                                <p class="text-gray-600">
                                    অনুমোদিত একটি নারী উদ্যক্তা প্রতিষ্ঠান
                                </p>
                            </div>
                        </div>

                        <!-- National emergency hotline image -->
                        <div class="shrink-0">
                            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white max-w-[140px]">
                                <img
                                    src="/images/hotline_v.2_bn.png"
                                    alt="জরুরি হটলাইন নম্বর"
                                    class="w-full h-full object-contain"
                                />
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 text-[11px] text-gray-500">
                    </p>
                </div>
            </section>
        </main>


        <!-- Bottom sticky CTA -->
        <div
            v-show="showStickyCta"
            class="fixed bottom-0 left-0 right-0 px-3 pb-3 pt-2 bg-transparent z-40"
        >
            <div
                class="mx-auto max-w-md rounded-2xl bg-gradient-to-r from-[#65cf72] via-[#3b9398] to-[#ff6d4d] px-3 py-2 shadow-lg shadow-[#3b9398]/40 text-white"
            >
                <div class="flex items-center justify-center gap-2 text-[12px] sm:text-[13px]">
                    <svg
                        class="h-4 w-4 text-white"
                        viewBox="0 0 20 20"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <path
                            d="M6 3h8l2 4v7a2 2 0 01-2 2H6a2 2 0 01-2-2V7l2-4z"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                        <path
                            d="M10 8v4m0 0h2m-2 0H8"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />
                    </svg>
                    <span class="font-medium">
                        *রেজিস্ট্রেশন কনফার্ম হয়ে গেলে

                    </span>
                </div>
                <p class="mt-1 text-center text-[11px] text-white/80">
                    ২৪ ঘন্টার মধ্যে ডেলিভারি ম্যান আপনার বাসায় যাবে*
                </p>

                <button
                    type="button"
                    @click="showRegistrationModal = true"
                    class="mt-2 w-full rounded-full bg-white/95 px-4 py-2 text-[13px] font-semibold text-[#1b2c40] shadow-md shadow-white/30 transition-transform duration-200 active:scale-95"
                >
                    এখনই আবেদন করতে এখানে ক্লিক করুন
                </button>
            </div>
        </div>

        <!-- Duplicate phone alert modal -->
        <div
            v-if="showDuplicatePhoneModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white p-6 border-2 border-[#ff3131]/60 shadow-2xl text-center"
            >
                <div class="flex justify-center mb-4">
                    <div class="w-20 h-20 rounded-full bg-[#ff3131] flex items-center justify-center">
                        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 9v4m0 4h.01M5 12a7 7 0 1114 0 7 7 0 01-14 0z"></path>
                        </svg>
                    </div>
                </div>
                <h3 class="text-2xl font-bold text-[#ff3131] mb-2">
                    দুঃখিত!
                </h3>
                <p class="text-[15px] text-gray-800 font-semibold mb-4">
                    {{ duplicatePhoneMessage }}
                </p>
                <button
                    type="button"
                    @click="showDuplicatePhoneModal = false"
                    class="mt-2 px-6 py-2.5 bg-[#3b9398] text-white rounded-full text-[14px] font-semibold hover:bg-[#1b2c40] transition-colors"
                >
                    ঠিক আছে
                </button>
            </div>
        </div>

        <!-- Registration Payment Modal -->
        <div v-if="showPaymentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl border border-gray-200">
                <div class="relative overflow-hidden rounded-2xl border border-[#b9e8df] bg-gradient-to-br from-[#effcf8] via-white to-[#f0fdfa] p-4 sm:p-5">
                    <div class="pointer-events-none absolute -right-4 -top-4 h-18 w-18 rounded-full bg-[#3b9398]/10"></div>
                    <div class="relative flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="mb-1 inline-flex items-center gap-2 rounded-full bg-white px-3 py-1 text-xs font-bold text-[#24777b] shadow-sm ring-1 ring-[#b9e8df]">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.7 0-3 .9-3 2s1.3 2 3 2 3 1 3 2-1.3 2-3 2m0-10V6m0 12v-2m9-4a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                                আবেদন ফি জমা দিন
                            </div>
                            <h3 class="text-lg font-extrabold leading-snug text-[#1b2c40] sm:text-xl" data-v-f6035110="">ফরমটি জমা দিতে ২০ টাকা পাঠাতে হবে। </h3>
                        </div>
                        <button
                            type="button"
                            @click="showPaymentModal = false"
                            class="shrink-0 rounded-full bg-white p-2 text-gray-500 shadow-sm ring-1 ring-gray-200 transition hover:bg-gray-100 hover:text-gray-700"
                            aria-label="Close payment modal"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="relative rounded-xl border border-white/90 bg-white/90 px-1.5 shadow-sm">
                        <p class="mt-1 text-sm leading-6 text-[#334155]">
                            <span class="text-center d-block">আপনি যদি বিকাশ দিয়ে পাঠাতে চান তাহলে bKash অথবা যদি, </span> 
                            <span class="text-center d-block"> নগদ দিয়ে পাঠাতে চান তাহলে Nagad বেছে নিবেন</span>
                        </p>
                    </div>
                </div>

                <div class="mt-5">
                    <p class="pt-0.5 text-sm leading-6 text-black">
                        বিকাশ বা নগদ যেকোন একটি সিলেক্ট করার পর <span class="font-extrabold text-[#0005f0]">“টাকা পাঠান”</span> বাটনে চাপ দিন।
                    </p>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <button
                            v-for="method in paymentMethods"
                            :key="method.key"
                            type="button"
                            @click="selectPaymentMethod(method.key)"
                            class="flex items-center justify-center gap-2 rounded-xl border p-3 transition-all duration-200"
                            :class="selectedPaymentMethod === method.key
                                ? 'border-[#3b9398] bg-[#e6fbf7] shadow-sm ring-2 ring-[#3b9398]/20'
                                : 'border-gray-200 bg-gray-50 hover:border-[#3b9398]/60 hover:bg-white'"
                        >
                            <img :src="method.logo" :alt="method.label" class="h-8 w-8 object-contain" />
                            <span class="text-sm font-semibold text-[#1b2c40]">{{ method.label }}</span>
                        </button>
                    </div>
                </div>

                <button
                type="button"
                :disabled="!selectedPaymentMethod || isPaymentLoading"
                @click="openPaymentFrame"
                class="mt-5 w-full rounded-full px-4 py-3 text-[15px] font-semibold text-white transition-all duration-200"
                :class="selectedPaymentMethod && !isPaymentLoading
                    ? 'bg-[#3b9398] hover:bg-[#1b2c40] shadow-lg shadow-[#3b9398]/30'
                    : 'cursor-not-allowed bg-gray-300 text-gray-500'"
            >
                <span v-if="isPaymentLoading">
                    পেমেন্ট প্রস্তুত হচ্ছে...
                </span>

                <span v-else class="inline-flex items-center justify-center gap-2">
                    টাকা পাঠান
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14m-6-6 6 6-6 6" />
                    </svg>
                </span>
            </button>
            </div>
        </div>

        <!-- Payment iframe modal -->
        <div
            v-if="showPaymentFrame"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 px-4"
        >
            <div class="w-full max-w-md rounded-2xl bg-[#e6ecf6db] p-3 shadow-2xl">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold">আবেদন ফী পাঠান</h3>
                    </div>
                    <button
                        type="button"
                        @click="showPaymentFrame = false; paymentFrameUrl = ''"
                        class="rounded-full p-2 hover:bg-gray-100 hover:text-gray-700"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex justify-center overflow-hidden rounded-xl">
                    <iframe
                        v-if="paymentFrameUrl"
                        :src="paymentFrameUrl"
                        title="আবেদন ফী পাঠান"
                        style="width: 332px; height: 660px; border: 0;"
                    ></iframe>
                </div>
            </div>
        </div>

        <!-- Registration Form Modal -->
        <div
            v-if="showRegistrationModal"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/60 px-4 overflow-y-auto py-8"
        >
            <div
                class="job-application-card w-full max-w-lg rounded-2xl bg-white p-5 border-2 border-[#3b9398]/30 shadow-2xl my-auto"
            >
                <div class="flex items-center justify-between mb-3 border-b border-dashed border-gray-200 pb-2">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.18em] text-gray-500">
                            চাকরির আবেদন ফরম
                        </p>
                        <h3 class="mt-1 text-lg font-semibold text-[#1b2c40]">
                            কলম প্যাকেটিং চাকরির আবেদন
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="flex items-center gap-1.5 text-[13px] font-semibold text-red-600 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors"
                        @click="showRegistrationModal = false"
                    >
                        বন্ধ করুন
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form
                    class="job-application-form space-y-4 text-[13px]"
                    @submit.prevent="handleRegistrationFormSubmit"
                >
                    <div>
                        <label class="block mb-1 text-[13px]">নাম</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#3b9398] focus:ring-1 focus:ring-[#3b9398]/40"
                            data-error-field="modal-name"
                            placeholder="আপনার পুরো নাম"
                        />
                        <p v-if="errors.name" class="mt-1 text-[11px] text-[#ff3131]">
                            {{ errors.name }}
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">পিতা/স্বামীর নাম</label>
                        <input
                            v-model="form.fatherOrHusband"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">মাতার নাম</label>
                        <input
                            v-model="form.mother"
                            type="text"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">মোবাইল নাম্বার</label>
                        <input
                            v-model="form.phone"
                            type="tel"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                            data-error-field="modal-phone"
                            placeholder="01XXXXXXXXX"
                        />
                        <p v-if="errors.phone" class="mt-1 text-[11px] text-[#ff3131]">
                            {{ errors.phone }}
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">সম্পূর্ন ঠিকানা</label>
                        <textarea
                            v-model="form.address"
                            rows="2"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                            data-error-field="modal-address"
                        />
                        <p
                            v-if="errors.address"
                            class="mt-1 text-[11px] text-[#ff3131]"
                        >
                            {{ errors.address }}
                        </p>
                    </div>
                    <div>
                        <label class="block mb-1 text-[13px]">শিক্ষাগত যোগ্যতা</label>
                        <select
                            v-model="form.education"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        >
                            <option value="primary">প্রাথমিক (৫ম শ্রেণী)</option>
                            <option value="secondary">মাধ্যমিক (এস.এস.সি)</option>
                            <option value="higher_secondary">উচ্চ মাধ্যমিক (এইচ.এস.সি)</option>
                            <option value="bachelor">স্নাতক (ব্যাচেলর)</option>
                            <option value="masters">স্নাতকোত্তর (মাস্টার্স)</option>
                        </select>
                    </div>
                    <!-- <div>
                        <label class="block mb-1 text-[13px]">
                            কুরিয়ার নিতে-দিতে পরিবারের সহায়তা পাবেন?
                        </label>
                        <div class="flex gap-4 text-[14px]">
                            <label class="flex items-center gap-1">
                                <input
                                    v-model="form.courierHelp"
                                    type="radio"
                                    value="yes"
                                    class="h-3 w-3 text-[#3b9398]"
                                />
                                <span>হ্যা</span>
                            </label>
                            <label class="flex items-center gap-1">
                                <input
                                    v-model="form.courierHelp"
                                    type="radio"
                                    value="no"
                                    class="h-3 w-3"
                                />
                                <span>না</span>
                            </label>
                        </div>
                    </div> -->
                    <div>
                        <label class="block mb-1 text-[12px]">
                            রেজিষ্ট্রেশন ফী
                        </label>
                        <input
                            v-model="form.nearestCourier"
                            type="text"
                            inputmode="numeric"
                            @blur="form.nearestCourier = normalizeRegistrationFee(form.nearestCourier)"
                            class="w-full rounded-lg bg-white border border-gray-200 px-3 py-2 text-[14px] focus:outline-none focus:border-[#4b9fd8] focus:ring-1 focus:ring-[#4b9fd8]/40"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="isSubmittingApplication"
                        class="job-submit-button mt-3 w-full rounded-xl px-4 py-4 text-[16px] font-bold text-white shadow-lg transition-all duration-200 sm:text-lg"
                        :class="
                            isSubmittingApplication
                                ? 'cursor-not-allowed bg-gray-400 shadow-gray-300'
                                : 'bg-gradient-to-r from-[#24777b] via-[#2d9295] to-[#3b9398] shadow-[#24777b]/30 hover:-translate-y-0.5 hover:from-[#1b2c40] hover:to-[#24777b] hover:shadow-xl active:translate-y-0 active:scale-[0.99]'
                        "
                    >
                        <span
                            v-if="isSubmittingApplication"
                            class="inline-flex items-center justify-center gap-3"
                        >
                            <svg
                                class="h-5 w-5 animate-spin text-[#1b2c40]"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none"
                                viewBox="0 0 24 24"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>

                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                ></path>
                            </svg>

                            <span>আবেদন জমা হচ্ছে...</span>
                        </span>

                        <span
                            v-else
                            class="inline-flex items-center justify-center gap-3"
                        >
                            <svg class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M12 5l7 7-7 7" /></svg>

                            <span>আবেদন সম্পন্ন করুন ও সাবমিট করুন</span>
                        </span>
                    </button>

                    <p class="mt-2 text-[12px] text-gray-600">
                        সাবমিটের পর আমাদের এ্যাডমিন ম্যানেজার আপনার সাথে ভেরিফাইড
                        নাম্বার থেকে যোগাযোগ করে রেজিস্ট্রেশন ফি কনফার্ম করবেন।
                    </p>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
.job-application-card {
    position: relative;
    isolation: isolate;
    background: linear-gradient(180deg, #ffffff 0%, #fbfefe 100%);
    box-shadow: 0 16px 38px rgba(27, 44, 64, 0.12), 0 3px 8px rgba(27, 44, 64, 0.05);
}

.job-application-card::before {
    content: "";
    position: absolute;
    z-index: -1;
    inset: 0 0 auto;
    height: 6px;
    border-radius: 1rem 1rem 0 0;
    background: linear-gradient(90deg, #1b2c40, #3b9398, #65cf72);
}

.job-application-form > div {
    padding: 0.8rem 0.85rem;
    border: 1px solid #e2e8f0;
    border-radius: 0.8rem;
    background: #f8fbfc;
    transition: border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;
}

.job-application-form > div:focus-within {
    border-color: #3b9398;
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(59, 147, 152, 0.12);
}

.job-application-form label {
    display: block;
    margin-bottom: 0.45rem;
    color: #243b53;
    font-weight: 700;
    font-size: 0.875rem;
}

.job-application-form input:not([type="radio"]),
.job-application-form textarea,
.job-application-form select {
    min-height: 46px;
    border: 1px solid #cbd5e1;
    border-radius: 0.55rem;
    background-color: #fff;
    padding: 0.7rem 0.8rem;
    color: #1f2937;
    box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.035);
}

.job-application-form input:focus,
.job-application-form textarea:focus,
.job-application-form select:focus {
    border-color: #2d9295;
    outline: 2px solid transparent;
    box-shadow: 0 0 0 3px rgba(59, 147, 152, 0.15);
}

.job-submit-button {
    min-height: 58px;
    border: 1px solid rgba(255, 255, 255, 0.35);
    letter-spacing: 0.01em;
    box-shadow: 0 8px 0 rgba(27, 44, 64, 0.12), 0 14px 24px rgba(36, 119, 123, 0.2);
    text-shadow: 0 1px 1px rgba(0, 0, 0, 0.12);
}

.job-submit-button:not(:disabled):active {
    box-shadow: 0 2px 0 rgba(27, 44, 64, 0.15), 0 5px 10px rgba(36, 119, 123, 0.16);
}

.job-submit-button:disabled {
    opacity: 0.8;
    box-shadow: none;
}

@media (max-width: 480px) {
    .job-application-form > div { padding: 0.7rem; }
    .job-submit-button { min-height: 56px; }
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

@keyframes error-blink-border {
    0% {
        border-color: #ef4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.0);
        transform: scale(1);
    }
    25% {
        border-color: #f97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.6);
        transform: scale(1.01);
    }
    50% {
        border-color: #ef4444;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.7);
        transform: scale(1.02);
    }
    75% {
        border-color: #f97316;
        box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.6);
        transform: scale(1.01);
    }
    100% {
        border-color: #ef4444;
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
        transform: scale(1);
    }
}

@keyframes error-blink-text {
    0% {
        opacity: 0.4;
        transform: translateX(0);
    }
    20% {
        opacity: 1;
        transform: translateX(-3px);
    }
    40% {
        transform: translateX(3px);
    }
    60% {
        transform: translateX(-2px);
    }
    80% {
        transform: translateX(2px);
    }
    100% {
        opacity: 1;
        transform: translateX(0);
    }
}

.error-blink-border {
    border-color: #ef4444 !important;
    animation: error-blink-border 0.75s ease-out 0s 2;
}

.error-blink-text {
    animation: error-blink-text 0.75s ease-out 0s 2;
}
</style>
