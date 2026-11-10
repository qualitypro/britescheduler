/*
 * Brite Scheduler shared CRUD pagination.
 * Default: 15 records per page.
 */
(function (global) {
    'use strict';

    class BritePagination {
        constructor(options) {
            if (!options || !options.container ||
                typeof options.render !== 'function') {
                throw new Error('Invalid pagination configuration');
            }

            this.container = options.container;
            this.render = options.render;
            this.pageSize = 15;
            this.page = 1;
            this.records = [];

            this.controls = document.createElement('nav');
            this.controls.className = 'brite-pagination';
            this.controls.setAttribute(
                'aria-label',
                'Table pagination'
            );

            this.controls.style.cssText = [
                'display:flex',
                'align-items:center',
                'justify-content:space-between',
                'flex-wrap:wrap',
                'gap:12px',
                'margin-top:14px'
            ].join(';');

            this.info = document.createElement('span');
            this.info.className = 'brite-pagination-info';
            this.info.setAttribute('aria-live', 'polite');

            this.pageSizeLabel = document.createElement('label');
            this.pageSizeLabel.className = 'brite-pagination-page-size';
            this.pageSizeLabel.textContent = 'Rows per page ';
            this.pageSizeSelect = document.createElement('select');
            this.pageSizeSelect.setAttribute('aria-label', 'Rows per page');
            [15, 30, 50].forEach(size => {
                const option = document.createElement('option');
                option.value = String(size);
                option.textContent = String(size);
                this.pageSizeSelect.appendChild(option);
            });
            this.pageSizeSelect.value = String(this.pageSize);
            this.pageSizeSelect.addEventListener('change', () => {
                this.pageSize = Number(this.pageSizeSelect.value);
                this.page = 1;
                this.update();
            });
            this.pageSizeLabel.appendChild(this.pageSizeSelect);

            this.buttons = document.createElement('div');
            this.buttons.style.cssText =
                'display:flex;align-items:center;gap:6px';

            this.first = this.makeButton(
                '«',
                'First page',
                () => this.goTo(1)
            );

            this.previous = this.makeButton(
                '‹',
                'Previous page',
                () => this.goTo(this.page - 1)
            );

            this.pageLabel = document.createElement('span');
            this.pageLabel.style.cssText =
                'padding:0 8px;white-space:nowrap';

            this.next = this.makeButton(
                '›',
                'Next page',
                () => this.goTo(this.page + 1)
            );

            this.last = this.makeButton(
                '»',
                'Last page',
                () => this.goTo(this.totalPages())
            );

            this.buttons.append(
                this.first,
                this.previous,
                this.pageLabel,
                this.next,
                this.last
            );

            this.controls.append(this.info, this.pageSizeLabel, this.buttons);
            this.container.appendChild(this.controls);

            this.update();
        }

        makeButton(symbol, label, handler) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = symbol;
            button.setAttribute('aria-label', label);
            button.title = label;
            button.addEventListener('click', handler);
            return button;
        }

        totalPages() {
            return Math.max(
                1,
                Math.ceil(this.records.length / this.pageSize)
            );
        }

        setRecords(records, resetPage = false) {
            this.records = Array.isArray(records)
                ? records.slice()
                : [];

            if (resetPage) this.page = 1;

            this.page = Math.min(
                Math.max(1, this.page),
                this.totalPages()
            );

            this.update();
        }

        goTo(page) {
            const target = Number(page);

            if (!Number.isInteger(target)) return;

            this.page = Math.min(
                Math.max(1, target),
                this.totalPages()
            );

            this.update();
        }

        update() {
            const total = this.records.length;
            const start = (this.page - 1) * this.pageSize;
            const visible = this.records.slice(
                start,
                start + this.pageSize
            );

            this.render(visible);

            const firstRecord = total ? start + 1 : 0;
            const lastRecord = total
                ? Math.min(start + this.pageSize, total)
                : 0;

            this.info.textContent =
                `Showing ${firstRecord}–${lastRecord} of ${total}`;

            this.pageLabel.textContent =
                `Page ${this.page} of ${this.totalPages()}`;
            this.controls.setAttribute('aria-label', `Table pagination, ${total} records`);

            this.first.disabled = this.page === 1;
            this.previous.disabled = this.page === 1;
            this.next.disabled =
                this.page === this.totalPages();
            this.last.disabled =
                this.page === this.totalPages();
        }
    }

    // Remote pagination for APIs that return {records, pagination}.
    // Keep BritePagination above unchanged for existing client-side tables.
    class BriteServerPagination {
        constructor(options) {
            if (!options || !options.container ||
                typeof options.load !== 'function' ||
                typeof options.render !== 'function') {
                throw new Error('Invalid server pagination configuration');
            }
            this.container = options.container;
            this.load = options.load;
            this.render = options.render;
            this.onError = options.onError;
            this.page = 1;
            this.pageSize = 25;
            this.sequence = 0;
            this.meta = null;

            this.nav = document.createElement('nav');
            this.nav.className = 'brite-pagination brite-server-pagination';
            this.nav.setAttribute('aria-label', 'Server-side pagination');
            this.nav.style.cssText =
                'display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:14px';

            this.info = document.createElement('span');
            this.info.setAttribute('aria-live', 'polite');
            this.previous = document.createElement('button');
            this.previous.type = 'button';
            this.previous.textContent = 'Previous';
            this.previous.addEventListener('click', () => this.goTo(this.page - 1));
            this.next = document.createElement('button');
            this.next.type = 'button';
            this.next.textContent = 'Next';
            this.next.addEventListener('click', () => this.goTo(this.page + 1));
            this.size = document.createElement('select');
            this.size.setAttribute('aria-label', 'Rows per page');
            [15,25,50,100].forEach(value => {
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = String(value);
                this.size.appendChild(option);
            });
            this.size.value = String(this.pageSize);
            this.size.addEventListener('change', () => {
                this.pageSize = Number(this.size.value);
                this.goTo(1);
            });
            this.retry = document.createElement('button');
            this.retry.type = 'button';
            this.retry.textContent = 'Retry';
            this.retry.hidden = true;
            this.retry.addEventListener('click', () => this.refresh());
            this.nav.append(this.info, this.previous, this.next, this.size, this.retry);
            this.container.appendChild(this.nav);
            this.refresh();
        }

        async goTo(page) {
            if (!Number.isInteger(page) || page < 1 ||
                (this.meta && page > this.meta.total_pages)) return;
            this.page = page;
            await this.refresh();
        }

        async refresh() {
            const request = ++this.sequence;
            this.previous.disabled = true;
            this.next.disabled = true;
            this.info.textContent = 'Loading records…';
            this.retry.hidden = true;
            try {
                const result = await this.load({
                    page: this.page,
                    per_page: this.pageSize
                });
                if (request !== this.sequence) return;
                if (!result || !Array.isArray(result.records) ||
                    !result.pagination) {
                    throw new Error('Invalid paginated API response');
                }
                this.meta = result.pagination;
                this.page = Number(this.meta.page);
                this.render(result.records);
                const total = Number(this.meta.total) || 0;
                const first = total ? (this.page - 1) * this.pageSize + 1 : 0;
                const last = Math.min(total, this.page * this.pageSize);
                this.info.textContent =
                    'Showing ' + first + '–' + last + ' of ' + total;
                this.previous.disabled = !this.meta.has_previous;
                this.next.disabled = !this.meta.has_next;
            } catch (error) {
                if (request !== this.sequence) return;
                this.info.textContent = 'Unable to load records';
                this.retry.hidden = false;
                if (typeof this.onError === 'function') this.onError(error);
            }
        }
    }

    global.BritePagination = BritePagination;
    global.BriteServerPagination = BriteServerPagination;
})(window);
