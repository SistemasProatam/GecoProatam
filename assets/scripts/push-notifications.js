// En push-notifications.js
document.addEventListener('DOMContentLoaded', function() {
    // Obtener user_id del data attribute del body
    const userId = document.body.getAttribute('data-user-id');
    
    if (userId) {
        const pushManager = new PushNotificationManager();
        pushManager.initialize(parseInt(userId)).then(success => {
            if (success) {
                console.log('Notificaciones push configuradas');
            }
        });
    }
});