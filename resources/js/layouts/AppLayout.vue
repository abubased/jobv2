<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    realtimeStatus?: 'connected' | 'disconnected' | 'reconnecting';
    realtimeStatusLabel?: { badge: string; text: string };
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    realtimeStatus: undefined,
    realtimeStatusLabel: undefined,
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :realtime-status="realtimeStatus" :realtime-status-label="realtimeStatusLabel">
        <slot />
    </AppLayout>
</template>
