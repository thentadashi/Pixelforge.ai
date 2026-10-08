export const seed = () => ({
    products: [
        { sku: "PR-001", name: "Office notebook", stock: 85, price: 75 },
        { sku: "PR-002", name: "Wireless keyboard", stock: 32, price: 950 },
        { sku: "PR-003", name: "USB-C adapter", stock: 46, price: 420 },
    ],
    sales: 0,
    requests: [
        {
            id: 104,
            name: "Alex Reyes",
            kind: "Barangay clearance",
            status: "Pending",
        },
        {
            id: 105,
            name: "Jamie Cruz",
            kind: "Certificate of residency",
            status: "Under review",
        },
    ],
    students: [
        {
            id: 201,
            name: "Morgan Santos",
            kind: "Information Technology",
            status: "Pending",
        },
    ],
    approvals: [
        {
            id: 301,
            subject: "Equipment purchase",
            department: "Operations",
            amount: 18500,
            status: "Pending",
        },
    ],
});
export function sell(state, index) {
    const item = state.products[index];
    if (!item || item.stock < 1) return false;
    item.stock--;
    state.sales += item.price;
    return true;
}
export function loadDemo(storage) {
    try {
        const saved = JSON.parse(storage.getItem("pixelforge.demo.v1"));
        return saved &&
            Array.isArray(saved.products) &&
            Array.isArray(saved.requests) &&
            Array.isArray(saved.students) &&
            Array.isArray(saved.approvals) &&
            typeof saved.sales === "number"
            ? saved
            : seed();
    } catch {
        return seed();
    }
}
