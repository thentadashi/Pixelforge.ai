// @vitest-environment jsdom
import { it, expect, vi, beforeEach } from "vitest";
import { mount, flushPromises } from "@vue/test-utils";
import { createMemoryHistory, createRouter } from "vue-router";
vi.mock("../../resources/js/api", () => ({
    api: vi.fn(),
    session: {
        content: {
            booking: {
                title: "Discovery call",
                description: "Tell us about your work.",
            },
        },
    },
    today: () => "2026-10-08",
}));
import { api } from "../../resources/js/api";
import BookingPage from "../../resources/js/pages/BookingPage.vue";
beforeEach(() => {
    vi.mocked(api).mockReset();
});
async function mountPage() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: "/", component: { template: "<p>Home</p>" } },
            { path: "/book", component: BookingPage },
        ],
    });
    await router.push("/book");
    await router.isReady();
    return mount(BookingPage, { global: { plugins: [router] } });
}
it("does not show stale slots when the visitor changes dates during an availability request", async () => {
    let resolveOld;
    vi.mocked(api).mockImplementation((path) => {
        return path.includes("2026-10-09")
            ? new Promise((resolve) => {
                  resolveOld = resolve;
              })
            : Promise.resolve({ slots: ["13:00"] });
    });
    const wrapper = await mountPage();
    await wrapper.find("input[type=date]").setValue("2026-10-09");
    await wrapper.find("input[type=date]").setValue("2026-10-10");
    await flushPromises();
    expect(wrapper.findAll(".slots button")).toHaveLength(1);
    expect(wrapper.find(".slots button").text()).toContain("1:00");
    resolveOld({ slots: ["09:00", "10:30"] });
    await flushPromises();
    expect(wrapper.findAll(".slots button")).toHaveLength(1);
    expect(wrapper.find(".slots button").text()).toContain("1:00");
    wrapper.unmount();
});
it("shows confirmation only after the server persists the booking", async () => {
    vi.mocked(api).mockImplementation((path, method) =>
        method === "POST"
            ? Promise.resolve({
                  reference: "booking-reference",
                  message: "Request saved.",
              })
            : Promise.resolve({ slots: ["09:00"] }),
    );
    const wrapper = await mountPage();
    const inputs = wrapper.findAll("input");
    await inputs[0].setValue("Client");
    await inputs[1].setValue("client@example.com");
    await inputs[2].setValue("Organization");
    await wrapper.find("textarea").setValue("Inventory workflow");
    await wrapper.find("input[type=date]").setValue("2026-10-09");
    await flushPromises();
    await wrapper.find(".slots button").trigger("click");
    await wrapper.find("form").trigger("submit");
    await flushPromises();
    expect(api).toHaveBeenLastCalledWith(
        "bookings",
        "POST",
        expect.objectContaining({
            date: "2026-10-09",
            slot: "09:00",
            name: "Client",
        }),
    );
    expect(wrapper.text()).toContain("booking-reference");
    expect(wrapper.find("form").exists()).toBe(false);
    wrapper.unmount();
});
