<?php
require_once __DIR__ . '/../config/db.php';

$BASE_URL = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$BASE_URL = preg_replace('#/views$#', '', $BASE_URL);
$BASE_URL = ($BASE_URL === '' ? '/' : $BASE_URL . '/');

$idEmpleado = isset($_GET['id_empleado']) ? (int)$_GET['id_empleado'] : 0;
if ($idEmpleado <= 0) {
    http_response_code(400);
    echo "Empleado inválido.";
    exit;
}

// Obtener datos básicos del empleado (nombre + documento)
$sql = "
    SELECT
      e.id_empleado,
      dp.nombre,
      dp.apellido,
      di.numero_documento
    FROM empleado e
    JOIN datos_persona dp ON dp.id_datos_persona = e.id_datos_persona
    LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
    WHERE e.id_empleado = ?
    LIMIT 1
";
$st = $conn->prepare($sql);
$st->bind_param("i", $idEmpleado);
$st->execute();
$cli = $st->get_result()->fetch_assoc();
$st->close();

if (!$cli) {
    http_response_code(404);
    echo "Cliente no encontrado.";
    exit;
}

$nombreCompleto = trim(($cli['nombre'] ?? '') . ' ' . ($cli['apellido'] ?? ''));
$numeroDoc = $cli['numero_documento'] ?? '';

// Construir nombre de carpeta igual que en la API
$folderName = preg_replace('/[^0-9A-Za-z]/', '', $numeroDoc);
if ($folderName === '') {
    $folderName = 'EMP_' . $idEmpleado;
}

$folderPath = __DIR__ . '/../uploads/empleados/' . $folderName;
$webFolder  = $BASE_URL . 'uploads/empleados/' . $folderName . '/';
$files = [];
if (is_dir($folderPath)) {
    foreach (scandir($folderPath) as $f) { 
        if ($f === '.' || $f === '..') continue;
        $files[] = [
            'name' => $f,
            'url'  => $webFolder . $f
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Documentos del empleado</title>
  <link rel="stylesheet" href="<?= $BASE_URL ?>public/css/dashboard.css">
  <style>
    body {
      margin: 0;
      background: var(--bg-page);
      color: var(--text-main);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    .doc-page {
      max-width: 960px;
      margin: 32px auto;
      padding: 20px 24px;
      background: var(--card-bg);
      border-radius: var(--radius);
      border: 1px solid var(--border-soft);
      box-shadow: var(--shadow);
    }

    .doc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 16px;
      gap: 16px;
      flex-wrap: wrap;
    }

    .doc-title {
      font-size: 1.1rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--text-main);
    }

    .doc-title span.icon {
      font-size: 1.4rem;
    }

    .doc-meta {
      font-size: 0.85rem;
      color: var(--text-dim);
    }

    .doc-meta span {
      display: block;
    }

    .doc-list {
      margin-top: 8px;
      border-radius: 10px;
      border: 1px solid var(--border-soft);
      background: #f9fafb;
      overflow: hidden;
    }

    .doc-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 14px;
      border-bottom: 1px solid var(--border-soft);
      background: #ffffff;
    }

    .doc-row:nth-child(odd) {
      background: #f9fafb;
    }

    .doc-row:last-child {
      border-bottom: none;
    }

    .doc-name {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.9rem;
      word-break: break-all;
    }

    .doc-name span.badge {
      font-size: 0.7rem;
      padding: 2px 8px;
      border-radius: 999px;
      border: 1px solid var(--blue-border);
      background: var(--blue-bg);
      color: var(--blue-strong);
    }

    .doc-actions a {
      text-decoration: none;
      font-size: 0.8rem;
      padding: 6px 10px;
      border-radius: 999px;
      border: 1px solid var(--grayblue-border);
      color: var(--grayblue-strong);
      background: #ffffff;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .doc-actions a:hover {
      border-color: var(--blue-border);
      color: var(--blue-strong);
      background: var(--blue-bg);
    }

    .empty-msg {
      padding: 18px;
      text-align: center;
      color: var(--text-dim);
      font-size: 0.9rem;
      background: #f9fafb;
    }

    .back-link {
      margin-top: 16px;
      text-align: right;
    }

    .back-link a {
      text-decoration: none;
      font-size: 0.8rem;
      padding: 6px 12px;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      color: var(--text-main);
      background: #ffffff;
    }

    .back-link a:hover {
      border-color: var(--blue-border);
      color: var(--blue-strong);
      background: var(--blue-bg);
    }

    @media (max-width: 640px) {
      .doc-page {
        margin: 16px;
        padding: 16px;
      }

      .doc-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
      }

      .back-link {
        text-align: left;
      }
    }
  </style>
</head>
<body>
  <div class="doc-page">
    <div class="doc-header">
      <div>
        <div class="doc-title">
          <span class="icon">📂</span>
          <span>Documentos del empleado</span>
        </div>
        <div class="doc-meta">
          <span><strong>Empleado:</strong> <?= htmlspecialchars($nombreCompleto ?: ('ID ' . $idEmpleado)) ?></span>
          <span><strong>Documento:</strong> <?= htmlspecialchars($numeroDoc ?: 'No registrado') ?></span>
          <span><strong>Carpeta:</strong> <?= htmlspecialchars($folderName) ?></span>
        </div>
      </div>
    </div>

    <div class="doc-list">
      <?php if (empty($files)): ?>
        <div class="empty-msg">
          No hay documentos cargados para este empleado.
        </div>
      <?php else: ?>
        <?php foreach ($files as $f): 
          $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        ?>
          <div class="doc-row">
            <div class="doc-name">
              <span class="badge"><?= strtoupper($ext) ?></span>
              <span><?= htmlspecialchars($f['name']) ?></span>
            </div>
            <div class="doc-actions">
              <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank">
                🔍 Ver / descargar
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="back-link">
      <a href="javascript:window.close()">Cerrar pestaña</a>
    </div>
  </div>
</body>
</html>