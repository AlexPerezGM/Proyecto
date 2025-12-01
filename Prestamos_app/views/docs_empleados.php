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
  echo "Empleado no encontrado.";
  exit;
}

$nombreCompleto = trim(($cli['nombre'] ?? '') . ' ' . ($cli['apellido'] ?? ''));
$numeroDoc = $cli['numero_documento'] ?? '';

$folderName = preg_replace('/[^0-9A-Za-z]/', '', $numeroDoc);
if ($folderName === '') {
  $folderName = 'EMP_' . $idEmpleado;
}

$folderPath = __DIR__ . '/../uploads/empleados/' . $folderName;
$webFolder  = $BASE_URL . 'uploads/empleados/' . $folderName . '/';

$entries = [];

if (is_dir($folderPath)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($folderPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $fileInfo) {
        $relPath = substr($fileInfo->getPathname(), strlen($folderPath) + 1);
        $relPath = str_replace('\\', '/', $relPath); // normalizar

        if ($fileInfo->isDir()) {
            $entries[] = [
                'type'    => 'dir',
                'name'    => $fileInfo->getFilename(),
                'relPath' => $relPath
            ];
        } else {
            $ext = strtolower(pathinfo($fileInfo->getFilename(), PATHINFO_EXTENSION));
            $relativeWeb = $relPath;
            $url = $webFolder . str_replace('%2F', '/', rawurlencode($relativeWeb));
            $entries[] = [
                'type'    => 'file',
                'name'    => $fileInfo->getFilename(),
                'relPath' => $relPath,
                'ext'     => $ext,
                'url'     => $url
            ];
        }
    }
}

$docsData = [
  'empleado' => [
    'id'               => $idEmpleado,
    'nombre'           => $nombreCompleto,
    'numero_documento' => $numeroDoc,
    'carpeta'          => $folderName,
  ],
  'entries' => $entries,
  'baseUrl' => $webFolder,
];
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

    .doc-search {
      margin-top: 12px;
      margin-bottom: 10px;
    }

    .doc-search-input {
      width: 100%;
      max-width: 100%;
      padding: 8px 10px;
      border-radius: 999px;
      border: 1px solid var(--border-soft);
      font-size: 0.9rem;
      outline: none;
      background: #ffffff;
    }

    .doc-search-input:focus {
      border-color: var(--blue-border);
      box-shadow: 0 0 0 1px var(--blue-border);
    }

    .doc-search small {
      display: block;
      margin-top: 4px;
      font-size: 0.75rem;
      color: var(--text-dim);
    }

    .doc-breadcrumb {
      font-size: 0.8rem;
      color: var(--text-dim);
      margin-bottom: 8px;
    }

    .doc-breadcrumb .crumb {
      cursor: pointer;
      text-decoration: underline;
      text-decoration-style: dotted;
    }

    .doc-breadcrumb .crumb-sep {
      margin: 0 4px;
    }

    .doc-list {
      margin-top: 4px;
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
      background: #f0f4f8;
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

    .doc-name span.badge-dir {
      border-color: var(--grayblue-border);
      background: #e5e7eb;
      color: var(--grayblue-strong);
    }

    .doc-actions {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 8px;
      font-size: 0.8rem;
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

    .doc-location {
      color: var(--text-dim);
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

      .doc-actions {
        justify-content: flex-start;
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
          <span><strong>Carpeta base:</strong> <?= htmlspecialchars($folderName) ?></span>
        </div>
      </div>
    </div>

    <div class="doc-search">
      <input
        id="docSearch"
        class="doc-search-input"
        type="text"
        placeholder="Buscar por nombre de archivo o ruta (ej: seguro, 2025/CONTRATO)...">
      <small>
        El buscador revisa todas las subcarpetas y te indica dónde está cada archivo.
        También puedes navegar manualmente usando la ruta de arriba.
      </small>
    </div>

    <div class="doc-breadcrumb" id="docBreadcrumb"></div>

    <div class="doc-list">
      <div id="docListBody"></div>
    </div>

    <div class="back-link">
      <a href="javascript:window.close()">Cerrar pestaña</a>
    </div>
  </div>

  <script>
    window.DOCS_DATA = <?php echo json_encode($docsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>
  <script>
    (function () {
      const data = window.DOCS_DATA || {};
      const entries = Array.isArray(data.entries) ? data.entries : [];
      const baseUrl = data.baseUrl || '';

      let currentPath = '';
      let searchTerm = '';   

      const $list = document.getElementById('docListBody');
      const $breadcrumb = document.getElementById('docBreadcrumb');
      const $search = document.getElementById('docSearch');

      function normalizePath(p) {
        if (!p) return '';
        return String(p).replace(/\\/g, '/').replace(/^\/+|\/+$/g, '');
      }

      function dirName(relPath) {
        const norm = normalizePath(relPath);
        if (!norm) return '';
        const parts = norm.split('/');
        parts.pop();
        return parts.join('/');
      }

      function escapeHtml(str) {
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#39;');
      }

      function renderBreadcrumb() {
        if (!$breadcrumb) return;
        const parts = currentPath ? currentPath.split('/') : [];
        let html = '<span class="crumb" data-folder="">📁 Raíz</span>';

        let acc = '';
        for (let i = 0; i < parts.length; i++) {
          const p = parts[i];
          if (!p) continue;
          acc = acc ? acc + '/' + p : p;
          html += ' <span class="crumb-sep">/</span> ';
          html += '<span class="crumb" data-folder="' + escapeHtml(acc) + '">' + escapeHtml(p) + '</span>';
        }

        $breadcrumb.innerHTML = html;

        $breadcrumb.querySelectorAll('.crumb').forEach(function (el) {
          el.addEventListener('click', function () {
            const path = this.getAttribute('data-folder') || '';
            currentPath = normalizePath(path);
            searchTerm = '';
            if ($search) $search.value = '';
            renderBreadcrumb();
            renderList();
          });
        });
      }

      function renderList() {
        if (!$list) return;
        const term = (searchTerm || '').toLowerCase();

        let filtered;
        if (term) {
          filtered = entries.filter(function (entry) {
            const name = (entry.name || '').toLowerCase();
            const rel = (entry.relPath || '').toLowerCase();
            return name.includes(term) || rel.includes(term);
          });
        } else {
          filtered = entries.filter(function (entry) {
            return dirName(entry.relPath) === currentPath;
          });
        }

        if (!filtered.length) {
          $list.innerHTML = '<div class="empty-msg">No se encontraron documentos para este criterio.</div>';
          return;
        }

        filtered.sort(function (a, b) {
          if (a.type !== b.type) {
            return a.type === 'dir' ? -1 : 1;
          }
          return (a.name || '').localeCompare(b.name || '');
        });

        const rows = filtered.map(function (entry) {
          const isDir = entry.type === 'dir';
          const nameEsc = escapeHtml(entry.name || '');
          const rel = entry.relPath || '';
          const location = dirName(rel) || 'carpeta raíz';

          if (term) {
            if (isDir) {
              return (
                '<div class="doc-row">' +
                  '<div class="doc-name">' +
                    '<span class="badge badge-dir">DIR</span>' +
                    '<span>' + nameEsc + '</span>' +
                  '</div>' +
                  '<div class="doc-actions">' +
                    '<span class="doc-location">Ubicación: ' + escapeHtml(location) + '</span>' +
                    '<a href="javascript:void(0)" data-folder="' + escapeHtml(rel) + '">📂 Abrir carpeta</a>' +
                  '</div>' +
                '</div>'
              );
            } else {
              const ext = ((entry.ext || '') || 'FILE').toUpperCase();
              const url = entry.url ? entry.url : (baseUrl + encodeURIComponent(rel));
              return (
                '<div class="doc-row">' +
                  '<div class="doc-name">' +
                    '<span class="badge">' + escapeHtml(ext) + '</span>' +
                    '<span>' + nameEsc + '</span>' +
                  '</div>' +
                  '<div class="doc-actions">' +
                    '<span class="doc-location">Ubicación: ' + escapeHtml(location) + '</span>' +
                    '<a href="' + escapeHtml(url) + '" target="_blank">🔍 Ver / descargar</a>' +
                  '</div>' +
                '</div>'
              );
            }
          } else {
            if (isDir) {
              return (
                '<div class="doc-row">' +
                  '<div class="doc-name">' +
                    '<span class="badge badge-dir">DIR</span>' +
                    '<span>' + nameEsc + '</span>' +
                  '</div>' +
                  '<div class="doc-actions">' +
                    '<a href="javascript:void(0)" data-folder="' + escapeHtml(rel) + '">📂 Abrir carpeta</a>' +
                  '</div>' +
                '</div>'
              );
            } else {
              const ext = ((entry.ext || '') || 'FILE').toUpperCase();
              const url = entry.url ? entry.url : (baseUrl + encodeURIComponent(rel));
              return (
                '<div class="doc-row">' +
                  '<div class="doc-name">' +
                    '<span class="badge">' + escapeHtml(ext) + '</span>' +
                    '<span>' + nameEsc + '</span>' +
                  '</div>' +
                  '<div class="doc-actions">' +
                    '<a href="' + escapeHtml(url) + '" target="_blank">🔍 Ver / descargar</a>' +
                  '</div>' +
                '</div>'
              );
            }
          }
        }).join('');

        $list.innerHTML = rows;
      }
      // Buscador
      if ($search) {
        $search.addEventListener('input', function () {
          searchTerm = this.value.trim();
          renderList();
        });
      }

      if ($list) {
        $list.addEventListener('click', function (e) {
          const folderLink = e.target.closest('[data-folder]');
          if (folderLink) {
            const path = folderLink.getAttribute('data-folder') || '';
            currentPath = normalizePath(path);
            searchTerm = '';
            if ($search) $search.value = '';
            renderBreadcrumb();
            renderList();
          }
        });
      }

      renderBreadcrumb();
      renderList();
    })();
  </script>
</body>
</html>