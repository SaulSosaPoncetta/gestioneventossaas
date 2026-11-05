/**
 * GestiónEventos — dibujo de mapas de butacas en SVG.
 *
 * Modo "edit"   (panel de productora): seleccionar butacas con clic, por fila,
 *               por sector o arrastrando un rectángulo con el mouse.
 * Modo "select" (compra): el comprador elige butacas disponibles, con un tope.
 *
 * Uso:
 *   const map = new SeatMap('#mapa', data, {
 *       mode: 'select',
 *       maxSelected: 4,
 *       seatState: (seat, section) => 'available' | 'unavailable',
 *       onChange: (seats) => {},
 *       onLimit: () => {},
 *   });
 *
 * data = { sections: [{ id, name, type, color, capacity, layout:{x,y,w,h},
 *                       seats:[{ i, r, n, x, y, k, a }] }] }
 *   i = id, r = fila, n = número, x/y = posición, k = tipo, a = activa
 */
(function (global) {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const KIND_COLORS = { accessible: '#2563eb', companion: '#0d9488' };
    const SELECTED = '#f59e0b';
    const UNAVAILABLE = '#d1d5db';

    function el(name, attrs, parent) {
        const node = document.createElementNS(NS, name);
        Object.entries(attrs || {}).forEach(([k, v]) => {
            if (v !== null && v !== undefined) node.setAttribute(k, v);
        });
        if (parent) parent.appendChild(node);
        return node;
    }

    function injectStyles() {
        if (document.getElementById('seatmap-styles')) return;
        const style = document.createElement('style');
        style.id = 'seatmap-styles';
        style.textContent = `
            .seatmap-svg { display:block; margin:0 auto; user-select:none; -webkit-user-select:none; }
            .seatmap-svg text { font-family: Inter, system-ui, sans-serif; }
            .sm-title { font-size:.6px; font-weight:700; cursor:pointer; }
            .sm-subtitle { font-size:.45px; fill:#64748b; }
            .sm-row { font-size:.38px; font-weight:600; fill:#64748b; cursor:pointer; }
            .sm-num { font-size:.3px; font-weight:600; pointer-events:none; }
            .sm-seat { cursor:pointer; }
            .sm-seat.is-off { cursor:not-allowed; }
            .sm-stage { fill:#1e1338; }
            .sm-stage-label { font-size:.6px; font-weight:700; fill:#fff; letter-spacing:.15px; }
            .sm-select-rect { fill:rgba(245,158,11,.12); stroke:${SELECTED}; stroke-width:.06; stroke-dasharray:.2 .12; }
        `;
        document.head.appendChild(style);
    }

    class SeatMap {
        constructor(container, data, options = {}) {
            this.container = typeof container === 'string' ? document.querySelector(container) : container;
            this.data = data || { sections: [] };
            this.opts = Object.assign({
                mode: 'edit',
                cell: 24,            // píxeles por unidad con zoom 1
                maxSelected: Infinity,
                seatState: null,
                onChange: null,
                onLimit: null,
                stageLabel: 'ESCENARIO',
            }, options);

            this.selected = new Set();
            this.items = new Map(); // id de butaca -> { node, circle, text, seat, section, cx, cy }
            this.zoom = 1;

            injectStyles();
            this.render();
            this.fit();
        }

        /* ---------------- Dibujo ---------------- */

        render() {
            this.container.innerHTML = '';
            this.items.clear();

            const svg = this.svg = el('svg', { class: 'seatmap-svg', xmlns: NS }, this.container);
            const g = el('g', {}, svg);

            let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
            const grow = (x1, y1, x2, y2) => {
                minX = Math.min(minX, x1); minY = Math.min(minY, y1);
                maxX = Math.max(maxX, x2); maxY = Math.max(maxY, y2);
            };

            this.data.sections.forEach((sec) => {
                const L = sec.layout || { x: 0, y: 0, w: 10, h: 4 };
                const seats = sec.seats || [];

                if (sec.type === 'seated' && seats.length) {
                    const w = Math.max(...seats.map((s) => s.x)) + 1;
                    const h = Math.max(...seats.map((s) => s.y)) + 2;
                    grow(L.x - 1.4, L.y, L.x + w + 1.4, L.y + h);

                    const title = el('text', { x: L.x + w / 2, y: L.y + 0.65, 'text-anchor': 'middle', class: 'sm-title', fill: sec.color }, g);
                    title.textContent = sec.name;
                    title.addEventListener('click', () => this.toggleGroup(seats));

                    // Etiquetas de fila a ambos lados
                    const rows = {};
                    seats.forEach((s) => { (rows[s.r] = rows[s.r] || { y: s.y, seats: [] }).seats.push(s); });
                    Object.entries(rows).forEach(([label, row]) => {
                        const cy = L.y + 1 + row.y + 0.5;
                        [L.x - 0.75, L.x + w + 0.75].forEach((x) => {
                            const t = el('text', { x, y: cy + 0.14, 'text-anchor': 'middle', class: 'sm-row' }, g);
                            t.textContent = label;
                            t.addEventListener('click', () => this.toggleGroup(row.seats));
                        });
                    });

                    seats.forEach((s) => {
                        const cx = L.x + s.x + 0.5;
                        const cy = L.y + 1 + s.y + 0.5;
                        const node = el('g', { class: 'sm-seat', 'data-id': s.i }, g);
                        const circle = el('circle', { cx, cy, r: 0.4 }, node);
                        const text = el('text', { x: cx, y: cy + 0.11, 'text-anchor': 'middle', class: 'sm-num' }, node);
                        text.textContent = s.n;
                        el('title', {}, node).textContent = `${sec.name} · Fila ${s.r} · Butaca ${s.n}`;

                        const item = { node, circle, text, seat: s, section: sec, cx, cy };
                        this.items.set(s.i, item);
                        node.addEventListener('click', (e) => { e.stopPropagation(); this.onSeatClick(item); });
                        this.paint(item);
                    });
                } else {
                    // Sector general (o numerado todavía sin butacas)
                    const empty = sec.type === 'seated';
                    const w = empty ? 10 : L.w;
                    const h = empty ? 3 : L.h;
                    grow(L.x, L.y, L.x + w, L.y + h);

                    el('rect', {
                        x: L.x, y: L.y, width: w, height: h, rx: 0.4,
                        fill: sec.color, 'fill-opacity': 0.16, stroke: sec.color, 'stroke-width': 0.08,
                        'stroke-dasharray': empty ? '0.3 0.2' : null,
                    }, g);
                    const t = el('text', { x: L.x + w / 2, y: L.y + h / 2, 'text-anchor': 'middle', class: 'sm-title', fill: sec.color }, g);
                    t.textContent = sec.name;
                    const sub = el('text', { x: L.x + w / 2, y: L.y + h / 2 + 0.7, 'text-anchor': 'middle', class: 'sm-subtitle' }, g);
                    sub.textContent = empty
                        ? 'Sin butacas generadas'
                        : `General · ${Number(sec.capacity || 0).toLocaleString('es-AR')} lugares`;
                }
            });

            if (minX === Infinity) { minX = 0; minY = 0; maxX = 20; maxY = 6; }

            // Escenario arriba de todo
            const width = maxX - minX;
            const sw = Math.max(8, width * 0.5);
            const sx = minX + (width - sw) / 2;
            const sy = minY - 3;
            el('rect', { x: sx, y: sy, width: sw, height: 1.6, rx: 0.3, class: 'sm-stage' }, g);
            el('text', { x: sx + sw / 2, y: sy + 1.02, 'text-anchor': 'middle', class: 'sm-stage-label' }, g)
                .textContent = this.opts.stageLabel;

            this.vb = { x: minX - 1, y: sy - 1, w: width + 2, h: maxY - sy + 2 };
            svg.setAttribute('viewBox', `${this.vb.x} ${this.vb.y} ${this.vb.w} ${this.vb.h}`);
            this.applyZoom();

            if (this.opts.mode === 'edit') this.enableRectSelect();
        }

        paint(item) {
            const s = item.seat;
            const isSel = this.selected.has(s.i);
            let fill, stroke = 'none', textFill = '#fff', dash = null, off = false;

            if (this.opts.mode === 'edit') {
                if (!s.a) {
                    fill = '#fff'; stroke = '#94a3b8'; textFill = '#94a3b8'; dash = '0.12 0.08';
                } else {
                    fill = KIND_COLORS[s.k] || item.section.color;
                }
            } else {
                const available = this.state(item) === 'available';
                off = !available;
                fill = available ? (KIND_COLORS[s.k] || item.section.color) : UNAVAILABLE;
                textFill = available ? '#fff' : '#9ca3af';
                if (!s.a) item.node.setAttribute('opacity', '0.15');
                if (isSel) { fill = SELECTED; }
            }

            if (isSel) stroke = this.opts.mode === 'edit' ? SELECTED : '#7c2d12';

            item.circle.setAttribute('fill', fill);
            item.circle.setAttribute('stroke', stroke);
            item.circle.setAttribute('stroke-width', isSel ? 0.16 : 0.06);
            if (dash) item.circle.setAttribute('stroke-dasharray', dash); else item.circle.removeAttribute('stroke-dasharray');
            item.text.setAttribute('fill', textFill);
            item.node.classList.toggle('is-off', off);
        }

        repaint() {
            this.items.forEach((item) => this.paint(item));
        }

        /* ---------------- Zoom ---------------- */

        applyZoom() {
            this.svg.setAttribute('width', Math.round(this.vb.w * this.opts.cell * this.zoom));
            this.svg.setAttribute('height', Math.round(this.vb.h * this.opts.cell * this.zoom));
        }

        zoomIn() { this.zoom = Math.min(4, this.zoom * 1.25); this.applyZoom(); }
        zoomOut() { this.zoom = Math.max(0.2, this.zoom / 1.25); this.applyZoom(); }

        /** Ajusta el zoom para que el mapa entre a lo ancho del contenedor */
        fit() {
            const available = this.container.clientWidth - 8;
            if (available > 0) {
                this.zoom = Math.min(1.5, Math.max(0.2, available / (this.vb.w * this.opts.cell)));
                this.applyZoom();
            }
        }

        /* ---------------- Selección ---------------- */

        state(item) {
            if (!item.seat.a) return 'unavailable';
            return this.opts.seatState ? this.opts.seatState(item.seat, item.section) : 'available';
        }

        onSeatClick(item) {
            const id = item.seat.i;

            if (this.opts.mode !== 'edit') {
                if (this.state(item) !== 'available') return;
                if (!this.selected.has(id) && this.selected.size >= this.opts.maxSelected) {
                    if (this.opts.onLimit) this.opts.onLimit(this.opts.maxSelected);
                    return;
                }
            }

            this.toggle(id);
        }

        toggle(id, force) {
            const on = force === undefined ? !this.selected.has(id) : force;
            if (on) this.selected.add(id); else this.selected.delete(id);
            const item = this.items.get(id);
            if (item) this.paint(item);
            this.emit();
        }

        /** En modo edición: selecciona/deselecciona una fila o un sector entero */
        toggleGroup(seats) {
            if (this.opts.mode !== 'edit') return;
            const ids = seats.map((s) => s.i);
            const allSelected = ids.every((id) => this.selected.has(id));
            ids.forEach((id) => {
                if (allSelected) this.selected.delete(id); else this.selected.add(id);
                this.paint(this.items.get(id));
            });
            this.emit();
        }

        clear() {
            this.selected.clear();
            this.repaint();
            this.emit();
        }

        setSelected(ids) {
            this.selected = new Set(ids);
            this.repaint();
            this.emit();
        }

        getSelected() {
            return Array.from(this.selected);
        }

        getSelectedSeats() {
            return this.getSelected().map((id) => {
                const item = this.items.get(id);
                return Object.assign({ sectionId: item.section.id, sectionName: item.section.name }, item.seat);
            });
        }

        emit() {
            if (this.opts.onChange) this.opts.onChange(this.getSelectedSeats());
        }

        /** Arrastrar con el mouse un rectángulo para seleccionar (Shift = agregar) */
        enableRectSelect() {
            const svg = this.svg;
            let start = null, rect = null, additive = false;

            const toSvg = (e) => {
                const pt = svg.createSVGPoint();
                pt.x = e.clientX; pt.y = e.clientY;
                return pt.matrixTransform(svg.getScreenCTM().inverse());
            };

            svg.addEventListener('pointerdown', (e) => {
                if (e.pointerType !== 'mouse' || e.button !== 0) return;
                if (e.target.closest('.sm-seat, .sm-row, .sm-title')) return;
                start = toSvg(e);
                additive = e.shiftKey || e.ctrlKey || e.metaKey;
                rect = el('rect', { x: start.x, y: start.y, width: 0, height: 0, class: 'sm-select-rect' }, svg);
                svg.setPointerCapture(e.pointerId);
            });

            svg.addEventListener('pointermove', (e) => {
                if (!start) return;
                const p = toSvg(e);
                rect.setAttribute('x', Math.min(p.x, start.x));
                rect.setAttribute('y', Math.min(p.y, start.y));
                rect.setAttribute('width', Math.abs(p.x - start.x));
                rect.setAttribute('height', Math.abs(p.y - start.y));
            });

            svg.addEventListener('pointerup', (e) => {
                if (!start) return;
                const p = toSvg(e);
                const x1 = Math.min(p.x, start.x), x2 = Math.max(p.x, start.x);
                const y1 = Math.min(p.y, start.y), y2 = Math.max(p.y, start.y);
                rect.remove();
                start = null;

                const tiny = (x2 - x1) < 0.2 && (y2 - y1) < 0.2;
                if (!additive) this.selected.clear();
                if (!tiny) {
                    this.items.forEach((item, id) => {
                        if (item.cx >= x1 && item.cx <= x2 && item.cy >= y1 && item.cy <= y2) this.selected.add(id);
                    });
                }
                this.repaint();
                this.emit();
            });
        }
    }

    global.SeatMap = SeatMap;
})(window);
