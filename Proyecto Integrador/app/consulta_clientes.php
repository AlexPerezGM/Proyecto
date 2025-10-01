<?php

require_once '../conf/connection.php';

try {
    // Crear conexión
    $connection = new Connection();
    $pdo = $connection->connect();

    // Manejar peticiones AJAX (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_client':
                addClient($pdo);
                break;
            case 'update_client':
                updateClient($pdo);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        exit;
    }

    // Manejar peticiones AJAX (GET)
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'get_client':
                getClient($pdo);
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Acción no válida']);
        }
        exit;
    }

    // Detalles de clientes para modificar (mantener compatibilidad)
    if (isset($_GET['detalle']) && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        
        // Verificar que el ID es válido
        if ($id <= 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'ID de cliente inválido']);
            exit;
        }
        
        $sql = "SELECT c.id_cliente, c.nombre, c.apellido, c.documento_identidad, c.email, c.telefono, 
                       c.edad, c.ocupacion, c.estado, c.direccion, c.fecha_registro,
                       ie.ingresos_mensuales, ie.egresos_mensuales, ie.fuente_ingresos, ie.comprobante_ingresos
                FROM clientes c
                LEFT JOIN ingresos_egresos ie ON c.id_cliente = ie.id_cliente AND ie.activo = TRUE
                WHERE c.id_cliente = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json');
        if ($cliente) {
            echo json_encode($cliente);
        } else {
            echo json_encode(['error' => 'Cliente no encontrado']);
        }
        exit;
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error al procesar la solicitud']);
    exit;
}

/**
 * Función para obtener datos de un cliente específico
 */
function getClient($pdo) {
    header('Content-Type: application/json');
    
    $id = intval($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de cliente inválido']);
        return;
    }
    
    try {
        $sql = "SELECT c.id_cliente, c.nombre, c.apellido, c.documento_identidad, c.email, c.telefono, 
                       c.edad, c.ocupacion, c.estado, c.direccion, c.fecha_registro,
                       ie.ingresos_mensuales, ie.egresos_mensuales, ie.fuente_ingresos, ie.comprobante_ingresos
                FROM clientes c
                LEFT JOIN ingresos_egresos ie ON c.id_cliente = ie.id_cliente AND ie.activo = TRUE
                WHERE c.id_cliente = :id LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($cliente) {
            echo json_encode(['success' => true, 'client' => $cliente]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cliente no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener el cliente']);
    }
}

/**
 * Función para agregar un nuevo cliente
 */
function addClient($pdo) {
    header('Content-Type: application/json');
    
    try {
        // Validar datos requeridos
        $requiredFields = ['nombre', 'apellido', 'edad', 'documento_identidad', 'telefono', 'email', 'direccion', 'ocupacion'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "El campo $field es obligatorio"]);
                return;
            }
        }
        
        // Validar que no exista ya un cliente con la misma cédula
        $checkSql = "SELECT id_cliente FROM clientes WHERE documento_identidad = :cedula";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute(['cedula' => $_POST['documento_identidad']]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un cliente con esta cédula']);
            return;
        }
        
        // Validar email único
        $checkEmailSql = "SELECT id_cliente FROM clientes WHERE email = :email";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->execute(['email' => $_POST['email']]);
        
        if ($checkEmailStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe un cliente con este email']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Insertar cliente
        $sql = "INSERT INTO clientes (nombre, apellido, edad, documento_identidad, telefono, email, direccion, ocupacion, estado, fecha_registro) 
                VALUES (:nombre, :apellido, :edad, :documento_identidad, :telefono, :email, :direccion, :ocupacion, :estado, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $clientData = [
            'nombre' => trim($_POST['nombre']),
            'apellido' => trim($_POST['apellido']),
            'edad' => intval($_POST['edad']),
            'documento_identidad' => trim($_POST['documento_identidad']),
            'telefono' => trim($_POST['telefono']),
            'email' => trim($_POST['email']),
            'direccion' => trim($_POST['direccion']),
            'ocupacion' => trim($_POST['ocupacion']),
            'estado' => $_POST['estado'] ?? 'Activo'
        ];
        
        $stmt->execute($clientData);
        $clientId = $pdo->lastInsertId();
        
        // Insertar información financiera si se proporciona
        if (!empty($_POST['ingresos_mensuales']) || !empty($_POST['egresos_mensuales'])) {
            $ingresosSql = "INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales, activo, fecha_registro) 
                           VALUES (:id_cliente, :ingresos, :egresos, TRUE, NOW())";
            
            $ingresosStmt = $pdo->prepare($ingresosSql);
            $ingresosStmt->execute([
                'id_cliente' => $clientId,
                'ingresos' => floatval($_POST['ingresos_mensuales'] ?? 0),
                'egresos' => floatval($_POST['egresos_mensuales'] ?? 0)
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cliente agregado correctamente']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al agregar el cliente: ' . $e->getMessage()]);
    }
}

/**
 * Función para actualizar un cliente existente
 */
function updateClient($pdo) {
    header('Content-Type: application/json');
    
    try {
        $id = intval($_POST['id_cliente'] ?? 0);
        
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de cliente inválido']);
            return;
        }
        
        // Validar datos requeridos
        $requiredFields = ['nombre', 'apellido', 'edad', 'documento_identidad', 'telefono', 'email', 'direccion', 'ocupacion'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                echo json_encode(['success' => false, 'message' => "El campo $field es obligatorio"]);
                return;
            }
        }
        
        // Validar que no exista otro cliente con la misma cédula
        $checkSql = "SELECT id_cliente FROM clientes WHERE documento_identidad = :cedula AND id_cliente != :id";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute(['cedula' => $_POST['documento_identidad'], 'id' => $id]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otro cliente con esta cédula']);
            return;
        }
        
        // Validar email único
        $checkEmailSql = "SELECT id_cliente FROM clientes WHERE email = :email AND id_cliente != :id";
        $checkEmailStmt = $pdo->prepare($checkEmailSql);
        $checkEmailStmt->execute(['email' => $_POST['email'], 'id' => $id]);
        
        if ($checkEmailStmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Ya existe otro cliente con este email']);
            return;
        }
        
        $pdo->beginTransaction();
        
        // Actualizar cliente
        $sql = "UPDATE clientes SET 
                nombre = :nombre, 
                apellido = :apellido, 
                edad = :edad, 
                documento_identidad = :documento_identidad, 
                telefono = :telefono, 
                email = :email, 
                direccion = :direccion, 
                ocupacion = :ocupacion, 
                estado = :estado 
                WHERE id_cliente = :id";
        
        $stmt = $pdo->prepare($sql);
        $clientData = [
            'id' => $id,
            'nombre' => trim($_POST['nombre']),
            'apellido' => trim($_POST['apellido']),
            'edad' => intval($_POST['edad']),
            'documento_identidad' => trim($_POST['documento_identidad']),
            'telefono' => trim($_POST['telefono']),
            'email' => trim($_POST['email']),
            'direccion' => trim($_POST['direccion']),
            'ocupacion' => trim($_POST['ocupacion']),
            'estado' => $_POST['estado'] ?? 'Activo'
        ];
        
        $stmt->execute($clientData);
        
        // Actualizar o insertar información financiera
        if (!empty($_POST['ingresos_mensuales']) || !empty($_POST['egresos_mensuales'])) {
            // Verificar si ya existe registro de ingresos/egresos
            $checkIngresosSQL = "SELECT id FROM ingresos_egresos WHERE id_cliente = :id_cliente AND activo = TRUE";
            $checkIngresosStmt = $pdo->prepare($checkIngresosSQL);
            $checkIngresosStmt->execute(['id_cliente' => $id]);
            
            if ($checkIngresosStmt->fetch()) {
                // Actualizar registro existente
                $updateIngresosSql = "UPDATE ingresos_egresos SET 
                                     ingresos_mensuales = :ingresos, 
                                     egresos_mensuales = :egresos 
                                     WHERE id_cliente = :id_cliente AND activo = TRUE";
                $updateIngresosStmt = $pdo->prepare($updateIngresosSql);
                $updateIngresosStmt->execute([
                    'id_cliente' => $id,
                    'ingresos' => floatval($_POST['ingresos_mensuales'] ?? 0),
                    'egresos' => floatval($_POST['egresos_mensuales'] ?? 0)
                ]);
            } else {
                // Insertar nuevo registro
                $insertIngresosSql = "INSERT INTO ingresos_egresos (id_cliente, ingresos_mensuales, egresos_mensuales, activo, fecha_registro) 
                                     VALUES (:id_cliente, :ingresos, :egresos, TRUE, NOW())";
                $insertIngresosStmt = $pdo->prepare($insertIngresosSql);
                $insertIngresosStmt->execute([
                    'id_cliente' => $id,
                    'ingresos' => floatval($_POST['ingresos_mensuales'] ?? 0),
                    'egresos' => floatval($_POST['egresos_mensuales'] ?? 0)
                ]);
            }
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cliente actualizado correctamente']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el cliente: ' . $e->getMessage()]);
    }
}

$limite = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limite;

$params = [];
$buscar = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = trim($_GET['search']);

    if (is_numeric($search)) {
        // Buscar por ID
        $buscar = "WHERE id_cliente = :id";
        $params['id'] = $search;
    } else {
        // Buscar por nombre (parcial)
        $buscar = "WHERE nombre LIKE :nombre";
        $params['nombre'] = "%$search%";
    }
}

// --- Total de clientes ---
$countSql = "SELECT COUNT(*) as total FROM clientes $buscar";
if (!empty($params)) {
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
} else {
    $countStmt = $pdo->query($countSql);
}
$totalClients = $countStmt->fetch()['total'];

$totalPages = ($totalClients > 0) ? ceil($totalClients / $limite) : 1;

$sql = "SELECT * FROM clientes $buscar ORDER BY id_cliente LIMIT $offset, $limite";

if (!empty($params)) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt;
} else {
    $result = $pdo->query($sql);
}
?>