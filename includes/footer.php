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
    function toggleSubmenu(e, menuId) {
      e.preventDefault();
      const menuItem = document.getElementById(menuId);
      const isExpanded = menuItem.classList.contains("expanded");

      // Opcional: Cerrar otros submenús al abrir uno nuevo
      // document.querySelectorAll(".has-submenu").forEach(item => item.classList.remove("expanded"));

      if (!isExpanded) {
        menuItem.classList.add("expanded");
      } else {
        menuItem.classList.remove("expanded");
      }
    }

    function toggleMobileSidebar() {
      const sidebar = document.getElementById('appSidebar');
      sidebar.classList.toggle('show');
    }

    function toggleSidebar() {
      const sidebar = document.getElementById('appSidebar');
      sidebar.classList.toggle('collapsed');
    }

    // Cerrar sidebar móvil al hacer clic fuera
    document.addEventListener('click', (e) => {
      const sidebar = document.getElementById('appSidebar');
      const toggleBtn = document.querySelector('.mobile-toggle');
      if (window.innerWidth <= 991 && sidebar.classList.contains('show')) {
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
          sidebar.classList.remove('show');
        }
      }
    });
  </script>
</body>
</html>
