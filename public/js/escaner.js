/**
 * GestiónEventos — Escáner de puerta (PWA)
 *
 * - Con conexión: cada escaneo lo decide el servidor (evita doble ingreso entre puertas).
 * - Sin conexión: valida la firma HMAC del QR con la clave del evento y el listado
 *   descargado, deja pasar y guarda el ingreso en una cola que se sincroniza sola.
 */
(() => {
    'use strict';

    const API = '/api/scanner';
    const SYNC_EVERY_MS = 15000;
    const RESULT_MS = 2200;

    /* ================================================================ */
    /* IndexedDB                                                         */
    /* ================================================================ */

    const idb = {
        db: null,
        open() {
            if (this.db) return Promise.resolve(this.db);
            return new Promise((resolve, reject) => {
                const req = indexedDB.open('geventos-escaner', 1);
                req.onupgradeneeded = () => {
                    const db = req.result;
                    db.createObjectStore('kv');
                    const t = db.createObjectStore('tickets', { keyPath: ['eventId', 'code'] });
                    t.createIndex('eventId', 'eventId');
                    const q = db.createObjectStore('queue', { keyPath: 'client_uuid' });
                    q.createIndex('eventId', 'eventId');
                };
                req.onsuccess = () => { this.db = req.result; resolve(this.db); };
                req.onerror = () => reject(req.error);
            });
        },
        async tx(store, mode, fn) {
            const db = await this.open();
            return new Promise((resolve, reject) => {
                const tx = db.transaction(store, mode);
                const out = fn(tx.objectStore(store));
                tx.oncomplete = () => resolve(out && 'result' in out ? out.result : out);
                tx.onerror = () => reject(tx.error);
            });
        },
        get(store, key) { return this.tx(store, 'readonly', (s) => s.get(key)); },
        put(store, value, key) { return this.tx(store, 'readwrite', (s) => (key === undefined ? s.put(value) : s.put(value, key))); },
        del(store, key) { return this.tx(store, 'readwrite', (s) => s.delete(key)); },
        byEvent(store, eventId) { return this.tx(store, 'readonly', (s) => s.index('eventId').getAll(eventId)); },
        putMany(store, items) { return this.tx(store, 'readwrite', (s) => { items.forEach((i) => s.put(i)); }); },
        async clearEvent(store, eventId) {
            const keys = await this.tx(store, 'readonly', (s) => s.index('eventId').getAllKeys(eventId));
            return this.tx(store, 'readwrite', (s) => { keys.forEach((k) => s.delete(k)); });
        },
    };

    /* ================================================================ */
    /* Estado                                                            */
    /* ================================================================ */

    const state = {
        auth: null,        // { token, user }
        device: null,
        gate: '',
        forceOffline: false,
        sound: true,
        event: null,       // { id, title, secret, ... , pkgTime, syncTime }
        tickets: new Map(),
        queueCount: 0,
        scanner: null,
        busy: false,
        lastCode: null,
        lastCodeAt: 0,
        syncTimer: null,
        syncing: false,
    };

    const $ = (sel) => document.querySelector(sel);
    const $$ = (sel) => Array.from(document.querySelectorAll(sel));
    const online = () => navigator.onLine && !state.forceOffline;

    function uuid() {
        if (crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = (Math.random() * 16) | 0;
            return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
        });
    }

    const fmtTime = (iso) => {
        if (!iso) return '—';
        const d = new Date(iso);
        return d.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit' }) + ' ' + d.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    };

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    /* ================================================================ */
    /* API                                                               */
    /* ================================================================ */

    async function api(path, { method = 'GET', body = null, timeout = 8000 } = {}) {
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), timeout);
        try {
            const res = await fetch(API + path, {
                method,
                signal: ctrl.signal,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    ...(state.auth ? { 'Authorization': 'Bearer ' + state.auth.token } : {}),
                },
                body: body ? JSON.stringify(body) : null,
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const err = new Error(data.message || ('Error ' + res.status));
                err.status = res.status;
                err.data = data;
                throw err;
            }
            return data;
        } finally {
            clearTimeout(timer);
        }
    }

    function handleAuthError(e) {
        if (e && e.status === 401) {
            toast('La sesión venció. Volvé a ingresar.', 'danger');
            logout(false);
            return true;
        }
        return false;
    }

    /* ================================================================ */
    /* Firma del QR (igual que TicketQrService en el servidor)           */
    /* ================================================================ */

    const QR_RE = /^GE1\.(\d+)\.([A-Z0-9]{6,16})\.(\d+)\.([A-Za-z0-9_-]{22})$/;
    let hmacKey = null;

    async function sign(base) {
        if (!hmacKey) {
            hmacKey = await crypto.subtle.importKey('raw', new TextEncoder().encode(state.event.secret),
                { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']);
        }
        const sig = new Uint8Array(await crypto.subtle.sign('HMAC', hmacKey, new TextEncoder().encode(base)));
        let bin = '';
        sig.forEach((b) => { bin += String.fromCharCode(b); });
        return btoa(bin).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '').substring(0, 22);
    }

    /** Validación local (sin conexión) */
    async function localCheck(raw) {
        let code, version = null;
        const R = (result, message, ticket = null) => ({ result, ok: result === 'ok', message, ticket });

        if (raw.startsWith('GE1.')) {
            const m = raw.match(QR_RE);
            if (!m) return R('invalid', 'QR inválido.');
            if (Number(m[1]) !== state.event.id) return R('wrong_event', 'El QR es de otro evento.');
            if (!window.crypto || !crypto.subtle) return R('invalid', 'Este navegador no puede validar sin conexión (requiere https).');
            if ((await sign(`GE1.${m[1]}.${m[2]}.${m[3]}`)) !== m[4]) return R('invalid', 'QR adulterado o inválido.');
            code = m[2];
            version = Number(m[3]);
        } else if (/^[A-Za-z0-9]{6,16}$/.test(raw)) {
            code = raw.toUpperCase();
        } else {
            return R('invalid', 'No es una entrada de este sistema.');
        }

        const t = state.tickets.get(code);
        if (!t) return R('invalid', 'La entrada no está en el listado. Si se vendió recién, sincronizá.');
        if (version !== null && version !== t.version) return R('invalid', 'QR anulado: la entrada fue transferida y tiene un QR nuevo.', t);
        if (t.status === 'cancelled') return R('cancelled', 'La entrada está anulada.', t);
        if (t.status === 'used') return R('already_used', `Ya ingresó el ${fmtTime(t.checked_in_at)}${t.gate ? ' por ' + t.gate : ''}.`, t);
        return R('ok', 'Ingreso registrado', t);
    }

    /* ================================================================ */
    /* Escaneo                                                           */
    /* ================================================================ */

    async function handleCode(raw) {
        raw = String(raw || '').trim();
        if (!raw || state.busy || !state.event) return;

        // El mismo QR frente a la cámara: se ignora por 4 segundos
        const now = Date.now();
        if (raw === state.lastCode && now - state.lastCodeAt < 4000) return;
        state.lastCode = raw;
        state.lastCodeAt = now;
        state.busy = true;

        const scan = {
            client_uuid: uuid(),
            code: raw,
            scanned_at: new Date().toISOString(),
            gate: state.gate || null,
            device: state.device,
        };

        let res = null;

        if (online()) {
            try {
                res = await api(`/events/${state.event.id}/scan`, { method: 'POST', body: scan, timeout: 4000 });
                if (res.ticket) await saveTicket(res.ticket);
                if (res.stats) updateCounters(res.stats);
            } catch (e) {
                if (handleAuthError(e)) { state.busy = false; return; }
                // Rechazo del servidor (permiso o datos): se muestra. Sin respuesta (red caída): se valida en el celular
                if ([403, 404, 422].includes(e.status)) {
                    res = { result: 'invalid', ok: false, message: e.message };
                }
            }
        }

        if (!res) {
            res = await localCheck(raw);
            res.offline = true;

            // Solo se encolan los ingresos aceptados: lo rechazado en puerta no debe marcarse en el servidor
            if (res.ok) {
                const t = state.tickets.get(res.ticket.code);
                await saveTicket({ ...t, status: 'used', checked_in_at: scan.scanned_at, gate: state.gate || null });
                await idb.put('queue', { ...scan, eventId: state.event.id, ticketCode: res.ticket.code });
                await refreshQueueCount();
                updateCounters();
            }
        }

        showResult(res);
    }

    function showResult(res) {
        const box = $('#result');
        const cls = res.ok ? 'ok' : (res.result === 'already_used' ? 'warn' : 'bad');
        const icons = { ok: 'bi-check-circle-fill', warn: 'bi-exclamation-triangle-fill', bad: 'bi-x-octagon-fill' };
        const titles = { ok: 'ADELANTE', already_used: 'YA INGRESÓ', invalid: 'NO VÁLIDA', wrong_event: 'OTRO EVENTO', cancelled: 'ANULADA' };

        box.className = 'show ' + cls;
        box.querySelector('.icon i').className = 'bi ' + icons[cls];
        box.querySelector('.title').textContent = titles[res.result] || 'NO VÁLIDA';

        const t = res.ticket;
        const seat = box.querySelector('.seat');
        if (t && t.seat && res.ok) { seat.textContent = t.seat; seat.classList.remove('d-none'); } else { seat.classList.add('d-none'); }

        const lines = [];
        if (t && t.type) lines.push(esc(t.type));
        if (t && t.name) lines.push('<strong>' + esc(t.name) + '</strong>' + (t.dni ? ' · DNI ' + esc(t.dni) : ''));
        if (!res.ok || res.result !== 'ok') lines.push(esc(res.message));
        if (res.offline) lines.push('<small>(validado sin conexión)</small>');
        box.querySelector('.detail').innerHTML = lines.join('<br>');

        feedback(res.ok, res.result === 'already_used');

        const close = () => {
            box.className = '';
            state.busy = false;
            box.removeEventListener('click', close);
            clearTimeout(timer);
        };
        const timer = setTimeout(close, res.ok ? RESULT_MS : RESULT_MS + 1500);
        box.addEventListener('click', close);
    }

    let audioCtx = null;
    function feedback(ok, warn) {
        if (!state.sound) return;
        try {
            audioCtx = audioCtx || new (window.AudioContext || window.webkitAudioContext)();
            const beep = (freq, start, dur) => {
                const o = audioCtx.createOscillator();
                const g = audioCtx.createGain();
                o.frequency.value = freq;
                o.type = ok ? 'sine' : 'square';
                g.gain.value = 0.15;
                o.connect(g); g.connect(audioCtx.destination);
                o.start(audioCtx.currentTime + start);
                o.stop(audioCtx.currentTime + start + dur);
            };
            if (ok) { beep(880, 0, 0.12); beep(1320, 0.13, 0.15); }
            else { beep(warn ? 440 : 220, 0, 0.25); beep(warn ? 440 : 220, 0.32, 0.25); }
        } catch (e) { /* sin audio */ }
        if (navigator.vibrate) navigator.vibrate(ok ? 80 : [200, 100, 200]);
    }

    /* ================================================================ */
    /* Datos locales                                                     */
    /* ================================================================ */

    async function saveTicket(t) {
        const item = { ...t, eventId: state.event.id };
        state.tickets.set(t.code, item);
        await idb.put('tickets', item);
    }

    async function refreshQueueCount() {
        const items = state.event ? await idb.byEvent('queue', state.event.id) : [];
        state.queueCount = items.length;
        const pill = $('#queuePill');
        pill.textContent = state.queueCount ? `${state.queueCount} sin sincronizar` : 'Todo sincronizado';
        pill.className = 'pill ' + (state.queueCount ? 'offline' : 'online');
        return items;
    }

    function updateCounters(stats) {
        let total = 0, used = 0;
        if (stats) { total = stats.total; used = stats.used; }
        else {
            state.tickets.forEach((t) => { if (t.status !== 'cancelled') total++; if (t.status === 'used') used++; });
        }
        $('#cUsed').textContent = used.toLocaleString('es-AR');
        $('#cTotal').textContent = total.toLocaleString('es-AR');
        $('#pkgCount').textContent = state.tickets.size.toLocaleString('es-AR');
    }

    /** Descarga el listado completo del evento */
    async function downloadPackage() {
        const pkg = await api(`/events/${state.event.id}/package`, { timeout: 60000 });
        await idb.clearEvent('tickets', state.event.id);
        state.tickets.clear();
        const items = pkg.tickets.map((t) => ({ ...t, eventId: state.event.id }));
        await idb.putMany('tickets', items);
        items.forEach((t) => state.tickets.set(t.code, t));

        state.event = { ...state.event, ...pkg.event, pkgTime: pkg.server_time, syncTime: pkg.server_time };
        hmacKey = null;
        await idb.put('kv', state.event, 'event:' + state.event.id);
        updateCounters(pkg.stats);
        renderSettings();
    }

    /** Sube la cola offline y trae los cambios de otras puertas */
    async function sync() {
        if (!state.event || state.syncing || !online()) return;
        state.syncing = true;
        try {
            const queue = await refreshQueueCount();
            let conflicts = 0;

            for (let i = 0; i < queue.length; i += 200) {
                const chunk = queue.slice(i, i + 200);
                const res = await api(`/events/${state.event.id}/sync`, {
                    method: 'POST',
                    body: { scans: chunk.map(({ eventId, ticketCode, ...s }) => s) },
                    timeout: 30000,
                });
                for (const r of res.results) {
                    if (r.ticket) await saveTicket(r.ticket);
                    if (r.result === 'already_used') conflicts++;
                    await idb.del('queue', r.client_uuid);
                }
                if (res.stats) updateCounters(res.stats);
            }

            if (conflicts) toast(`Atención: ${conflicts} entrada(s) ya habían ingresado por otra puerta.`, 'warning');

            // Cambios hechos en otras puertas o en el panel (ventas nuevas, transferencias, anulaciones)
            const since = state.event.syncTime || state.event.pkgTime;
            const pkg = await api(`/events/${state.event.id}/package?since=${encodeURIComponent(since)}`, { timeout: 30000 });
            const pending = new Set((await idb.byEvent('queue', state.event.id)).map((q) => q.ticketCode));
            for (const t of pkg.tickets) {
                // No se pisa un ingreso local que todavía no se sincronizó
                const local = state.tickets.get(t.code);
                if (local && local.status === 'used' && t.status === 'valid' && pending.has(t.code)) continue;
                await saveTicket(t);
            }
            state.event.syncTime = pkg.server_time;
            await idb.put('kv', state.event, 'event:' + state.event.id);
            if (pkg.stats) updateCounters(pkg.stats);
            renderSettings();
        } catch (e) {
            handleAuthError(e);
        } finally {
            state.syncing = false;
            await refreshQueueCount();
        }
    }

    /* ================================================================ */
    /* Pantallas                                                         */
    /* ================================================================ */

    function show(screen) {
        $$('.screen').forEach((s) => s.classList.add('d-none'));
        $('#screen-' + screen).classList.remove('d-none');
        if (screen !== 'scan') stopCamera();
    }

    function toast(msg, type = 'info') {
        const el = $('#toast');
        el.className = 'alert alert-' + type + ' mb-0 py-2 px-3 small';
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 5000);
    }

    function renderNet() {
        $$('.net-pill').forEach((p) => {
            const on = online();
            p.className = 'pill net-pill ' + (on ? 'online' : 'offline');
            p.innerHTML = on ? '<i class="bi bi-wifi"></i> En línea' : '<i class="bi bi-wifi-off"></i> Sin conexión';
        });
    }

    function renderSettings() {
        if (!state.event) return;
        $('#pkgTime').textContent = fmtTime(state.event.pkgTime);
        $('#syncTime').textContent = fmtTime(state.event.syncTime);
        $('#gateLabel').textContent = state.gate || '—';
    }

    async function showEvents() {
        show('events');
        $('#userName').textContent = state.auth.user.name + ' · ' + state.auth.user.email;
        const list = $('#eventsList');
        list.innerHTML = '<div class="text-soft small">Cargando…</div>';

        const card = (e, downloaded) => `
            <div class="card-dark p-3 event-item" data-id="${e.id}">
                <div class="fw-semibold">${esc(e.title)}</div>
                <div class="small text-soft">${fmtTime(e.starts_at)}${e.venue ? ' · ' + esc(e.venue) : ''}</div>
                <div class="small mt-1">
                    ${e.stats ? `<span class="pill">${e.stats.used} / ${e.stats.total} ingresaron</span>` : ''}
                    ${downloaded ? '<span class="pill online"><i class="bi bi-cloud-check"></i> Descargado</span>' : ''}
                </div>
            </div>`;

        let events = [];
        try {
            if (!navigator.onLine) throw new Error('offline');
            events = (await api('/events')).events;
            $('#eventsOffline').classList.add('d-none');
        } catch (e) {
            if (handleAuthError(e)) return;
            $('#eventsOffline').classList.remove('d-none');
            const saved = (await idb.get('kv', 'events')) || [];
            events = saved;
        }

        if (navigator.onLine && events.length) await idb.put('kv', events, 'events');

        const downloaded = {};
        for (const e of events) downloaded[e.id] = !!(await idb.get('kv', 'event:' + e.id));

        list.innerHTML = events.length
            ? events.map((e) => card(e, downloaded[e.id])).join('')
            : '<div class="text-soft small">No tenés eventos próximos para controlar.</div>';

        list.querySelectorAll('.event-item').forEach((el) => el.addEventListener('click', () => {
            const ev = events.find((x) => x.id === Number(el.dataset.id));
            openEvent(ev);
        }));
    }

    async function openEvent(ev) {
        const saved = await idb.get('kv', 'event:' + ev.id);
        state.event = saved || { id: ev.id, title: ev.title };
        hmacKey = null;
        state.tickets.clear();
        (await idb.byEvent('tickets', ev.id)).forEach((t) => state.tickets.set(t.code, t));

        $('#eventTitle').textContent = ev.title;
        show('scan');
        switchTab('scan');
        updateCounters();
        renderSettings();
        await refreshQueueCount();

        if (!saved || !state.event.secret) {
            if (!navigator.onLine) {
                toast('Este evento no está descargado. Necesitás conexión la primera vez.', 'danger');
                showEvents();
                return;
            }
            toast('Descargando el listado de entradas…', 'info');
            try {
                await downloadPackage();
                toast(`Listo: ${state.tickets.size} entradas descargadas.`, 'success');
            } catch (e) {
                if (!handleAuthError(e)) toast('No se pudo descargar el listado: ' + e.message, 'danger');
                showEvents();
                return;
            }
        } else {
            sync();
        }

        startCamera();
        clearInterval(state.syncTimer);
        state.syncTimer = setInterval(sync, SYNC_EVERY_MS);
    }

    function switchTab(tab) {
        ['scan', 'search', 'settings'].forEach((t) => $('#tab-' + t).classList.toggle('d-none', t !== tab));
        $$('.bottombar button').forEach((b) => b.classList.toggle('active', b.dataset.tab === tab));
        if (tab === 'scan') startCamera(); else stopCamera();
        if (tab === 'search') { $('#searchInput').focus(); renderSearch(); }
    }

    /* ---------------- Cámara ---------------- */

    async function startCamera() {
        if (state.scanner || $('#tab-scan').classList.contains('d-none')) return;
        if (!window.Html5Qrcode) { $('#cameraMsg').textContent = 'No se pudo cargar el lector de QR. Usá el código manual.'; return; }
        try {
            state.scanner = new Html5Qrcode('reader', { verbose: false });
            await state.scanner.start(
                { facingMode: 'environment' },
                { fps: 12, qrbox: (w, h) => { const s = Math.floor(Math.min(w, h) * 0.75); return { width: s, height: s }; } },
                (text) => handleCode(text),
                () => {}
            );
            $('#cameraMsg').textContent = 'Apuntá la cámara al QR de la entrada';
        } catch (e) {
            state.scanner = null;
            $('#cameraMsg').textContent = 'No se pudo abrir la cámara (' + (e.message || e) + '). Revisá los permisos o usá el código manual.';
        }
    }

    async function stopCamera() {
        if (!state.scanner) return;
        try { await state.scanner.stop(); state.scanner.clear(); } catch (e) { /* nada */ }
        state.scanner = null;
    }

    /* ---------------- Búsqueda ---------------- */

    function renderSearch() {
        const q = $('#searchInput').value.trim().toLowerCase();
        const box = $('#searchResults');
        if (q.length < 2) { box.innerHTML = '<div class="text-soft">Escribí al menos 2 letras o números.</div>'; return; }

        const found = [];
        for (const t of state.tickets.values()) {
            if ((t.name || '').toLowerCase().includes(q) || (t.dni || '').includes(q) || t.code.toLowerCase().includes(q)) found.push(t);
            if (found.length >= 50) break;
        }

        const badge = (t) => t.status === 'used'
            ? `<span class="pill offline">Ingresó ${fmtTime(t.checked_in_at)}</span>`
            : t.status === 'cancelled' ? '<span class="pill">Anulada</span>' : '<span class="pill online">Válida</span>';

        box.innerHTML = found.length ? found.map((t) => `
            <div class="ticket-row d-flex justify-content-between align-items-center gap-2">
                <div>
                    <div class="fw-semibold">${esc(t.name || 'Sin nombre')} ${t.dni ? '<span class="text-soft">· DNI ' + esc(t.dni) + '</span>' : ''}</div>
                    <div class="text-soft">${esc(t.type || '')}${t.seat ? ' · ' + esc(t.seat) : ''} · <span class="font-monospace">${t.code}</span></div>
                    <div class="mt-1">${badge(t)}</div>
                </div>
                ${t.status === 'valid' ? `<button class="btn btn-sm btn-success" data-code="${t.code}">Ingresar</button>` : ''}
            </div>`).join('') : '<div class="text-soft">Sin resultados.</div>';

        box.querySelectorAll('button[data-code]').forEach((b) => b.addEventListener('click', async () => {
            await handleCode(b.dataset.code);
            setTimeout(renderSearch, RESULT_MS + 200);
        }));
    }

    /* ---------------- Sesión ---------------- */

    async function logout(callApi = true) {
        stopCamera();
        clearInterval(state.syncTimer);
        if (callApi && navigator.onLine) { try { await api('/logout', { method: 'POST' }); } catch (e) { /* nada */ } }
        state.auth = null;
        await idb.del('kv', 'auth');
        show('login');
    }

    /* ================================================================ */
    /* Inicio                                                            */
    /* ================================================================ */

    async function init() {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw-escaner.js', { scope: '/escaner' }).catch(() => {});
        }

        state.device = (await idb.get('kv', 'device')) || ('PWA-' + uuid().substring(0, 8));
        await idb.put('kv', state.device, 'device');
        const prefs = (await idb.get('kv', 'prefs')) || {};
        state.gate = prefs.gate || '';
        state.forceOffline = !!prefs.forceOffline;
        state.sound = prefs.sound !== false;
        $('#gateInput').value = state.gate;
        $('#forceOffline').checked = state.forceOffline;
        $('#soundOn').checked = state.sound;

        const savePrefs = () => idb.put('kv', { gate: state.gate, forceOffline: state.forceOffline, sound: state.sound }, 'prefs');

        $('#gateInput').addEventListener('change', (e) => { state.gate = e.target.value.trim(); savePrefs(); renderSettings(); });
        $('#forceOffline').addEventListener('change', (e) => { state.forceOffline = e.target.checked; savePrefs(); renderNet(); });
        $('#soundOn').addEventListener('change', (e) => { state.sound = e.target.checked; savePrefs(); });

        $('#loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const f = e.target;
            $('#loginError').textContent = '';
            try {
                const res = await api('/login', { method: 'POST', body: { email: f.email.value, password: f.password.value, device: state.device } });
                state.auth = { token: res.token, user: res.user };
                await idb.put('kv', state.auth, 'auth');
                f.reset();
                showEvents();
            } catch (err) {
                $('#loginError').textContent = (err.data && err.data.errors && Object.values(err.data.errors)[0][0]) || err.message || 'No se pudo ingresar.';
            }
        });

        $('#btnLogout').addEventListener('click', async () => {
            const pending = (await idb.tx('queue', 'readonly', (s) => s.count()));
            if (pending && !confirm(`Hay ${pending} ingresos sin sincronizar. Si salís se van a sincronizar cuando vuelvas a ingresar. ¿Salir?`)) return;
            logout(true);
        });
        $('#btnBack').addEventListener('click', () => { clearInterval(state.syncTimer); state.event = null; showEvents(); });
        $$('.bottombar button').forEach((b) => b.addEventListener('click', () => switchTab(b.dataset.tab)));
        $('#manualForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const code = e.target.code.value.trim().toUpperCase();
            e.target.reset();
            state.lastCode = null;
            handleCode(code);
        });
        $('#searchInput').addEventListener('input', renderSearch);
        $('#btnSyncNow').addEventListener('click', async () => { await sync(); toast('Sincronizado.', 'success'); });
        $('#btnRedownload').addEventListener('click', async () => {
            if (!online()) { toast('Necesitás conexión.', 'danger'); return; }
            await sync();
            try { await downloadPackage(); toast('Listado actualizado.', 'success'); } catch (e) { toast(e.message, 'danger'); }
        });

        window.addEventListener('online', () => { renderNet(); sync(); });
        window.addEventListener('offline', renderNet);
        renderNet();

        state.auth = await idb.get('kv', 'auth');
        if (state.auth) showEvents(); else show('login');
    }

    init();
})();
