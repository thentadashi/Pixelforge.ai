const field = (key, title, type = "text", extra = {}) => ({
    key,
    title,
    type,
    ...extra,
});
const client = () =>
    field("client_id", "Client", "select", { source: "clients" });
const project = () =>
    field("project_id", "Project", "select", { source: "projects" });
const status = (options) => field("status", "Status", "select", { options });
const date = (key) =>
    field(
        key,
        key === "valid_until"
            ? "Valid until"
            : key === "due_date"
              ? "Due date"
              : "Target date",
        "date",
        { optional: key === "target_date" },
    );
export const schemas = {
    clients: [
        field("name", "Client name"),
        field("email", "Email", "email"),
        field("organization", "Organization"),
    ],
    projects: [
        client(),
        field("name", "Project name"),
        field("description", "Description", "textarea"),
        status(["planning", "in_progress", "review", "completed", "archived"]),
        date("target_date"),
    ],
    quotations: [
        client(),
        field("title", "Quotation title"),
        field("scope", "Scope & terms", "textarea"),
        field("amount", "Amount (PHP)", "number", { step: ".01" }),
        date("valid_until"),
        status(["draft", "sent"]),
    ],
    milestones: [
        project(),
        field("title", "Deliverable / milestone"),
        field("description", "Description", "textarea", { optional: true }),
        date("target_date"),
        status(["planned", "in_progress", "ready_for_review"]),
        field("position", "Display order", "number", { step: 1 }),
    ],
    updates: [project(), field("body", "Client update", "textarea")],
    invoices: [
        project(),
        field("reference", "Invoice reference"),
        field("description", "Description"),
        field("amount", "Amount (PHP)", "number", { step: ".01" }),
        date("due_date"),
        status(["draft", "sent", "paid", "cancelled"]),
    ],
};
export function newRecord(type, projectId = null) {
    return Object.fromEntries(
        schemas[type].map((f) => [
            f.key,
            f.key === "project_id"
                ? projectId || ""
                : f.type === "number"
                  ? 0
                  : f.options
                    ? f.options[0]
                    : "",
        ]),
    );
}
