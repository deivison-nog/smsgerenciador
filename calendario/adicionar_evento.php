<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado');
}

$titulo = $_POST['titulo'] ?? '';
$data = $_POST['data'] ?? '';
$tipo = $_SESSION['usuario_tipo'];
$id_usuario = $_SESSION['usuario_id'];

if ($tipo === 'estabelecimento') {
    $funcionario_id = $_POST['funcionario_id'] ?? null;
    // Buscar o id do estabelecimento na tabela estabelecimentos
    $stmtEstab = $conn->prepare("SELECT id FROM estabelecimentos WHERE usuario_id = ?");
    $stmtEstab->execute([$id_usuario]);
    $rowEstab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
    $id_estabelecimento = $rowEstab ? $rowEstab['id'] : null;

    if (empty($titulo) || empty($data) || empty($funcionario_id) || empty($id_estabelecimento)) {
        http_response_code(400);
        echo 'Dados incompletos ou estabelecimento inexistente';
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO eventos (titulo, data, id_usuario, id_estabelecimento) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$titulo, $data, $funcionario_id, $id_estabelecimento]);
} elseif ($tipo === 'funcionario') {
    $id_estabelecimento = $_POST['estab_id'] ?? null;
    if (empty($titulo) || empty($data) || empty($id_estabelecimento)) {
        http_response_code(400);
        echo 'Dados incompletos';
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO eventos (titulo, data, id_usuario, id_estabelecimento) VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([$titulo, $data, $id_usuario, $id_estabelecimento]);
} else {
    // Admin adicionando evento para si mesmo
    if (empty($titulo) || empty($data)) {
        http_response_code(400);
        echo 'Dados incompletos';
        exit;
    }
    $stmt = $conn->prepare("INSERT INTO eventos (titulo, data, id_usuario) VALUES (?, ?, ?)");
    $success = $stmt->execute([$titulo, $data, $id_usuario]);
}

if ($success) {
    echo 'Evento adicionado com sucesso';
} else {
    http_response_code(500);
    echo 'Erro ao adicionar evento';
}
