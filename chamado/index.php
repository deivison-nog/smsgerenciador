<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Chamado - Smsgerenciador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; margin: 0; }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-0">
  <div class="container-fluid">
    <span class="navbar-brand">Smsgerenciador - <?= htmlspecialchars($_SESSION['usuario_nome']) ?></span>
    <div class="d-flex">
      <a class="btn btn-light btn-sm" href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="d-flex">
  <?php $basePath = '..'; $activePage = 'chamado'; require_once '../includes/sidebar.php'; ?>

  <div class="flex-grow-1 p-4">
    <h2>Chamado</h2>
    <p class="text-muted">Abertura de chamados para manutenções.</p>
    <div class="alert alert-info">
      Esta seção está em desenvolvimento. Em breve você poderá abrir e acompanhar chamados de manutenção.
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
