<?php
// URL base de la base de datos Firebase
$firebase_base_url = 'https://almacen-bb9c0-default-rtdb.firebaseio.com/';

function getFirebaseData($path) {
    global $firebase_base_url;
    
    // Construye la URL completa de Firebase con la ruta especificada
    $url = "$firebase_base_url$path.json";

    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // Deshabilitar verificación de certificado SSL
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($curl);

    if (curl_errno($curl)) {
        // Si hay un error, devolver un mensaje de error con estado 500
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

// Captura la URI solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/Proyecto_final_ws_ENRUTADO/Empaquetadora/Solicitudes/';

// Verifica que la URI comience con el base_path
if (strpos($request_uri, $base_path) === 0) {
    // Obtén la ruta relativa eliminando el base_path
    $path = substr($request_uri, strlen($base_path));

    // Limpia la ruta eliminando barras adicionales
    $path = trim($path, '/');

    // Si la ruta es "Pedidos" o "Devoluciones", realiza la solicitud correspondiente a Firebase
    if ($path && (strpos($path, 'Pedidos') === 0 || strpos($path, 'devoluciones') === 0)) {
        // Obtener los datos desde Firebase
        $data = getFirebaseData($path);

        // Configura el tipo de contenido como JSON y muestra los datos obtenidos
        header('Content-Type: application/json');
        echo json_encode($data);
    } else {
        // Si no coincide con ninguna ruta válida, devuelve un código 404
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Ruta no válida']);
    }
} else {
    // Si la URI no coincide con el base_path, devuelve un código 404
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Ruta no válida']);
}
