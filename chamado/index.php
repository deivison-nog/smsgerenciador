<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

// Garante que a tabela existe
$conn->exec("
    CREATE TABLE IF NOT EXISTS chamados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(50) NOT NULL,
        titulo VARCHAR(150) NOT NULL,
        descricao TEXT,
        status VARCHAR(30) NOT NULL DEFAULT 'Aberto',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$tipos = ['TI / Sistema', 'Equipamentos', 'Infraestrutura', 'Limpeza', 'Suprimentos'];
$isAdmin = ($_SESSION['usuario_tipo'] === 'admin');
$mensagem = '';
$erro = '';

// POST: abrir novo chamado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'abrir') {
    $tipo     = $_POST['tipo']     ?? '';
    $titulo   = trim($_POST['titulo']   ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    if (!in_array($tipo, $tipos)) {
        $erro = 'Selecione um tipo de chamado válido.';
    } elseif ($titulo === '') {
        $erro = 'O título é obrigatório.';
    } else {
        $stmt = $conn->prepare("INSERT INTO chamados (usuario_id, tipo, titulo, descricao) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['usuario_id'], $tipo, $titulo, $descricao]);
        $mensagem = 'Chamado aberto com sucesso!';
    }
}

// POST: atualizar status (somente admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'status' && $isAdmin) {
    $id     = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $statusPermitidos = ['Aberto', 'Em andamento', 'Concluído'];
    if ($id > 0 && in_array($status, $statusPermitidos)) {
        $stmt = $conn->prepare("UPDATE chamados SET status = ? WHERE id = ?");
        $stmt->execute([$status, $id]);
        $mensagem = 'Status atualizado.';
    }
}

// Buscar chamados
if ($isAdmin) {
    $stmt = $conn->query("
        SELECT c.*, u.nome AS usuario_nome
        FROM chamados c
        JOIN usuarios u ON c.usuario_id = u.id
        ORDER BY c.criado_em DESC
    ");
} else {
    $stmt = $conn->prepare("
        SELECT c.*, u.nome AS usuario_nome
        FROM chamados c
        JOIN usuarios u ON c.usuario_id = u.id
        WHERE c.usuario_id = ?
        ORDER BY c.criado_em DESC
    ");
    $stmt->execute([$_SESSION['usuario_id']]);
}
$chamados = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusBadge = [
    'Aberto'       => 'danger',
    'Em andamento' => 'warning',
    'Concluído'    => 'success',
];
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

    <?php if ($mensagem): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Formulário de abertura de chamado -->
    <div class="card mb-4">
      <div class="card-header bg-primary text-white">Abrir Novo Chamado</div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="acao" value="abrir">

          <div class="mb-3">
            <label for="tipo" class="form-label">Tipo de Chamado <span class="text-danger">*</span></label>
            <select id="tipo" name="tipo" class="form-select" required>
              <option value="">Selecione...</option>
              <?php foreach ($tipos as $t): ?>
                <option value="<?= htmlspecialchars($t) ?>"
                  <?= (isset($_POST['tipo']) && $_POST['tipo'] === $t) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($t) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label for="titulo" class="form-label">Título <span class="text-danger">*</span></label>
            <input type="text" id="titulo" name="titulo" class="form-control" maxlength="150"
              value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>" required>
          </div>

          <div class="mb-3">
            <label for="descricao" class="form-label">Descrição</label>
            <textarea id="descricao" name="descricao" class="form-control" rows="3"><?= htmlspecialchars($_POST['descricao'] ?? '') ?></textarea>
          </div>

          <button type="submit" class="btn btn-primary">Abrir Chamado</button>
        </form>
      </div>
    </div>

    <!-- Lista de chamados -->
    <h5><?= $isAdmin ? 'Todos os Chamados' : 'Meus Chamados' ?></h5>
    <?php if (empty($chamados)): ?>
      <p class="text-muted">Nenhum chamado encontrado.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
          <thead class="table-primary">
            <tr>
              <th>#</th>
              <?php if ($isAdmin): ?><th>Solicitante</th><?php endif; ?>
              <th>Tipo</th>
              <th>Título</th>
              <th>Descrição</th>
              <th>Status</th>
              <th>Data</th>
              <?php if ($isAdmin): ?><th>Ação</th><?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($chamados as $c): ?>
            <tr>
              <td><?= (int)$c['id'] ?></td>
              <?php if ($isAdmin): ?><td><?= htmlspecialchars($c['usuario_nome']) ?></td><?php endif; ?>
              <td><?= htmlspecialchars($c['tipo']) ?></td>
              <td><?= htmlspecialchars($c['titulo']) ?></td>
              <td><?= nl2br(htmlspecialchars($c['descricao'])) ?></td>
              <td>
                <span class="badge bg-<?= $statusBadge[$c['status']] ?? 'secondary' ?>">
                  <?= htmlspecialchars($c['status']) ?>
                </span>
              </td>
              <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($c['criado_em']))) ?></td>
              <?php if ($isAdmin): ?>
              <td>
                <form method="POST" class="d-flex gap-1">
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <select name="status" class="form-select form-select-sm" style="width:auto;">
                    <option value="Aberto"       <?= $c['status'] === 'Aberto'       ? 'selected' : '' ?>>Aberto</option>
                    <option value="Em andamento" <?= $c['status'] === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                    <option value="Concluído"    <?= $c['status'] === 'Concluído'    ? 'selected' : '' ?>>Concluído</option>
                  </select>
                  <button type="submit" class="btn btn-sm btn-outline-primary">Salvar</button>
                </form>
              </td>
              <?php endif; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
