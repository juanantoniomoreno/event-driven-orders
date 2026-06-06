import { defineConfig } from "@playwright/test";

export default defineConfig({
	testDir: "./tests/e2e",
	timeout: 30000,
	retries: 1,
	use: {
		baseURL: "http://localhost:3000",
		headless: true,
		screenshot: "only-on-failure",
	},
	webServer: {
		command:
			"docker compose -f ../docker-compose.yml ps --status running | grep -q edo_frontend || exit 1",
		cwd: ".",
		reuseExistingServer: true,
		timeout: 1000,
	},
});
