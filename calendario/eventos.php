<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit;
}

$tipo = $_SESSION['usuario_tipo'];
$usuario_id = $_SESSION['usuario_id'];
$funcionario_id = $_GET['funcionario_id'] ?? null;
$estab_id = $_GET['estab_id'] ?? null;

if ($tipo === 'admin') {
    if ($funcionario_id && is_numeric($funcionario_id)) {
        $stmt = $conn->prepare(
            "SELECT eventos.id, UPPER(eventos.titulo) AS title, UPPER(eventos.data) AS start, UPPER(usuarios.nome) AS profissional, eventos.id_usuario AS profissional_id
             FROM eventos
             JOIN usuarios ON eventos.id_usuario = usuarios.id
             WHERE eventos.id_usuario = ?"
        );
        $stmt->execute([$funcionario_id]);
    } else {
        $stmt = $conn->prepare(
            "SELECT eventos.id, UPPER(eventos.titulo) AS title, UPPER(eventos.data) AS start, UPPER(usuarios.nome) AS profissional, eventos.id_usuario AS profissional_id
             FROM eventos
             JOIN usuarios ON eventos.id_usuario = usuarios.id"
        );
        $stmt->execute();
    }
} elseif ($tipo === 'estabelecimento') {
    // Buscar o id do estabelecimento real na tabela estabelecimentos
    $stmtEstab = $conn->prepare("SELECT id FROM estabelecimentos WHERE usuario_id = ?");
    $stmtEstab->execute([$usuario_id]);
    $rowEstab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
    $id_estabelecimento = $rowEstab ? $rowEstab['id'] : null;

    if (!$id_estabelecimento) {
        echo json_encode([]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT eventos.id, UPPER(eventos.titulo) AS title, UPPER(eventos.data) AS start, UPPER(usuarios.nome) AS profissional, eventos.id_usuario AS profissional_id
         FROM eventos
         JOIN usuarios ON eventos.id_usuario = usuarios.id
         WHERE eventos.id_estabelecimento = ?"
    );
    $stmt->execute([$id_estabelecimento]);
} else { // FUNCIONÁRIO
    if ($estab_id && is_numeric($estab_id)) {
        $stmt = $conn->prepare(
            "SELECT eventos.id, UPPER(eventos.titulo) AS title, UPPER(eventos.data) AS start, UPPER(estabelecimentos.nome) AS estabelecimento
             FROM eventos
             JOIN estabelecimentos ON eventos.id_estabelecimento = estabelecimentos.id
             WHERE eventos.id_usuario = ? AND eventos.id_estabelecimento = ?"
        );
        $stmt->execute([$usuario_id, $estab_id]);
    } else {
        $stmt = $conn->prepare(
            "SELECT eventos.id, UPPER(eventos.titulo) AS title, UPPER(eventos.data) AS start, UPPER(estabelecimentos.nome) AS estabelecimento
             FROM eventos
             JOIN estabelecimentos ON eventos.id_estabelecimento = estabelecimentos.id
             WHERE eventos.id_usuario = ?"
        );
        $stmt->execute([$usuario_id]);
    }
}

$eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($eventos);
