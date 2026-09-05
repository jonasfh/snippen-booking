const { test, expect } = require('@playwright/test');
const path = require('path');

test.describe('Booking Details Modal View', () => {
    test('renders booking details overlay matching golden snapshot', async ({ page }) => {
        const fixturePath = 'file://' + path.resolve(__dirname, 'fixtures/modal-fixture.html');
        await page.goto(fixturePath);

        const modalContent = page.locator('.snippen-booking-modal-content');
        await expect(modalContent).toBeVisible();

        // Focused snapshot of the modal dialog
        await expect(modalContent).toHaveScreenshot('booking-details-modal.png');
    });
});
