<?php
// URL de Firebase
define('FIREBASE_URL', 'https://almacen-bb9c0-default-rtdb.firebaseio.com/');
define('WEBHOOK_URL', 'https://02df-2806-262-40d-8f08-ad29-11fd-1357-821.ngrok-free.app/Proyecto_final_ws_ENRUTADO/webhook.php');

// Función para obtener datos desde Firebase
function obtenerDatosFirebase($endpoint) {
    $url = FIREBASE_URL . $endpoint . '.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

// Función para actualizar datos en Firebase
function actualizarFirebase($endpoint, $data, $method = 'PUT') {
    $url = FIREBASE_URL . $endpoint . '.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// Función para notificar al webhook
function notificarWebhook($data) {
    $ch = curl_init(WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        file_put_contents('curl_error_log.txt', 'Error cURL: ' . curl_error($ch) . PHP_EOL, FILE_APPEND);
    }

    curl_close($ch);

    return $response;
}

// Función principal para procesar entrega
function procesarEntrega($tipo, $id) {
    $pedido = obtenerDatosFirebase("$tipo/$id");
    if ($pedido === null) {
        http_response_code(404);
        echo json_encode(['error' => "$tipo no encontrado."]);
        return;
    }

    if ($pedido['estado'] !== 'activo') {
        echo json_encode(['error' => "Este $tipo ya fue enviado."]);
        return;
    }

    $stockProveedor = obtenerDatosFirebase('Stockproveedor');
    $productos = obtenerDatosFirebase('Almacenproductos');
    $errores = [];

    foreach ($pedido as $producto => $cantidad) {
        if (in_array($producto, ['estado', 'fecha'])) continue;

        if (!isset($stockProveedor[$producto])) {
            $errores[] = "El producto '$producto' no está en el stock del proveedor.";
        } elseif ($cantidad > $stockProveedor[$producto]) {
            $errores[] = "Faltan " . ($cantidad - $stockProveedor[$producto]) . " unidades del producto '$producto'.";
        }
    }

    if (!empty($errores)) {
        echo json_encode(['error' => 'No se puede procesar el pedido:', 'detalles' => $errores]);
        return;
    }

    foreach ($pedido as $producto => $cantidad) {
        if (in_array($producto, ['estado', 'fecha'])) continue;

        $stockProveedor[$producto] -= $cantidad;
        actualizarFirebase("Stockproveedor/$producto", $stockProveedor[$producto]);

        $nuevaCantidad = isset($productos[$producto]) ? $productos[$producto] + $cantidad : $cantidad;
        actualizarFirebase("Almacenproductos/$producto", $nuevaCantidad);
    }

    $fecha_entrega = date('Y-m-d', strtotime('+3 days'));
    actualizarFirebase("$tipo/$id", ['estado' => 'enviado', 'fecha_entrega' => $fecha_entrega], 'PATCH');

    $response = notificarWebhook([
        'id' => $id,
        'tipo' => $tipo,
        'estado' => 'enviado',
        'mensaje' => "$tipo enviado con éxito.",
        'fecha_entrega' => $fecha_entrega
    ]);

    echo json_encode(['message' => "$tipo enviado con éxito.", 'webhook_response' => $response]);
}

// Enrutamiento
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/Proyecto_final_ws_ENRUTADO/Empaquetadora/Envios/';
$path = trim(str_replace($base_path, '', $request_uri), '/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['id']) || !isset($input['tipo'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Falta el ID o tipo en el cuerpo de la solicitud.']);
        exit;
    }

    $tipo = $input['tipo'];
    $id = $input['id'];

    if (!in_array($tipo, ['Pedidos', 'devoluciones'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Tipo inválido.']);
        exit;
    }

    procesarEntrega($tipo, $id);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido.']);
}
