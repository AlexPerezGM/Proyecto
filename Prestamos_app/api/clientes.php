<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE); exit; }
function ok($data=[]){
    if (isset($data['rows']) && !isset($data['data'])) $data['data'] = $data['rows'];
    $data['ok'] = true;
    out($data);
}
function bad($msg, $code=500){ http_response_code($code); out(['ok'=>false,'error'=>$msg]); }
function s($k,$def=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : (isset($_GET[$k]) ? trim((string)$_GET[$k]) : $def); }
function i($k,$def=0){ $v = $_POST[$k] ?? $_GET[$k] ?? $def; return is_numeric($v) ? (int)$v : (int)$def; }
function fnum_in($k){ $v = $_POST[$k] ?? $_GET[$k] ?? null; return is_numeric($v) ? (float)$v : 0.0; }

function bind_params_safe(mysqli_stmt $st, string $types, array $params): bool {
    if ($types === '' || empty($params)) return true;
    $refs = [];
    foreach ($params as $k=>$v) { $refs[$k] = &$params[$k]; }
    return $st->bind_param($types, ...$refs);
}

function table_has_column(mysqli $conn, string $table, string $column): bool {
    static $cache = [];
    $key = $table.'.'.$column;
    if (isset($cache[$key])) return $cache[$key];
    $rs = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    $cache[$key] = (bool)$rs->num_rows;
    return $cache[$key];
}

if (!function_exists('fnum')) {
    function fnum($v,$d=2){ return number_format((float)$v,$d,'.',','); }
}

function validarCedula($cedula){
    $cedula = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($cedula) !== 11) return false;

    $multiplicadores = [1,2,1,2,1,2,1,2,1,2,1];
    $suma = 0;

    for ($i = 0; $i < 11; $i++){
        $n = $cedula[$i] * $multiplicadores[$i];
        if ($n >=10) $n = ($n % 10) + intdiv($n,10);
        $suma += $n;
    }
    return ($suma % 10) === 0;
}
if (!function_exists('san_nombre_archivo')) {
    function san_nombre_archivo($s) {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', '_', $s);              // espacios -> _
        $s = preg_replace('/[^0-9A-Za-z_\-]/', '', $s);   // quitar caracteres raros
        return $s !== '' ? $s : 'NA';
    }
}

// Helper para sanear partes del nombre de archivo (solo letras, números, _ y -)
if (!function_exists('san_nombre_archivo')) {
    function san_nombre_archivo($s) {
        $s = trim((string)$s);
        $s = preg_replace('/\s+/', '_', $s);              // espacios -> _
        $s = preg_replace('/[^0-9A-Za-z_\-]/', '', $s);   // quitar caracteres raros
        return $s !== '' ? $s : 'NA';
    }
}

/**
 * Subir documentos personales del cliente.
 * Ruta final:
 *   .../uploads/clientes/{CEDULA_LIMPIA}/Documentos personales/NOMBRE_ARCHIVO.ext
 */
function upload_doc_cliente(mysqli $conn) {
    header('Content-Type: application/json; charset=utf-8');

    $id_cliente   = (int)($_POST['id_cliente']   ?? 0);
    $tipo_archivo = (string)($_POST['tipo_archivo'] ?? 'OTRO');

    if ($id_cliente <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'id_cliente inválido']);
        return;
    }
    if (
        !isset($_FILES['archivo']) ||
        ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
    ) {
        echo json_encode(['ok' => false, 'msg' => 'Archivo requerido']);
        return;
    }

    // 1) Datos básicos para nombre de carpeta/archivo.
    //    Pueden venir del formulario o se consultan en BD si no llegan.
    $nombre           = trim((string)($_POST['nombre']           ?? ''));
    $apellido         = trim((string)($_POST['apellido']         ?? ''));
    $numero_documento = trim((string)($_POST['numero_documento'] ?? ''));

    if ($nombre === '' || $apellido === '' || $numero_documento === '') {
        $sql = "SELECT dp.nombre, dp.apellido, di.numero_documento
                FROM cliente c
                JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
                LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
                WHERE c.id_cliente = ? LIMIT 1";
        $st = $conn->prepare($sql);
        $st->bind_param('i', $id_cliente);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row) {
            if ($nombre === '')           $nombre           = (string)($row['nombre'] ?? '');
            if ($apellido === '')         $apellido         = (string)($row['apellido'] ?? '');
            if ($numero_documento === '') $numero_documento = (string)($row['numero_documento'] ?? '');
        }
    }

    // Carpeta del cliente basada en su documento (igual que en pagos/préstamos)
    $docDir = preg_replace('/[^0-9A-Za-z]/', '', $numero_documento);
    if ($docDir === '') {
        $docDir = 'CLI_' . $id_cliente;
    }

    // 2) Construir estructura de carpetas
    // Base: .../uploads/clientes
    $baseDir = __DIR__ . '/../uploads/clientes';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0777, true);
    }

    // Carpeta del cliente: .../uploads/clientes/00300011123
    $clienteDir = $baseDir . DIRECTORY_SEPARATOR . $docDir;
    if (!is_dir($clienteDir)) {
        @mkdir($clienteDir, 0777, true);
    }

    // Subcarpeta "Documentos personales": .../uploads/clientes/00300011123/Documentos personales
    $personalesDir = $clienteDir . DIRECTORY_SEPARATOR . 'Documentos personales';
    if (!is_dir($personalesDir)) {
        @mkdir($personalesDir, 0777, true);
    }

    // 3) Validar extensión y armar nombre de archivo descriptivo
    $orig = (string)($_FILES['archivo']['name'] ?? '');
    $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

    $permitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!in_array($ext, $permitidas, true)) {
        echo json_encode([
            'ok'  => false,
            'msg' => 'Extensión no permitida (solo PDF/JPG/PNG)'
        ]);
        return;
    }

    $tipoSan   = san_nombre_archivo($tipo_archivo);          // CEDULA, PASAPORTE, OTRO
    $nomSan    = san_nombre_archivo($nombre . '_' . $apellido);
    $docSan    = san_nombre_archivo($numero_documento);

    // Ejemplo:
    // CEDULA_Juan_Perez_00300011123_15_20251211_210530.pdf
    $fileOut = sprintf(
        '%s_%s_%s_%d_%s.%s',
        $tipoSan,                 // tipo de documento (ej. CEDULA)
        $nomSan,                  // Juan_Perez
        $docSan,                  // 00300011123
        $id_cliente,              // ID cliente
        date('Ymd_His'),          // timestamp
        $ext                      // pdf
    );

    $destAbs = $personalesDir . DIRECTORY_SEPARATOR . $fileOut;

    if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destAbs)) {
        echo json_encode(['ok' => false, 'msg' => 'No se pudo guardar el archivo en disco']);
        return;
    }

    // 4) (Opcional) Aquí podrías registrar el documento en una tabla de BD si la tienes.

    // 5) Respuesta al frontend
    $pathRel = 'uploads/clientes/' . $docDir . '/Documentos personales/' . $fileOut;

    echo json_encode([
        'ok'        => true,
        'msg'       => 'Documento personal subido correctamente',
        'path'      => $pathRel,
        'id_cliente'=> $id_cliente,
        'archivo'   => $fileOut
    ]);
}



function datos_basicos_cliente(mysqli $conn, int $id_cliente): array {
    $sql = "
        SELECT dp.nombre, dp.apellido, di.numero_documento
        FROM cliente c
        JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
        LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
        WHERE c.id_cliente = ?
        LIMIT 1
    ";
    $st = $conn->prepare($sql);
    bind_params_safe($st, "i", [$id_cliente]);
    $st->execute();
    $row = $st->get_result()->fetch_assoc() ?: [];
    $st->close();

    return [
        'nombre'  => $row['nombre'] ?? 'Cliente',
        'apellido'=> $row['apellido'] ?? (string)$id_cliente,
        'numero'  => $row['numero_documento'] ?? ''
    ];
}

function crearCarpetaCliente(int $id_cliente): string {
    global $conn;

    $info = datos_basicos_cliente($conn, $id_cliente);
    $num = preg_replace('/[^0-9A-Za-z]/', '', $info['numero']);
    if ($num === '') $num = 'CLI_' . $id_cliente;

    $base = __DIR__ . '/../uploads/clientes';
    if (!file_exists($base)) mkdir($base, 0777, true);

    $path = $base . '/' . $num;
    if (!file_exists($path)) mkdir($path, 0777, true);

    return $path;
}

function resolve_id_documentacion_cliente(mysqli $conn, string $tipoCodigo): int {
    static $cache = [];

    $tipoCodigo = strtoupper(trim($tipoCodigo));
    if (isset($cache[$tipoCodigo])) return $cache[$tipoCodigo];
    $cols = [];
    $rs = $conn->query("SHOW COLUMNS FROM cat_documentacion_cliente");
    while ($row = $rs->fetch_assoc()) {
        $field = $row['Field'];
        $type  = strtolower($row['Type']);
        if (strpos($type, 'varchar') !== false || strpos($type, 'text') !== false) {
            if ($field !== 'ruta_documento') {
                $cols[] = $field;
            }
        }
    }
    $patterns = [$tipoCodigo];
    if ($tipoCodigo === 'CEDULA')    $patterns[] = 'CEDULA';
    if ($tipoCodigo === 'PASAPORTE') $patterns[] = 'PASAPORTE';
    if ($tipoCodigo === 'LICENCIA')  $patterns[] = 'LICENCIA';
    if ($tipoCodigo === 'SEGURO')    $patterns[] = 'SEGURO';
    if ($tipoCodigo === 'CONTRATO')  $patterns[] = 'CONTRATO';
    if ($tipoCodigo === 'OTRO')      $patterns[] = 'OTRO';

    $id = 0;

    foreach ($cols as $field) {
        foreach ($patterns as $pat) {
            $pat = strtoupper($pat);
            $sql = "SELECT id_documentacion_cliente FROM cat_documentacion_cliente WHERE UPPER($field) LIKE CONCAT('%', ?, '%') LIMIT 1";
            $st  = $conn->prepare($sql);
            bind_params_safe($st, "s", [$pat]);
            $st->execute();
            $rres = $st->get_result();
            $row = $rres ? $rres->fetch_row() : null;
            $st->close();
            $res = $row && isset($row[0]) ? $row[0] : null;
            if ($res) {
                $id = (int)$res;
                break 2;
            }
        }
    }

    if ($id <= 0) {
        $rs2 = $conn->query("SELECT id_documentacion_cliente FROM cat_documentacion_cliente ORDER BY id_documentacion_cliente LIMIT 1");
        $row2 = $rs2 ? $rs2->fetch_row() : null;
        $id = (int)(($row2 && isset($row2[0])) ? $row2[0] : 1);
    }

    $cache[$tipoCodigo] = $id;
    return $id;
}
try {
    $action = s('action','list');
    if ($action === 'catalogos') {
        $out = ['generos'=>[], 'tipos_documento'=>[]];
        $res = $conn->query("SELECT id_genero, genero FROM cat_genero ORDER BY id_genero");
        while ($r = $res->fetch_assoc()) $out['generos'][] = $r;

        $res = $conn->query("SELECT id_tipo_documento, tipo_documento FROM cat_tipo_documento ORDER BY id_tipo_documento");
        while ($r = $res->fetch_assoc()) $out['tipos_documento'][] = $r;

        ok($out);
    }
    if ($action === 'list' || $action === '') {
        $q     = s('q','');
        $page  = max(1, i('page', 1));
        $size  = max(1, min(100, i('size', (i('limit',10)))));
        $off   = ($page-1) * $size;

        $where = " WHERE 1=1 ";
        $types = "";
        $vals  = [];

        if ($q !== '') {
            $where .= " AND (dp.nombre LIKE ? OR dp.apellido LIKE ? OR di.numero_documento LIKE ?) ";
            $like   = "%$q%";
            $types .= "sss";
            $vals[] = $like; $vals[] = $like; $vals[] = $like;
        }

        $sqlCount = "
            SELECT COUNT(DISTINCT c.id_cliente) AS total
            FROM cliente c
            JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
            LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
            $where
        ";
        $st = $conn->prepare($sqlCount);
        if ($types) bind_params_safe($st, $types, $vals);
        $st->execute();
        $total = (int)$st->get_result()->fetch_assoc()['total'];
        $st->close();

        $sql = "
            SELECT
                c.id_cliente,
                dp.nombre, dp.apellido, dp.creado_en,
                di.numero_documento,
                em.email,
                t.telefono,
                COALESCE((
                    SELECT ce.estado
                    FROM prestamo p
                    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
                    WHERE p.id_cliente=c.id_cliente
                    ORDER BY p.actualizado_en DESC
                    LIMIT 1
                ), 'Inactivo') AS estado_prestamo
            FROM cliente c
            JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
            LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
            LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal=1
            LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
            $where
            ORDER BY dp.creado_en DESC
            LIMIT ? OFFSET ?
        ";
        $st = $conn->prepare($sql);
        $types2 = $types . "ii";
        $vals2  = $vals; $vals2[] = $size; $vals2[] = $off;
        bind_params_safe($st, $types2, $vals2);
        $st->execute();
        $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
        $st->close();

        ok(['rows'=>$rows, 'total'=>$total, 'page'=>$page, 'size'=>$size, 'pages'=>($size>0?ceil($total/$size):1)]);
    }
    if ($action === 'cargar_persona'){
        $doc = s('numero_documento','');
        $tipoDoc = i('id_tipo_documento',0);

        if ($doc === '' || $tipoDoc === 0)
            ok(['found'=>false]);

        $sql = "
            SELECT
                dp.id_datos_persona, dp.nombre, dp.apellido, dp.fecha_nacimiento, dp.genero,
                c.id_cliente,
                em.email, t.telefono, d.ciudad, d.sector, d.calle, d.numero_casa
            FROM documento_identidad di
            JOIN datos_persona dp ON dp.id_datos_persona = di.id_datos_persona
            LEFT JOIN cliente c ON c.id_datos_persona = dp.id_datos_persona
            LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal=1
            LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
            LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
            WHERE di.numero_documento = ? AND di.id_tipo_documento = ?
            LIMIT 1
        ";

        $st = $conn->prepare($sql);
        bind_params_safe($st, "si", [$doc, $tipoDoc]);
        $st->execute();
        $rres = $st->get_result();
        $res = $rres ? $rres->fetch_assoc() : null;
        $st->close();

        if ($res) {
            if (!empty($res['id_cliente'])){
                ok(['found' => true, 'is_client' => true, 'data' => $res]);
            } else{
                ok(['found' => true, 'is_client' => false, 'data' => $res]);
            }
        }else {
            ok(['found' => false]);
        }
    }

    if ($action === 'get') {
        $id = i('id_cliente', 0);
        if ($id <= 0) bad('id_cliente requerido', 400);

        $sql = "
            SELECT
                c.id_cliente,
                dp.id_datos_persona, dp.nombre, dp.apellido, dp.fecha_nacimiento, dp.estado_cliente, dp.creado_en,
                dp.genero, cg.genero AS genero_txt,
                di.numero_documento, td.tipo_documento, di.fecha_emision,
                em.email, t.telefono,
                d.ciudad, d.sector, d.calle, d.numero_casa,
                ie.ingresos_mensuales, ie.egresos_mensuales, fi.fuente,
                oc.ocupacion, oc.empresa,
                COALESCE((
                    SELECT ce.estado
                    FROM prestamo p
                    LEFT JOIN cat_estado_prestamo ce ON ce.id_estado_prestamo=p.id_estado_prestamo
                    WHERE p.id_cliente=c.id_cliente
                    ORDER BY p.actualizado_en DESC
                    LIMIT 1
                ), 'Inactivo') AS estado_prestamo
            FROM cliente c
            JOIN datos_persona dp ON dp.id_datos_persona = c.id_datos_persona
            LEFT JOIN cat_genero cg ON cg.id_genero = dp.genero
            LEFT JOIN documento_identidad di ON di.id_datos_persona = dp.id_datos_persona
            LEFT JOIN cat_tipo_documento td ON td.id_tipo_documento = di.id_tipo_documento
            LEFT JOIN email em ON em.id_datos_persona = dp.id_datos_persona AND em.es_principal=1
            LEFT JOIN telefono t ON t.id_datos_persona = dp.id_datos_persona AND t.es_principal=1
            LEFT JOIN direccion d ON d.id_datos_persona = dp.id_datos_persona
            LEFT JOIN ingresos_egresos ie ON ie.id_cliente = c.id_cliente
            LEFT JOIN fuente_ingreso fi ON fi.id_ingresos_egresos = ie.id_ingresos_egresos
            LEFT JOIN ocupacion oc ON oc.id_datos_persona = dp.id_datos_persona
            WHERE c.id_cliente = ?
            LIMIT 1
        ";
        $st = $conn->prepare($sql);
        bind_params_safe($st, "i", [$id]);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$row) bad('No encontrado', 404);
        ok($row);
    }

    $ing = fnum_in('ingresos_mensuales');
    $egr = fnum_in('egresos_mensuales');
    $valida_ie = function(float $ing, float $egr){
        if ($ing > 0 || $egr > 0) {
            if ($ing < 10000) bad('Ingresos mensuales deben ser ≥ 10,000.', 422);
            if ($ing <= $egr) bad('Ingresos deben ser mayores que egresos.', 422);
        }
    };

    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'upload_doc') {
        upload_doc_cliente($conn);
        exit;
    }

    if ($action === 'validarCedula'){
        $cedula_raw = $_POST['cedula'] ?? '';
        $cedula = preg_replace('/[^0-9]/', '', $cedula_raw);
        if (!validarCedula($cedula)){
            out(['ok' => false, 'msg' => 'Formato de cedula incorrecto' ]);
        }
        ok(['data' => $cedula]);
    }

    if ($action === 'create') {
        $nombre   = s('nombre');
        $apellido = s('apellido');
        $fnac     = s('fecha_nacimiento');
        $genero   = i('genero') ?: null;
        $tipoDoc  = i('id_tipo_documento');
        $numDoc   = s('numero_documento');

        $cedula_raw = $_POST['numero_documento'] ?? '';
        $cedula = preg_replace('/[^0-9]/', '', $cedula_raw);
        if ($tipoDoc === 1){
            if (!validarCedula($cedula)){
                out(['ok' => false, 'msg' => 'Cedula invalida']);
            }
        }
        
        if ($nombre==='' || $apellido==='' || $fnac==='' || !$tipoDoc || $numDoc==='') 
            bad('Campos requeridos incompletos', 422);

        $bday  = new DateTime($fnac);
        $mayor = (new DateTime())->diff($bday)->y >= 18;
        if (!$mayor) bad('El cliente debe ser mayor de edad', 422);

        $valida_ie($ing, $egr);

        $estadoCliente = s('estado_cliente','Activo');
        $tel   = s('telefono');
        $email = s('email');
        $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');
        $fuente = s('fuente_ingresos');
        $ocup   = s('ocupacion'); $emp = s('empresa');

        $id_dp_existente = i('existing_id_dp', 0);

        $conn->begin_transaction();

        try{
            if ($id_dp_existente > 0){
                $id_dp = $id_dp_existente;
                $chk = $conn->query("
                    SELECT id_cliente 
                    FROM cliente 
                    WHERE id_datos_persona = $id_dp              
                ");
                if ($chk->num_rows > 0){
                    throw new Exception('Esta persona ya existe como cliente.');
                }

                $st = $conn->prepare("UPDATE datos_persona 
                    SET estado_cliente = ? 
                    WHERE id_datos_persona = ? ");
                    bind_params_safe($st, "si", [$estadoCliente, $id_dp]);
                    $st->execute();
                    $st->close();

                if ($email !== ''){
                    $conn->query("DELETE FROM email WHERE id_datos_persona=$id_dp");
                    $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) 
                    VALUES (?,?,1)");
                    bind_params_safe($st, "is", [$id_dp,$email]);
                    $st->execute();
                    $st->close();
                }
                if ($tel !== ''){
                    $conn->query("DELETE FROM telefono WHERE id_datos_persona=$id_dp");
                    $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) 
                    VALUES (?,?,1)");
                    bind_params_safe($st, "is", [$id_dp, $tel]);
                    $st->execute();
                    $st->close();
                }
                if ($ciudad !== '' && $sector !== ''){
                    $conn->query("DELETE FROM direccion WHERE id_datos_persona=$id_dp");
                    $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa)
                    VALUES (?,?,?,?,?)");
                    bind_params_safe($st, "isssi", [$id_dp,$ciudad,$sector,$calle,$numCasa]);
                    $st->execute();
                    $st->close();
                }
            } else {
                $st = $conn->prepare("INSERT INTO datos_persona(nombre,apellido,fecha_nacimiento,genero,estado_cliente) 
                VALUES (?,?,?,?,?)");
                bind_params_safe($st, "sssis", [$nombre,$apellido,$fnac,$genero,$estadoCliente]);
                $st->execute();
                $id_dp = $conn->insert_id; 
                $st->close();

                $st = $conn->prepare("
                    INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
                    VALUES (?,?,?,CURDATE())
                    ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
                ");
                bind_params_safe($st, "iis", [$id_dp,$tipoDoc,$numDoc]);
                $st->execute(); 
                $st->close();

                if ($email !== '') {
                    $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) 
                    VALUES (?,?,1)");
                    bind_params_safe($st, "is", [$id_dp,$email]);
                    $st->execute(); 
                    $st->close();
                }
                if ($tel !== '') {
                    $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) 
                    VALUES (?,?,1)");
                    bind_params_safe($st, "is", [$id_dp,$tel]);
                    $st->execute(); 
                    $st->close();
                }
                if ($ciudad !== '' && $sector !== '' && $calle !== '' && $numCasa > 0) {
                    $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa) 
                    VALUES (?,?,?,?,?)");
                    bind_params_safe($st, "isssi", [$id_dp,$ciudad,$sector,$calle,$numCasa]);
                    $st->execute(); 
                    $st->close();
                }
            }

            $st = $conn->prepare("INSERT INTO cliente(id_datos_persona) VALUES (?)");
            bind_params_safe($st, "i", [$id_dp]);
            $st->execute();
            $id_cliente = $conn->insert_id;
            $st->close();

            if ($ing>0 || $egr>0) {
                $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) 
                VALUES (?,?,?)");
                bind_params_safe($st, "idd", [$id_cliente,$ing,$egr]);
                $st->execute(); $id_ie = $conn->insert_id; 
                $st->close();

                if ($fuente!=='') {
                    if (table_has_column($conn, 'fuente_ingreso', 'monto')) {
                        $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) 
                        VALUES (?,?,?)");
                        bind_params_safe($st, "isd", [$id_ie,$fuente,$ing]);
                    } else {
                        $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente) 
                        VALUES (?,?)");
                        bind_params_safe($st, "is", [$id_ie,$fuente]);
                    }
                    $st->execute(); 
                    $st->close();
                }
            }
            if ($ocup!=='' || $emp!=='') {
                $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) 
                VALUES (?,?,?)");
                bind_params_safe($st, "iss", [$id_dp,$ocup,$emp]);
                $st->execute(); 
                $st->close();
            }

            $conn->commit();
            ok(['id_cliente'=>$id_cliente]);
            
        }  catch (Throwable $e){
            $conn->rollback();
            if(strpos($e->getMessage(), 'Entrada duplicada') !== false){
                bad('El número de documento ya está asociado a otro cliente.', 409);
            }
            bad('Error al crear cliente: ' . $e->getMessage(), 500);
        }
    }

    if ($action === 'update') {
        $id = i('id_cliente',0);
        if ($id <= 0) bad('id_cliente requerido', 400);

        $st = $conn->prepare("SELECT id_datos_persona FROM cliente WHERE id_cliente=? LIMIT 1");
        bind_params_safe($st, "i", [$id]); $st->execute();
        $rres = $st->get_result();
        $row = $rres ? $rres->fetch_row() : null;
        $id_dp = (int)(($row && isset($row[0])) ? $row[0] : 0);
        $st->close();
        if (!$id_dp) bad('Cliente inválido', 404);

        $nombre   = s('nombre');
        $apellido = s('apellido');
        $fnac     = s('fecha_nacimiento');
        $genero   = i('genero') ?: null;
        $estadoCliente = s('estado_cliente','');

        $tipoDoc = i('id_tipo_documento'); $numDoc = s('numero_documento');
        $email = s('email'); $tel = s('telefono');

        $ciudad = s('ciudad'); $sector = s('sector'); $calle = s('calle'); $numCasa = i('numero_casa');
        $direccion_alguno   = ($ciudad!=='' || $sector!=='' || $calle!=='' || $numCasa>0);
        $direccion_completa = ($ciudad!=='' && $sector!=='' && $calle!=='' && $numCasa>0);

        $fuente = s('fuente_ingresos');
        $ocup = s('ocupacion'); $emp = s('empresa');

        $valida_ie($ing, $egr);

        $conn->begin_transaction();

        if ($estadoCliente !== '') {
            $st = $conn->prepare("UPDATE datos_persona SET nombre=?, apellido=?, fecha_nacimiento=?, genero=?, estado_cliente=? WHERE id_datos_persona=?");
            bind_params_safe($st,"sssisi",[$nombre,$apellido,$fnac,$genero,$estadoCliente,$id_dp]);
        } else {
            $st = $conn->prepare("UPDATE datos_persona SET nombre=?, apellido=?, fecha_nacimiento=?, genero=? WHERE id_datos_persona=?");
            bind_params_safe($st,"sssii",[$nombre,$apellido,$fnac,$genero,$id_dp]);
        }
        $st->execute(); $st->close();

        if ($tipoDoc && $numDoc!=='') {
            $st = $conn->prepare("
                INSERT INTO documento_identidad(id_datos_persona,id_tipo_documento,numero_documento,fecha_emision)
                VALUES (?,?,?,CURDATE())
                ON DUPLICATE KEY UPDATE id_datos_persona=VALUES(id_datos_persona), id_tipo_documento=VALUES(id_tipo_documento)
            ");
            bind_params_safe($st,"iis",[$id_dp,$tipoDoc,$numDoc]);
            $st->execute(); $st->close();
        }

        if ($email!=='') {
            $conn->query("DELETE FROM email WHERE id_datos_persona=".$id_dp);
            $st = $conn->prepare("INSERT INTO email(id_datos_persona,email,es_principal) VALUES (?,?,1)");
            bind_params_safe($st,"is",[$id_dp,$email]); $st->execute(); $st->close();
        }
        if ($tel!=='') {
            $conn->query("DELETE FROM telefono WHERE id_datos_persona=".$id_dp);
            $st = $conn->prepare("INSERT INTO telefono(id_datos_persona,telefono,es_principal) VALUES (?,?,1)");
            bind_params_safe($st,"is",[$id_dp,$tel]); $st->execute(); $st->close();
        }

        if ($direccion_alguno) {
            if (!$direccion_completa) bad('Para actualizar dirección debes enviar: ciudad, sector, calle y numero_casa.', 422);
            $conn->query("DELETE FROM direccion WHERE id_datos_persona=".$id_dp);
            $st = $conn->prepare("INSERT INTO direccion(id_datos_persona,ciudad,sector,calle,numero_casa) VALUES (?,?,?,?,?)");
            bind_params_safe($st,"isssi",[$id_dp,$ciudad,$sector,$calle,$numCasa]);
            $st->execute(); $st->close();
        }

        if ($ing>0 || $egr>0) {
            $conn->query("DELETE fi FROM fuente_ingreso fi JOIN ingresos_egresos ie ON fi.id_ingresos_egresos=ie.id_ingresos_egresos WHERE ie.id_cliente=".$id);
            $conn->query("DELETE FROM ingresos_egresos WHERE id_cliente=".$id);

            $st = $conn->prepare("INSERT INTO ingresos_egresos(id_cliente,ingresos_mensuales,egresos_mensuales) VALUES (?,?,?)");
            bind_params_safe($st,"idd",[$id,$ing,$egr]); $st->execute(); $id_ie = $conn->insert_id; $st->close();

            if ($fuente!=='') {
                if (table_has_column($conn, 'fuente_ingreso', 'monto')) {
                    $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente,monto) VALUES (?,?,?)");
                    bind_params_safe($st,"isd",[$id_ie,$fuente,$ing]);
                } else {
                    $st = $conn->prepare("INSERT INTO fuente_ingreso(id_ingresos_egresos,fuente) VALUES (?,?)");
                    bind_params_safe($st,"is",[$id_ie,$fuente]);
                }
                $st->execute(); $st->close();
            }
        }

        if ($ocup!=='' || $emp!=='') {
            $conn->query("DELETE FROM ocupacion WHERE id_datos_persona=".$id_dp);
            $st = $conn->prepare("INSERT INTO ocupacion(id_datos_persona,ocupacion,empresa) VALUES (?,?,?)");
            bind_params_safe($st,"iss",[$id_dp,$ocup,$emp]);
            $st->execute(); $st->close();
        }

        $conn->commit();
        ok(['id_cliente'=>$id]);
    }

    if ($action === 'ping') { ok(['pong'=>true]); }

    if ($action === 'delete') {
        $id = i('id_cliente',0);
        if ($id <= 0) bad('id_cliente requerido', 400);

        $st=$conn->prepare("SELECT COUNT(*) FROM prestamo WHERE id_cliente=?");
        bind_params_safe($st,"i",[$id]); $st->execute();
        $rres = $st->get_result();
        $row = $rres ? $rres->fetch_row() : null;
        $t = (int)(($row && isset($row[0])) ? $row[0] : 0);
        $st->close();
        if ($t>0) bad('No se puede eliminar: el cliente tiene préstamos asociados.', 409);

        $st=$conn->prepare("SELECT id_datos_persona FROM cliente WHERE id_cliente=? LIMIT 1");
        bind_params_safe($st,"i",[$id]); $st->execute();
        $rres = $st->get_result();
        $row = $rres ? $rres->fetch_row() : null;
        $id_dp = (int)(($row && isset($row[0])) ? $row[0] : 0);
        $st->close();
        if (!$id_dp) bad('Cliente inválido', 404);

        $conn->begin_transaction();
        $conn->query("DELETE fi FROM fuente_ingreso fi JOIN ingresos_egresos ie ON fi.id_ingresos_egresos=ie.id_ingresos_egresos WHERE ie.id_cliente=".$id);
        $conn->query("DELETE FROM ingresos_egresos WHERE id_cliente=".$id);
        $conn->query("DELETE FROM direccion WHERE id_datos_persona=".$id_dp);
        $conn->query("DELETE FROM email WHERE id_datos_persona=".$id_dp);
        $conn->query("DELETE FROM telefono WHERE id_datos_persona=".$id_dp);
        $conn->query("DELETE FROM ocupacion WHERE id_datos_persona=".$id_dp);
        $conn->query("DELETE FROM documento_identidad WHERE id_datos_persona=".$id_dp);
        $conn->query("DELETE FROM cliente WHERE id_cliente=".$id);
        $conn->query("DELETE FROM datos_persona WHERE id_datos_persona=".$id_dp);
        $conn->commit();

        ok(['deleted'=>true]);
    }

    if ($action === 'upload_doc') {
        $id_cliente = i('id_cliente', 0);
        if ($id_cliente <= 0) bad('id_cliente requerido', 400);

        $tipo = strtoupper(trim($_POST['tipo_archivo'] ?? ''));
        if ($tipo === '') bad('Tipo de documento requerido', 422);

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            bad('No se recibió archivo válido', 400);
        }

        $origName = $_FILES['archivo']['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowed = ['pdf','jpg','jpeg','png'];
        if (!in_array($ext, $allowed, true)) {
            bad('Tipo de archivo no permitido. Solo PDF, JPG o PNG.', 422);
        }

        $path = crearCarpetaCliente($id_cliente);
        $folderName = basename($path);

        $nombreForm   = trim($_POST['nombre'] ?? '');
        $apellidoForm = trim($_POST['apellido'] ?? '');
        $numDocForm   = trim($_POST['numero_documento'] ?? '');

        $info = datos_basicos_cliente($conn, $id_cliente);

        $nombreBase = trim(
            ($nombreForm   !== '' ? $nombreForm   : ($info['nombre']   ?? 'Cliente')) . ' ' .
            ($apellidoForm !== '' ? $apellidoForm : ($info['apellido'] ?? (string)$id_cliente))
        );
        $numeroDoc = $numDocForm !== '' ? $numDocForm : ($info['numero'] ?? '');

        $baseName = $tipo . '_' . $nombreBase . '_' . $numeroDoc;
        $baseName = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$baseName) ?: $baseName;
        $baseName = preg_replace('/[^A-Za-z0-9_]/', '_', $baseName);

        $fileName = $baseName . '_' . time() . '.' . $ext;
        $destino  = $path . '/' . $fileName;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $destino)) {
            bad('Error al mover el archivo subido', 500);
        }

        $rutaRel = "uploads/clientes/{$folderName}/{$fileName}";

        if (table_has_column($conn, 'documentacion_cliente', 'id_cliente') &&
            table_has_column($conn, 'documentacion_cliente', 'ruta_documento')) {

            $tieneTipoCat = table_has_column($conn, 'documentacion_cliente', 'id_documentacion_cliente');

            if ($tieneTipoCat) {
                $idDocCat = resolve_id_documentacion_cliente($conn, $tipo);
                $sqlIns = "
                    INSERT INTO documentacion_cliente (id_cliente, id_documentacion_cliente, ruta_documento)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE ruta_documento = VALUES(ruta_documento)
                ";
                $st = $conn->prepare($sqlIns);
                bind_params_safe($st, "iis", [$id_cliente, $idDocCat, $rutaRel]);
            } else {
                $sqlIns = "
                    INSERT INTO documentacion_cliente (id_cliente, ruta_documento)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE ruta_documento = VALUES(ruta_documento)
                ";
                $st = $conn->prepare($sqlIns);
                bind_params_safe($st, "is", [$id_cliente, $rutaRel]);
            }

            $st->execute();
            $st->close();
        }

        ok([
            'message'   => 'Documento subido correctamente',
            'file'      => $fileName,
            'ruta'      => $rutaRel,
            'carpeta'   => "uploads/clientes/{$folderName}/"
        ]);
    }

    if ($action === 'list_docs') {
        $id = i('id_cliente', 0);
        if ($id <= 0) bad('id_cliente requerido', 400);

        $path = crearCarpetaCliente($id);
        $folderName = basename($path);
        $folderUrl  = "uploads/clientes/{$folderName}/";

        $files = [];
        if (file_exists($path)) {
            foreach (scandir($path) as $f) {
                if ($f !== "." && $f !== "..") {
                    $files[] = [
                        'nombre' => $f,
                        'ruta'   => $folderUrl . $f
                    ];
                }
            }
        }

        ok([
            'files'   => $files,
            'carpeta' => $folderUrl
        ]);
    }

    bad('Acción no soportada', 404);

} catch (Throwable $e) {
    try { if (isset($conn)) $conn->rollback(); } catch (Throwable $__) {}
    bad('Error en la operación: '.$e->getMessage(), 500);
}
