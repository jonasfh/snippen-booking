/**
 * Snippen Table Filter Component
 * 
 * Reusable client-side filtering and sorting for admin tables.
 */
class SnippenTableFilter {
    constructor(tableSelector) {
        this.tables = document.querySelectorAll(tableSelector);
        if (this.tables.length === 0) return;

        // Ensure snippenAdmin.strings is available
        this.strings = (window.snippenAdmin && window.snippenAdmin.strings) ? window.snippenAdmin.strings : {
            resetFilters: 'Rens alle filtre',
            showing: 'Viser',
            of: 'av',
            rows: 'rader',
            min: 'Min',
            max: 'Maks',
            all: 'Alle'
        };

        this.init();
    }

    init() {
        this.tables.forEach((table, index) => {
            const tableId = table.id || `snippen-table-${index}`;
            this.setupTable(table, tableId);
        });
    }

    setupTable(table, tableId) {
        const thead = table.querySelector('thead');
        if (!thead) return;

        const headers = thead.querySelectorAll('th');
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const rows = Array.from(tbody.querySelectorAll('tr'));
        // If there is a "No rows" message, skip
        if (rows.length === 1 && rows[0].cells.length === 1 && rows[0].cells[0].colSpan > 1) {
            return;
        }

        // Initialize state
        const stateKey = `snippen_filter_state_${tableId}`;
        const savedState = sessionStorage.getItem(stateKey);
        let state = savedState ? JSON.parse(savedState) : { filters: {}, sort: { column: null, dir: 'asc' } };

        // Ensure table is wrapped in responsive scroll container
        let responsiveWrapper = table.closest('.snippen-table-responsive');
        if (!responsiveWrapper && table.parentNode) {
            responsiveWrapper = document.createElement('div');
            responsiveWrapper.className = 'snippen-table-responsive';
            table.parentNode.insertBefore(responsiveWrapper, table);
            responsiveWrapper.appendChild(table);
        }

        // Create filter row
        const filterRow = document.createElement('tr');
        filterRow.className = 'snippen-filter-row';

        const filterInputs = [];

        headers.forEach((th, i) => {
            const filterType = th.getAttribute('data-filter-type');
            const sortType = th.getAttribute('data-sort-type');
            
            const cell = document.createElement('th');
            
            if (filterType) {
                const inputContainer = this.createFilterInput(table, th, i, filterType, state.filters[i]);
                if (inputContainer) {
                    cell.appendChild(inputContainer);
                    filterInputs.push({ index: i, element: inputContainer, type: filterType });
                }
            }

            // Setup sorting
            if (sortType) {
                th.classList.add('sortable');
                const sortIcon = document.createElement('span');
                sortIcon.className = 'sort-icon dashicons dashicons-sort';
                th.appendChild(sortIcon);
                
                if (state.sort.column === i) {
                    th.classList.add(`sorted-${state.sort.dir}`);
                }

                th.addEventListener('click', () => {
                    this.handleSort(table, th, i, sortType, state, stateKey);
                });
            }

            filterRow.appendChild(cell);
        });

        // Add filter row below headers if there are filter inputs
        if (filterInputs.length > 0) {
            thead.appendChild(filterRow);
        }

        // Add reset and summary UI
        this.addControls(table, filterInputs, state, stateKey);

        // Bind filter events
        this.bindFilterEvents(table, filterInputs, state, stateKey);

        // Initial apply
        this.applyFiltersAndSort(table, state);
    }

    createFilterInput(table, th, index, type, savedFilter) {
        const container = document.createElement('div');
        container.className = `snippen-filter-input snippen-filter-${type}`;

        if (type === 'text') {
            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'Søk...';
            input.dataset.index = index;
            if (savedFilter && savedFilter.value) input.value = savedFilter.value;
            container.appendChild(input);
            return container;
        } 
        else if (type === 'multiselect') {
            const values = new Set();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                if (row.cells[index]) {
                    const text = row.cells[index].textContent.trim();
                    if (text && text !== '-') {
                        text.split(',').forEach(v => values.add(v.trim()));
                    }
                }
            });

            const wrapper = document.createElement('div');
            wrapper.className = 'snippen-multiselect-dropdown';
            
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'snippen-multiselect-btn';
            btn.textContent = this.strings.all;
            wrapper.appendChild(btn);

            const list = document.createElement('div');
            list.className = 'snippen-multiselect-list';
            list.style.display = 'none';
            list.dataset.index = index;

            Array.from(values).sort().forEach(val => {
                if (!val) return;
                const label = document.createElement('label');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.value = val;
                if (savedFilter && savedFilter.value && savedFilter.value.includes(val)) {
                    checkbox.checked = true;
                }
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + val));
                list.appendChild(label);
            });
            wrapper.appendChild(list);

            btn.addEventListener('click', () => {
                const isOpen = list.style.display === 'block';
                document.querySelectorAll('.snippen-multiselect-list').forEach(l => l.style.display = 'none');
                list.style.display = isOpen ? 'none' : 'block';
            });

            const currentVal = savedFilter ? savedFilter.value : [];
            this.updateMultiselectButtonText(btn, list, currentVal);

            container.appendChild(wrapper);
            return container;
        }
        else if (type === 'minmax') {
            const min = document.createElement('input');
            min.type = 'number';
            min.placeholder = this.strings.min;
            min.className = 'snippen-min-input';
            min.dataset.index = index;

            const max = document.createElement('input');
            max.type = 'number';
            max.placeholder = this.strings.max;
            max.className = 'snippen-max-input';
            max.dataset.index = index;

            if (savedFilter && savedFilter.value) {
                if (savedFilter.value.min) min.value = savedFilter.value.min;
                if (savedFilter.value.max) max.value = savedFilter.value.max;
            }

            container.appendChild(min);
            container.appendChild(max);
            return container; 
        }
        return null;
    }

    updateMultiselectButtonText(btn, list, savedValue) {
        if (!savedValue || savedValue.length === 0) {
            btn.textContent = this.strings.all;
        } else if (savedValue.length === 1) {
            btn.textContent = savedValue[0];
        } else {
            btn.textContent = `${savedValue.length} valgt`;
        }
    }

    addControls(table, filterInputs, state, stateKey) {
        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'snippen-table-controls';

        const resetBtn = document.createElement('button');
        resetBtn.type = 'button';
        resetBtn.className = 'snippen-btn snippen-btn-outline';
        resetBtn.textContent = this.strings.resetFilters;
        resetBtn.addEventListener('click', () => {
            state.filters = {};
            state.sort = { column: null, dir: 'asc' };
            sessionStorage.removeItem(stateKey);

            filterInputs.forEach(fi => {
                if (fi.type === 'text') {
                    const input = fi.element.querySelector('input');
                    if (input) input.value = '';
                } else if (fi.type === 'multiselect') {
                    const checkboxes = fi.element.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(cb => cb.checked = false);
                    const btn = fi.element.querySelector('.snippen-multiselect-btn');
                    const list = fi.element.querySelector('.snippen-multiselect-list');
                    if (btn && list) {
                        this.updateMultiselectButtonText(btn, list, []);
                    }
                } else if (fi.type === 'minmax') {
                    const inputs = fi.element.querySelectorAll('input');
                    inputs.forEach(inp => inp.value = '');
                }
            });

            table.querySelectorAll('th.sortable').forEach(th => {
                th.classList.remove('sorted-asc', 'sorted-desc');
            });

            this.applyFiltersAndSort(table, state);
        });

        const summary = document.createElement('span');
        summary.className = 'snippen-table-summary';
        
        controlsDiv.appendChild(summary);
        controlsDiv.appendChild(resetBtn);

        const insertTarget = table.closest('.snippen-table-responsive') || table;
        insertTarget.parentNode.insertBefore(controlsDiv, insertTarget);
    }

    bindFilterEvents(table, filterInputs, state, stateKey) {
        const applyAndSave = () => {
            sessionStorage.setItem(stateKey, JSON.stringify(state));
            this.applyFiltersAndSort(table, state);
        };

        filterInputs.forEach(fi => {
            if (fi.type === 'text') {
                fi.element.addEventListener('input', (e) => {
                    const val = e.target.value.trim();
                    if (val) state.filters[fi.index] = { type: 'text', value: val };
                    else delete state.filters[fi.index];
                    applyAndSave();
                });
            } else if (fi.type === 'multiselect') {
                fi.element.addEventListener('change', () => {
                    const checked = Array.from(fi.element.querySelectorAll('input:checked')).map(cb => cb.value);
                    if (checked.length > 0) {
                        state.filters[fi.index] = { type: 'multiselect', value: checked };
                    } else {
                        delete state.filters[fi.index];
                    }
                    const btn = fi.element.querySelector('.snippen-multiselect-btn');
                    const list = fi.element.querySelector('.snippen-multiselect-list');
                    if (btn && list) {
                        this.updateMultiselectButtonText(btn, list, checked);
                    }
                    applyAndSave();
                });
            } else if (fi.type === 'minmax') {
                fi.element.querySelectorAll('input').forEach(inp => {
                    inp.addEventListener('input', () => {
                        const minVal = fi.element.querySelector('.snippen-min-input').value;
                        const maxVal = fi.element.querySelector('.snippen-max-input').value;
                        if (minVal || maxVal) {
                            state.filters[fi.index] = { 
                                type: 'minmax', 
                                value: { min: minVal || null, max: maxVal || null } 
                            };
                        } else {
                            delete state.filters[fi.index];
                        }
                        applyAndSave();
                    });
                });
            }
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.snippen-multiselect-dropdown')) {
                document.querySelectorAll('.snippen-multiselect-list').forEach(l => l.style.display = 'none');
            }
        });
    }

    handleSort(table, th, index, sortType, state, stateKey) {
        const currentDir = state.sort.column === index ? state.sort.dir : null;
        let newDir = 'asc';
        if (currentDir === 'asc') newDir = 'desc';
        else if (currentDir === 'desc') newDir = null;

        table.querySelectorAll('th.sortable').forEach(el => {
            el.classList.remove('sorted-asc', 'sorted-desc');
        });

        if (newDir) {
            state.sort.column = index;
            state.sort.dir = newDir;
            state.sort.type = sortType;
            th.classList.add(`sorted-${newDir}`);
        } else {
            state.sort.column = null;
        }

        sessionStorage.setItem(stateKey, JSON.stringify(state));
        this.applyFiltersAndSort(table, state);
    }

    applyFiltersAndSort(table, state) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        let visibleCount = 0;

        if (state.sort.column !== null) {
            rows.sort((a, b) => {
                let valA = a.cells[state.sort.column].textContent.trim();
                let valB = b.cells[state.sort.column].textContent.trim();

                if (state.sort.type === 'number') {
                    valA = parseFloat(valA.replace(/[^\d.-]/g, '')) || 0;
                    valB = parseFloat(valB.replace(/[^\d.-]/g, '')) || 0;
                    return state.sort.dir === 'asc' ? valA - valB : valB - valA;
                } else {
                    return state.sort.dir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        }

        rows.forEach(row => {
            let show = true;
            for (const [indexStr, filter] of Object.entries(state.filters)) {
                const index = parseInt(indexStr, 10);
                if (!row.cells[index]) continue;
                const cellText = row.cells[index].textContent.trim();

                if (filter.type === 'text') {
                    const searchParts = filter.value.toLowerCase().split('*').map(s => s.trim()).filter(s => s);
                    let textLower = cellText.toLowerCase();
                    for (const part of searchParts) {
                        const idx = textLower.indexOf(part);
                        if (idx === -1) {
                            show = false;
                            break;
                        }
                        textLower = textLower.substring(idx + part.length);
                    }
                } else if (filter.type === 'multiselect') {
                    const cellValues = cellText.split(',').map(s => s.trim());
                    const hasMatch = filter.value.some(v => cellValues.includes(v));
                    if (!hasMatch) show = false;
                } else if (filter.type === 'minmax') {
                    const numVal = parseFloat(cellText.replace(/[^\d.-]/g, '')) || 0;
                    if (filter.value.min && numVal < parseFloat(filter.value.min)) show = false;
                    if (filter.value.max && numVal > parseFloat(filter.value.max)) show = false;
                }
            }
            row.style.display = show ? '' : 'none';
            if (show) visibleCount++;
        });

        const summaryElement = table.parentNode.parentNode.querySelector('.snippen-table-summary');
        if (summaryElement) {
            summaryElement.textContent = `${this.strings.showing} ${visibleCount} ${this.strings.of} ${rows.length} ${this.strings.rows}`;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    new SnippenTableFilter('.snippen-filterable-table');
});

if (typeof module !== 'undefined' && module.exports) {
    module.exports = SnippenTableFilter;
}
