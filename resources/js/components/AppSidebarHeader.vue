<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
        realtimeStatus?: 'connected' | 'disconnected' | 'reconnecting';
        realtimeStatusLabel?: { badge: string; text: string };
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="flex h-[35px] shrink-0 items-center justify-between gap-1 border-b border-sidebar-border/70 px-2 transition-[width,height] ease-linear sm:h-16 sm:gap-2 sm:px-6 md:px-4 sm:group-has-data-[collapsible=icon]/sidebar-wrapper:h-12"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div
            v-if="realtimeStatus && realtimeStatusLabel"
            class="flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border px-2 py-1 text-[10px] font-semibold sm:gap-2 sm:px-3 sm:py-1.5 sm:text-xs"
            :class="realtimeStatus === 'connected' ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300' : realtimeStatus === 'reconnecting' ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/50 dark:text-amber-300' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300'"
            role="status"
            aria-live="polite"
        >
            <span class="h-2 w-2 rounded-full" :class="realtimeStatus === 'connected' ? 'bg-emerald-500' : realtimeStatus === 'reconnecting' ? 'animate-pulse bg-amber-500' : 'bg-red-500'"></span>
            <span>{{ realtimeStatusLabel.badge }}</span>
            <span class="hidden font-normal sm:inline">{{ realtimeStatusLabel.text }}</span>
        </div>
    </header>
</template>
