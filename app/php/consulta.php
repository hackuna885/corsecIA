<?php
// Configuración de errores y cabeceras
error_reporting(E_ALL); // Muestra todos los errores para depuración. En producción, podrías usar E_ALL & ~E_DEPRECATED
ini_set('display_errors', 1); // Muestra los errores en la salida (cambiar a 0 en producción)
header("Content-Type: application/json; Charset=UTF-8"); // Se cambió a application/json ya que la respuesta final es JSON
date_default_timezone_set('America/Mexico_City');

// Inclusión de la configuración
// Es crucial que 'config.php' defina $apiKey.
if (file_exists('config.php')) {
    include 'config.php';
} else {
    // Manejo de error si el archivo de configuración no existe
    http_response_code(500);
    echo json_encode(['error' => 'Error: El archivo de configuración "config.php" no se encuentra.']);
    exit();
}

// Validación y saneamiento de la entrada
// Siempre valida y sanea las entradas de usuario.
$consulta = (isset($_POST['consulta'])) ? $_POST['consulta'] : '';

if (empty($consulta)) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Error: El parámetro "consulta" no puede estar vacío.']);
    exit();
}

// Construcción de la URL de la API
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key='.$apiKey;

// Preparación de los datos para la solicitud
$datos = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => $consulta
                ]
            ]
        ]
    ]
];

$datosJSON = json_encode($datos);

// Verificación de errores en json_encode
if ($datosJSON === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al codificar los datos JSON: ' . json_last_error_msg()]);
    exit();
}

// Configuración de las opciones de la solicitud cURL
$opciones = [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true, // Devuelve el resultado como una cadena
    CURLOPT_FOLLOWLOCATION => true, // Sigue cualquier encabezado Location:
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $datosJSON,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($datosJSON) // Es una buena práctica incluir Content-Length
    ],
    CURLOPT_TIMEOUT => 30, // Tiempo máximo en segundos que se permite ejecutar las funciones cURL (opcional, pero recomendado)
    CURLOPT_CONNECTTIMEOUT => 10, // Tiempo máximo en segundos que se permite para intentar la conexión (opcional, pero recomendado)
];

// Inicializa cURL y configura las opciones
$curl = curl_init();
if ($curl === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al inicializar cURL.']);
    exit();
}
curl_setopt_array($curl, $opciones);

// Ejecuta la solicitud cURL
$respGemini = curl_exec($curl);

// Manejo de errores de cURL
if (curl_errno($curl)) {
    $error_msg = curl_error($curl);
    curl_close($curl);
    http_response_code(500);
    echo json_encode(['error' => 'Error en la solicitud cURL: ' . $error_msg]);
    exit();
}

// Obtener el código de estado HTTP de la respuesta
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

// Cierra la sesión cURL
curl_close($curl);

// Decodifica la respuesta JSON de Gemini
$respuesta = json_decode($respGemini, true);

// Manejo de errores en la decodificación JSON de la respuesta
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al decodificar la respuesta JSON de la API: ' . json_last_error_msg(), 'raw_response' => $respGemini]);
    exit();
}

// Manejo de posibles errores de la API de Gemini (códigos de estado HTTP y estructura de la respuesta)
if ($http_code !== 200 || !isset($respuesta['candidates'][0]['content']['parts'][0]['text'])) {
    http_response_code($http_code !== 200 ? $http_code : 500); // Usa el código HTTP de Gemini si es un error, sino 500
    echo json_encode([
        'error' => 'La API de Gemini devolvió un error o un formato inesperado.',
        'gemini_response' => $respuesta
    ]);
    exit();
}

// Envia la respuesta final en formato JSON
echo json_encode(['mensaje' => $respuesta['candidates'][0]['content']['parts'][0]['text']]);
?>