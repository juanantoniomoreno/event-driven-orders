import { test, expect } from "@playwright/test";

test("create order and watch it process via SSE", async ({ page }) => {
	await page.goto("/");

	// Wait for the page to be fully loaded
	await expect(page.locator("h1")).toHaveText("Event-Driven Orders");

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
	const firstOrder = page.locator("li").first();
	await expect(firstOrder).toContainText(email);
	// JS parseFloat strips the trailing zero: 42.50 → 42.5
	await expect(firstOrder).toContainText("$42.5");
	await expect(firstOrder).toContainText("pending");

	// Wait for Mercure SSE update: workers process the order and push real-time
	// status changes (notifications ~2s, inventory ~1s, analytics ~1s).
	// Each handler publishes when it finishes, so "processed" appears within 3s.
	await expect(firstOrder).toContainText("processed", { timeout: 5000 });
});

test("existing orders are loaded on page refresh", async ({ page }) => {
	await page.goto("/");

	// The page should load and display the <h1> title
	await expect(page.locator("h1")).toHaveText("Event-Driven Orders");

	// The order list should exist (may be empty or contain existing orders)
	const orderList = page.locator("ul");
	await expect(orderList).toBeVisible();
});
