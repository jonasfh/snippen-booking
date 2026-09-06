const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Admin Rules Table View (Pricing & Discounts)', () => {
    test('renders header and rules table matching snapshot without horizontal overflow', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/admin-rules-fixture.html');
        await page.goto(fixturePath);

        const wrap = page.locator('.snippen-booking-admin-wrap');
        await expect(wrap).toBeVisible();

        // Focused snapshot of the rules view
        await expect(wrap).toHaveScreenshot('admin-rules-view.png');

        // Verify that the page container has no horizontal overflow beyond the viewport
        const hasPageHorizontalOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(hasPageHorizontalOverflow).toBe(false);

        // Verify that the responsive scroll container exists and table is scrollable
        const responsiveContainer = page.locator('.snippen-table-responsive');
        await expect(responsiveContainer).toBeVisible();

        // Verify that the table header title line-height is sufficient (no line overlap)
        const h1LineHeight = await page.evaluate(() => {
            const h1 = document.querySelector('.snippen-admin-header h1');
            const style = window.getComputedStyle(h1);
            return parseFloat(style.lineHeight) / parseFloat(style.fontSize);
        });
        expect(h1LineHeight).toBeGreaterThanOrEqual(1.2);
    });

    test('can scroll horizontally inside the table container to reach actions', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/admin-rules-fixture.html');
        await page.goto(fixturePath);

        const scrollContainer = page.locator('.snippen-table-responsive');
        await expect(scrollContainer).toBeVisible();

        // Scroll to the rightmost edge
        await scrollContainer.evaluate((el) => {
            el.scrollLeft = el.scrollWidth;
        });

        const actionButtons = page.locator('.snippen-btn-outline');
        await expect(actionButtons.first()).toBeVisible();
    });
});
