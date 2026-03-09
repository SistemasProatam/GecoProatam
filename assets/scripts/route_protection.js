// ===== PROTECCIÓN DE RUTAS EN CLIENTE =====
class RouteProtection {
  constructor() {
    this.allowedRoutes = [
      "/dashboard.php",
      "/requisiciones/",
      "/orders/",
      // Agrega aquí todas las rutas permitidas
    ];
    this.init();
  }

  init() {
    this.preventUrlManipulation();
    this.preventBackButton();
    this.setupRouteGuards();
  }

  // Prevenir manipulación de URL
  preventUrlManipulation() {
    let currentPath = window.location.pathname;

    // Verificar si la ruta actual es permitida
    if (!this.isRouteAllowed(currentPath)) {
      this.redirectToLogin("Acceso no autorizado mediante URL");
      return;
    }

    // Monitorear cambios en la URL
    window.addEventListener("popstate", (event) => {
      if (!this.isRouteAllowed(window.location.pathname)) {
        history.pushState(null, null, currentPath);
        this.showWarning("Navegación no permitida");
      }
    });

    // Prevenir que se cargue desde cache
    window.addEventListener("pageshow", (event) => {
      if (event.persisted) {
        window.location.reload();
      }
    });
  }

  // Prevenir uso del botón Atrás
  preventBackButton() {
    history.pushState(null, null, window.location.href);

    window.addEventListener("popstate", () => {
      history.pushState(null, null, window.location.href);
      this.showWarning("No puede navegar hacia atrás");
    });
  }

  // Configurar guards para enlaces
  setupRouteGuards() {
    document.addEventListener("click", (e) => {
      const link = e.target.closest("a");
      if (link && link.href) {
        const href = new URL(link.href).pathname;

        if (!this.isRouteAllowed(href)) {
          e.preventDefault();
          this.showWarning("Acceso no autorizado a esta ruta");
        }
      }
    });
  }

  // Verificar si la ruta está permitida
  isRouteAllowed(path) {
    // Rutas públicas que no requieren verificación
    const publicRoutes = [
      "/login.php",
      "/logout.php",
      "/unauthorized.php",
    ];

    if (publicRoutes.includes(path)) {
      return true;
    }

    // Verificar si la ruta está en las permitidas
    return this.allowedRoutes.some((route) => path.startsWith(route));
  }

  // Mostrar advertencia
  showWarning(message) {
    const warningHtml = `
            <div class="alert alert-warning alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3" style="z-index: 9999;">
                <i class="bi bi-exclamation-triangle"></i> ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;

    document.body.insertAdjacentHTML("beforeend", warningHtml);

    setTimeout(() => {
      const alert = document.querySelector(".alert");
      if (alert) alert.remove();
    }, 5000);
  }

  // Redirigir al login
  redirectToLogin(reason = "") {
    sessionStorage.setItem("redirect_reason", reason);
    window.location.href = "/login.php";
  }

  // Verificar estado de sesión periódicamente
  checkSessionStatus() {
    setInterval(() => {
      // Hacer una petición al servidor para verificar sesión
      fetch("/includes/check_session_status.php")
        .then((response) => response.json())
        .then((data) => {
          if (!data.valid) {
            this.redirectToLogin("Sesión expirada");
          }
        })
        .catch(() => {
          this.redirectToLogin("Error de conexión");
        });
    }, 30000); // Verificar cada 30 segundos
  }
}

// Inicializar protección de rutas
const routeProtection = new RouteProtection();
