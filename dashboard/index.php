<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}
// Profissional só tem acesso ao cronograma
if (($_SESSION['usuario_perfil'] ?? '') === 'profissional') {
    header('Location: ../calendario/crono.php');
    exit;
}

$usuarioTipo = $_SESSION['usuario_tipo'];
$usuarioId   = $_SESSION['usuario_id'];
$isAdmin     = ($usuarioTipo === 'admin');

// Eventos de hoje
$hoje = date('Y-m-d');

if ($isAdmin) {
    $stmtEv = $conn->prepare("
        SELECT e.id, e.titulo, e.data, u.nome AS profissional
        FROM eventos e
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE DATE(e.data) = ?
        ORDER BY e.data ASC
    ");
    $stmtEv->execute([$hoje]);
} elseif ($usuarioTipo === 'estabelecimento') {
    $stmtEstab = $conn->prepare("SELECT id FROM estabelecimentos WHERE usuario_id = ?");
    $stmtEstab->execute([$usuarioId]);
    $rowEstab = $stmtEstab->fetch(PDO::FETCH_ASSOC);
    $idEstab  = $rowEstab ? $rowEstab['id'] : 0;

    $stmtEv = $conn->prepare("
        SELECT e.id, e.titulo, e.data, u.nome AS profissional
        FROM eventos e
        JOIN usuarios u ON e.id_usuario = u.id
        WHERE DATE(e.data) = ? AND e.id_estabelecimento = ?
        ORDER BY e.data ASC
    ");
    $stmtEv->execute([$hoje, $idEstab]);
} else {
    $stmtEv = $conn->prepare("
        SELECT e.id, e.titulo, e.data, est.nome AS estabelecimento
        FROM eventos e
        JOIN estabelecimentos est ON e.id_estabelecimento = est.id
        WHERE DATE(e.data) = ? AND e.id_usuario = ?
        ORDER BY e.data ASC
    ");
    $stmtEv->execute([$hoje, $usuarioId]);
}
$eventosHoje = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// Chamados
// Garante que a tabela existe (caso o usuário acesse o dashboard antes da página de chamados)
$conn->exec("
    CREATE TABLE IF NOT EXISTS chamados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(100) NOT NULL,
        subtipo VARCHAR(150) NOT NULL DEFAULT '',
        titulo VARCHAR(150) NOT NULL,
        descricao TEXT,
        status VARCHAR(30) NOT NULL DEFAULT 'Aberto',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if ($isAdmin) {
    $stmtCh = $conn->query("
        SELECT c.id, c.tipo, c.subtipo, c.titulo, c.status, c.criado_em, u.nome AS usuario_nome
        FROM chamados c
        JOIN usuarios u ON c.usuario_id = u.id
        ORDER BY c.criado_em DESC
    ");
} else {
    $stmtCh = $conn->prepare("
        SELECT c.id, c.tipo, c.subtipo, c.titulo, c.status, c.criado_em, u.nome AS usuario_nome
        FROM chamados c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.usuario_id = ?
        ORDER BY c.criado_em DESC
    ");
    $stmtCh->execute([$usuarioId]);
}
$chamados = $stmtCh->fetchAll(PDO::FETCH_ASSOC);

$statusBadge = [
    'Aberto'       => 'danger',
    'Em andamento' => 'warning',
    'Concluído'    => 'success',
];

// Contadores de chamados por status
$totalAberto     = 0;
$totalAndamento  = 0;
$totalConcluido  = 0;
foreach ($chamados as $c) {
    if ($c['status'] === 'Aberto')       $totalAberto++;
    elseif ($c['status'] === 'Em andamento') $totalAndamento++;
    elseif ($c['status'] === 'Concluído')    $totalConcluido++;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Smsgerenciador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; margin: 0; }
    .card-stat { border-left: 4px solid; }
    .card-stat.aberto      { border-color: #dc3545; }
    .card-stat.andamento   { border-color: #ffc107; }
    .card-stat.concluido   { border-color: #198754; }
    .card-stat.eventos     { border-color: #0d6efd; }
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
  <?php $basePath = '..'; $activePage = 'dashboard'; require_once '../includes/sidebar.php'; ?>

  <div class="flex-grow-1 p-4">
    <h2>&#128202; Dashboard</h2>
    <p class="text-muted">Resumo do dia — <?= date('d/m/Y') ?></p>

    <!-- Cards de resumo -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3">
        <div class="card card-stat eventos h-100">
          <div class="card-body">
            <div class="text-muted small">Eventos Hoje</div>
            <div class="fs-2 fw-bold text-primary"><?= count($eventosHoje) ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-stat aberto h-100">
          <div class="card-body">
            <div class="text-muted small">Chamados Abertos</div>
            <div class="fs-2 fw-bold text-danger"><?= $totalAberto ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-stat andamento h-100">
          <div class="card-body">
            <div class="text-muted small">Em Andamento</div>
            <div class="fs-2 fw-bold text-warning"><?= $totalAndamento ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3">
        <div class="card card-stat concluido h-100">
          <div class="card-body">
            <div class="text-muted small">Concluídos</div>
            <div class="fs-2 fw-bold text-success"><?= $totalConcluido ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Eventos do dia -->
      <div class="col-12 col-lg-5">
        <div class="card h-100">
          <div class="card-header bg-primary text-white">&#128197; Cronograma de Hoje</div>
          <div class="card-body p-0">
            <?php if (empty($eventosHoje)): ?>
              <p class="p-3 text-muted mb-0">Nenhum evento agendado para hoje.</p>
            <?php else: ?>
              <ul class="list-group list-group-flush">
                <?php foreach ($eventosHoje as $ev): ?>
                <li class="list-group-item">
                  <div class="fw-semibold"><?= htmlspecialchars(strtoupper($ev['titulo'])) ?></div>
                  <?php if (!empty($ev['profissional'])): ?>
                    <small class="text-muted">&#128100; <?= htmlspecialchars($ev['profissional']) ?></small>
                  <?php elseif (!empty($ev['estabelecimento'])): ?>
                    <small class="text-muted">&#127968; <?= htmlspecialchars($ev['estabelecimento']) ?></small>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <div class="card-footer text-end">
            <a href="../calendario/crono.php" class="btn btn-sm btn-outline-primary">Ver Cronograma</a>
          </div>
        </div>
      </div>

      <!-- Chamados -->
      <div class="col-12 col-lg-7">
        <div class="card h-100">
          <div class="card-header bg-primary text-white">&#128295; <?= $isAdmin ? 'Todos os Chamados' : 'Meus Chamados' ?></div>
          <div class="card-body p-0">
            <?php if (empty($chamados)): ?>
              <p class="p-3 text-muted mb-0">Nenhum chamado registrado.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <?php if ($isAdmin): ?><th>Solicitante</th><?php endif; ?>
                      <th>Tipo</th>
                      <th>Subtipo</th>
                      <th>Descrição</th>
                      <th>Status</th>
                      <th>Data</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($chamados as $c): ?>
                    <tr>
                      <td><?= (int)$c['id'] ?></td>
                      <?php if ($isAdmin): ?><td><?= htmlspecialchars($c['usuario_nome']) ?></td><?php endif; ?>
                      <td><?= htmlspecialchars($c['tipo']) ?></td>
                      <td><?= htmlspecialchars($c['subtipo'] ?? '') ?></td>
                      <td><?= htmlspecialchars($c['titulo']) ?></td>
                      <td>
                        <span class="badge bg-<?= $statusBadge[$c['status']] ?? 'secondary' ?>">
                          <?= htmlspecialchars($c['status']) ?>
                        </span>
                      </td>
                      <td class="text-nowrap"><?= date('d/m/Y', strtotime($c['criado_em'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-footer text-end">
            <a href="../chamado/index.php" class="btn btn-sm btn-outline-primary">Abrir Chamado</a>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
