<?php
// views/pagos.php
// Ajusta $APP_BASE si ya lo usas globalmente
$APP_BASE = rtrim(preg_replace('#/views$#','', dirname($_SERVER['SCRIPT_NAME'])), '/').'/';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>Gestión de pagos</title>
  <link rel="stylesheet" href="<?= $APP_BASE ?>assets/dashboard.css" />
  <link rel="stylesheet" href="<?= $APP_BASE ?>assets/clientes.css" />
  <link rel="stylesheet" href="<?= $APP_BASE ?>assets/pagos.css" />
  <script>window.APP_BASE = "<?= $APP_BASE ?>";</script>
</head>
<body>
<div class="app-shell">

  <main class="content-area">
    <div class="page-wrapper">
      <h2>Gestión de pagos</h2>
      <div class="card">
        <div class="card-header">Buscar préstamo</div>
        <div class="card-body">
          <div class="list-tools-inner">
            <input id="q" class="input" placeholder="Nombre / Cédula / Contrato" />
            <button id="btnBuscar" class="btn">Buscar</button>
          </div>
          <div class="table-responsive" style="margin-top:10px">
            <table class="table-simple" id="tablaResultados">
              <thead>
                <tr>
                  <th>ID</th><th>Cliente</th><th>Documento</th><th>Estado</th><th></th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div id="errorBox" class="error-box" hidden></div>
        </div>
      </div>

      <div id="panelResumen" class="card" style="margin-top:16px; display:none;">
        <div class="card-header">Resumen del préstamo seleccionado</div>
        <div class="card-body">
          <div class="grid-2">
            <div>
              <p><b>Estado:</b> <span id="p_estado"></span></p>
              <p><b>Saldo pendiente:</b> RD$ <span id="p_saldo"></span></p>
              <p><b>Cuota actual (#<span id="c_num"></span>):</b> vence <span id="c_fecha"></span></p>
              <ul style="margin:0 0 8px 18px;">
                <li>Capital: RD$ <span id="c_capital"></span></li>
                <li>Interés: RD$ <span id="c_interes"></span></li>
                <li>Cargos: RD$ <span id="c_cargos"></span></li>
                <li>Saldo de la cuota: RD$ <span id="c_saldo"></span></li>
              </ul>
            </div>
            <div>
              <p><b>Mora (sugerida):</b> RD$ <span id="p_mora"></span></p>
              <button id="btnAplicarMora" class="btn-light">Recalcular mora</button>
              <p style="margin-top:8px"><b>Total a cobrar hoy (cuota + mora):</b> RD$ <span id="p_total_hoy"></span></p>
            </div>
          </div>

          <div style="display:flex; gap:8px; margin-top:12px;">
            <button id="btnEfectivo" class="btn">Pago en efectivo</button>
            <button id="btnTransfer" class="btn">Pago por transferencia</button>
            <button id="btnGarantia" class="btn-light">Uso de garantía</button>
            <button id="btnCerrar" class="btn-light">Cerrar préstamo</button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- MODAL EFECTIVO -->
<div class="modal" id="modalEfectivo">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Pago en efectivo</h3>
      <button class="modal__close" data-close>✕</button>
    </div>
    <form id="frmEfectivo" class="modal__body grid-2">
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="metodo" value="Efectivo">
      <input type="hidden" name="id_prestamo" id="ef_id_prestamo">
      <div><label>Monto entregado</label><input class="input" required name="monto" type="number" step="0.01"></div>
      <div><label>Moneda</label>
        <select class="input" name="id_tipo_moneda">
          <option value="1">DOP</option>
          <option value="2">USD</option>
        </select>
      </div>
      <div style="grid-column:1/-1;"><label>Observación (opcional)</label><textarea class="input" name="observacion"></textarea></div>
      <div class="modal__footer">
        <button class="btn" type="submit">Registrar pago y generar comprobante</button>
        <button class="btn-light" type="button" data-close>Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL TRANSFER -->
<div class="modal" id="modalTransfer">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Pago por transferencia</h3>
      <button class="modal__close" data-close>✕</button>
    </div>
    <form id="frmTransfer" class="modal__body grid-2">
      <input type="hidden" name="action" value="pay">
      <input type="hidden" name="metodo" value="Transferencia">
      <input type="hidden" name="id_prestamo" id="tr_id_prestamo">
      <div><label>Número de referencia</label><input class="input" required name="referencia"></div>
      <div><label>Monto transferido</label><input class="input" required name="monto" type="number" step="0.01"></div>
      <div><label>Moneda</label>
        <select class="input" name="id_tipo_moneda">
          <option value="1">DOP</option>
          <option value="2">USD</option>
        </select>
      </div>
      <div style="grid-column:1/-1;"><label>Observación (opcional)</label><textarea class="input" name="observacion"></textarea></div>
      <div class="modal__footer">
        <button class="btn" type="submit">Registrar pago y generar comprobante</button>
        <button class="btn-light" type="button" data-close>Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL GARANTÍA -->
<div class="modal" id="modalGarantia">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Uso de garantía</h3>
      <button class="modal__close" data-close>✕</button>
    </div>
    <form id="frmGarantia" class="modal__body grid-2">
      <input type="hidden" name="action" value="garantia">
      <input type="hidden" name="id_prestamo" id="ga_id_prestamo">
      <div><label>ID Garantía (asociada al préstamo)</label><input class="input" required name="id_garantia" type="number"></div>
      <div><label>Monto a usar</label><input class="input" required name="monto" type="number" step="0.01"></div>
      <div style="grid-column:1/-1;"><label>Motivo</label><input class="input" name="motivo"></div>
      <div style="grid-column:1/-1;"><label>Observación (opcional)</label><textarea class="input" name="observacion"></textarea></div>
      <div class="modal__footer">
        <button class="btn" type="submit">Registrar uso y generar comprobante</button>
        <button class="btn-light" type="button" data-close>Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL CIERRE -->
<div class="modal" id="modalCierre">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Cerrar préstamo</h3>
      <button class="modal__close" data-close>✕</button>
    </div>
    <form id="frmCierre" class="modal__body">
      <input type="hidden" name="action" value="close">
      <input type="hidden" name="id_prestamo" id="cl_id_prestamo">
      <label>Observación (opcional)</label>
      <textarea class="input" name="observacion"></textarea>
      <div class="modal__footer">
        <button class="btn" type="submit">Validar y cerrar</button>
        <button class="btn-light" type="button" data-close>Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL COMPROBANTE -->
<div class="modal" id="modalComprobante">
  <div class="modal__dialog">
    <div class="modal__header">
      <h3>Comprobante</h3>
      <button class="modal__close" data-close>✕</button>
    </div>
    <div class="modal__body" id="compContenido"></div>
    <div class="modal__footer">
      <button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button>
      <button class="btn-light" data-close>Cerrar</button>
    </div>
  </div>
</div>

<script src="<?= $APP_BASE ?>assets/pagos.js"></script>
</body>
</html>
