# Sistema de Diseño y UI/UX (Proatam)

Este documento explica de forma sencilla cómo está estructurado y cómo funciona el nuevo sistema de diseño refactorizado del proyecto. El objetivo de este sistema es que todas las pantallas se vean iguales, que el código no se repita y que sea fácil crear nuevas vistas a futuro con este mismo sistema.

---

## 1. Organización del CSS (Estilos)

Todo el diseño centralizado vive en la carpeta `assets/styles/core/`.

Los archivos principales que siempre debes incluir en una vista nueva son:

- **`tokens.css`**: Es la base. Aquí están declarados los colores principales (`var(--primary)`, `var(--secondary)`), tipos de letra, tamaños de sombra y márgenes.
- **`layout.css`**: Controla el esqueleto de la página (la barra de navegación, el menú lateral y el contenedor principal).
- **`components.css`**: Contiene los botones (`btn-geco-primary`), tarjetas (`oc-card`), inputs de formularios (`form-control`) y badges de estado (`status-badge`).
- **`modules.css`**: Tiene utilidades genéricas que comparten varios módulos, como el diseño estándar de las tablas (`data-table`), pantallas de carga y tarjetas de estadísticas.

### ¿Cómo aplicar un estilo?
En lugar de escribir CSS nuevo, utiliza las variables de `tokens.css`. Por ejemplo:
```css
/* CORRECTO */
.mi-boton {
    background-color: var(--primary);
    border-radius: var(--radius-md);
}

/* INCORRECTO (No usar colores duros) */
.mi-boton {
    background-color: #113456;
    border-radius: 8px;
}
```

---

## 2. Sistema de Alertas y Modales (UI.js)

Se creó un sistema unificado en JavaScript para manejar todas las alertas, ventanas de confirmación y pantallas de carga. Esto reemplaza el uso manual de `Swal.fire` (SweetAlert) en cada archivo, para que todas las alertas tengan el mismo diseño (no se ha reemplazado por completo).

El archivo que hace la magia es `assets/js/ui.js` y su diseño está en `assets/styles/ui.css`.

### Cómo usarlo

**1. Mensajes Rápidos (Toasts)**
Aparecen en la esquina superior derecha y desaparecen solos.
```javascript
UI.toast.success("El usuario se guardó correctamente.");
UI.toast.error("Ocurrió un error al guardar.");
UI.toast.warning("Faltan campos por llenar.");
```

**2. Confirmaciones (Preguntar antes de borrar o guardar)**
Se usan cuando el usuario va a hacer una acción destructiva o importante.
```javascript
const confirmado = await UI.confirm({
    title: "¿Eliminar orden?",
    text: "Esta acción no se puede deshacer.",
    confirmText: "Sí, eliminar",
    type: "danger" // Puede ser "danger", "warning", o "primary"
});

if (confirmado) {
    // Código para eliminar...
}
```

**3. Modales Personalizados (Alertas con botones personalizados)**
Sirve para mostrar mensajes que bloquean la pantalla hasta que el usuario hace clic.
```javascript
UI.modal({
    title: "¡Éxito!",
    text: "La orden se generó correctamente con el folio OC-001.",
    icon: "success",
    confirmText: "Ver detalle"
}).then(() => {
    window.location.href = "see_oc.php?id=1";
});
```

**4. Pantalla de Carga (Loading)**
Para bloquear la pantalla mientras el servidor procesa algo pesado.
```javascript
UI.loading.show("Guardando información...");

// Cuando termine el proceso:
UI.loading.hide();
```

---

## Detalles a considerar
1. **No insertar CSS en PHP**: Mantén los archivos PHP dedicados solo a lógica, HTML semántico y JS. Se usa la etiqueta `style=""` cuando se requiere, fuera de eso no se usa CSS en PHP.
2. **Reutilizar componentes**: Antes de crear un CSS nuevo, revisa si `components.css` ya tiene una clase que sirva (botones, tablas, tarjetas). 
3. **Usa UI.js para avisos**: No llames a `alert()` de navegador. Usa `UI.toast` o `UI.confirm` para estandarizar la experiencia.
