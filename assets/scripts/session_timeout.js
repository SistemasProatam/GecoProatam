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

        Swal.fire({
            title: '¿Sigues ahí?',
            html: `
                <div class="text-center">
                    <p class="mb-3">Tu sesión se cerrará automáticamente por inactividad en <strong id="countdown">${this.warningSeconds}</strong> segundos.</p>
                    <small class="text-muted">¿Deseas mantener tu sesión activa?</small>
                </div>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, mantener sesión',
            cancelButtonText: 'Cerrar sesión',
            confirmButtonColor: '#0f172a',
            cancelButtonColor: '#d33',
            allowOutsideClick: false,
            allowEscapeKey: false,
            allowEnterKey: true,
            reverseButtons: true,
            timer: this.warningSeconds * 1000,
            timerProgressBar: true,
            didOpen: () => {
                // Contador regresivo
                const timer = Swal.getTimerLeft();
                let secondsLeft = Math.ceil(timer / 1000);
                const countdownEl = document.getElementById('countdown');
                
                const countdownInterval = setInterval(() => {
                    secondsLeft--;
                    if (countdownEl) {
                        countdownEl.textContent = secondsLeft;
                    }
                    if (secondsLeft <= 0) {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            },
            willClose: () => {
                this.isWarningActive = false;
            }
        }).then((result) => {
            if (result.dismiss === Swal.DismissReason.timer) {
                // Tiempo agotado - cerrar sesión
                this.logout();
            } else if (result.dismiss === Swal.DismissReason.cancel) {
                // Usuario eligió cerrar sesión
                this.logout();
            } else if (result.isConfirmed) {
                // Usuario quiere mantener la sesión
                this.extendSession();
            }
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
        fetch('/includes/extend_session.php', {
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
        Swal.fire({
            title: 'Sesión extendida',
            text: 'Tu sesión se ha mantenido activa',
            icon: 'success',
            timer: 2000,
            showConfirmButton: false,
            timerProgressBar: true
        });
    }

    logout() {
        // Limpiar timeouts
        if (this.timeout) clearTimeout(this.timeout);
        if (this.warningTimeout) clearTimeout(this.warningTimeout);
        
        // Mostrar mensaje de cierre por timeout
        Swal.fire({
            title: 'Sesión cerrada',
            text: 'Tu sesión ha expirado por inactividad',
            icon: 'info',
            timer: 3000,
            showConfirmButton: false,
            timerProgressBar: true,
            willClose: () => {
                // Redirigir al logout con razón de timeout
                window.location.href = '". BASE_URL ."/logout.php?reason=timeout';
            }
        });
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