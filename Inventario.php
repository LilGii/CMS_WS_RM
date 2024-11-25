<?php
// URL base de la base de datos Firebase
$firebase_base_url = 'https://almacen-bb9c0-default-rtdb.firebaseio.com/Almacenproductos';

function getFirebaseData($path = '') {
    global $firebase_base_url;
    
    // Construye la URL completa de Firebase con la ruta especificada
    $url = "$firebase_base_url/$path.json";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // Deshabilitar verificación de certificado SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        // Si hay un error en la solicitud, devolver un código de error 500
        http_response_code(500);
        echo json_encode(['error' => 'Error en la solicitud: ' . curl_error($curl)]);
        curl_close($curl);
        return null;
    }

    curl_close($curl);

    // Decodificar la respuesta JSON
    $data = json_decode($response, true);

    // Si el recurso no existe en Firebase, devolver un código 404
    if ($data === null) {
        http_response_code(404);
        return ['error' => 'Recurso no encontrado'];
    }

    // Devolver los datos con un código 200 en caso de éxito
    http_response_code(200);
    return $data;
}

// Captura el REQUEST_URI y remueve el prefijo relacionado con Inventario
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// Elimina la base del proyecto en la ruta (usando el RewriteBase configurado en .htaccess)
$path_info = str_replace('/Proyecto_final_ws_ENRUTADO/Inventario', '', $request_uri);
$path_info = trim($path_info, '/');

// Depuración, para verificar las rutas procesadas)
error_log("REQUEST_URI: $request_uri");
error_log("SCRIPT_NAME: $script_name");
error_log("PATH_INFO: $path_info");

// Configura el encabezado de respuesta como JSON
header('Content-Type: application/json');

// Si la ruta es Almacenproductos o Almacenproductos/ProductoID, realiza la solicitud correspondiente a Firebase
if ($path_info) {
    // Dividimos el path en segmentos
    $path_segments = explode('/', $path_info);

    // Comprobamos si el primer segmento es Almacenproductos
    if ($path_segments[0] === 'Almacenproductos') {
        // Si solo se solicita "Almacenproductos", obtenemos todos los productos
        $product_id = isset($path_segments[1]) ? $path_segments[1] : null;
        $data = getFirebaseData($product_id);
    } else {
        // Si el primer segmento no es Almacenproductos, devolvemos un error 404
        http_response_code(404);
        $data = ['error' => 'Ruta no válida'];
    }
} else {
    // Si no hay path_info, devolvemos un error 404
    http_response_code(404);
    $data = ['error' => 'Ruta no válida'];
}

// Muestra los datos obtenidos
echo json_encode($data);
