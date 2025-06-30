<?php
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['system_unit_id'])) {
    $_SESSION['system_unit_id'] = (int) $data['system_unit_id'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'ID da unidade não informado']);
}
