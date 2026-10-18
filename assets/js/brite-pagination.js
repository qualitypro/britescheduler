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

            this.controls.append(this.info, this.buttons);
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

            this.first.disabled = this.page === 1;
            this.previous.disabled = this.page === 1;
            this.next.disabled =
                this.page === this.totalPages();
            this.last.disabled =
                this.page === this.totalPages();
        }
    }

    global.BritePagination = BritePagination;
})(window);
