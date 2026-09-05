const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Booking Calendar View', () => {
    test('renders calendar week grid matching golden snapshot', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/calendar-fixture.html');
        await page.goto(fixturePath);

        const calendar = page.locator('#calendar-container');
        await expect(calendar).toBeVisible();

        // Focused snapshot of the calendar component
        await expect(calendar).toHaveScreenshot('calendar-week-grid.png');
    });
});
