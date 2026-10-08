// @vitest-environment jsdom
import { beforeEach, afterEach, it, expect, vi } from "vitest";
import { api, session } from "../../resources/js/api";
beforeEach(() => {
    document.head.innerHTML = '<meta name="csrf-token" content="old-token">';
    session.user = { id: 1 };
});
afterEach(() => vi.unstubAllGlobals());
it("uses same-origin sessions and adopts the regenerated CSRF token after login", async () => {
    const fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({ user: { id: 1 }, csrf: "renewed-token" }),
    });
    vi.stubGlobal("fetch", fetch);
    await api("login", "POST", {
        email: "test@example.com",
        password: "secret",
    });
    expect(fetch.mock.calls[0][1]).toMatchObject({
        credentials: "same-origin",
        headers: { "X-CSRF-TOKEN": "old-token" },
    });
    expect(document.querySelector("meta").content).toBe("renewed-token");
    await api("tickets", "POST", { subject: "Support" });
    expect(fetch.mock.calls[1][1].headers["X-CSRF-TOKEN"]).toBe(
        "renewed-token",
    );
});
it("clears stale authentication and gives a specific recovery message for expired sessions", async () => {
    vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
            ok: false,
            status: 401,
            json: async () => ({ message: "Unauthenticated." }),
        }),
    );
    await expect(api("workspace")).rejects.toMatchObject({ status: 401 });
    expect(session.user).toBe(null);
    vi.stubGlobal(
        "fetch",
        vi.fn().mockResolvedValue({
            ok: false,
            status: 419,
            json: async () => ({ message: "CSRF token mismatch." }),
        }),
    );
    await expect(api("tickets", "POST", {})).rejects.toMatchObject({
        message: "Your session expired. Refresh this page and try again.",
    });
});
