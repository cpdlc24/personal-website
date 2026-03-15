/**
 * DataTable — Shared JSON-driven virtual table with filtering & pagination.
 *
 * Usage:
 *   const dt = new DataTable({
 *     tableId:      'perfTableBody',
 *     data:         [...],            // initial JSON rows
 *     serverTimeMs: 1234567890000,
 *     columns:      [ { key, label, classes, escape } ],
 *     filters:      [ { col, type:'select'|'time'|'range', key, options } ],
 *     chartInstance: chartJsRef,       // optional Chart.js chart to update
 *     chartBuilder: fn(filteredRows) => { labels, loads, ttfbs }, // optional
 *   });
 *
 *   dt.replaceData(newArray);   // swap entire dataset (e.g. after bg fetch)
 */
class DataTable {
    constructor(opts) {
        this.allData       = opts.data || [];
        this.serverTimeMs  = opts.serverTimeMs;
        this.columns       = opts.columns;
        this.filterConfigs = opts.filters || [];
        this.chart         = opts.chartInstance || null;
        this.chartBuilder  = opts.chartBuilder || null;
        this.onFilter      = opts.onFilter || null;

        this.tbody         = document.getElementById(opts.tableId);
        this.clearBtn      = document.getElementById(opts.clearBtnId || 'clearFiltersBtn');
        this.pageInfo      = document.getElementById(opts.pageInfoId || 'pageInfo');
        this.prevBtn       = document.getElementById(opts.prevBtnId  || 'prevPageBtn');
        this.nextBtn       = document.getElementById(opts.nextBtnId  || 'nextPageBtn');
        this.pageSizeSelect= document.getElementById(opts.pageSizeId || 'pageSizeSelect');

        this.filterState   = new Array(this.filterConfigs.length).fill('');
        this.filteredData  = [];
        this.currentPage   = 1;
        this.pageSize      = 50;

        // Time formatter for table cells
        this.timeFormatter = new Intl.DateTimeFormat(undefined, {
            month:  '2-digit', day:    '2-digit',
            hour:   '2-digit', minute: '2-digit', second: '2-digit',
            hour12: false
        });

        this._bindEvents();
        this._applyDefaultFilters();
        this.applyFilters();
    }

    /* ── public ─────────────────────────────────────────── */

    replaceData(newData) {
        this.allData = newData;
        this._rebuildFilterOptions();
        this.applyFilters();
    }

    _rebuildFilterOptions() {
        // Rebuild <option> elements for select/contains filter dropdowns from allData
        this.filterSelects.forEach(sel => {
            const colIdx = parseInt(sel.dataset.col, 10);
            if (isNaN(colIdx)) return;
            const f = this.filterConfigs[colIdx];
            if (!f || (f.type !== 'select' && f.type !== 'contains')) return;

            // Collect unique values
            const uniqueVals = new Set();
            this.allData.forEach(r => {
                const val = r[f.key];
                if (!val) return;
                if (f.type === 'contains') {
                    // Parse action strings like "3 Click, 2 Keyboard up" to extract action types
                    val.split(', ').forEach(part => {
                        const spaceIdx = part.indexOf(' ');
                        if (spaceIdx > 0) {
                            uniqueVals.add(part.substring(spaceIdx + 1));
                        } else {
                            uniqueVals.add(part); // e.g. "Passive"
                        }
                    });
                } else {
                    uniqueVals.add(val);
                }
            });

            // Remember current selection
            const current = sel.value;

            // Get the "ALL" option (first option)
            const allOption = sel.options[0];
            const allLabel = allOption ? allOption.textContent : '';
            const allValue = allOption ? allOption.value : '';

            // Rebuild options
            sel.innerHTML = '';
            const opt0 = document.createElement('option');
            opt0.value = allValue;
            opt0.textContent = allLabel;
            sel.appendChild(opt0);

            const sorted = [...uniqueVals].sort();
            sorted.forEach(v => {
                const opt = document.createElement('option');
                opt.value = v;
                opt.textContent = f.type === 'contains' ? v.toUpperCase() : v;
                sel.appendChild(opt);
            });

            // Restore selection if it still exists
            if (current && [...sel.options].some(o => o.value === current)) {
                sel.value = current;
            } else {
                sel.value = allValue;
                this.filterState[colIdx] = '';
            }
        });
    }

    /* ── internal ───────────────────────────────────────── */

    _applyDefaultFilters() {
        const wrapper = this.tbody.closest('.data-table-wrapper');
        const selects = wrapper
            ? wrapper.querySelectorAll('.filter-bar .filter-select')
            : document.querySelectorAll('.filter-bar .filter-select');

        this.filterSelects = selects;

        selects.forEach(sel => {
            const colIdx = parseInt(sel.dataset.col, 10);
            if (sel.value !== '' && !isNaN(colIdx)) {
                this.filterState[colIdx] = sel.value;
            }
        });

        if (this.filterState.some(f => f !== '')) {
            this.clearBtn.style.display = 'inline-block';
        }
    }

    _bindEvents() {
        const self = this;
        // Filter selects — bound after DOM is ready
        setTimeout(() => {
            self.filterSelects.forEach(sel => {
                const colIdx = parseInt(sel.dataset.col, 10);
                if (isNaN(colIdx)) return;
                sel.addEventListener('change', () => {
                    self.filterState[colIdx] = sel.value;
                    const anyActive = self.filterState.some(f => f !== '');
                    self.clearBtn.style.display = anyActive ? 'inline-block' : 'none';
                    self.applyFilters();
                });
            });
        }, 0);

        this.clearBtn.addEventListener('click', () => {
            self.filterState.fill('');
            self.clearBtn.style.display = 'none';
            self.filterSelects.forEach(sel => {
                sel.value = '';
            });
            self.applyFilters();
        });

        this.pageSizeSelect.addEventListener('change', () => {
            self.pageSize = parseInt(self.pageSizeSelect.value, 10);
            self.currentPage = 1;
            self._renderPage();
        });

        this.prevBtn.addEventListener('click', () => {
            if (self.currentPage > 1) { self.currentPage--; self._renderPage(); }
        });

        this.nextBtn.addEventListener('click', () => {
            const tp = Math.ceil(self.filteredData.length / self.pageSize) || 1;
            if (self.currentPage < tp) { self.currentPage++; self._renderPage(); }
        });
    }

    applyFilters() {
        const self = this;
        this.filteredData = this.allData.filter(r => {
            for (let i = 0; i < self.filterConfigs.length; i++) {
                const f = self.filterConfigs[i];
                const val = self.filterState[i];
                if (!val) continue;

                if (f.type === 'time') {
                    const diffHours = (self.serverTimeMs - r.raw_time) / (1000 * 60 * 60);
                    if (val === '1h'   && diffHours > 1)   return false;
                    if (val === '3h'   && diffHours > 3)   return false;
                    if (val === '12h'  && diffHours > 12)  return false;
                    if (val === '24h'  && diffHours > 24)  return false;
                    if (val === '7d'   && diffHours > 168) return false;
                    if (val === '30d'  && diffHours > 720) return false;
                    if (val === '12h+' && diffHours <= 12) return false;
                } else if (f.type === 'range') {
                    const num = r[f.numKey];
                    if (val.endsWith('+')) {
                        // Greater than threshold
                        if (num < parseFloat(val)) return false;
                    } else {
                        // Less than threshold
                        if (num >= parseFloat(val)) return false;
                    }
                } else if (f.type === 'contains') {
                    if (!r[f.key] || !r[f.key].includes(val)) return false;
                } else {
                    // exact match on column key
                    if (r[f.key] !== val) return false;
                }
            }
            return true;
        });

        // Update chart if configured
        if (this.chart && this.chartBuilder) {
            const chartData = this.chartBuilder(this.filteredData);
            if (chartData) {
                this.chart.data.labels = chartData.labels;
                for (let d = 0; d < chartData.datasets.length; d++) {
                    this.chart.data.datasets[d].data = chartData.datasets[d];
                    if (chartData.pointRadius !== undefined) {
                        this.chart.data.datasets[d].pointRadius = chartData.pointRadius;
                    }
                }
                this.chart.update();
            }
        }

        this.currentPage = 1;
        this._renderPage();

        // Fire onFilter callback with the filtered data
        if (this.onFilter) {
            this.onFilter(this.filteredData);
        }
    }

    _renderPage() {
        const tp = Math.ceil(this.filteredData.length / this.pageSize) || 1;
        if (this.currentPage > tp) this.currentPage = tp;
        if (this.currentPage < 1) this.currentPage = 1;

        const start = (this.currentPage - 1) * this.pageSize;
        const pageRows = this.filteredData.slice(start, start + this.pageSize);

        let html = '';
        if (pageRows.length === 0) {
            html = `<tr><td colspan="${this.columns.length}" class="py-8 text-sm italic text-gray-400 font-light">No matching entries.</td></tr>`;
        } else {
            const esc = s => String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            pageRows.forEach(r => {
                html += '<tr class="data-row hover:bg-gray-50/50 group">';
                this.columns.forEach(col => {
                    let val;
                    if (col.key === '_time') {
                        val = this.timeFormatter.format(new Date(r.raw_time));
                    } else if (col.render) {
                        val = col.render(r);
                    } else {
                        val = col.escape !== false ? esc(r[col.key]) : r[col.key];
                    }
                    html += `<td class="${col.classes || 'py-4 pr-4 whitespace-nowrap text-sm text-gray-400 font-light'}">${val}</td>`;
                });
                html += '</tr>';
            });
        }
        this.tbody.innerHTML = html;

        this.pageInfo.textContent = `Page ${this.currentPage} of ${tp} (${this.filteredData.length} entries)`;
        this.prevBtn.disabled = this.currentPage === 1;
        this.nextBtn.disabled = this.currentPage === tp;
    }
}
