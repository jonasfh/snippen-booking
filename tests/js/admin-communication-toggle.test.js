describe('Admin Communication History Toggle', () => {
    function setupToggleHandler() {
        const table = document.querySelector('.bookings-table');
        table.addEventListener('click', function(e) {
            const toggleTrigger = e.target.closest('.toggle-msg-history, .msg-history-header');
            if (!toggleTrigger) {
                return;
            }
            if (e.target.closest('input, select, textarea')) {
                return;
            }
            e.preventDefault();

            const container = toggleTrigger.closest('.booking-messages-history');
            const body = container.querySelector('.msg-history-body');
            const btn = container.querySelector('.toggle-msg-history');
            const text = btn.querySelector('.toggle-text');
            const icon = btn.querySelector('.dashicons');

            const isCurrentlyVisible = body.style.display !== 'none';
            if (isCurrentlyVisible) {
                body.style.display = 'none';
                btn.setAttribute('aria-expanded', 'false');
                text.textContent = (window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.showCommunication) || 'Vis kommunikasjon';
                icon.classList.remove('dashicons-arrow-up-alt2');
                icon.classList.add('dashicons-arrow-down-alt2');
            } else {
                body.style.display = 'block';
                btn.setAttribute('aria-expanded', 'true');
                text.textContent = (window.snippenAdmin && window.snippenAdmin.strings && window.snippenAdmin.strings.hideCommunication) || 'Skjul kommunikasjon';
                icon.classList.remove('dashicons-arrow-down-alt2');
                icon.classList.add('dashicons-arrow-up-alt2');
            }
        });
    }

    beforeEach(() => {
        document.body.innerHTML = `
            <table class="bookings-table">
                <tr class="snippen-details-row" id="details-1">
                    <td>
                        <div class="booking-messages-history" data-booking-id="1">
                            <div class="msg-history-header">
                                <div class="msg-history-header-title">
                                    <strong>Kommunikasjonshistorikk (<span class="msg-count">1</span>)</strong>
                                </div>
                                <button type="button" class="button button-small toggle-msg-history" aria-expanded="false">
                                    <span class="toggle-text">Vis kommunikasjon</span>
                                    <span class="dashicons dashicons-arrow-down-alt2"></span>
                                </button>
                            </div>
                            <div class="msg-history-body" style="display:none;">
                                <div class="msg-list-container">
                                    <div class="msg-item" data-event-type="booking_confirmation">
                                        <div class="msg-item-body">Test melding</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        `;

        window.snippenAdmin = {
            strings: {
                showCommunication: 'Vis kommunikasjon',
                hideCommunication: 'Skjul kommunikasjon'
            }
        };

        setupToggleHandler();
    });

    it('initial state has communication history body hidden', () => {
        const body = document.querySelector('.msg-history-body');
        const text = document.querySelector('.toggle-text');
        const btn = document.querySelector('.toggle-msg-history');
        const icon = btn.querySelector('.dashicons');

        expect(body.style.display).toBe('none');
        expect(text.textContent).toBe('Vis kommunikasjon');
        expect(btn.getAttribute('aria-expanded')).toBe('false');
        expect(icon.classList.contains('dashicons-arrow-down-alt2')).toBe(true);
    });

    it('toggles communication history open when toggle button is clicked', () => {
        const btn = document.querySelector('.toggle-msg-history');
        btn.click();

        const body = document.querySelector('.msg-history-body');
        const text = document.querySelector('.toggle-text');
        const icon = btn.querySelector('.dashicons');

        expect(body.style.display).toBe('block');
        expect(text.textContent).toBe('Skjul kommunikasjon');
        expect(btn.getAttribute('aria-expanded')).toBe('true');
        expect(icon.classList.contains('dashicons-arrow-up-alt2')).toBe(true);
    });

    it('toggles communication history closed when clicked again', () => {
        const btn = document.querySelector('.toggle-msg-history');
        btn.click(); // Open
        expect(document.querySelector('.msg-history-body').style.display).toBe('block');

        btn.click(); // Close
        expect(document.querySelector('.msg-history-body').style.display).toBe('none');
        expect(document.querySelector('.toggle-text').textContent).toBe('Vis kommunikasjon');
        expect(btn.getAttribute('aria-expanded')).toBe('false');
        expect(btn.querySelector('.dashicons').classList.contains('dashicons-arrow-down-alt2')).toBe(true);
    });

    it('toggles when clicking the header area', () => {
        const header = document.querySelector('.msg-history-header');
        header.click();
        expect(document.querySelector('.msg-history-body').style.display).toBe('block');
    });
});
