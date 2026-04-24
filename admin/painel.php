<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$mensagem = '';
$erro = '';

// Garante que a tabela chamados existe
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

// ---------- POST: cadastrar usuário ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    $nome     = trim($_POST['nome']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $senha    = trim($_POST['senha']    ?? '');
    $tipo     = $_POST['tipo']          ?? 'funcionario';
    $ocupacao = trim($_POST['ocupacao'] ?? '');

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha todos os campos obrigatórios.';
    } else {
        $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ocupacao) VALUES (?, ?, ?, ?, ?)");
        $ok = $stmt->execute([$nome, $email, md5($senha), $tipo, $ocupacao]);

        if ($ok && $tipo === 'estabelecimento') {
            $uid = $conn->lastInsertId();
            $conn->prepare("INSERT INTO estabelecimentos (nome, email, usuario_id) VALUES (?, ?, ?)")
                 ->execute([$nome, $email, $uid]);
        }

        $mensagem = $ok ? 'Usuário cadastrado com sucesso!' : 'Erro ao cadastrar usuário.';
    }
}

// ---------- POST: excluir usuário ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid > 0 && $uid !== (int) $_SESSION['usuario_id']) {
        $conn->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$uid]);
        $mensagem = 'Usuário excluído.';
    } else {
        $erro = 'Não é possível excluir o próprio usuário.';
    }
}

// ---------- POST: atualizar status de chamado ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    $cid    = (int) ($_POST['id']     ?? 0);
    $status = $_POST['status'] ?? '';
    $permitidos = ['Aberto', 'Em andamento', 'Concluído'];
    if ($cid > 0 && in_array($status, $permitidos)) {
        $conn->prepare("UPDATE chamados SET status = ? WHERE id = ?")->execute([$status, $cid]);
        $mensagem = 'Status atualizado.';
    }
}

// ---------- Consultas de estatísticas ----------
$hoje = date('Y-m-d');

// Totais de usuários por tipo
$stmtUsr = $conn->query("SELECT tipo, COUNT(*) AS total FROM usuarios GROUP BY tipo");
$totalPorTipo = ['admin' => 0, 'funcionario' => 0, 'estabelecimento' => 0];
foreach ($stmtUsr->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $totalPorTipo[$row['tipo']] = (int) $row['total'];
}
$totalUsuarios = array_sum($totalPorTipo);

// Chamados por status
$stmtCh = $conn->query("SELECT status, COUNT(*) AS total FROM chamados GROUP BY status");
$totalPorStatus = ['Aberto' => 0, 'Em andamento' => 0, 'Concluído' => 0];
foreach ($stmtCh->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $totalPorStatus[$row['status']] = (int) $row['total'];
}

// Eventos hoje
$stmtEv = $conn->prepare("SELECT COUNT(*) FROM eventos WHERE DATE(data) = ?");
$stmtEv->execute([$hoje]);
$eventosHoje = (int) $stmtEv->fetchColumn();

// Lista de todos os usuários
$usuarios = $conn->query("SELECT id, nome, email, ocupacao, tipo FROM usuarios ORDER BY tipo, nome")
                 ->fetchAll(PDO::FETCH_ASSOC);

// Últimos 15 chamados
$chamados = $conn->query("
    SELECT c.id, c.tipo, c.subtipo, c.titulo, c.status, c.criado_em, u.nome AS usuario_nome
    FROM chamados c
    JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.criado_em DESC
    LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);

$statusBadge = ['Aberto' => 'danger', 'Em andamento' => 'warning', 'Concluído' => 'success'];
$tipoBadge   = ['admin' => 'danger', 'funcionario' => 'primary', 'estabelecimento' => 'success'];
$tipoLabel   = ['admin' => 'Administrador', 'funcionario' => 'Funcionário', 'estabelecimento' => 'Estabelecimento'];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel Admin - Smsgerenciador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; margin: 0; }
    .card-stat { border-left: 4px solid; }
    .card-stat.usuarios    { border-color: #6f42c1; }
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
  <?php $basePath = '..'; $activePage = 'admin'; require_once '../includes/sidebar.php'; ?>

  <div class="flex-grow-1 p-4">
    <h2>&#9881; Painel do Administrador</h2>
    <p class="text-muted">Visão geral do sistema — <?= date('d/m/Y') ?></p>

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

    <!-- Cards de resumo -->
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat usuarios h-100">
          <div class="card-body">
            <div class="text-muted small">Total Usuários</div>
            <div class="fs-2 fw-bold text-purple" style="color:#6f42c1"><?= $totalUsuarios ?></div>
            <div class="text-muted" style="font-size:.75rem;">
              <?= $totalPorTipo['funcionario'] ?> func. &bull;
              <?= $totalPorTipo['estabelecimento'] ?> estab. &bull;
              <?= $totalPorTipo['admin'] ?> admin
            </div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat eventos h-100">
          <div class="card-body">
            <div class="text-muted small">Eventos Hoje</div>
            <div class="fs-2 fw-bold text-primary"><?= $eventosHoje ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat aberto h-100">
          <div class="card-body">
            <div class="text-muted small">Chamados Abertos</div>
            <div class="fs-2 fw-bold text-danger"><?= $totalPorStatus['Aberto'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat andamento h-100">
          <div class="card-body">
            <div class="text-muted small">Em Andamento</div>
            <div class="fs-2 fw-bold text-warning"><?= $totalPorStatus['Em andamento'] ?></div>
          </div>
        </div>
      </div>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat concluido h-100">
          <div class="card-body">
            <div class="text-muted small">Concluídos</div>
            <div class="fs-2 fw-bold text-success"><?= $totalPorStatus['Concluído'] ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">

      <!-- Coluna esquerda: Usuários -->
      <div class="col-12 col-xl-7">

        <!-- Tabela de usuários -->
        <div class="card mb-4">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>&#128100; Usuários Cadastrados</span>
            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
              + Novo Usuário
            </button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Ocupação</th>
                    <th>Tipo</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($usuarios as $u): ?>
                  <tr>
                    <td><?= (int)$u['id'] ?></td>
                    <td><?= htmlspecialchars($u['nome']) ?></td>
                    <td class="text-nowrap"><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['ocupacao'] ?? '') ?></td>
                    <td>
                      <span class="badge bg-<?= $tipoBadge[$u['tipo']] ?? 'secondary' ?>">
                        <?= $tipoLabel[$u['tipo']] ?? htmlspecialchars($u['tipo']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                      <form method="POST" onsubmit="return confirm('Excluir este usuário?')">
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger py-0">&#128465;</button>
                      </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- Coluna direita: Chamados recentes -->
      <div class="col-12 col-xl-5">
        <div class="card">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>&#128295; Chamados Recentes</span>
            <a href="../chamado/index.php" class="btn btn-sm btn-light">Ver Todos</a>
          </div>
          <div class="card-body p-0">
            <?php if (empty($chamados)): ?>
              <p class="p-3 text-muted mb-0">Nenhum chamado registrado.</p>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>#</th>
                      <th>Solicitante</th>
                      <th>Tipo / Subtipo</th>
                      <th>Status</th>
                      <th>Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($chamados as $c): ?>
                    <tr>
                      <td><?= (int)$c['id'] ?></td>
                      <td class="text-nowrap"><?= htmlspecialchars($c['usuario_nome']) ?></td>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($c['tipo']) ?></div>
                        <?php if (!empty($c['subtipo'])): ?>
                          <small class="text-muted"><?= htmlspecialchars($c['subtipo']) ?></small>
                        <?php endif; ?>
                      </td>
                      <td>
                        <span class="badge bg-<?= $statusBadge[$c['status']] ?? 'secondary' ?>">
                          <?= htmlspecialchars($c['status']) ?>
                        </span>
                      </td>
                      <td>
                        <form method="POST" class="d-flex gap-1">
                          <input type="hidden" name="acao" value="status">
                          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                          <select name="status" class="form-select form-select-sm" style="width:auto;">
                            <option value="Aberto"       <?= $c['status'] === 'Aberto'       ? 'selected' : '' ?>>Aberto</option>
                            <option value="Em andamento" <?= $c['status'] === 'Em andamento' ? 'selected' : '' ?>>Em andamento</option>
                            <option value="Concluído"    <?= $c['status'] === 'Concluído'    ? 'selected' : '' ?>>Concluído</option>
                          </select>
                          <button type="submit" class="btn btn-sm btn-outline-primary">&#10003;</button>
                        </form>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div><!-- /row -->
  </div><!-- /flex-grow-1 -->
</div><!-- /d-flex -->

<!-- Modal: Novo Usuário -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-labelledby="modalNovoUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">
        <div class="modal-header">
          <h5 class="modal-title" id="modalNovoUsuarioLabel">&#128100; Cadastrar Novo Usuário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Nome <span class="text-danger">*</span></label>
            <input type="text" name="nome" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Ocupação</label>
            <input type="text" name="ocupacao" class="form-control">
          </div>
          <div class="mb-3">
            <label class="form-label">E-mail <span class="text-danger">*</span></label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Senha <span class="text-danger">*</span></label>
            <input type="password" name="senha" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo de Usuário</label>
            <select name="tipo" class="form-select">
              <option value="funcionario">Funcionário</option>
              <option value="admin">Administrador</option>
              <option value="estabelecimento">Estabelecimento</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Cadastrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
