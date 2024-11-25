<?php
define('FIREBASE_URL', 'https://almacen-bb9c0-default-rtdb.firebaseio.com/');

// Función para obtener el stock del proveedor desde Firebase
function obtenerStockProveedor() {
    $url = FIREBASE_URL . 'Stockproveedor.json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener el stock del proveedor.']);
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

// Función para verificar si un pedido ya existe en Firebase
function verificarPedidoExistente($pedidoID) {
    $url = FIREBASE_URL . 'Pedidos/' . $pedidoID . '.json';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    curl_close($ch);

    $pedido = json_decode($response, true);
    return $pedido !== null ? $pedido : false;
}

// Función para crear el pedido en Firebase
function crearPedido($pedidoID, $productos) {
    $url = FIREBASE_URL . 'Pedidos/' . $pedidoID . '.json';

    $data = json_encode(array_merge($productos, [
        'estado' => 'activo',
        'fecha' => date('Y-m-d H:i:s')
    ]));

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear el pedido.']);
    } else {
        http_response_code(201);
        echo json_encode(['message' => 'Pedido creado', 'response' => json_decode($response, true)]);
    }

    curl_close($ch);
}

// Procesar la ruta desde el REQUEST_URI
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Elimina la base del proyecto en la ruta (usando el RewriteBase configurado en .htaccess)
$path_info = str_replace('/Proyecto_final_ws_ENRUTADO/pedido', '', $request_uri);
$path_info = trim($path_info, '/');

// Depuración (opcional)
error_log("REQUEST_URI: $request_uri");
error_log("SCRIPT_NAME: $script_name");
error_log("PATH_INFO: $path_info");

// Configura el encabezado de respuesta como JSON
header('Content-Type: application/json');

// Enrutamiento basado en el método HTTP
$method = $_SERVER['REQUEST_METHOD'];
$path_segments = explode('/', $path_info);

switch ($method) {
    case 'GET':
        if (empty($path_segments[0])) {
            http_response_code(400);
            echo json_encode(['error' => 'No se especificó un ID de pedido.']);
            exit;
        }

        $pedidoID = $path_segments[0];
        $pedido = verificarPedidoExistente($pedidoID);
        if ($pedido) {
            echo json_encode(['pedidoID' => $pedidoID, 'pedido' => $pedido]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Pedido no encontrado.']);
        }
        break;

    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['pedidoID']) && isset($input['productos'])) {
            if (empty($input['productos'])) {
                http_response_code(400);
                echo json_encode(['error' => 'El pedido debe contener al menos un producto.']);
                exit;
            }

            $pedidoID = $input['pedidoID'];
            if (verificarPedidoExistente($pedidoID)) {
                http_response_code(409);
                echo json_encode(['error' => 'Código de pedido repetido.']);
                exit;
            }

            $stockProveedor = obtenerStockProveedor();
            if ($stockProveedor === null) {
                exit;
            }

            foreach ($input['productos'] as $producto => $cantidad) {
                if (!array_key_exists($producto, $stockProveedor)) {
                    http_response_code(400);
                    echo json_encode(['error' => "El producto '$producto' no existe en el stock del proveedor."]);
                    exit;
                }

                if ($cantidad > $stockProveedor[$producto]) {
                    http_response_code(400);
                    echo json_encode(['error' => "Máximo de unidades de $producto es de " . $stockProveedor[$producto] . "."]);
                    exit;
                }
            }

            $productos = $input['productos'];
            crearPedido($pedidoID, $productos);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Falta pedidoID o productos en el cuerpo de la solicitud.']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido.']);
        break;
}
