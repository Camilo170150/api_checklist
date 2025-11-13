<?php
// registrar_asistencia.php
date_default_timezone_set('America/Bogota');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// usar las variables EXACTAS que tienes en Railway
$servername = getenv('DB_HOST') ?: 'localhost';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$dbname = getenv('DB_NAME') ?: 'railway';
$port = getenv('DB_PORT') ?: 3306;

$conn = new mysqli($servername, $username, $password, $dbname, (int) $port);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido. Usa POST.']);
    $conn->close();
    exit;
}

$uid = $_POST['uid'] ?? null;
if (!$uid) {
    $data = json_decode(file_get_contents("php://input"), true);
    $uid = $data['uid'] ?? null;
}

if (!$uid) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No se recibió el UID.']);
    $conn->close();
    exit;
}

$uid = $conn->real_escape_string(trim($uid));

$sql = "SELECT idestudiante FROM rfid_map WHERE uid = '$uid' LIMIT 1";
$res = $conn->query($sql);
if (!$res || $res->num_rows == 0) {
    echo json_encode(['status' => 'not_found', 'message' => 'Tarjeta no registrada en el sistema.']);
    $conn->close();
    exit;
}

$row = $res->fetch_assoc();
$idestudiante = $row['idestudiante'];

$dia_semana = date('N');
$hora_actual = date('H:i:s');
$fecha_actual = date('Y-m-d');

$sql_h = "
SELECT i.idinscripcion, m.nombremateria
FROM horario h
INNER JOIN asignacion a ON h.idasignacion = a.idasignacion
INNER JOIN inscripcion i ON i.idasignacion = a.idasignacion
INNER JOIN materias m ON a.idmateria = m.idmateria
WHERE i.idestudiante = '$idestudiante'
    AND h.dia_semana = $dia_semana
    AND h.hora_inicio <= '$hora_actual'
    AND h.hora_fin >= '$hora_actual'
    AND '$fecha_actual' BETWEEN h.fecha_inicio AND h.fecha_fin
LIMIT 1
";

$res_h = $conn->query($sql_h);
if (!$res_h || $res_h->num_rows == 0) {
    echo json_encode(['status' => 'no_class', 'message' => 'No hay clase activa para este estudiante.']);
    $conn->close();
    exit;
}

$rowh = $res_h->fetch_assoc();
$idinscripcion = $rowh['idinscripcion'];
$materia = $rowh['nombremateria'];
$fecha = date("Y-m-d H:i:s");

$sql_chk = "
SELECT 1 
FROM asistencias a
INNER JOIN inscripcion i ON a.idinscripcion = i.idinscripcion
INNER JOIN asignacion asig ON i.idasignacion = asig.idasignacion
WHERE i.idestudiante = '$idestudiante'
  AND i.idinscripcion = '$idinscripcion'
  AND DATE(a.fecha) = CURDATE()
LIMIT 1
";

$res_chk = $conn->query($sql_chk);
if ($res_chk && $res_chk->num_rows > 0) {
    echo json_encode([
        'status' => 'already',
        'message' => "Ya se registró asistencia hoy para la materia $materia."
    ]);
    $conn->close();
    exit;
}


$stmt = $conn->prepare("INSERT INTO asistencias (idinscripcion, fecha) VALUES (?, ?)");
if ($stmt) {
    $stmt->bind_param("is", $idinscripcion, $fecha);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok', 'message' => "Asistencia registrada en $materia a las $fecha."]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Error al insertar: ' . $stmt->error]);
    }
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Error en prepare(): ' . $conn->error]);
}

$conn->close();

?>

