<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';

interface FieldOption {
    value: string | number;
    label: string;
}

interface FieldDependency {
    field: string;
    values: Array<string | number>;
}

interface FormField {
    name: string;
    label: string;
    type: string;
    placeholder?: string | null;
    required: boolean;
    default?: string | number | boolean | null;
    options: FieldOption[];
    help_text?: string | null;
    section?: string | null;
    order: number;
    depends_on?: FieldDependency | null;
}

interface CommonField {
    label?: string;
    placeholder?: string;
    type?: string;
}

interface PublicForm {
    slug: string;
    form_name: string;
    description: string;
    fields: FormField[];
    common_fields: Record<string, CommonField>;
    compose_address_from: string[];
    payment_enabled: boolean;
    payment_amount: string | null;
    accepted_payment_methods: string[];
    merchant_name: string;
}

const props = defineProps<{
    form: PublicForm;
}>();

const common = reactive({
    name: '',
    phone: '',
    address: '',
});

const formData = reactive<Record<string, string | number | boolean | null>>({});

props.form.fields.forEach((field) => {
    if (field.type === 'checkbox' && field.options.length === 0) {
        formData[field.name] = Boolean(field.default);
        return;
    }

    formData[field.name] = field.default ?? (field.type === 'checkbox' ? false : '');
});

const errors = ref<Record<string, string>>({});
const status = ref('');
const submitting = ref(false);
const paying = ref(false);
const applicationId = ref<number | null>(null);
const showPayment = ref(false);
const method = ref('');

const composesAddress = computed(() => props.form.compose_address_from.length > 0);

const sections = computed(() => {
    const groups = new Map<string, FormField[]>();

    [...props.form.fields]
        .sort((a, b) => a.order - b.order)
        .forEach((field) => {
            const section = field.section || 'বিবরণ';
            const list = groups.get(section) ?? [];
            list.push(field);
            groups.set(section, list);
        });

    return [...groups.entries()];
});

const methodLabels: Record<string, string> = {
    bkash: 'bKash',
    nagad: 'Nagad',
    rocket: 'Rocket',
    upay: 'Upay',
};

const commonLabel = (key: 'name' | 'phone' | 'address', fallback: string) =>
    props.form.common_fields[key]?.label || fallback;

const csrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const fieldError = (name: string) => errors.value[name] || errors.value[`form_data.${name}`] || '';

const isVisible = (field: FormField) => {
    if (!field.depends_on) {
        return true;
    }

    return field.depends_on.values.map(String).includes(String(formData[field.depends_on.field] ?? ''));
};

const applyErrors = (payload: { errors?: Record<string, string | string[]>; message?: string }) => {
    const next: Record<string, string> = {};

    Object.entries(payload.errors || {}).forEach(([key, value]) => {
        next[key] = Array.isArray(value) ? value[0] : value;
    });

    errors.value = next;
    status.value = payload.message || 'আবেদনটি যাচাই হয়নি।';
};

const submit = async () => {
    if (submitting.value) {
        return;
    }

    submitting.value = true;
    errors.value = {};
    status.value = '';

    try {
        const response = await fetch('/submit-form', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                form_slug: props.form.slug,
                name: common.name,
                phone: common.phone,
                address: composesAddress.value ? null : common.address,
                form_data: Object.fromEntries(
                    props.form.fields
                        .filter((field) => isVisible(field))
                        .map((field) => [field.name, formData[field.name]]),
                ),
            }),
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            applyErrors(result);
            return;
        }

        applicationId.value = result.application_id ?? null;
        status.value = result.message;

        if (result.payment_enabled) {
            showPayment.value = true;
        }
    } catch {
        status.value = 'নেটওয়ার্ক সমস্যা। আবার চেষ্টা করুন।';
    } finally {
        submitting.value = false;
    }
};

const startPayment = async () => {
    if (paying.value || !applicationId.value || !method.value) {
        return;
    }

    paying.value = true;
    status.value = '';

    try {
        const response = await fetch('/init-payment', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                application_id: applicationId.value,
                method: method.value,
            }),
        });

        const result = await response.json();

        if (!response.ok || !result.success || !result.url) {
            applyErrors(result);
            return;
        }

        window.location.href = result.url;
    } catch {
        status.value = 'পেমেন্ট শুরু করা যায়নি।';
    } finally {
        paying.value = false;
    }
};
</script>

<template>
    <Head :title="form.form_name" />

    <main class="min-h-screen bg-[#f5f7fb] px-4 py-10 text-[#111827]">
        <form
            class="mx-auto max-w-2xl space-y-6 rounded-2xl border border-[#e5e7eb] bg-white p-6 shadow-sm"
            @submit.prevent="submit"
        >
            <header>
                <h1 class="text-2xl font-semibold">{{ form.form_name }}</h1>
                <p v-if="form.description" class="mt-2 text-sm text-[#4b5563]">
                    {{ form.description }}
                </p>
                <p v-if="form.payment_amount" class="mt-2 text-sm font-medium">
                    আবেদন ফি: {{ form.payment_amount }} টাকা
                    <span v-if="form.merchant_name"> · {{ form.merchant_name }}</span>
                </p>
            </header>

            <section class="space-y-4">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[#6b7280]">
                    আবেদনকারী
                </h2>

                <label class="block text-sm">
                    <span>{{ commonLabel('name', 'নাম') }} <i class="text-[#ff3131]">*</i></span>
                    <input
                        v-model="common.name"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                        :placeholder="form.common_fields.name?.placeholder"
                        required
                    />
                    <p v-if="fieldError('name')" class="mt-1 text-xs text-[#ff3131]">{{ fieldError('name') }}</p>
                </label>

                <label class="block text-sm">
                    <span>{{ commonLabel('phone', 'মোবাইল') }} <i class="text-[#ff3131]">*</i></span>
                    <input
                        v-model="common.phone"
                        type="tel"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                        :placeholder="form.common_fields.phone?.placeholder"
                        required
                    />
                    <p v-if="fieldError('phone')" class="mt-1 text-xs text-[#ff3131]">{{ fieldError('phone') }}</p>
                </label>

                <label v-if="!composesAddress" class="block text-sm">
                    <span>{{ commonLabel('address', 'ঠিকানা') }} <i class="text-[#ff3131]">*</i></span>
                    <textarea
                        v-if="(form.common_fields.address?.type || 'textarea') === 'textarea'"
                        v-model="common.address"
                        rows="3"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                        :placeholder="form.common_fields.address?.placeholder"
                        required
                    />
                    <input
                        v-else
                        v-model="common.address"
                        type="text"
                        class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                        required
                    />
                    <p v-if="fieldError('address')" class="mt-1 text-xs text-[#ff3131]">{{ fieldError('address') }}</p>
                </label>
            </section>

            <section
                v-for="[section, fields] in sections"
                v-show="fields.some(isVisible)"
                :key="section"
                class="space-y-4"
            >
                <h2 class="text-sm font-semibold uppercase tracking-wide text-[#6b7280]">
                    {{ section }}
                </h2>

                <div v-for="field in fields" v-show="isVisible(field)" :key="field.name">
                    <input v-if="field.type === 'hidden'" type="hidden" :value="String(formData[field.name] ?? '')" />

                    <label v-else class="block text-sm">
                        <span>
                            {{ field.label }}
                            <i v-if="field.required" class="text-[#ff3131]">*</i>
                        </span>

                        <textarea
                            v-if="field.type === 'textarea'"
                            v-model="formData[field.name]"
                            rows="3"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                            :placeholder="field.placeholder || undefined"
                            :required="field.required"
                        />

                        <select
                            v-else-if="field.type === 'select'"
                            v-model="formData[field.name]"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                            :required="field.required"
                        >
                            <option value="">নির্বাচন করুন</option>
                            <option v-for="option in field.options" :key="String(option.value)" :value="String(option.value)">
                                {{ option.label }}
                            </option>
                        </select>

                        <span v-else-if="field.type === 'radio'" class="mt-2 grid gap-2">
                            <label
                                v-for="option in field.options"
                                :key="String(option.value)"
                                class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2"
                            >
                                <input v-model="formData[field.name]" type="radio" :value="String(option.value)" :required="field.required" />
                                <span>{{ option.label }}</span>
                            </label>
                        </span>

                        <span v-else-if="field.type === 'checkbox' && field.options.length" class="mt-2 grid gap-2">
                            <label v-for="option in field.options" :key="String(option.value)" class="flex items-center gap-2">
                                <input type="checkbox" :value="String(option.value)" />
                                <span>{{ option.label }}</span>
                            </label>
                        </span>

                        <label v-else-if="field.type === 'checkbox'" class="mt-2 flex items-center gap-2">
                            <input v-model="formData[field.name]" type="checkbox" />
                            <span>{{ field.help_text || field.label }}</span>
                        </label>

                        <input
                            v-else
                            v-model="formData[field.name]"
                            :type="field.type"
                            class="mt-1 w-full rounded-lg border border-gray-200 px-3 py-2"
                            :placeholder="field.placeholder || undefined"
                            :required="field.required"
                        />

                        <p v-if="field.help_text && field.type !== 'checkbox'" class="mt-1 text-xs text-[#6b7280]">
                            {{ field.help_text }}
                        </p>
                        <p v-if="fieldError(field.name)" class="mt-1 text-xs text-[#ff3131]">
                            {{ fieldError(field.name) }}
                        </p>
                    </label>
                </div>
            </section>

            <p v-if="status" class="text-sm">{{ status }}</p>

            <button
                type="submit"
                class="w-full rounded-xl bg-[#1b2c40] px-4 py-3 font-semibold text-white disabled:bg-gray-400"
                :disabled="submitting"
            >
                {{ submitting ? 'জমা হচ্ছে...' : 'আবেদন জমা দিন' }}
            </button>
        </form>

        <section
            v-if="showPayment"
            class="mx-auto mt-6 max-w-2xl rounded-2xl border border-[#e5e7eb] bg-white p-6 shadow-sm"
        >
            <h2 class="text-lg font-semibold">পেমেন্ট মাধ্যম</h2>
            <div class="mt-4 grid gap-2">
                <label
                    v-for="item in form.accepted_payment_methods"
                    :key="item"
                    class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2"
                >
                    <input v-model="method" type="radio" name="payment_method" :value="item" />
                    <span>{{ methodLabels[item] || item }}</span>
                </label>
            </div>
            <button
                type="button"
                class="mt-4 w-full rounded-xl bg-[#24777b] px-4 py-3 font-semibold text-white disabled:bg-gray-400"
                :disabled="paying || !method"
                @click="startPayment"
            >
                {{ paying ? 'পেমেন্ট প্রস্তুত হচ্ছে...' : 'পেমেন্ট শুরু করুন' }}
            </button>
        </section>
    </main>
</template>
