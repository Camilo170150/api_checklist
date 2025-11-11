<?php
// test_db.php - endpoint de diagnóstico para verificar conexión a la base de datos
require_once __DIR__ . '/config/database.php';
header('Content-Type: application/json; charset=UTF-8');

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'No se pudo establecer conexión a la base de datos (getConnection devolvió null).']);
        exit;
    }

    // prueba sencilla
    $stmt = $db->query('SELECT 1 AS v');
    $r = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'result' => $r]);
} catch (Exception $e) {
    http_response_code(500);
    // Para debugging mostramos el mensaje; en producción quitar detalles
    echo json_encode(['ok' => false, 'message' => 'Excepción al intentar conectar', 'error' => $e->getMessage()]);
}
