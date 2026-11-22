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
const normalizeRegistrationFee = (_value: string | number): string => '20';
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
        name: form.value.name,
        father_or_husband: form.value.fatherOrHusband,
        mother: form.value.mother,
        phone: form.value.phone,
        address: form.value.address,
        education: form.value.education,
        courier_help: form.value.courierHelp,
        nearest_courier: normalizeRegistrationFee(form.value.nearestCourier),
        submitted: true,
    };

    const csrfToken = getCsrfToken();

    try {
        const response = await fetch('/job-applications', {
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

        if (!response.ok) {
            const serverErrors = result.errors || {};

            errors.value = serverErrors;

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
        currentApplicationId.value =
            result.application?.id ?? null;

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
           