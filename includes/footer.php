<?php
require_once __DIR__ . '/../version.php';
?>
        </main> <!-- /app-content -->

        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/styles/footer.css" />
        <footer class="proatam-footer" style="margin-top: auto; width: 100%; z-index: 10;">
            <div class="footer-content">
            <span class="copyright">
                © 2026 PROATAM S.A. DE C.V. - Todos los derechos reservados.
            </span>
            <span class="separator">|</span>
            <span class="update">
                Versión <?= APP_VERSION ?> - Última actualización <?= APP_UPDATE ?>
            </span>
            <span class="separator">|</span>
            <a href="<?= BASE_URL ?>/politicas_privacidad.php" class="footer-link">Aviso de Privacidad</a>
            </div>
        </footer>
        
    </div> <!-- /app-main -->
  </div> <!-- /app-layout -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  
  <!-- Sistema UI + Session Timeout -->
  <script src="<?= BASE_URL ?>/assets/scripts/ui.js"></script>
  <script src="<?= BASE_URL ?>/assets/scripts/session_timeout.js"></script>

  <!-- Layout Scripts -->
  <script>
    const _tip = document.createElement('div');
    _tip.id = 'sidebar-tooltip';
    Object.assign(_tip.style, {
      position: 'fixed', background: '#ffffff', color: '#1e293b',
      fontSize: '0.78rem', fontWeight: '600', whiteSpace: 'nowrap',
      padding: '5px 10px', borderRadius: '7px', pointerEvents: 'none',
      opacity: '0', transition: 'opacity 0.15s ease',
      zIndex: '99999', boxShadow: '0 4px 16px rgba(0,0,0,0.12)',
      border: '1px solid #e2e8f0', fontFamily: 'inherit'
    });
    document.body.appendChild(_tip);

    function toggleSubmenu(e, menuId) {
      e.preventDefault();
      document.getElementById(menuId).classList.toggle('expanded');
    }

    function toggleMobileSidebar() {
      document.getElementById('appSidebar').classList.toggle('show');
    }

    function toggleSidebar() {
      const sidebar = document.getElementById('appSidebar');
      sidebar.classList.toggle('collapsed');
      initFlyouts();
    }

    document.addEventListener('click', (e) => {
      const sidebar = document.getElementById('appSidebar');
      const toggleBtn = document.querySelector('.mobile-toggle');
      if (window.innerWidth <= 991 && sidebar.classList.contains('show')) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('show');
        }
      }
    });

    function initFlyouts() {
      const sidebar = document.getElementById('appSidebar');

      sidebar.querySelectorAll('.has-submenu').forEach(item => {
        if (item._flyoutEnter) item.removeEventListener('mouseenter', item._flyoutEnter);
        item._flyoutEnter = function() {
          if (!sidebar.classList.contains('collapsed')) return;
          const flyout = item.querySelector('.submenu-container');
          if (!flyout) return;
          const rect = item.querySelector('.menu-item').getBoundingClientRect();
          flyout.style.left = (rect.right + 8) + 'px';
          flyout.style.top = rect.top + 'px';
        };
        item.addEventListener('mouseenter', item._flyoutEnter);
      });

      sidebar.querySelectorAll('.menu-item[data-tooltip]').forEach(el => {
        if (el._tipEnter) {
          el.removeEventListener('mouseenter', el._tipEnter);
          el.removeEventListener('mouseleave', el._tipLeave);
        }
        el._tipEnter = function() {
          if (!sidebar.classList.contains('collapsed')) return;
          const rect = el.getBoundingClientRect();
          _tip.textContent = el.dataset.tooltip;
          _tip.style.left = (rect.right + 10) + 'px';
          _tip.style.top = (rect.top + rect.height / 2 - 12) + 'px';
          _tip.style.opacity = '1';
        };
        el._tipLeave = function() { _tip.style.opacity = '0'; };
        el.addEventListener('mouseenter', el._tipEnter);
        el.addEventListener('mouseleave', el._tipLeave);
      });
    }

    document.addEventListener('DOMContentLoaded', initFlyouts);
  </script>
</body>
</html>
