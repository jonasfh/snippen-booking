const SnippenTableFilter = require('../../src/wp-content/plugins/booking-plugin/js/admin-table-filter');

describe('SnippenTableFilter', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div id="table-wrapper">
                <table id="test-table" class="snippen-filterable-table">
                    <thead>
                        <tr>
                            <th data-filter-type="text" data-sort-type="string">Navn</th>
                            <th data-filter-type="multiselect" data-sort-type="string">Lokaler</th>
                            <th data-filter-type="minmax" data-sort-type="number">Pris</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Festsalen Helg</td><td>Festsalen</td><td>1 500,-</td></tr>
                        <tr><td>Møterom Ukedag</td><td>Møterom A, Møterom B</td><td>500,-</td></tr>
                        <tr><td>Hovedsal Helligdag</td><td>Festsalen</td><td>2000,-</td></tr>
                    </tbody>
                </table>
            </div>
        `;

        window.snippenAdmin = {
            strings: {
                resetFilters: 'Rens alle filtre',
                showing: 'Viser',
                of: 'av',
                rows: 'rader',
                min: 'Min',
                max: 'Maks',
                all: 'Alle'
            }
        };

        sessionStorage.clear();
    });

    it('injects filter row correctly', () => {
        new SnippenTableFilter('.snippen-filterable-table');
        
        const filterRow = document.querySelector('.snippen-filter-row');
        expect(filterRow).not.toBeNull();
        
        const textInputs = filterRow.querySelectorAll('input[type="text"]');
        expect(textInputs.length).toBe(1); // 1 text filter

        const minmaxInputs = filterRow.querySelectorAll('input[type="number"]');
        expect(minmaxInputs.length).toBe(2); // min and max

        const multiselects = filterRow.querySelectorAll('.snippen-multiselect-dropdown');
        expect(multiselects.length).toBe(1); // 1 multiselect
    });

    it('filters by text including wildcard', () => {
        new SnippenTableFilter('.snippen-filterable-table');
        const textInput = document.querySelector('.snippen-filter-text input');
        textInput.value = 'fest*helg';
        textInput.dispatchEvent(new Event('input', { bubbles: true }));

        const rows = document.querySelectorAll('tbody tr');
        expect(rows[0].style.display).toBe(''); // Festsalen Helg
        expect(rows[1].style.display).toBe('none'); // Møterom
        expect(rows[2].style.display).toBe('none'); // Hovedsal
    });

    it('filters by minmax', () => {
        new SnippenTableFilter('.snippen-filterable-table');
        const minInput = document.querySelector('.snippen-min-input');
        
        minInput.value = '1000';
        minInput.dispatchEvent(new Event('input', { bubbles: true }));

        const rows = document.querySelectorAll('tbody tr');
        expect(rows[0].style.display).toBe(''); // 1500
        expect(rows[1].style.display).toBe('none'); // 500
        expect(rows[2].style.display).toBe(''); // 2000
    });

    it('sorts numbers correctly', () => {
        new SnippenTableFilter('.snippen-filterable-table');
        const priceHeader = document.querySelector('th[data-sort-type="number"]');
        
        // click to sort asc
        priceHeader.dispatchEvent(new Event('click'));
        let rows = document.querySelectorAll('tbody tr');
        expect(rows[0].cells[0].textContent).toBe('Møterom Ukedag'); // 500
        expect(rows[1].cells[0].textContent).toBe('Festsalen Helg'); // 1500
        expect(rows[2].cells[0].textContent).toBe('Hovedsal Helligdag'); // 2000
        
        // click to sort desc
        priceHeader.dispatchEvent(new Event('click'));
        rows = document.querySelectorAll('tbody tr');
        expect(rows[0].cells[0].textContent).toBe('Hovedsal Helligdag'); // 2000
    });

    it('resets filters correctly', () => {
        const filter = new SnippenTableFilter('.snippen-filterable-table');
        const textInput = document.querySelector('.snippen-filter-text input');
        
        textInput.value = 'none';
        textInput.dispatchEvent(new Event('input', { bubbles: true }));

        let rows = document.querySelectorAll('tbody tr');
        expect(rows[0].style.display).toBe('none');

        const resetBtn = document.querySelector('.snippen-table-controls .snippen-btn');
        resetBtn.dispatchEvent(new Event('click'));

        rows = document.querySelectorAll('tbody tr');
        expect(rows[0].style.display).toBe('');
        expect(textInput.value).toBe('');
    });
});
