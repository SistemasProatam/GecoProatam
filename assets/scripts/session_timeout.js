class SessionManager {
    constructor() {
        this.timeoutMinutes = 15; // 15 minutos para mostrar alerta
        this.warningSeconds = 60; // 60 segundos para cerrar después de la alerta
        this.timeout = null;
        this.warningTimeout = null;
        this.isWarningActive = false;
        this.lastActivity = Date.now();
        
        this.init();
    }

    init() {
        // Detectar actividad del usuario
        this.bindEvents();
        
        // Iniciar el temporizador
        this.resetTimer();
        
        // Verificar cada minuto si hay inactividad
        setInterval(() => this.checkInactivity(), 60000);
    }

    bindEvents() {
        // Eventos que indican actividad del usuario
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click', 'input'];
        
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.resetTimer();
                this.lastActivity = Date.now();
            });
        });

        // También detectar actividad en pestañas/ventanas
        window.addEventListener('focus', () => {
            this.resetTimer();
        });
    }

    resetTimer() {
        // Limpiar timeouts existentes
        if (this.timeout) clearTimeout(this.timeout);
        if (this.warningTimeout) clearTimeout(this.warningTimeout);
        
        this.isWarningActive = false;

        // Establecer nuevo timeout para la advertencia (15 minutos)
        this.timeout = setTimeout(() => {
            this.showWarning();
        }, this.timeoutMinutes * 60 * 1000);
    }

    showWarning() {
        if (this.isWarningActive) return;

        this.isWarningActive = true;

        UI.sessionWarning({
            seconds: this.warningSeconds,
            onExtend: () => {
                this.isWarningActive = false;
                this.extendSession();
            },
            onLogout: () => {
                this.isWarningActive = false;
                this.logout();
            },
        });

        // Backup: cerrar sesión si el usuario no responde
        this.warningTimeout = setTimeout(() => {
            if (this.isWarningActive) {
                this.logout();
            }
        }, (this.warningSeconds + 5) * 1000);
    }

    extendSession() {
        // Hacer una petición al servidor para extender la sesión
        fetch(window.BASE_URL + '/includes/extend_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ extend: true })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.resetTimer();
                this.showSuccessMessage();
            }
        })
        .catch(error => {
            console.error('Error extendiendo sesión:', error);
            this.resetTimer(); // Resetear igualmente
        });
    }

    showSuccessMessage() {
        UI.toast.success('Tu sesión se ha mantenido activa', 2500);
    }

    logout() {
        // Limpiar timeouts
        if (this.timeout) clearTimeout(this.timeout);
        if (this.warningTimeout) clearTimeout(this.warningTimeout);

        // Usar el sistema de logout inmersivo de UI
        UI.logout('timeout');
    }

    checkInactivity() {
        // Verificar inactividad cada minuto (backup)
        const currentTime = Date.now();
        const inactiveTime = (currentTime - this.lastActivity) / 1000 / 60; // en minutos
        
        if (inactiveTime >= this.timeoutMinutes && !this.isWarningActive) {
            this.showWarning();
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    window.sessionManager = new SessionManager();
});