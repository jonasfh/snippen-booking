const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Admin Bookings Overview & Details View', () => {
    test('renders admin bookings overview with closed communication matching snapshot', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/admin-bookings-fixture.html');
        await page.goto(fixturePath);

        const card = page.locator('.snippen-card');
        await expect(card).toBeVisible();

        // Focused snapshot of the booking table with details
        await expect(card).toHaveScreenshot('admin-bookings-table.png');
    });

    test('toggles communication history open and verifies no horizontal overflow on viewport', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/admin-bookings-fixture.html');
        await page.goto(fixturePath);

        const toggleBtn = page.locator('.toggle-msg-history');
        const historyBody = page.locator('.msg-history-body');

        // Initially closed
        await expect(historyBody).not.toBeVisible();
        await expect(toggleBtn).toContainText('Vis kommunikasjon');

        // Open communication history
        await toggleBtn.click();
        await expect(historyBody).toBeVisible();
        await expect(toggleBtn).toContainText('Skjul kommunikasjon');

        // Verify that on mobile/desktop there is no horizontal page overflow
        const hasHorizontalOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(hasHorizontalOverflow).toBe(false);

        // Snapshot with communication expanded
        const card = page.locator('.snippen-card');
        await expect(card).toHaveScreenshot('admin-bookings-table-expanded-comm.png');
    });
});
