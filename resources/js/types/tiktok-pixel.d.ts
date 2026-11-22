
export {};

declare global {
    interface Window {
        ttq?: {
            page: () => void;
            track: (
                event: string,
                data?: Record<string, unknown>
            ) => void;
        };
    }
}
