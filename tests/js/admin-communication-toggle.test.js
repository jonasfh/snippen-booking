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

    describe('Vis all kommunikasjon filter toggle', () => {
        function setupFilterToggleHandler() {
            const table = document.querySelector('.bookings-table');
            table.addEventListener('change', function(e) {
                const cb = e.target.closest('.snippen-toggle-all-messages');
                if (!cb) return;

                const container = cb.closest('.booking-messages-history');
                const countSpan = container.querySelector('.msg-count');
                const indicator = container.querySelector('.msg-filtered-indicator');
                const visibleCount = parseInt(countSpan.getAttribute('data-visible-count'), 10) || 0;
                const totalCount = parseInt(countSpan.getAttribute('data-total-count'), 10) || 0;
                const filteredCount = totalCount - visibleCount;

                if (cb.checked) {
                    container.classList.add('show-all-messages');
                    countSpan.textContent = totalCount;
                    if (filteredCount > 0 && indicator) {
                        indicator.textContent = '(viser alle)';
                        indicator.style.display = '';
                    }
                } else {
                    container.classList.remove('show-all-messages');
                    countSpan.textContent = visibleCount;
                    if (filteredCount > 0 && indicator) {
                        const hiddenText = filteredCount === 1 ? '1 skjult' : filteredCount + ' skjulte';
                        indicator.textContent = '(' + hiddenText + ')';
                        indicator.style.display = '';
                    }
                }
            });
        }

        beforeEach(() => {
            document.body.innerHTML = `
                <table class="bookings-table">
                    <tr class="snippen-details-row" id="details-1">
                        <td>
                            <div class="booking-messages-history has-visible-messages" data-booking-id="1">
                                <div class="msg-history-header">
                                    <div class="msg-history-header-title">
                                        <strong>Kommunikasjonshistorikk (<span class="msg-count" data-visible-count="1" data-total-count="2">1</span>)</strong>
                                    </div>
                                    <button type="button" class="button button-small toggle-msg-history" aria-expanded="false">
                                        <span class="toggle-text">Vis kommunikasjon</span>
                                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                                    </button>
                                </div>
                                <div class="msg-history-body" style="display:none;">
                                    <div class="msg-history-toolbar">
                                        <label class="msg-history-filter-toggle">
                                            <input type="checkbox" class="snippen-toggle-all-messages">
                                            <span>Vis all kommunikasjon</span>
                                        </label>
                                        <span class="msg-filtered-indicator">(1 skjult)</span>
                                    </div>
                                    <p class="no-visible-messages-text" style="margin:0; font-size:12px; color:#64748b;">Ingen meldinger å vise med gjeldende filter.</p>
                                    <div class="msg-list-container">
                                        <div class="msg-item" data-event-type="booking_confirmation">
                                            <div class="msg-item-body">Kunde bekreftelse</div>
                                        </div>
                                        <div class="msg-item msg-item-filtered" data-event-type="admin_booking">
                                            <div class="msg-item-body">Admin bookingvarsel</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            `;

            setupToggleHandler();
            setupFilterToggleHandler();
        });

        it('shows total count and adds show-all-messages class when checkbox is checked', () => {
            const container = document.querySelector('.booking-messages-history');
            const checkbox = document.querySelector('.snippen-toggle-all-messages');
            const countSpan = document.querySelector('.msg-count');
            const indicator = document.querySelector('.msg-filtered-indicator');

            expect(container.classList.contains('show-all-messages')).toBe(false);
            expect(countSpan.textContent).toBe('1');
            expect(indicator.textContent).toBe('(1 skjult)');

            checkbox.checked = true;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(container.classList.contains('show-all-messages')).toBe(true);
            expect(countSpan.textContent).toBe('2');
            expect(indicator.textContent).toBe('(viser alle)');

            checkbox.checked = false;
            checkbox.dispatchEvent(new Event('change', { bubbles: true }));

            expect(container.classList.contains('show-all-messages')).toBe(false);
            expect(countSpan.textContent).toBe('1');
            expect(indicator.textContent).toBe('(1 skjult)');
        });

        it('does not toggle accordion when clicking checkbox', () => {
            const body = document.querySelector('.msg-history-body');
            const checkbox = document.querySelector('.snippen-toggle-all-messages');

            expect(body.style.display).toBe('none');

            checkbox.click();

            // Clicking input should not trigger toggle-msg-history / accordion
            expect(body.style.display).toBe('none');
        });
    });
});
