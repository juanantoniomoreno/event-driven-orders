import { test, expect } from "@playwright/test";

test("create order and watch it process via SSE", async ({ page, request }) => {
	await page.goto("/");

	// Wait for the page to be fully loaded
	await expect(page.locator("h1")).toHaveText("EVENT-DRIVEN-ORDERS");

	// Fill in the order form
	const email = `e2e-${Date.now()}@test.com`;
	await page.fill('input[placeholder="Email"]', email);
	await page.fill(
		'input[placeholder="Items (comma separated)"]',
		"Widget,Gadget",
	);
	await page.fill('input[placeholder="Total"]', "42.50");
	await page.click('button[type="submit"]');

	// The new order should appear immediately with status "pending"
	// Locate by unique email so stale orders from retries don't break assertions
	const orderRow = page.locator("li", { hasText: email });
	await expect(orderRow).toContainText(email);
	// Money format: $X.XX (cents divided by 100, formatted to 2 decimal places)
	await expect(orderRow).toContainText("$42.50");
	await expect(orderRow).toContainText("pending");

	// Find the order ID via the backend API so we can poll for completion
	const listRes = await request.get("/api/orders");
	const orders = await listRes.json();
	const ourOrder = orders.find(
		(o: { customerEmail: string }) => o.customerEmail === email,
	);

	// Poll the backend API until the order is processed (up to 30s, every 500ms)
	const maxWaitMs = 30_000;
	const pollIntervalMs = 500;
	const startTime = Date.now();
	let orderStatus = "pending";
	while (Date.now() - startTime < maxWaitMs) {
		const res = await request.get(`/api/orders/${ourOrder.id}`);
		const order = await res.json();
		orderStatus = order.status;
		if (orderStatus === "processed") break;
		await page.waitForTimeout(pollIntervalMs);
	}
	expect(orderStatus).toBe("processed");

	// Verify the UI reflects the status update pushed via Mercure SSE
	await expect(orderRow).toContainText("processed");
});

test("existing orders are loaded on page refresh", async ({ page }) => {
	await page.goto("/");

	// The page should load and display the <h1> title
	await expect(page.locator("h1")).toHaveText("EVENT-DRIVEN-ORDERS");

	// The order list should exist (may be empty or contain existing orders)
	const orderList = page.locator("ul");
	await expect(orderList).toBeVisible();
});
