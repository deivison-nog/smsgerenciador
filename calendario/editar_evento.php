<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acesso negado');
}

$id = $_POST['id'] ?? null;
$titulo = $_POST['titulo'] ?? null;
$data = $_POST['data'] ?? null;
$usuario_id = $_SESSION['usuario_id'];
$usuario_tipo = $_SESSION['usuario_tipo'];

if (empty($id) || empty($titulo) || empty($data)) {
    http_response_code(400);
    exit('Dados incompletos');
}

// Não permitir datas retroativas (data menor que a data de hoje)
$hoje = date('Y-m-d');
if ($data < $hoje) {
    http_response_code(400);
    exit('Não é permitido selecionar datas retroativas.');
}

// Verifica se o evento existe e se o usuário pode editar
// Admin pode editar qualquer evento
// Estabelecimento só pode editar eventos do seu estabelecimento
// Funcionário só pode editar eventos em que ele é o dono

if ($usuario_tipo === 'admin') {
    $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ?");
    $stmt->execute([$id]);
} elseif ($usuario_tipo === 'estabelecimento') {
    // Descobre qual estabelecimento ele é responsável
    $stmtEstab = $conn->prepare("SELECT id FROM estabelecimentos WHERE usuario_id = ?");
    $stmtEstab->execute([$usuario_id]);
    $estab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
    if (!$estab) {
        http_response_code(403);
        exit('Estabelecimento não encontrado');
    }
    $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND id_estabelecimento = ?");
    $stmt->execute([$id, $estab['id']]);
} else { // funcionario
    $stmt = $conn->prepare("SELECT id FROM eventos WHERE id = ? AND id_usuario = ?");
    $stmt->execute([$id, $usuario_id]);
}

if ($stmt->rowCount() === 0) {
    http_response_code(403);
    exit('Você não tem permissão para editar este evento ou evento não encontrado');
}

// Atualiza título e data
$stmt = $conn->prepare("UPDATE eventos SET titulo = ?, data = ? WHERE id = ?");
$success = $stmt->execute([$titulo, $data, $id]);

if ($success) {
    echo 'Evento atualizado com sucesso';
} else {
    http_response_code(500);
    echo 'Erro ao atualizar evento';
}
