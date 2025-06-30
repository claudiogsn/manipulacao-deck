<?php
session_start();

$response = ['success' => false, 'message' => 'Erro desconhecido'];

if (!isset($_FILES['fotos'])) {
    $response['message'] = 'Nenhum arquivo enviado';
    echo json_encode($response);
    exit;
}

// Parâmetros obrigatórios
$documento = $_POST['documento'] ?? null;
$userId = $_POST['user_id'] ?? null;
$unitId = $_POST['system_unit_id'] ?? null;

if (!$documento || !$userId || !$unitId) {
    $response['message'] = 'Dados obrigatórios ausentes';
    echo json_encode($response);
    exit;
}

// URL da API
$urlApi = ($_SERVER['SERVER_NAME'] == "localhost")
    ? "http://localhost/portal-deck/api/v1/index.php"
    : "https://portal.vemprodeck.com.br/api/v1/index.php";

// Caminho base do upload e da URL pública
$uploadDir = __DIR__ . '/uploads/';
$publicBaseUrl = "https://vemprodeck.com.br/manipulacao/uploads/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$sucesso = true;
$anexosRegistrados = [];

foreach ($_FILES['fotos']['tmp_name'] as $index => $tmpPath) {
    $originalName = $_FILES['fotos']['name'][$index];
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $novoNome = "{$documento}_{$userId}_{$unitId}_" . uniqid() . "." . $extension;
    $destino = $uploadDir . $novoNome;

    if (!move_uploaded_file($tmpPath, $destino)) {
        $sucesso = false;
        $response['message'] = "Erro ao mover o arquivo: $originalName";
        break;
    }

    // Chamar API para registrar anexo
    $apiResponse = file_get_contents($urlApi, false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/json",
            'content' => json_encode([
                "method" => "registrarAnexoMovimentacao",
                "data"   => [
                    "documento" => $documento,
                    "system_unit_id" => (int)$unitId,
                    "url" => $publicBaseUrl . $novoNome
                ]
            ])
        ]
    ]));

    $apiDecoded = json_decode($apiResponse, true);
    if (!isset($apiDecoded['success']) || !$apiDecoded['success']) {
        $sucesso = false;
        $response['message'] = 'Erro ao registrar anexo na API';
        break;
    }

    $anexosRegistrados[] = $publicBaseUrl . $novoNome;
}

$response['success'] = $sucesso;
$response['message'] = $sucesso ? 'Arquivos enviados e anexos registrados com sucesso' : $response['message'];
$response['anexos'] = $anexosRegistrados;

echo json_encode($response);
