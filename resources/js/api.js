import { reactive } from "vue";
export const session = reactive({ user: null, content: null, loaded: false });
export class ApiError extends Error {
    constructor(message, status, errors = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}
export async function api(path, method = "GET", data = null) {
    const form = data instanceof FormData;
    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.content || "";
    const response = await fetch("/api/" + path, {
        method,
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(method === "GET" ? {} : { "X-CSRF-TOKEN": csrf }),
            ...(data && !form ? { "Content-Type": "application/json" } : {}),
        },
        body: data ? (form ? data : JSON.stringify(data)) : undefined,
    });
    const result = await response.json().catch(() => ({
        message: "The server returned an unexpected response.",
    }));
    if (result.csrf)
        document
            .querySelector('meta[name="csrf-token"]')
            ?.setAttribute("content", result.csrf);
    if (!response.ok) {
        if (response.status === 401) session.user = null;
        throw new ApiError(
            response.status === 419
                ? "Your session expired. Refresh this page and try again."
                : result.message || "Unable to complete the request.",
            response.status,
            result.errors,
        );
    }
    return result;
}
export const money = (value) =>
    new Intl.NumberFormat("en-PH", {
        style: "currency",
        currency: "PHP",
    }).format(Number(value) || 0);
export const label = (value) =>
    String(value || "")
        .replaceAll("_", " ")
        .replace(/\b\w/g, (c) => c.toUpperCase());
export const today = () =>
    new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Manila",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(new Date());
export const progress = (milestones) =>
    milestones.length
        ? Math.round(
              (100 * milestones.filter((m) => m.status === "approved").length) /
                  milestones.length,
          )
        : 0;
