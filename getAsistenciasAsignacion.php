<?php
// getAsistenciasAsignacion.php
// Devuelve las asistencias de una asignación (idasignacion) en JSON.

// CORS + JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Responder preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Convertir cualquier excepción en JSON
set_exception_handler(function ($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
    exit;
});

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/secret.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// --- Validar Authorization: Bearer <token>
$headers = function_exists('apache_request_headers') ? apache_request_headers() : getallheaders();
$authHeader = '';
if (is_array($headers)) {
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
}

if (empty($authHeader) || !preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token faltante']);
    exit;
}

$token = $m[1];
try {
    $decoded = JWT::decode($token, new Key($secret_key, 'HS256'));
    // opcional: podríamos comprobar scopes o idprofesor si se requiere
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido o expirado']);
    exit;
}

// --- Leer y validar parámetro idasignacion
$idasignacion = $_GET['idasignacion'] ?? null;
if ($idasignacion === null || $idasignacion === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Falta el parámetro idasignacion']);
    exit;
}

if (!is_numeric($idasignacion)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'idasignacion debe ser numérico']);
    exit;
}

$idasignacion = (int)$idasignacion;

// --- Conexión PDO
$db = (new Database())->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // Seleccionamos fecha (solo fecha), hora (solo hora) y nombre completo del estudiante.
    $sql = "
        SELECT
            DATE_FORMAT(a.fecha, '%Y-%m-%d') AS fecha,
            DATE_FORMAT(a.fecha, '%H:%i:%s') AS hora,
            CONCAT(TRIM(e.nombre), ' ', TRIM(e.apellido)) AS nombre,
            e.programa AS programa,
            e.idestudiante AS idestudiante,
            e.correoinst AS email
        FROM asistencias a
        INNER JOIN inscripcion i ON a.idinscripcion = i.idinscripcion
        INNER JOIN estudiante e ON i.idestudiante = e.idestudiante
    WHERE i.idasignacion = :idasignacion
        ORDER BY a.fecha DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':idasignacion', $idasignacion, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'idestudiante' => $r['idestudiante'] ?? null,
            'email' => $r['email'] ?? null,
            'nombre' => $r['nombre'],
            'programa' => $r['programa'] ?? null,
            'fecha' => $r['fecha'],
            'hora' => $r['hora'],
        ];
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'count' => count($out), 'asistencias' => $out]);
    exit;
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al consultar las asistencias.', 'error' => $e->getMessage()]);
    exit;
}

?>
