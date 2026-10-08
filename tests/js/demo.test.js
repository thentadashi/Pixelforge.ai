import { describe, it, expect } from "vitest";
import { seed, sell, loadDemo } from "../../resources/js/demo-store";
import { newRecord, schemas } from "../../resources/js/resource-schema";
import { progress } from "../../resources/js/api";
describe("fictional demos", () => {
    it("updates stock and sales atomically and refuses negative stock", () => {
        const s = seed();
        s.products[0].stock = 1;
        expect(sell(s, 0)).toBe(true);
        expect(s.products[0].stock).toBe(0);
        expect(s.sales).toBe(75);
        expect(sell(s, 0)).toBe(false);
        expect(s.sales).toBe(75);
        expect(sell(s, 99)).toBe(false);
    });
    it("uses fresh records if browser storage is broken or corrupt", () => {
        expect(loadDemo({ getItem: () => "{invalid" })).toEqual(seed());
        expect(loadDemo({ getItem: () => '{"products":null}' })).toEqual(
            seed(),
        );
        expect(
            loadDemo({
                getItem: () => {
                    throw new Error("denied");
                },
            }),
        ).toEqual(seed());
    });
    it("does not share state between visitors or resets", () => {
        const a = seed(),
            b = seed();
        sell(a, 0);
        expect(b.products[0].stock).toBe(85);
    });
});
describe("workspace forms", () => {
    it("creates drafts with a selected project and explicit statuses", () => {
        expect(newRecord("milestones", 12)).toMatchObject({
            project_id: 12,
            status: "planned",
            position: 0,
        });
        expect(newRecord("quotations").status).toBe("draft");
        expect(schemas.clients.map((f) => f.key)).not.toContain("role");
    });
    it("counts only approved deliverables toward progress", () => {
        expect(progress([])).toBe(0);
        expect(
            progress([{ status: "approved" }, { status: "ready_for_review" }]),
        ).toBe(50);
    });
});
