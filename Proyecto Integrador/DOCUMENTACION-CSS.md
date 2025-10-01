# Sistema de Préstamos - Nueva Estructura CSS

## Descripción
Se ha creado un nuevo sistema CSS completamente en español, organizado y modular, basado en el diseño moderno mostrado en las imágenes.

## Archivos CSS Creados

### 1. `estilos-principales.css`
Archivo base con variables CSS, estilos globales y clases principales en español:

**Variables principales:**
- `--color-primario`: Azul principal (#007acc)
- `--color-secundario`: Azul secundario (#005a9e)
- `--color-fondo-principal`: Fondo oscuro (#1e1e1e)
- `--color-exito`: Verde (#4caf50)
- `--color-peligro`: Rojo (#f44336)
- `--color-advertencia`: Amarillo (#ffc107)

**Clases principales:**
- `.cuerpo-pagina` - Estilo del body
- `.cabecera-superior` - Header principal
- `.barra-lateral` - Sidebar
- `.contenido-principal` - Contenido principal
- `.rejilla-estadisticas` - Grid de estadísticas
- `.tarjeta-estadistica` - Tarjetas de métricas

### 2. `estilos-dashboard.css`
Estilos específicos para la página del dashboard:
- Diseño responsive del dashboard
- Estilos del calendario
- Tarjetas de estadísticas con colores específicos
- Grid layout moderno

### 3. `estilos-clientes.css`
Estilos para la gestión de clientes:
- Tabla responsive de clientes
- Formularios modales
- Barra de herramientas
- Estados de clientes
- Paginación moderna

### 4. `estilos-login.css`
Estilos para autenticación:
- Formularios con campos flotantes
- Animaciones de entrada
- Diseño responsive

## Clases CSS Nuevas (en Español)

### Layout Principal
- `.cabecera-superior` - Header fijo superior
- `.barra-lateral` - Sidebar izquierda
- `.contenido-principal` - Área de contenido principal
- `.contenedor-principal` - Contenedor del layout

### Navegación
- `.seccion-navegacion` - Sección de menú
- `.titulo-navegacion` - Títulos de sección
- `.elemento-navegacion` - Enlaces de navegación
- `.icono-navegacion` - Iconos de menú

### Estadísticas y Métricas
- `.rejilla-estadisticas` - Grid de estadísticas
- `.tarjeta-estadistica` - Tarjeta individual
- `.cabecera-estadistica` - Header de tarjeta
- `.titulo-estadistica` - Título de métrica
- `.valor-estadistica` - Valor numérico
- `.cambio-estadistica` - Indicador de cambio

### Formularios
- `.seccion-formulario` - Contenedor de formulario
- `.grupo-formulario` - Grupo de campo
- `.etiqueta-campo` - Label de campo
- `.campo-entrada` - Input de texto
- `.campo-obligatorio` - Campo requerido

### Botones
- `.boton` - Botón base
- `.boton-primario` - Botón principal
- `.boton-secundario` - Botón secundario
- `.boton-exito` - Botón verde
- `.boton-peligro` - Botón rojo

### Tablas
- `.contenedor-tabla-clientes` - Contenedor de tabla
- `.tabla-clientes` - Tabla principal
- `.acciones-fila` - Acciones de fila
- `.estado-cliente` - Badge de estado

### Modales
- `.modal` - Modal base
- `.contenido-modal` - Contenido del modal
- `.cabecera-modal` - Header del modal
- `.cuerpo-modal` - Cuerpo del modal
- `.cerrar-modal` - Botón cerrar

### Utilidades
- `.texto-centrado` - Centrar texto
- `.margen-inferior-1` - Margin bottom
- `.relleno-2` - Padding
- `.oculto` - Display none
- `.flex-centro` - Flex center

## Compatibilidad

Se mantiene compatibilidad con las clases existentes en inglés para evitar errores durante la transición:
- `.container` → `.contenedor-principal`
- `.sidebar` → `.barra-lateral`
- `.main-content` → `.contenido-principal`
- `.header` → `.cabecera-superior`
- `.btn` → `.boton`
- `.table` → `.tabla-principal`

## Responsive Design

Todos los estilos incluyen breakpoints responsivos:
- **Desktop**: > 1200px
- **Tablet**: 768px - 1200px
- **Mobile**: < 768px

## Tema de Colores

El diseño usa un tema oscuro moderno con:
- **Fondo principal**: Gris muy oscuro (#1e1e1e)
- **Fondo secundario**: Gris oscuro (#252526)
- **Acentos**: Azul brillante (#007acc)
- **Texto principal**: Gris claro (#d4d4d4)
- **Bordes**: Gris medio (#3e3e42)

## Archivos Actualizados

### PHP Files
- `dashboard.php` - Actualizado con nuevas clases
- `Views/gestion_clientes.php` - Actualizado con nuevas clases

### JavaScript Files
- `js/calendario_dashboard.js` - Funciones en español manteniendo compatibilidad
- `js/clientes.js` - Funciones actualizadas

## Cómo Usar

1. **Importar los estilos principales:**
```html
<link rel="stylesheet" href="css/estilos-principales.css">
```

2. **Importar estilos específicos según la página:**
```html
<!-- Para dashboard -->
<link rel="stylesheet" href="css/estilos-dashboard.css">

<!-- Para gestión de clientes -->
<link rel="stylesheet" href="css/estilos-clientes.css">

<!-- Para login -->
<link rel="stylesheet" href="css/estilos-login.css">
```

3. **Usar las nuevas clases en español:**
```html
<body class="pagina-dashboard">
    <header class="cabecera-superior">
        <div class="logotipo">Mi Sistema</div>
    </header>
    
    <div class="contenedor-principal">
        <aside class="barra-lateral">
            <nav>
                <div class="seccion-navegacion">
                    <a href="#" class="elemento-navegacion activo">
                        <span class="icono-navegacion">🏠</span>
                        Dashboard
                    </a>
                </div>
            </nav>
        </aside>
        
        <main class="contenido-principal">
            <div class="rejilla-estadisticas">
                <div class="tarjeta-estadistica">
                    <div class="titulo-estadistica">Clientes</div>
                    <div class="valor-estadistica">250</div>
                </div>
            </div>
        </main>
    </div>
</body>
```

## Características Destacadas

### 1. **Variables CSS**
Uso de custom properties para fácil mantenimiento de colores y espaciados.

### 2. **Grid Layout Moderno**
Sistema de rejillas flexibles y responsivas.

### 3. **Animaciones Suaves**
Transiciones CSS para mejor experiencia de usuario.

### 4. **Accesibilidad**
- Colores con suficiente contraste
- Estados de focus visibles
- Soporte para prefers-reduced-motion

### 5. **Compatibilidad**
Mantiene funcionamiento con código existente mientras permite migración gradual.

## Próximos Pasos

1. Continuar actualizando archivos PHP restantes
2. Actualizar archivos JavaScript con funciones en español
3. Crear estilos específicos para otras páginas (préstamos, pagos, reportes)
4. Implementar modo claro/oscuro
5. Agregar más animaciones y microinteracciones

## Notas de Desarrollo

- Todos los nombres de clases están en español
- Se mantiene compatibilidad con clases en inglés
- El diseño es completamente responsive
- Uso intensivo de Flexbox y CSS Grid
- Variables CSS para fácil personalización
- Animations y transitions suaves