<?php

// Asegúrate de que este archivo recibe las notificaciones desde Firebase
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if ($input) {
        // Procesa los datos del webhook aquí y guárdalos en notificaciones.json
        $file = 'notificaciones.json'; // Archivo donde se guardarán las notificaciones

        // Si el archivo no existe, crearlo con un array vacío
        if (!file_exists($file)) {
            file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
        }

        // Leer el contenido actual del archivo
        $notificaciones = json_decode(file_get_contents($file), true);

        // Agregar la nueva notificación al array
        $notificaciones[] = $input;

        // Guardar el array actualizado en el archivo
        file_put_contents($file, json_encode($notificaciones, JSON_PRETTY_PRINT));

        // Registrar la notificación en un log adicional
        file_put_contents('webhook_log.txt', print_r($input, true), FILE_APPEND);

        // Devuelve una respuesta de éxito al webhook
        http_response_code(200);
        echo json_encode(['message' => 'Webhook recibido con éxito']);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Payload vacío o no válido']);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido. Usa POST.']);
}
