/**
 * UI.JS — Sistema de notificaciones y modales PROATAM
 * API:
 *   UI.toast.success|error|warning|info(msg, [duration])
 *   UI.confirm({ title, message, danger, confirmText, cancelText }) → Promise<bool>
 *   UI.modal({ title, html, size, icon }) → { close }
 *   UI.modal.close()
 *   UI.inline.show(selector|element, msg, type)
 *   UI.inline.clear(selector|element)
 *   UI.loading(msg)
 *   UI.loading.hide()
 *   UI.sessionWarning({ seconds, onExtend, onLogout })
 */
var UI = (() => {
  /* ── ICONS MAP ─────────────────────────────────────────────────── */
  const ICONS = {
    success : 'bi-check-circle-fill',
    error   : 'bi-x-circle-fill',
    warning : 'bi-exclamation-triangle-fill',
    info    : 'bi-info-circle-fill',
    danger  : 'bi-x-circle-fill',
    question: 'bi-question-circle-fill',
  };
  const TITLES = {
    success: 'Éxito',
    error  : 'Error',
    warning: 'Aviso',
    info   : 'Información',
    danger : 'Error',
  };

  /* ── TOAST CONTAINER ───────────────────────────────────────────── */
  let _tc = null;
  function _getToastContainer() {
    if (!_tc) {
      _tc = document.getElementById('ui-toast-container');
      if (!_tc) {
        _tc = document.createElement('div');
        _tc.id = 'ui-toast-container';
        document.body.appendChild(_tc);
      }
    }
    return _tc;
  }

  /* ── TOAST ─────────────────────────────────────────────────────── */
  function _toast(type, msg, duration = 4000) {
    const tc  = _getToastContainer();
    const id  = 'uit_' + Date.now() + Math.random().toString(36).slice(2, 5);
    const icon = ICONS[type] || ICONS.info;
    const title = TITLES[type] || 'Info';

    const el = document.createElement('div');
    el.id = id;
    el.className = `ui-toast ui-${type}`;
    el.innerHTML = `
      <i class="bi ${icon} ui-toast-icon"></i>
      <div class="ui-toast-body">
        <div class="ui-toast-title">${title}</div>
        <div class="ui-toast-msg">${msg}</div>
      </div>
      <button class="ui-toast-close" aria-label="Cerrar">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="ui-toast-progress"></div>`;

    tc.appendChild(el);

    // max 6 toasts
    const all = tc.querySelectorAll('.ui-toast');
    if (all.length > 6) _closeToast(all[0].id);

    // close button
    el.querySelector('.ui-toast-close').onclick = () => _closeToast(id);

    // progress bar animation
    const bar = el.querySelector('.ui-toast-progress');
    bar.style.transition = `transform ${duration}ms linear`;
    requestAnimationFrame(() => {
      requestAnimationFrame(() => { bar.style.transform = 'scaleX(0)'; });
    });

    // auto-close
    const timer = setTimeout(() => _closeToast(id), duration);

    // pause on hover
    el.addEventListener('mouseenter', () => clearTimeout(timer));
    el.addEventListener('mouseleave', () => setTimeout(() => _closeToast(id), 1200));

    return id;
  }

  function _closeToast(id) {
    const el = document.getElementById(id);
    if (!el || el.classList.contains('ui-toast-out')) return;
    el.classList.add('ui-toast-out');
    setTimeout(() => el?.remove(), 290);
  }

  const toast = {
    success : (msg, d) => _toast('success', msg, d),
    error   : (msg, d) => _toast('error',   msg, d),
    warning : (msg, d) => _toast('warning', msg, d),
    info    : (msg, d) => _toast('info',    msg, d),
    danger  : (msg, d) => _toast('error',   msg, d), // alias
  };

  /* ── OVERLAY HELPER ────────────────────────────────────────────── */
  function _createOverlay() {
    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    document.body.appendChild(ov);
    document.body.style.overflow = 'hidden';
    return ov;
  }
  function _removeOverlay(ov, cb) {
    ov.classList.add('ui-overlay-out');
    setTimeout(() => {
      ov?.remove();
      document.body.style.overflow = '';
      cb?.();
    }, 200);
  }

  /* ── CONFIRM ───────────────────────────────────────────────────── */
  function confirm({
    title       = '¿Confirmar acción?',
    message     = '',
    danger      = false,
    confirmText = 'Confirmar',
    cancelText  = 'Cancelar',
    icon        = null,
  } = {}) {
    return new Promise(resolve => {
      const ov = _createOverlay();

      const iconClass = icon
        ? ICONS[icon]
        : danger ? ICONS.danger : ICONS.question;
      const iconColor = danger ? 'ui-danger-icon' : 'ui-info-icon';

      const box = document.createElement('div');
      box.className = 'ui-confirm';
      box.innerHTML = `
        <span class="ui-confirm-icon ${iconColor}">
          <i class="bi ${iconClass}"></i>
        </span>
        <h3>${title}</h3>
        ${message ? `<p>${message}</p>` : ''}
        <div class="ui-confirm-actions">
          <button class="ui-btn-cancel" id="ui-cancel">${cancelText}</button>
          <button class="ui-btn-confirm ${danger ? 'ui-btn-danger' : ''}" id="ui-ok">${confirmText}</button>
        </div>`;

      ov.appendChild(box);

      const done = val => _removeOverlay(ov, () => resolve(val));

      box.querySelector('#ui-ok').onclick     = () => done(true);
      box.querySelector('#ui-cancel').onclick = () => done(false);
      ov.addEventListener('click', e => { if (e.target === ov) done(false); });

      // Keyboard: Enter = confirm, Esc = cancel
      const onKey = e => {
        if (e.key === 'Escape') { done(false); document.removeEventListener('keydown', onKey); }
        if (e.key === 'Enter')  { done(true);  document.removeEventListener('keydown', onKey); }
      };
      document.addEventListener('keydown', onKey);
    });
  }

  /* ── MODAL ─────────────────────────────────────────────────────── */
  let _currentModal = null;

  function _openModal({ title = '', html = '', size = 'md', icon = null, footer = '' } = {}) {
    if (_currentModal) _closeModal();

    const ov = _createOverlay();
    _currentModal = ov;

    const iconHtml = icon
      ? `<i class="bi ${ICONS[icon] || icon} me-2"></i>`
      : '';

    const sizeMap = { sm: 'ui-sm', md: 'ui-md', lg: 'ui-lg', xl: 'ui-xl' };

    const box = document.createElement('div');
    box.className = `ui-modal ${sizeMap[size] || 'ui-md'}`;
    box.innerHTML = `
      <div class="ui-modal-header">
        <div class="ui-modal-title">${iconHtml}${title}</div>
        <button class="ui-modal-close" id="ui-modal-x" aria-label="Cerrar">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="ui-modal-body">${html}</div>
      ${footer ? `<div class="ui-modal-footer">${footer}</div>` : ''}`;

    ov.appendChild(box);
    box.querySelector('#ui-modal-x').onclick = () => _closeModal();
    ov.addEventListener('click', e => { if (e.target === ov) _closeModal(); });

    const onKey = e => {
      if (e.key === 'Escape') { _closeModal(); document.removeEventListener('keydown', onKey); }
    };
    document.addEventListener('keydown', onKey);

    return ov;
  }

  function _closeModal() {
    if (!_currentModal) return;
    _removeOverlay(_currentModal, () => { _currentModal = null; });
  }

  // modal is both function and object with .close()
  const modal     = (opts) => _openModal(opts);
  modal.close     = _closeModal;
  modal.closeAll  = _closeModal;

  /* ── INLINE ALERT ──────────────────────────────────────────────── */
  function _resolve(target) {
    return typeof target === 'string'
      ? document.querySelector(target)
      : target;
  }

  const inline = {
    show(target, msg, type = 'info') {
      const el = _resolve(target);
      if (!el) return;
      // remove previous
      const prev = el.querySelector?.('.ui-inline') ?? (el.classList?.contains('ui-inline') ? el : null);
      if (prev && prev !== el) prev.remove();

      const iconClass = ICONS[type] || ICONS.info;
      const div = document.createElement('div');
      div.className = `ui-inline ui-${type}`;
      div.innerHTML = `<i class="bi ${iconClass}"></i><span>${msg}</span>`;

      // If target is a container, prepend; otherwise insert after
      if (el.children?.length !== undefined) {
        el.insertBefore(div, el.firstChild);
      } else {
        el.after(div);
      }
      return div;
    },
    clear(target) {
      const el = _resolve(target);
      if (!el) return;
      el.querySelectorAll?.('.ui-inline').forEach(n => n.remove());
    },
  };

  /* ── LOADING ───────────────────────────────────────────────────── */
  let _loadingEl = null;

  function _loading(msg = 'Cargando...') {
    if (_loadingEl) { _loadingEl.querySelector('.ui-loading-text').textContent = msg; return; }
    _loadingEl = document.createElement('div');
    _loadingEl.id = 'ui-loading-overlay';
    _loadingEl.innerHTML = `
      <div class="ui-loading-spinner"></div>
      <div class="ui-loading-text">${msg}</div>`;
    document.body.appendChild(_loadingEl);
    document.body.style.overflow = 'hidden';
  }
  _loading.hide = function () {
    if (!_loadingEl) return;
    _loadingEl.style.animation = 'uiOverlayOut 0.2s ease forwards';
    setTimeout(() => {
      _loadingEl?.remove();
      _loadingEl = null;
      document.body.style.overflow = '';
    }, 200);
  };

  /* ── SESSION WARNING ───────────────────────────────────────────── */
  function sessionWarning({ seconds = 60, onExtend = null, onLogout = null } = {}) {
    let remaining = seconds;
    const ov = _createOverlay();

    const box = document.createElement('div');
    box.className = 'ui-session-modal';
    box.innerHTML = `
      <div class="ui-session-icon"><i class="bi bi-shield-exclamation"></i></div>
      <h3>¿Sigues ahí?</h3>
      <p>Tu sesión se cerrará automáticamente por inactividad en:</p>
      <div class="ui-session-countdown" id="ui-sess-count">${remaining}</div>
      <div class="ui-session-progress">
        <div class="ui-session-progress-bar" id="ui-sess-bar" style="width:100%"></div>
      </div>
      <div class="ui-session-actions">
        <button class="ui-btn-cancel" id="ui-sess-logout">
          <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
        </button>
        <button class="ui-btn-confirm" id="ui-sess-extend">
          <i class="bi bi-arrow-clockwise me-1"></i>Mantener sesión
        </button>
      </div>`;

    ov.appendChild(box);

    const countEl = box.querySelector('#ui-sess-count');
    const barEl   = box.querySelector('#ui-sess-bar');

    const interval = setInterval(() => {
      remaining--;
      countEl.textContent = remaining;
      barEl.style.width = `${(remaining / seconds) * 100}%`;
      if (remaining <= 0) { clearInterval(interval); _removeOverlay(ov); onLogout?.(); }
    }, 1000);

    const done = (extend) => {
      clearInterval(interval);
      _removeOverlay(ov);
      if (extend) onExtend?.();
      else onLogout?.();
    };

    box.querySelector('#ui-sess-extend').onclick = () => done(true);
    box.querySelector('#ui-sess-logout').onclick = () => done(false);
  }

  /* ── LOGOUT ────────────────────────────────────────────────────── */
  async function _logout(reason = 'logout') {
    if (reason === 'logout') {
      const ok = await confirm({
        title: '¿Cerrar sesión?',
        message: '¿Estás seguro de que deseas salir del sistema GECO?',
        danger: true,
        confirmText: 'Sí, salir',
        cancelText: 'Cancelar',
        icon: 'logout'
      });
      if (!ok) return;
    }

    const messages = {
      logout: 'Has salido de tu cuenta correctamente.',
      timeout: 'Tu sesión ha expirado por inactividad.',
    };
    const msg = messages[reason] || messages.logout;
    const isTimeout = reason === 'timeout';

    // 1. Mostrar Toast en la página actual
    if (isTimeout) toast.info(msg, 3500);
    else toast.success(msg, 3500);

    // 2. Notificar al servidor en segundo plano
    fetch(`${window.BASE_URL}/logout.php?silent=1`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(err => console.error('Logout error:', err));

    // 3. Redirigir después del toast
    setTimeout(() => {
      window.location.href = `${window.BASE_URL}/login.php`;
    }, 1500); // Reducido un poco para que no se sienta tan lento después de confirmar
  }

  /* ── PUBLIC API ────────────────────────────────────────────────── */
  return { toast, confirm, modal, inline, loading: _loading, sessionWarning, logout: _logout };
})();

// Backward-compat shims so files still calling old names work
// during the migration period (will be removed file-by-file)
if (typeof window !== 'undefined') {
  window.UI = UI;
}
