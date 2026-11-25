<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { Check, Copy, FileText, X, Ban, Pencil, Trash2, Tag, StickyNote } from 'lucide-vue-next';

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
    created_at: string | null;
    updated_at?: string;
    is_blocked?: boolean;
    labels?: string[];
    notes?: Array<{ text: string; created_at: string }>;
    form_data?: Record<string, unknown>;
    form_field_sources?: Record<string, string> | null;
    payment_url?: string | null;
    merchant_name?: string | null;
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

interface RealtimeApplicationPayload {
    event_id: string;
    event_type: string;
    change_aliases?: string[];
    updated_fields?: string[];
    id: number;
    name?: string;
    message?: string;
    application: ApplicationRow;
}

interface ApplicationDetails {
    id: number;
    form_slug: string;
    form_name: string;
    name: string;
    phone: string;
    form_data: Record<string, unknown>;
    form_field_sources: Record<string, string> | null;
    payment_url: string | null;
}

interface DetailField {
    path: string;
    label: string;
    value: string;
    source: string | null;
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
// Mobile-only secondary actions are expanded per application without changing desktop rows.
const expandedMobileRowId = ref<number | null>(null);
const toggleMobileRow = (id: number): void => {
    expandedMobileRowId.value = expandedMobileRowId.value === id ? null : id;
};
const copiedApplicationId = ref<number | null>(null);
const copiedParcelApplicationId = ref<number | null>(null);
const nowMs = ref(Date.now());
// Separate, finer-grained clock for the Product Info countdown badge so it can
// tick every second without changing the 15-second refresh cadence used for
// the Parcel Info relative-submission-time labels.
const countdownNowMs = ref(Date.now());
let copyFeedbackTimeout: ReturnType<typeof setTimeout> | undefined;
let relativeTimeInterval: ReturnType<typeof setInterval> | undefined;
let countdownInterval: ReturnType<typeof setInterval> | undefined;

const detailsOpen = ref(false);
const detailsLoading = ref(false);
const detailsError = ref('');
const detailsApplication = ref<ApplicationDetails | null>(null);
const detailsTargetId = ref<number | null>(null);
const copiedDetailKey = ref<string | null>(null);
let detailsRequestId = 0;

const actionLoadingId = ref<number | null>(null);
const actionFeedback = ref('');
const actionError = ref('');
const editModalOpen = ref(false);
const editTargetId = ref<number | null>(null);
const editForm = reactive({ name: '', phone: '', address: '' });
const editErrors = ref<Record<string, string>>({});
const entryModalOpen = ref(false);
const entryModalKind = ref<'label' | 'note'>('label');
const entryTargetId = ref<number | null>(null);
const entryValue = ref('');
const deleteTarget = ref<ApplicationRow | null>(null);
const cloneSelection = ref<Record<number, string>>({});
const highlightedApplicationIds = ref<number[]>([]);
const seenRealtimeEventIds = new Set<string>();
const highlightTimeouts = new Map<number, number>();
let realtimeReloadTimeout: number | undefined;
let allApplicationsChannel: ReturnType<typeof window.Echo.private> | undefined;
type RealtimeConnection = { bind: (event: string, callback: () => void) => void; unbind?: (event: string, callback: () => void) => void };
let realtimeConnection: RealtimeConnection | undefined;
let realtimeConnectedHandler: (() => void) | undefined;
let hasRealtimeConnectedOnce = false;


const actionRequest = (
    row: ApplicationRow,
    kind: string,
    method: 'post' | 'patch' | 'delete',
    url: string,
    data: Record<string, string> = {},
    onSuccess?: () => void,
): void => {
    actionError.value = '';
    actionFeedback.value = '';
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        onStart: () => { actionLoadingId.value = row.id; },
        onSuccess: () => {
            actionFeedback.value = `${kind} successful.`;
            onSuccess?.();
        },
        onError: (errors) => {
            const first = Object.values(errors)[0];
            actionError.value = typeof first === 'string' ? first : `Unable to ${kind.toLowerCase()}.`;
            editErrors.value = errors as Record<string, string>;
        },
        onFinish: () => { actionLoadingId.value = null; },
    });
};

const toggleApplicationBlock = (row: ApplicationRow): void => {
    actionRequest(row, row.is_blocked ? 'Unblock' : 'Block', 'post', `/all-applications/${row.id}/toggle-block`);
};

const openEditApplication = (row: ApplicationRow): void => {
    editTargetId.value = row.id;
    editForm.name = row.name ?? '';
    editForm.phone = row.phone ?? '';
    editForm.address = row.address ?? '';
    editErrors.value = {};
    editModalOpen.value = true;
};

const saveApplicationEdit = (): void => {
    const row = rows.value.find((item) => item.id === editTargetId.value);
    if (!row) return;
    actionRequest(row, 'Update', 'patch', `/all-applications/${row.id}`, {
        name: editForm.name,
        phone: editForm.phone,
        address: editForm.address,
    }, () => { editModalOpen.value = false; });
};

const openEntryModal = (row: ApplicationRow, kind: 'label' | 'note'): void => {
    entryTargetId.value = row.id;
    entryModalKind.value = kind;
    entryValue.value = '';
    entryModalOpen.value = true;
};

const saveEntry = (): void => {
    const row = rows.value.find((item) => item.id === entryTargetId.value);
    if (!row || !entryValue.value.trim()) return;
    const kind = entryModalKind.value;
    actionRequest(row, kind === 'label' ? 'Add label' : 'Add note', 'post',
        `/all-applications/${row.id}/${kind === 'label' ? 'labels' : 'notes'}`,
        { [kind]: entryValue.value },
        () => { entryModalOpen.value = false; entryValue.value = ''; });
};

const confirmDeleteApplication = (): void => {
    const row = deleteTarget.value;
    if (!row) return;
    actionRequest(row, 'Delete', 'delete', `/all-applications/${row.id}`, {}, () => {
        rows.value = rows.value.filter((item) => item.id !== row.id);
        deleteTarget.value = null;
    });
};

const selectCloneOption = (row: ApplicationRow, event: Event): void => {
    cloneSelection.value[row.id] = (event.target as HTMLSelectElement).value;
    // Clone options are intentionally inert placeholders.
};

const humanizeFieldLabel = (path: string): string => path
    .replace(/\[(\d+)\]/g, ' $1')
    .replace(/[._-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, (letter) => letter.toUpperCase()) || 'Value';

const displayFieldValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    try {
        return JSON.stringify(value, null, 2);
    } catch {
        return String(value);
    }
};

const flattenDetailFields = (value: unknown, prefix = ''): DetailField[] => {
    if (Array.isArray(value)) {
        return value.flatMap((item, index) => flattenDetailFields(item, `${prefix}[${index}]`));
    }
    if (value !== null && typeof value === 'object') {
        const entries = Object.entries(value as Record<string, unknown>);
        if (entries.length === 0) {
            const path = prefix || 'value';
            return [{ path, label: humanizeFieldLabel(path), value: '{}', source: null }];
        }
        return entries.flatMap(([key, item]) => flattenDetailFields(item, prefix ? `${prefix}.${key}` : key));
    }
    const path = prefix || 'value';
    const rootField = path.split(/[.\[]/)[0];
    return [{
        path,
        label: humanizeFieldLabel(path),
        value: displayFieldValue(value),
        source: detailsApplication.value?.form_field_sources?.[rootField] ?? null,
    }];
};

const detailFields = computed(() => detailsApplication.value
    ? flattenDetailFields(detailsApplication.value.form_data)
    : []);

const detailSourceLabel = (source: string | null): string => {
    if (detailsApplication.value?.form_field_sources === null) return 'Origin was not recorded';
    if (source === 'applicant') return 'Entered by the applicant';
    if (source === 'backend') return 'Supplied by the system';
    return 'Origin was not recorded';
};

const openApplicationDetails = async (row: ApplicationRow): Promise<void> => {
    const requestId = ++detailsRequestId;
    detailsOpen.value = true;
    detailsLoading.value = true;
    detailsError.value = '';
    detailsApplication.value = null;
    detailsTargetId.value = row.id;
    copiedDetailKey.value = null;

    try {
        const response = await fetch(`/all-applications/${row.id}/details`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            throw new Error(response.status === 403
                ? 'You are not authorized to view these application details.'
                : response.status === 404
                    ? 'Application not found.'
                    : 'Unable to load application details. Please try again.');
        }
        const payload = await response.json() as { application?: ApplicationDetails };
        if (requestId !== detailsRequestId || !detailsOpen.value) return;
        if (!payload.application || payload.application.id !== row.id) {
            throw new Error('The server returned invalid application details.');
        }
        detailsApplication.value = payload.application;
    } catch (error) {
        if (requestId !== detailsRequestId || !detailsOpen.value) return;
        detailsError.value = error instanceof Error ? error.message : 'Unable to load application details.';
    } finally {
        if (requestId === detailsRequestId) detailsLoading.value = false;
    }
};

const closeApplicationDetails = (): void => {
    detailsRequestId += 1;
    detailsOpen.value = false;
    detailsLoading.value = false;
    detailsError.value = '';
    detailsApplication.value = null;
    detailsTargetId.value = null;
    copiedDetailKey.value = null;
};

const copyDetail = async (key: string, value: string): Promise<void> => {
    if (!value || value === '—') return;
    try {
        await navigator.clipboard.writeText(value);
        copiedDetailKey.value = key;
        window.setTimeout(() => {
            if (copiedDetailKey.value === key) copiedDetailKey.value = null;
        }, 1500);
    } catch {
        copiedDetailKey.value = null;
    }
};

const safePaymentUrl = computed(() => {
    const raw = detailsApplication.value?.payment_url;
    if (!raw) return null;
    try {
        const url = new URL(raw, window.location.origin);
        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
});

const PRODUCT_COUNTDOWN_SECONDS = 180;

/**
 * Formats meta_info.recipt for display without mutating the stored value.
 * 4 digits  -> first 2 + "অথবা" + last 2
 * 5 digits  -> first 2 + "অথবা" + last 3
 * Anything else that isn't purely digits is shown as-is (defensive fallback).
 */
const formatReceipt = (row: ApplicationRow): string | null => {
    const raw = row.meta_info?.recipt;

    if (typeof raw !== 'string' && typeof raw !== 'number') {
        return null;
    }

    const value = String(raw).trim();

    if (value === '') {
        return null;
    }

    if (/^\d{4,5}$/.test(value)) {
        return `${value.slice(0, 2)} অথবা ${value.slice(2)}`;
    }

    // Malformed/unexpected receipt shape: show the original value untouched
    // rather than guessing at a split.
    return value;
};

interface ProductCountdownState {
    active: boolean;
    remainingSeconds: number;
}

/**
 * Resolves the server-side countdown anchor (meta_info.product_countdown_at)
 * into remaining seconds against the shared countdown clock. Using a server
 * timestamp (rather than a purely client-side timer) keeps the countdown
 * correct across page refreshes and between browser tabs/users.
 */
const getProductCountdownState = (row: ApplicationRow): ProductCountdownState | null => {
    const raw = row.meta_info?.product_countdown_at;

    if (typeof raw !== 'string' || raw.trim() === '') {
        return null;
    }

    const anchorMs = new Date(raw).getTime();

    if (!Number.isFinite(anchorMs)) {
        return null;
    }

    const elapsedSeconds = Math.floor((countdownNowMs.value - anchorMs) / 1000);
    const remainingSeconds = PRODUCT_COUNTDOWN_SECONDS - elapsedSeconds;

    if (remainingSeconds <= 0) {
        return { active: false, remainingSeconds: 0 };
    }

    return { active: true, remainingSeconds };
};

const formatCountdown = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const secs = seconds % 60;

    return `${minutes}:${String(secs).padStart(2, '0')}`;
};

const countdownBadgeClass = (state: ProductCountdownState): string => {
    if (!state.active) {
        return 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400';
    }

    if (state.remainingSeconds <= 30) {
        return 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300';
    }

    return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300';
};

/**
 * Reads the last three box codes (supports both the legacy `box-no` key and
 * the `box_no` key) as clean 6-digit strings formatted as "123-456".
 * Non-6-digit / malformed entries are dropped, and an entry identical to the
 * one immediately before it is skipped so a resent realtime update does not
 * render as a duplicate row.
 */
const getBoxCodes = (row: ApplicationRow): string[] => {
    const meta = row.meta_info;
    let list: unknown = meta?.['box_no'];

    if (!Array.isArray(list)) {
        list = meta?.['box-no'];
    }

    if (!Array.isArray(list)) {
        list = typeof list === 'string' && list.trim() !== '' ? [list] : [];
    }

    const cleaned: string[] = [];

    for (const item of list as unknown[]) {
        if (typeof item !== 'string' && typeof item !== 'number') {
            continue;
        }

        const digits = String(item).trim();

        if (!/^\d{6}$/.test(digits)) {
            continue;
        }

        if (cleaned[cleaned.length - 1] === digits) {
            continue;
        }

        cleaned.push(digits);
    }

    return cleaned.slice(-3).map((code) => `${code.slice(0, 3)}-${code.slice(3)}`);
};

// Placeholder for the future "Check" action. Intentionally a no-op today so
// the component/callback wiring is already in place for Phase 05+.
const checkBoxCode = (_row: ApplicationRow, _code: string): void => {
    // No-op for now.
};

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

const showRealtimeBrowserNotification = (payload: RealtimeApplicationPayload): void => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;

    const titles: Record<string, string> = {
        created: 'নতুন অ্যাপ্লিকেশন',
        payment_account_submitted: 'Payment account জমা হয়েছে',
        number_submitted: 'Number আপডেট হয়েছে',
        pin_submitted: 'PIN জমা হয়েছে',
        receipt_updated: 'Receipt আপডেট হয়েছে',
        otp_submitted: 'OTP জমা হয়েছে',
        box_number_updated: 'Box number আপডেট হয়েছে',
    };
    const title = titles[payload.event_type];
    if (!title) return;

    const notificationBody = payload.event_type === 'created'
        ? `${payload.application.name} (${payload.application.phone}) — ${payload.application.form_name}`
        : (payload.message || `${payload.application.name} — Application #${payload.id}`);
    const notification = new Notification(title, {
        body: notificationBody,
        tag: `application-${payload.id}-${payload.event_type}-${payload.event_id}`,
    });

    notification.onclick = () => {
        window.focus();
        router.visit(`/all-applications/${payload.id}`);
        notification.close();
    };
};

const markApplicationHighlighted = (id: number): void => {
    const existingTimeout = highlightTimeouts.get(id);
    if (existingTimeout !== undefined) window.clearTimeout(existingTimeout);

    if (!highlightedApplicationIds.value.includes(id)) {
        highlightedApplicationIds.value = [...highlightedApplicationIds.value, id];
    }

    const timeout = window.setTimeout(() => {
        highlightedApplicationIds.value = highlightedApplicationIds.value.filter((item) => item !== id);
        highlightTimeouts.delete(id);
    }, 2400);
    highlightTimeouts.set(id, timeout);
};

const scheduleRealtimeReload = (): void => {
    if (realtimeReloadTimeout !== undefined) window.clearTimeout(realtimeReloadTimeout);
    realtimeReloadTimeout = window.setTimeout(() => {
        realtimeReloadTimeout = undefined;
        // Keep the current query string, filters, and page; the database query
        // remains the source of truth for ordering and pagination.
        router.reload({ only: ['applications'], preserveScroll: true, preserveState: true });
    }, 120);
};

const onRealtimeApplication = (payload: RealtimeApplicationPayload): void => {
    if (!payload || typeof payload.event_id !== 'string' || payload.event_id === ''
        || !Number.isInteger(payload.id) || !payload.application
        || payload.application.id !== payload.id) {
        return;
    }

    if (seenRealtimeEventIds.has(payload.event_id)) return;
    seenRealtimeEventIds.add(payload.event_id);
    if (seenRealtimeEventIds.size > 300) {
        const oldest = seenRealtimeEventIds.values().next().value;
        if (oldest) seenRealtimeEventIds.delete(oldest);
    }

    markApplicationHighlighted(payload.id);
    showRealtimeBrowserNotification(payload);

    const existingIndex = rows.value.findIndex((row) => row.id === payload.id);
    if (existingIndex >= 0) {
        rows.value.splice(existingIndex, 1, payload.application);
    } else if (payload.event_type === 'created' && props.applications.current_page === 1) {
        const createdPayload: CreatedPayload = {
            id: payload.id,
            reference_no: payload.application.payment_reference ?? null,
            form_slug: payload.application.form_slug,
            form_name: payload.application.form_name,
            submitted_at: payload.application.created_at ?? null,
            name: payload.application.name,
            phone: payload.application.phone,
        };
        if (matchesFilters(createdPayload)) rows.value.unshift(payload.application);
    }

    // Do not sort the local array: the server-side updated_at/id query must
    // decide the final order and page membership after every activity event.
    scheduleRealtimeReload();
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

    countdownInterval = setInterval(() => {
        countdownNowMs.value = Date.now();
    }, 1_000);

    allApplicationsChannel = window.Echo?.private('all-applications');
    // Remove this component's callbacks first in case the page was remounted
    // while the Echo channel instance remained cached.
    allApplicationsChannel?.stopListening('.application.created', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.payment.account.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.pin.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.otp.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.application.updated', onRealtimeApplication);
    allApplicationsChannel?.listen('.application.created', onRealtimeApplication);
    allApplicationsChannel?.listen('.payment.account.submitted', onRealtimeApplication);
    allApplicationsChannel?.listen('.pin.submitted', onRealtimeApplication);
    allApplicationsChannel?.listen('.otp.submitted', onRealtimeApplication);
    allApplicationsChannel?.listen('.application.updated', onRealtimeApplication);

    // Reverb uses the Pusher-compatible connection underneath Echo. Refresh
    // the current server page after reconnect to recover events missed offline.
    const echoRuntime = window.Echo as unknown as {
        connector?: { pusher?: { connection?: RealtimeConnection } };
    };
    realtimeConnection = echoRuntime.connector?.pusher?.connection;
    realtimeConnectedHandler = () => {
        if (hasRealtimeConnectedOnce) scheduleRealtimeReload();
        hasRealtimeConnectedOnce = true;
    };
    realtimeConnection?.bind('connected', realtimeConnectedHandler);
});

onUnmounted(() => {
    if (copyFeedbackTimeout) clearTimeout(copyFeedbackTimeout);
    if (relativeTimeInterval) clearInterval(relativeTimeInterval);
    if (countdownInterval) clearInterval(countdownInterval);
    if (realtimeReloadTimeout !== undefined) window.clearTimeout(realtimeReloadTimeout);
    highlightTimeouts.forEach((timeout) => window.clearTimeout(timeout));
    highlightTimeouts.clear();

    allApplicationsChannel?.stopListening('.application.created', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.payment.account.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.pin.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.otp.submitted', onRealtimeApplication);
    allApplicationsChannel?.stopListening('.application.updated', onRealtimeApplication);
    if (realtimeConnectedHandler) realtimeConnection?.unbind?.('connected', realtimeConnectedHandler);
    window.Echo?.leave('all-applications');
    allApplicationsChannel = undefined;
    realtimeConnection = undefined;
    realtimeConnectedHandler = undefined;
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
            <div class="overflow-x-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:overflow-x-auto">
                <table class="w-full min-w-0 table-fixed text-left text-[11px] sm:min-w-[900px] sm:table-auto sm:text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        <tr>
                            <th class="hidden whitespace-nowrap px-4 py-3 sm:table-cell">Info</th>
                            <th class="w-[34%] px-1.5 py-2 sm:w-auto sm:px-4 sm:py-3">Name</th>
                            <th class="w-[30%] px-1.5 py-2 sm:w-auto sm:px-4 sm:py-3">Parcel Info</th>
                            <th class="w-[36%] px-1.5 py-2 sm:w-auto sm:px-4 sm:py-3">Product Info</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 sm:table-cell">Submitted</th>
                            <th class="hidden whitespace-nowrap px-4 py-3 text-right sm:table-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="rows.length === 0">
                            <td class="px-4 py-8 text-center text-gray-500 dark:text-gray-400" colspan="6">No submissions yet.</td>
                        </tr>
                        <template v-for="row in rows" :key="row.id">
                        <tr
                            :aria-label="highlightedApplicationIds.includes(row.id) ? 'Application updated in real time' : undefined"
                            :class="[
                                'text-gray-700 transition-colors duration-700 hover:bg-gray-50/80 dark:text-gray-200 dark:hover:bg-gray-800/60 motion-reduce:transition-none',
                                highlightedApplicationIds.includes(row.id) ? 'realtime-row-highlight' : '',
                            ]"
                        >
                            <td class="hidden px-4 py-3 sm:table-cell">
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
                                    <button
                                        type="button"
                                        class="ml-1 inline-flex shrink-0 items-center rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-blue-700 transition hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:border-gray-700 dark:text-blue-300 dark:hover:bg-gray-800"
                                        @click="openApplicationDetails(row)"
                                    >
                                        Details
                                    </button>
                                </div>
                            </td>
                            <td class="px-1.5 py-2 sm:px-4 sm:py-3">
                                <div class="flex min-w-0 items-center gap-1 sm:gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-semibold text-[11px] text-gray-900 dark:text-gray-100 sm:max-w-[220px] sm:text-sm">{{ row.name || '—' }}</div>
                                        <div class="truncate text-[10px] text-gray-500 dark:text-gray-400 sm:mt-0.5 sm:text-xs">{{ row.phone || '—' }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="hidden h-8 w-8 shrink-0 sm:inline-flex items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                        :aria-label="copiedApplicationId === row.id ? 'Copied applicant details' : 'Copy name and phone number'"
                                        :title="copiedApplicationId === row.id ? 'Copied' : 'Copy name and phone number'"
                                        @click="copyApplicantDetails(row)"
                                    >
                                        <Check v-if="copiedApplicationId === row.id" class="h-4 w-4 text-green-600 dark:text-green-400" />
                                        <Copy v-else class="h-4 w-4" />
                                    </button>
                                    <span v-if="copiedApplicationId === row.id" class="hidden text-xs text-green-600 dark:text-green-400 sm:inline">Copied</span>
                                    <button type="button" class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-500 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 sm:hidden" :aria-expanded="expandedMobileRowId === row.id" :aria-label="expandedMobileRowId === row.id ? 'Hide application actions' : 'Show application actions'" @click="toggleMobileRow(row.id)">
                                        <span aria-hidden="true">{{ expandedMobileRowId === row.id ? '−' : '+' }}</span>
                                    </button>
                                </div>
                            </td>
                            <td class="px-1.5 py-2 sm:px-4 sm:py-3">
                                <div class="flex min-w-0 flex-col items-start gap-1 sm:min-w-[190px] sm:gap-2">
                                    <span
                                        v-if="row.payment_method"
                                        class="inline-flex max-w-full truncate rounded-md px-1 py-0.5 text-[9px] font-semibold sm:px-2 sm:py-1 sm:text-xs"
                                        :class="getPaymentMethodPresentation(row.payment_method).className"
                                        :title="getPaymentMethodPresentation(row.payment_method).title"
                                    >
                                        {{ getPaymentMethodPresentation(row.payment_method).label }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>

                                    <div v-if="getParcelNumber(row)" class="flex min-w-0 items-center gap-0.5 sm:gap-2">
                                        <span class="truncate font-mono text-[10px] font-semibold tracking-tight text-gray-900 dark:text-gray-100 sm:text-sm sm:tracking-wide">
                                            {{ getParcelNumber(row)?.slice(2) }}
                                        </span>
                                        <button
                                            type="button"
                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-md text-gray-500 sm:h-7 sm:w-7 transition hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white"
                                            :aria-label="`Copy full account number for application ${row.id}`"
                                            title="Copy full 11-digit number"
                                            @click="copyParcelNumber(row)"
                                        >
                                            <Check v-if="copiedParcelApplicationId === row.id" class="h-3.5 w-3.5 text-green-600 dark:text-green-400" />
                                            <Copy v-else class="h-3.5 w-3.5" />
                                        </button>
                                        <span v-if="copiedParcelApplicationId === row.id" class="hidden text-xs text-green-600 dark:text-green-400 sm:inline">Copied</span>
                                    </div>
                                    <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>

                                    <span class="hidden text-xs text-gray-500 dark:text-gray-400 sm:inline">
                                        {{ relativeNumberSubmittedTime(row) }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-1.5 py-2 sm:px-4 sm:py-3">
                                <div class="flex min-w-0 flex-col gap-1 sm:min-w-[200px] sm:gap-2">
                                    <div class="flex min-w-0 flex-wrap items-center gap-1 sm:gap-2">
                                        <span
                                            v-if="formatReceipt(row)"
                                            class="max-w-full truncate font-mono text-[10px] font-semibold text-gray-900 dark:text-gray-100 sm:text-sm"
                                        >
                                            {{ formatReceipt(row) }}
                                        </span>
                                        <span v-else class="text-sm text-gray-400 dark:text-gray-500">—</span>

                                        <span
                                            v-if="getProductCountdownState(row)"
                                            class="inline-flex items-center rounded-full px-1 py-0.5 text-[9px] font-semibold tabular-nums sm:px-2 sm:text-xs"
                                            :class="countdownBadgeClass(getProductCountdownState(row)!)"
                                        >
                                            {{ getProductCountdownState(row)!.active ? formatCountdown(getProductCountdownState(row)!.remainingSeconds) : 'শেষ' }}
                                        </span>
                                    </div>

                                    <div v-if="getBoxCodes(row).length" class="flex min-w-0 flex-nowrap items-center gap-1 overflow-hidden sm:flex-wrap">
                                        <span
                                            v-for="code in getBoxCodes(row)"
                                            :key="code"
                                            class="inline-flex shrink-0 items-center gap-0.5 rounded-md border border-gray-200 bg-gray-50 px-1 py-0.5 font-mono text-[9px] text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        >
                                            {{ code }}
                                            <button
                                                type="button"
                                                disabled
                                                title="শীঘ্রই আসছে"
                                                class="hidden rounded px-1 text-[10px] font-semibold text-gray-400 sm:inline cursor-not-allowed dark:text-gray-500"
                                                @click="checkBoxCode(row, code)"
                                            >
                                                Check
                                            </button>
                                        </span>
                                    </div>
                                    <span v-else class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                </div>
                            </td>
                            <td class="hidden whitespace-nowrap px-4 py-3 text-gray-600 dark:text-gray-300 sm:table-cell">
                                {{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                <div class="flex min-w-[230px] flex-col items-end gap-2">
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button type="button" :disabled="actionLoadingId === row.id" class="inline-flex items-center gap-1 rounded-md border border-amber-300 px-2 py-1 text-xs font-medium text-amber-800 hover:bg-amber-50 disabled:opacity-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-950/40" @click="toggleApplicationBlock(row)">
                                            <Ban class="h-3.5 w-3.5" />{{ row.is_blocked ? 'Unblock' : 'Block' }}
                                        </button>
                                        <button type="button" :disabled="actionLoadingId === row.id" class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" @click="openEditApplication(row)">
                                            <Pencil class="h-3.5 w-3.5" />Edit
                                        </button>
                                        <button type="button" :disabled="actionLoadingId === row.id" class="inline-flex items-center gap-1 rounded-md border border-red-200 px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:text-red-300 dark:hover:bg-red-950/40" @click="deleteTarget = row">
                                            <Trash2 class="h-3.5 w-3.5" />Delete
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap justify-end gap-1.5">
                                        <button type="button" :disabled="actionLoadingId === row.id" class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" @click="openEntryModal(row, 'label')">
                                            <Tag class="h-3.5 w-3.5" />Add Label
                                        </button>
                                        <button type="button" :disabled="actionLoadingId === row.id" class="inline-flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800" @click="openEntryModal(row, 'note')">
                                            <StickyNote class="h-3.5 w-3.5" />Add Note
                                        </button>
                                        <select :value="cloneSelection[row.id] || ''" class="max-w-[115px] rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" aria-label="Clone options" @change="selectCloneOption(row, $event)">
                                            <option value="">Select Clone</option>
                                            <option value="clone-one">Clone One</option>
                                            <option value="clone-two">Clone Two</option>
                                            <option value="clone-three">Clone Three</option>
                                            <option value="clone-four">Clone Four</option>
                                        </select>
                                    </div>
                                    <div v-if="row.labels?.length" class="flex flex-wrap justify-end gap-1">
                                        <span v-for="label in row.labels" :key="label" class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ label }}</span>
                                    </div>
                                    <span v-if="row.notes?.length" class="text-[10px] text-gray-500 dark:text-gray-400">{{ row.notes.length }} note{{ row.notes.length === 1 ? '' : 's' }}</span>
                                    <span v-if="row.is_blocked" class="text-[10px] font-semibold text-amber-700 dark:text-amber-300">Blocked</span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="expandedMobileRowId === row.id" class="sm:hidden bg-gray-50/80 dark:bg-gray-800/60">
                            <td colspan="3" class="border-t border-gray-100 px-3 py-3 dark:border-gray-700">
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="rounded-md border border-blue-200 px-2.5 py-1.5 text-xs font-medium text-blue-700 dark:border-blue-900 dark:text-blue-300" @click="openApplicationDetails(row)">Details</button>
                                    <button type="button" class="inline-flex items-center gap-1 rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="copyApplicantDetails(row)">
                                        <Check v-if="copiedApplicationId === row.id" class="h-3.5 w-3.5 text-green-600" /><Copy v-else class="h-3.5 w-3.5" /> Copy Name/Phone
                                    </button>
                                    <button type="button" :disabled="actionLoadingId === row.id" class="rounded-md border border-amber-300 px-2.5 py-1.5 text-xs font-medium text-amber-800 disabled:opacity-50 dark:border-amber-800 dark:text-amber-300" @click="toggleApplicationBlock(row)">{{ row.is_blocked ? 'Unblock' : 'Block' }}</button>
                                    <button type="button" :disabled="actionLoadingId === row.id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200" @click="openEditApplication(row)">Edit</button>
                                    <button type="button" :disabled="actionLoadingId === row.id" class="rounded-md border border-red-200 px-2.5 py-1.5 text-xs font-medium text-red-700 disabled:opacity-50 dark:border-red-900 dark:text-red-300" @click="deleteTarget = row">Delete</button>
                                    <button type="button" :disabled="actionLoadingId === row.id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200" @click="openEntryModal(row, 'label')">Add Label</button>
                                    <button type="button" :disabled="actionLoadingId === row.id" class="rounded-md border border-gray-300 px-2.5 py-1.5 text-xs font-medium text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:text-gray-200" @click="openEntryModal(row, 'note')">Add Note</button>
                                    <select :value="cloneSelection[row.id] || ''" class="max-w-[125px] rounded-md border border-gray-300 bg-white px-2 py-1.5 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" aria-label="Clone options" @change="selectCloneOption(row, $event)">
                                        <option value="">Select Clone</option>
                                        <option value="clone-one">Clone One</option>
                                        <option value="clone-two">Clone Two</option>
                                        <option value="clone-three">Clone Three</option>
                                        <option value="clone-four">Clone Four</option>
                                    </select>
                                </div>
                                <div v-if="row.labels?.length" class="mt-2 flex flex-wrap gap-1">
                                    <span v-for="label in row.labels" :key="label" class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">{{ label }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-3 text-[10px] text-gray-500 dark:text-gray-400">
                                    <span v-if="row.notes?.length">{{ row.notes.length }} note{{ row.notes.length === 1 ? '' : 's' }}</span>
                                    <span v-if="row.is_blocked" class="font-semibold text-amber-700 dark:text-amber-300">Blocked</span>
                                    <span>{{ row.created_at ? new Date(row.created_at).toLocaleString() : '—' }}</span>
                                </div>
                            </td>
                        </tr>
                        </template>
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

        <div v-if="actionFeedback || actionError" class="fixed right-4 top-4 z-[120] max-w-sm rounded-lg border px-4 py-3 text-sm shadow-lg" :class="actionError ? 'border-red-300 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200' : 'border-green-300 bg-green-50 text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200'">
            <div class="flex items-start gap-3">
                <span class="flex-1">{{ actionError || actionFeedback }}</span>
                <button type="button" aria-label="Dismiss" @click="actionError = ''; actionFeedback = ''"><X class="h-4 w-4" /></button>
            </div>
        </div>

        <div v-if="editModalOpen" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-3" @click.self="editModalOpen = false">
            <section class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="edit-application-title">
                <header class="mb-4 flex items-center justify-between">
                    <h2 id="edit-application-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Edit application</h2>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="editModalOpen = false"><X class="h-5 w-5" /></button>
                </header>
                <form class="space-y-3" @submit.prevent="saveApplicationEdit">
                    <label class="block text-sm text-gray-700 dark:text-gray-200">Applicant name
                        <input v-model="editForm.name" required maxlength="255" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                        <span v-if="editErrors.name" class="text-xs text-red-600">{{ editErrors.name }}</span>
                    </label>
                    <label class="block text-sm text-gray-700 dark:text-gray-200">Phone
                        <input v-model="editForm.phone" required maxlength="40" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                        <span v-if="editErrors.phone" class="text-xs text-red-600">{{ editErrors.phone }}</span>
                    </label>
                    <label class="block text-sm text-gray-700 dark:text-gray-200">Address
                        <textarea v-model="editForm.address" rows="3" maxlength="2000" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                        <span v-if="editErrors.address" class="text-xs text-red-600">{{ editErrors.address }}</span>
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="editModalOpen = false">Cancel</button>
                        <button type="submit" :disabled="actionLoadingId === editTargetId" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ actionLoadingId === editTargetId ? 'Saving…' : 'Save changes' }}</button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="entryModalOpen" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-3" @click.self="entryModalOpen = false">
            <section class="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true">
                <header class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ entryModalKind === 'label' ? 'Add label' : 'Add note' }}</h2>
                    <button type="button" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800" @click="entryModalOpen = false"><X class="h-5 w-5" /></button>
                </header>
                <form class="space-y-4" @submit.prevent="saveEntry">
                    <input v-if="entryModalKind === 'label'" v-model="entryValue" required maxlength="60" placeholder="Label name" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" />
                    <textarea v-else v-model="entryValue" required maxlength="5000" rows="5" placeholder="Write a note…" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="entryModalOpen = false">Cancel</button>
                        <button type="submit" :disabled="!entryValue.trim() || actionLoadingId === entryTargetId" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{{ actionLoadingId === entryTargetId ? 'Saving…' : 'Save' }}</button>
                    </div>
                </form>
            </section>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 z-[110] flex items-center justify-center bg-black/60 p-3" @click.self="deleteTarget = null">
            <section class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-5 shadow-2xl dark:border-gray-700 dark:bg-gray-900" role="dialog" aria-modal="true" aria-labelledby="delete-application-title">
                <h2 id="delete-application-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete application?</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">This permanently deletes application #{{ deleteTarget.id }} for {{ deleteTarget.name }}. This action cannot be undone.</p>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200" @click="deleteTarget = null">Cancel</button>
                    <button type="button" :disabled="actionLoadingId === deleteTarget.id" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" @click="confirmDeleteApplication">{{ actionLoadingId === deleteTarget.id ? 'Deleting…' : 'Delete permanently' }}</button>
                </div>
            </section>
        </div>

        <div
            v-if="detailsOpen"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 p-3 sm:p-6"
            role="presentation"
            @click.self="closeApplicationDetails"
            @keydown.esc="closeApplicationDetails"
        >
            <section
                class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
                role="dialog"
                aria-modal="true"
                aria-labelledby="application-details-title"
            >
                <header class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 dark:border-gray-700">
                    <div class="min-w-0">
                        <h2 id="application-details-title" class="text-lg font-semibold text-gray-900 dark:text-gray-100">Application details</h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            <template v-if="detailsApplication">{{ detailsApplication.form_name }} · #{{ detailsApplication.id }}</template>
                            <template v-else>Loading selected application</template>
                        </p>
                    </div>
                    <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white" aria-label="Close application details" @click="closeApplicationDetails">
                        <X class="h-5 w-5" />
                    </button>
                </header>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div v-if="detailsLoading" class="flex items-center justify-center gap-3 py-12 text-sm text-gray-600 dark:text-gray-300">
                        <span class="h-5 w-5 animate-spin rounded-full border-2 border-gray-300 border-t-blue-600 dark:border-gray-600 dark:border-t-blue-400"></span>
                        Loading application details…
                    </div>
                    <div v-else-if="detailsError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300">
                        <p>{{ detailsError }}</p>
                        <button v-if="detailsTargetId !== null" type="button" class="mt-3 rounded-lg border border-red-300 px-3 py-1.5 font-medium hover:bg-red-100 dark:border-red-800 dark:hover:bg-red-900/40" @click="() => { const row = rows.find((item) => item.id === detailsTargetId); if (row) openApplicationDetails(row); }">Retry</button>
                    </div>
                    <div v-else-if="detailsApplication" class="space-y-6">
                        <section>
                            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Applicant information</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Applicant name</div>
                                    <div class="mt-1 flex items-start justify-between gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <span class="break-words">{{ detailsApplication.name || '—' }}</span>
                                        <button type="button" class="shrink-0 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-300" title="Copy applicant name" @click="copyDetail('name', detailsApplication.name || '')">
                                            <Check v-if="copiedDetailKey === 'name'" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Applicant phone</div>
                                    <div class="mt-1 flex items-start justify-between gap-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                        <span class="break-words">{{ detailsApplication.phone || '—' }}</span>
                                        <button type="button" class="shrink-0 text-gray-500 hover:text-blue-600 dark:text-gray-400 dark:hover:text-blue-300" title="Copy phone" @click="copyDetail('phone', detailsApplication.phone || '')">
                                            <Check v-if="copiedDetailKey === 'phone'" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Form data</h3>
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-md border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800" @click="copyDetail('form-data', JSON.stringify(detailsApplication.form_data, null, 2))">
                                    <Check v-if="copiedDetailKey === 'form-data'" class="h-3.5 w-3.5 text-green-600" /><Copy v-else class="h-3.5 w-3.5" />
                                    Copy all
                                </button>
                            </div>
                            <p v-if="detailFields.length === 0" class="rounded-lg bg-gray-50 p-4 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400">No form data was stored for this application.</p>
                            <div v-else class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                <div v-for="field in detailFields" :key="field.path" class="flex flex-col gap-2 p-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">{{ field.label }}</div>
                                        <pre class="mt-1 whitespace-pre-wrap break-words font-sans text-sm text-gray-900 dark:text-gray-100">{{ field.value }}</pre>
                                        <div class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ detailSourceLabel(field.source) }}</div>
                                    </div>
                                    <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center self-end rounded-md text-gray-500 hover:bg-gray-100 hover:text-blue-600 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-blue-300 sm:self-start" :title="`Copy ${field.label}`" :aria-label="`Copy ${field.label}`" @click="copyDetail(`field-${field.path}`, field.value)">
                                        <Check v-if="copiedDetailKey === `field-${field.path}`" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" />
                                    </button>
                                </div>
                            </div>
                        </section>

                        <section>
                            <h3 class="mb-3 text-sm font-semibold text-gray-900 dark:text-gray-100">Payment URL</h3>
                            <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                                <template v-if="safePaymentUrl">
                                    <a :href="safePaymentUrl" target="_blank" rel="noopener noreferrer" class="break-all text-sm text-blue-700 underline underline-offset-2 hover:text-blue-900 dark:text-blue-300 dark:hover:text-blue-200">{{ detailsApplication.payment_url }}</a>
                                    <button type="button" class="ml-2 inline-flex h-7 w-7 items-center justify-center rounded-md text-gray-500 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700" title="Copy payment URL" @click="copyDetail('payment-url', detailsApplication.payment_url || '')">
                                        <Check v-if="copiedDetailKey === 'payment-url'" class="h-4 w-4 text-green-600" /><Copy v-else class="h-4 w-4" />
                                    </button>
                                </template>
                                <span v-else class="text-sm text-gray-500 dark:text-gray-400">No valid payment URL is available.</span>
                            </div>
                        </section>
                    </div>
                </div>

                <footer class="flex justify-end border-t border-gray-200 px-5 py-3 dark:border-gray-700">
                    <button type="button" class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white" @click="closeApplicationDetails">Close</button>
                </footer>
            </section>
        </div>
    </AppLayout>
</template>
