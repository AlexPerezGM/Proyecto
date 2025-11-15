<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header('Location: ../Index.php');
    exit;
}

// Verificar permisos de administrador
require_once __DIR__ . '/../config/db.php';

// Calcular la base de la aplicación
$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME']));
$APP_BASE = rtrim(dirname($scriptDir), '/');
if ($APP_BASE === '' || $APP_BASE === false) {
  $APP_BASE = '/';
}
$APP_BASE = $APP_BASE . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Configuración del Sistema - Sistema de Préstamos</title>
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/dashboard.css">
  <link rel="stylesheet" href="<?= $APP_BASE ?>public/css/configuracion.css">
</head>
<body>

<div class="app-shell">
  <!-- SIDEBAR -->
  <aside class="sidebar sidebar-expanded">
    <div class="sidebar-inner">

      <!-- DASHBOARD -->
      <div class="sidebar-section">
        <div class="section-label">DASHBOARD</div>
        <a class="nav-link" href="<?= $APP_BASE ?>views/dashboard.php">
          <span class="nav-icon">🏠</span>
          <span class="nav-text">Dashboard</span>
        </a>
      </div>

      <!-- GESTIÓN -->
      <div class="sidebar-section">
        <div class="section-label">GESTIÓN</div>

        <a class="nav-link" href="<?= $APP_BASE ?>views/clientes.php">
          <span class="nav-icon">👥</span>
          <span class="nav-text">Gestión de Clientes</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/prestamos.php">
          <span class="nav-icon">💼</span>
          <span class="nav-text">Control de Préstamos</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/pagos.php">
          <span class="nav-icon">💰</span>
          <span class="nav-text">Gestión de Pagos</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/seguimiento.php">
          <span class="nav-icon">📈</span>
          <span class="nav-text">Seguimiento de Préstamos</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/reestructuracion.php">
          <span class="nav-icon">♻️</span>
          <span class="nav-text">Reestructuración de Préstamos</span>
        </a>
      </div>

      <!-- ADMINISTRACIÓN -->
      <div class="sidebar-section">
        <div class="section-label">ADMINISTRACIÓN</div>

        <a class="nav-link" href="<?= $APP_BASE ?>views/seguridad.php">
          <span class="nav-icon">🔐</span>
          <span class="nav-text">Usuarios y Roles</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/rrhh.php">
          <span class="nav-icon">🧑</span>
          <span class="nav-text">Recursos Humanos</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>views/campanas_promocion.php">
          <span class="nav-icon">📅</span>
          <span class="nav-text">Campañas de promoción</span>
        </a>

        <a class="nav-link active" href="<?= $APP_BASE ?>views/configuracion.php">
          <span class="nav-icon">⚙️</span>
          <span class="nav-text">Configuración</span>
        </a>

        <a class="nav-link" href="<?= $APP_BASE ?>api/cerrar_sesion.php">
          <span class="nav-icon">🚪</span>
          <span class="nav-text">Cerrar Sesión</span>
        </a>
      </div>
    </div>

    <div class="sidebar-footer">
      <a class="nav-link footer-link" href="<?= $APP_BASE ?>views/perfil.php">
        <span class="nav-icon">👤</span>
        <span class="nav-text">Mi Perfil</span>
      </a>
    </div>
  </aside>

  <div class="main-area">
    <header class="topbar topbar-light">
      <div class="topbar-left">
        <div class="brand-inline">
          <span class="brand-logo">⚙️</span>
          <span class="brand-name">Configuración del Sistema</span>
        </div>
      </div>

      <div class="topbar-right">
        <?php
        $u = $_SESSION['usuario'];
        ?>
        <div class="user-chip">
          <div class="avatar-circle"><?= htmlspecialchars($u['inicial_empleado'] ?? '') ?></div>
          <div class="user-info">
            <div class="user-name"><?= htmlspecialchars($u['nombre_empleado'] ?? $u['nombre_usuario']) ?></div>
            <div class="user-role"><?= htmlspecialchars($u['rol']) ?></div>
          </div>
        </div>
      </div>
    </header>
    <main class="page-wrapper">
      <!-- Navegación por pestañas -->
      <div class="config-tabs">
        <button class="tab-btn active" data-tab="catalogos">📋 Catálogos</button>
        <button class="tab-btn" data-tab="parametros">🔧 Parámetros del Sistema</button>
        <button class="tab-btn" data-tab="auditoria">🔍 Gestión de Auditoría</button>
        <button class="tab-btn" data-tab="fondos">💰 Administración de Fondos y Caja</button>
      </div>
      <!-- Seccion de catalogos -->
      <section id="catalogos" class="config-section active">
        <div class="config-grid">
          <!-- Deducciones -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>📊 Deducciones</h3>
              <button class="btn-primary" onclick="openModal('modalDeduccion')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableDeduccion" class="config-table">
                  <thead>
                    <tr>
                      <th>Tipo de Deducción</th>
                      <th>Valor (%)</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Tipo Monedas -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>💱 Tipo de Monedas</h3>
              <button class="btn-primary" onclick="openModal('modalMoneda')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableMoneda" class="config-table">
                  <thead>
                    <tr>
                      <th>Tipo de Moneda</th>
                      <th>Valor</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Tipo Contrato -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>📄 Tipo de Contrato</h3>
              <button class="btn-primary" onclick="openModal('modalContrato')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableContrato" class="config-table">
                  <thead>
                    <tr>
                      <th>Tipo de Contrato</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Tipo Garantía -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>🛡️ Tipo de Garantía</h3>
              <button class="btn-primary" onclick="openModal('modalGarantia')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableGarantia" class="config-table">
                  <thead>
                    <tr>
                      <th>Tipo de Garantía</th>
                      <th>Descripción</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- Beneficios -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>🎁 Beneficios</h3>
              <button class="btn-primary" onclick="openModal('modalBeneficio')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableBeneficio" class="config-table">
                  <thead>
                    <tr>
                      <th>Tipo de Beneficio</th>
                      <th>Valor</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </section>
      <!-- Seccion parametros del sistema -->
      <section id="parametros" class="config-section">
        <div class="config-grid">
          <!-- Configuración General -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>⚙️ Configuración General</h3>
              <button class="btn-primary" onclick="openModal('modalConfiguracion')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableConfiguracion" class="config-table">
                  <thead>
                    <tr>
                      <th>Parámetro</th>
                      <th>Valor</th>
                      <th>Estado</th>
                      <th>Última Actualización</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Configuración de Mora -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>📈 Configuración de Mora</h3>
              <button class="btn-primary" onclick="openModal('modalMora')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableMora" class="config-table">
                  <thead>
                    <tr>
                      <th>Porcentaje Mora (%)</th>
                      <th>Días de Gracia</th>
                      <th>Vigente Desde</th>
                      <th>Vigente Hasta</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Tipos de Préstamo -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>💼 Tipos de Préstamo</h3>
              <button class="btn-primary" onclick="openModal('modalTipoPrestamo')">Agregar</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableTipoPrestamo" class="config-table">
                  <thead>
                    <tr>
                      <th>Nombre</th>
                      <th>Tasa Interés (%)</th>
                      <th>Monto Mínimo</th>
                      <th>Plazo Mín. (meses)</th>
                      <th>Plazo Máx. (meses)</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </section>
      <!-- Sección auditoría -->
      <section id="auditoria" class="config-section">
        <div class="config-card full-width">
          <div class="config-card-header">
            <h3>🔍 Gestión de Auditoría</h3>
            <div class="audit-filters">
              <select id="filterTabla">
                <option value="">Todas las tablas</option>
                <option value="prestamo">Préstamos</option>
                <option value="pago">Pagos</option>
                <option value="cliente">Clientes</option>
                <option value="cronograma_cuota">Cronograma</option>
              </select>
              <select id="filterTipo">
                <option value="">Todos los tipos</option>
                <option value="INSERT">Creación</option>
                <option value="UPDATE">Actualización</option>
                <option value="DELETE">Eliminación</option>
              </select>
              <input type="date" id="filterFecha">
              <button class="btn-secondary" onclick="filtrarAuditoria()">Filtrar</button>
            </div>
          </div>
          <div class="config-card-body">
            <div class="table-container">
              <table id="tableAuditoria" class="config-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Tabla</th>
                    <th>ID Registro</th>
                    <th>Tipo de Cambio</th>
                    <th>Usuario</th>
                    <th>Acciones</th>
                  </tr>
                </thead>
                <tbody>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>
      <!-- Sección fondos y caja -->
      <section id="fondos" class="config-section">
        <div class="config-grid">
          <!-- Aporte / Inicialización de Fondos -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>💡 Capital Disponible</h3>
            </div>
            <div class="config-card-body">
              <form id="formFondosDirectos">
                <div class="form-group">
                  <label for="montoFondosDirectos">Monto:</label>
                  <input type="number" id="montoFondosDirectos" name="monto" required step="0.01" min="50000" placeholder="50000.00">
                </div>
                <div class="form-group">
                  <button type="submit" id="btnFondosDirectos" class="btn-primary">Inicializar Fondos</button>
                </div>
              </form>
            </div>
          </div>
          <!-- Fondos -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>💰 Gestión de Fondos</h3>
              <button class="btn-primary" onclick="openModal('modalMovimientoFondo')">Movimiento</button>
            </div>
            <div class="config-card-body">
              <div class="fondos-summary">
                <div class="fondo-item">
                  <span class="fondo-label">Cartera Normal:</span>
                  <span id="carteraNormal" class="fondo-value">$0.00</span>
                </div>
                <div class="fondo-item">
                  <span class="fondo-label">Cartera Vencida:</span>
                  <span id="carteraVencida" class="fondo-value">$0.00</span>
                </div>
                <div class="fondo-item">
                  <span class="fondo-label">Total Fondos:</span>
                  <span id="totalFondos" class="fondo-value total">$0.00</span>
                </div>
              </div>
              <div class="table-container">
                <table id="tableFondos" class="config-table">
                  <thead>
                    <tr>
                      <th>Fecha</th>
                      <th>Tipo</th>
                      <th>Monto</th>
                      <th>Caja</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <!-- Cajas -->
          <div class="config-card">
            <div class="config-card-header">
              <h3>🏦 Gestión de Cajas</h3>
              <button class="btn-primary" onclick="openModal('modalCaja')">+ Nueva Caja</button>
            </div>
            <div class="config-card-body">
              <div class="table-container">
                <table id="tableCaja" class="config-table">
                  <thead>
                    <tr>
                      <th>Empleado</th>
                      <th>Saldo Caja</th>
                      <th>Fecha Apertura</th>
                      <th>Estado</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

        </div>
      </section>

    </main>

  </div>
</div>
<!-- POPUP Deducción -->
<div id="modalDeduccion" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleDeduccion">Agregar Deducción</h3>
      <span class="close" onclick="closeModal('modalDeduccion')">&times;</span>
    </div>
    <form id="formDeduccion" onsubmit="saveDeduccion(event)">
      <input type="hidden" id="idDeduccion" name="id">
      <div class="form-group">
        <label for="tipoDeduccion">Tipo de Deducción:</label>
        <input type="text" id="tipoDeduccion" name="tipo_deduccion" required maxlength="50">
      </div>
      <div class="form-group">
        <label for="valorDeduccion">Valor (%):</label>
        <input type="number" id="valorDeduccion" name="valor" required step="0.01" min="0" max="100">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalDeduccion')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Moneda -->
<div id="modalMoneda" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleMoneda">Agregar Tipo de Moneda</h3>
      <span class="close" onclick="closeModal('modalMoneda')">&times;</span>
    </div>
    <form id="formMoneda" onsubmit="saveMoneda(event)">
      <input type="hidden" id="idMoneda" name="id">
      <div class="form-group">
        <label for="tipoMoneda">Tipo de Moneda:</label>
        <input type="text" id="tipoMoneda" name="tipo_moneda" required maxlength="50">
      </div>
      <div class="form-group">
        <label for="valorMoneda">Valor:</label>
        <input type="number" id="valorMoneda" name="valor" required step="0.01" min="0">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalMoneda')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Contrato -->
<div id="modalContrato" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleContrato">Agregar Tipo de Contrato</h3>
      <span class="close" onclick="closeModal('modalContrato')">&times;</span>
    </div>
    <form id="formContrato" onsubmit="saveContrato(event)">
      <input type="hidden" id="idContrato" name="id">
      <div class="form-group">
        <label for="tipoContrato">Tipo de Contrato:</label>
        <input type="text" id="tipoContrato" name="tipo_contrato" required maxlength="50">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalContrato')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Garantía -->
<div id="modalGarantia" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleGarantia">Agregar Tipo de Garantía</h3>
      <span class="close" onclick="closeModal('modalGarantia')">&times;</span>
    </div>
    <form id="formGarantia" onsubmit="saveGarantia(event)">
      <input type="hidden" id="idGarantia" name="id">
      <div class="form-group">
        <label for="tipoGarantia">Tipo de Garantía:</label>
        <input type="text" id="tipoGarantia" name="tipo_garantia" required maxlength="100">
      </div>
      <div class="form-group">
        <label for="descripcionGarantia">Descripción:</label>
        <textarea id="descripcionGarantia" name="descripcion" maxlength="255"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalGarantia')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Beneficios -->
<div id="modalBeneficio" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleBeneficio">Agregar Beneficio</h3>
      <span class="close" onclick="closeModal('modalBeneficio')">&times;</span>
    </div>
    <form id="formBeneficio" onsubmit="saveBeneficio(event)">
      <input type="hidden" id="idBeneficio" name="id">
      <div class="form-group">
        <label for="tipoBeneficio">Tipo de Beneficio:</label>
        <input type="text" id="tipoBeneficio" name="tipo_beneficio" required maxlength="50">
      </div>
      <div class="form-group">
        <label for="valorBeneficio">Valor:</label>
        <input type="number" id="valorBeneficio" name="valor" required step="0.01" min="0">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalBeneficio')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Configuración -->
<div id="modalConfiguracion" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleConfiguracion">Agregar Configuración</h3>
      <span class="close" onclick="closeModal('modalConfiguracion')">&times;</span>
    </div>
    <form id="formConfiguracion" onsubmit="saveConfiguracion(event)">
      <input type="hidden" id="idConfiguracion" name="id">
      <div class="form-group">
        <label for="nombreConfiguracion">Nombre del Parámetro:</label>
        <input type="text" id="nombreConfiguracion" name="nombre_configuracion" required maxlength="100">
      </div>
      <div class="form-group">
        <label for="valorConfiguracion">Valor:</label>
        <input type="number" id="valorConfiguracion" name="valor_decimal" required step="0.01">
      </div>
      <div class="form-group">
        <label for="estadoConfiguracion">Estado:</label>
        <select id="estadoConfiguracion" name="estado" required>
          <option value="Activo">Activo</option>
          <option value="Inactivo">Inactivo</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalConfiguracion')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Mora -->
<div id="modalMora" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleMora">Agregar Configuración de Mora</h3>
      <span class="close" onclick="closeModal('modalMora')">&times;</span>
    </div>
    <form id="formMora" onsubmit="saveMora(event)">
      <input type="hidden" id="idMora" name="id">
      <div class="form-group">
        <label for="porcentajeMora">Porcentaje Mora (%):</label>
        <input type="number" id="porcentajeMora" name="porcentaje_mora" required step="0.01" min="0" max="100">
      </div>
      <div class="form-group">
        <label for="diasGracia">Días de Gracia:</label>
        <input type="number" id="diasGracia" name="dias_gracia" required min="0">
      </div>
      <div class="form-group">
        <label for="vigenteDesde">Vigente Desde:</label>
        <input type="date" id="vigenteDesde" name="vigente_desde" required>
      </div>
      <div class="form-group">
        <label for="vigenteHasta">Vigente Hasta:</label>
        <input type="date" id="vigenteHasta" name="vigente_hasta">
      </div>
      <div class="form-group">
        <label for="estadoMora">Estado:</label>
        <select id="estadoMora" name="estado" required>
          <option value="Activo">Activo</option>
          <option value="Inactivo">Inactivo</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalMora')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Tipo de Préstamo -->
<div id="modalTipoPrestamo" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleTipoPrestamo">Agregar Tipo de Préstamo</h3>
      <span class="close" onclick="closeModal('modalTipoPrestamo')">&times;</span>
    </div>
    <form id="formTipoPrestamo" onsubmit="saveTipoPrestamo(event)">
      <input type="hidden" id="idTipoPrestamo" name="id">
      <div class="form-group">
        <label for="nombreTipoPrestamo">Nombre:</label>
        <input type="text" id="nombreTipoPrestamo" name="nombre" required maxlength="100">
      </div>
      <div class="form-group">
        <label for="tasaInteresTipoPrestamo">Tasa de Interés (%):</label>
        <input type="number" id="tasaInteresTipoPrestamo" name="tasa_interes" required step="0.01" min="0" max="100">
      </div>
      <div class="form-group">
        <label for="montoMinimoTipoPrestamo">Monto Mínimo:</label>
        <input type="number" id="montoMinimoTipoPrestamo" name="monto_minimo" required step="0.01" min="0">
      </div>
      <div class="form-group">
        <label for="tipoAmortizacionTipoPrestamo">Tipo de Amortización:</label>
        <select id="tipoAmortizacionTipoPrestamo" name="id_tipo_amortizacion" required>
          <option value="1">Método Francés</option>
          <option value="2">Método Alemán</option>
          <option value="3">Diferida</option>
        </select>
      </div>
      <div class="form-group">
        <label for="plazoMinimoTipoPrestamo">Plazo Mínimo (meses):</label>
        <input type="number" id="plazoMinimoTipoPrestamo" name="plazo_minimo_meses" required min="1">
      </div>
      <div class="form-group">
        <label for="plazoMaximoTipoPrestamo">Plazo Máximo (meses):</label>
        <input type="number" id="plazoMaximoTipoPrestamo" name="plazo_maximo_meses" required min="1">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalTipoPrestamo')">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Caja -->
<div id="modalCaja" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleCaja">Abrir Nueva Caja</h3>
      <span class="close" onclick="closeModal('modalCaja')">&times;</span>
    </div>
    <form id="formCaja" onsubmit="openCaja(event)">
      <div class="form-group">
        <label for="empleadoCaja">Empleado:</label>
        <select id="empleadoCaja" name="id_empleado" required>
          <option value="">Seleccionar empleado...</option>
        </select>
      </div>
      <div class="form-group">
        <label for="montoAsignadoCaja">Monto Asignado:</label>
        <input type="number" id="montoAsignadoCaja" name="monto_asignado" required step="0.01" min="0">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalCaja')">Cancelar</button>
        <button type="submit" class="btn-primary">Abrir Caja</button>
      </div>
    </form>
  </div>
</div>
<!-- POPUP Movimiento de Fondo -->
<div id="modalMovimientoFondo" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="titleMovimientoFondo">Registrar Movimiento de Fondo</h3>
      <span class="close" onclick="closeModal('modalMovimientoFondo')">&times;</span>
    </div>
    <form id="formMovimientoFondo" onsubmit="saveMovimientoFondo(event)">
      <div class="form-group">
        <label for="tipoMovimientoFondo">Tipo de Movimiento:</label>
        <select id="tipoMovimientoFondo" name="tipo_movimiento" required>
          <option value="">Seleccionar tipo...</option>
          <option value="aporte">Aporte</option>
          <option value="desembolso">Desembolso</option>
        </select>
      </div>
      <div class="form-group">
        <label for="montoMovimientoFondo">Monto:</label>
        <input type="number" id="montoMovimientoFondo" name="monto" required step="0.01" min="0.01">
      </div>
      <div class="form-group">
        <label for="cajaMovimientoFondo">Caja:</label>
        <select id="cajaMovimientoFondo" name="id_caja" required>
          <option value="">Seleccionar caja...</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="closeModal('modalMovimientoFondo')">Cancelar</button>
        <button type="submit" class="btn-primary">Registrar Movimiento</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= $APP_BASE ?>public/js/configuracion.js"></script>
</body>
</html>