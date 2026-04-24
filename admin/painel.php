<?php
session_start();
require_once '../includes/db.php';

$perfil      = $_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] === 'admin' ? 'admin' : '');
$isAdmin     = ($perfil === 'admin');
$isAdminist  = ($perfil === 'administrativo');

if (!$isAdmin && !$isAdminist) {
    header("Location: ../index.php");
    exit;
}

$lotacaoId = (int) ($_SESSION['usuario_lotacao_id'] ?? 0);

$mensagem = '';
$erro     = '';

// ── listas auxiliares ──────────────────────────────────────────────────────
$ocupacoes = [
    'Agente Comunitário de Saúde','Agente de Combate às Endemias','Assistente Social',
    'Aux Administrativo','Aux de Saúde Bucal','Biomédico','Enfermeiro','Fisioterapeuta',
    'Médico','Motorista','Naturólogo','Nutricionista','Odontólogo','Psicólogo',
    'Serviços Gerais','Tec de Laboratório','Tec em Enfermagem','Tec em Radiologia',
    'Terapeuta Ocupacional','TI','Vigia',
];
$statusBadge = ['Aberto' => 'danger', 'Em andamento' => 'warning', 'Concluído' => 'success'];
$perfilBadge = ['admin' => 'danger', 'administrativo' => 'warning', 'coordenador' => 'info', 'profissional' => 'primary'];
$perfilLabel = ['admin' => 'Administrador', 'administrativo' => 'Administrativo', 'coordenador' => 'Coordenador', 'profissional' => 'Profissional'];

// ── POST: cadastrar usuário ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'cadastrar') {
    $nome               = trim($_POST['nome']               ?? '');
    $cpf                = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $data_nascimento    = trim($_POST['data_nascimento']    ?? '') ?: null;
    $email              = trim($_POST['email']              ?? '');
    $senha              = trim($_POST['senha']              ?? '');
    $perfil_novo        = $_POST['perfil_novo']             ?? 'profissional';
    $ocupacao           = trim($_POST['ocupacao']           ?? '');
    $registro_classe    = trim($_POST['registro_classe']    ?? '');
    $lotacao_nova       = (int) ($_POST['lotacao_id']       ?? 0) ?: null;
    $carga_horaria      = trim($_POST['carga_horaria']      ?? '');
    $regime             = $_POST['regime_contratacao']      ?? '';
    $sit                = 'ativo';

    // Administrativo não pode criar admin nem outros administrativos; lotacao é a sua própria
    if (!$isAdmin && in_array($perfil_novo, ['admin', 'administrativo'])) {
        $erro = 'Sem permissão para criar usuários com este perfil.';
    } elseif ($nome === '' || $cpf === '' || $senha === '') {
        $erro = 'Preencha todos os campos obrigatórios (Nome, CPF, Senha).';
    } else {
        $tipo = ($perfil_novo === 'admin') ? 'admin' : 'funcionario';
        if (!$isAdmin) { $lotacao_nova = $lotacaoId; }

        $stmt = $conn->prepare("
            INSERT INTO usuarios
                (nome, cpf, data_nascimento, email, senha, tipo, perfil, ocupacao,
                 registro_classe, lotacao_id, carga_horaria, regime_contratacao, situacao, cadastrado_em)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $ok = $stmt->execute([
            $nome, $cpf ?: null, $data_nascimento, $email, md5($senha),
            $tipo, $perfil_novo, $ocupacao, $registro_classe ?: null,
            $lotacao_nova, $carga_horaria ?: null, $regime ?: null, $sit,
        ]);

        if ($ok && $tipo === 'estabelecimento') {
            $uid = $conn->lastInsertId();
            $conn->prepare("INSERT INTO estabelecimentos (nome, email, usuario_id) VALUES (?,?,?)")
                 ->execute([$nome, $email, $uid]);
        }

        $mensagem = $ok ? 'Usuário cadastrado com sucesso!' : 'Erro ao cadastrar usuário.';
    }
}

// ── POST: inativar usuário ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'inativar') {
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid > 0 && $uid !== (int) $_SESSION['usuario_id']) {
        // administrativo só pode inativar usuários da própria lotação
        $where = $isAdmin ? "id = ?" : "id = ? AND lotacao_id = ?";
        $params = $isAdmin ? [$uid] : [$uid, $lotacaoId];
        $stmt = $conn->prepare("UPDATE usuarios SET situacao='inativo', inativado_em=NOW() WHERE $where");
        $stmt->execute($params);
        $mensagem = $stmt->rowCount() ? 'Usuário inativado com sucesso.' : 'Usuário não encontrado na sua lotação.';
    } else {
        $erro = 'Não é possível inativar o próprio usuário.';
    }
}

// ── POST: reativar usuário ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'reativar') {
    $uid = (int) ($_POST['uid'] ?? 0);
    if ($uid > 0) {
        $where = $isAdmin ? "id = ?" : "id = ? AND lotacao_id = ?";
        $params = $isAdmin ? [$uid] : [$uid, $lotacaoId];
        $stmt = $conn->prepare("UPDATE usuarios SET situacao='ativo', inativado_em=NULL WHERE $where");
        $stmt->execute($params);
        $mensagem = $stmt->rowCount() ? 'Usuário reativado com sucesso.' : 'Usuário não encontrado na sua lotação.';
    }
}

// ── POST: editar usuário ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'editar') {
    $uid             = (int) ($_POST['uid']              ?? 0);
    $nome            = trim($_POST['nome']               ?? '');
    $cpf             = preg_replace('/\D/', '', trim($_POST['cpf'] ?? ''));
    $data_nascimento = trim($_POST['data_nascimento']    ?? '') ?: null;
    $email           = trim($_POST['email']              ?? '');
    $senha           = trim($_POST['senha']              ?? '');
    $perfil_edit     = $_POST['perfil_novo']             ?? 'profissional';
    $ocupacao        = trim($_POST['ocupacao']           ?? '');
    $registro_classe = trim($_POST['registro_classe']    ?? '');
    $lotacao_edit    = (int) ($_POST['lotacao_id']       ?? 0) ?: null;
    $carga_horaria   = trim($_POST['carga_horaria']      ?? '');
    $regime          = $_POST['regime_contratacao']      ?? '';

    if (!$isAdmin && in_array($perfil_edit, ['admin', 'administrativo'])) {
        $erro = 'Sem permissão para definir este perfil.';
    } elseif ($uid <= 0 || $nome === '') {
        $erro = 'Preencha todos os campos obrigatórios (Nome).';
    } else {
        if (!$isAdmin) { $lotacao_edit = $lotacaoId; }
        $tipo_edit = ($perfil_edit === 'admin') ? 'admin' : 'funcionario';

        if ($senha !== '') {
            // atualizar com nova senha
            $stmt = $conn->prepare("
                UPDATE usuarios SET
                    nome=?, cpf=?, data_nascimento=?, email=?, senha=?, tipo=?,
                    perfil=?, ocupacao=?, registro_classe=?, lotacao_id=?,
                    carga_horaria=?, regime_contratacao=?
                WHERE id=?" . ($isAdmin ? '' : ' AND lotacao_id=?')
            );
            $params = [
                $nome, $cpf ?: null, $data_nascimento, $email, md5($senha),
                $tipo_edit, $perfil_edit, $ocupacao, $registro_classe ?: null,
                $lotacao_edit, $carga_horaria ?: null, $regime ?: null, $uid,
            ];
            if (!$isAdmin) { $params[] = $lotacaoId; }
        } else {
            // manter senha existente
            $stmt = $conn->prepare("
                UPDATE usuarios SET
                    nome=?, cpf=?, data_nascimento=?, email=?, tipo=?,
                    perfil=?, ocupacao=?, registro_classe=?, lotacao_id=?,
                    carga_horaria=?, regime_contratacao=?
                WHERE id=?" . ($isAdmin ? '' : ' AND lotacao_id=?')
            );
            $params = [
                $nome, $cpf ?: null, $data_nascimento, $email,
                $tipo_edit, $perfil_edit, $ocupacao, $registro_classe ?: null,
                $lotacao_edit, $carga_horaria ?: null, $regime ?: null, $uid,
            ];
            if (!$isAdmin) { $params[] = $lotacaoId; }
        }
        $stmt->execute($params);
        $mensagem = $stmt->rowCount() ? 'Profissional atualizado com sucesso.' : 'Nenhuma alteração detectada.';
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    $cid    = (int) ($_POST['id']     ?? 0);
    $status = $_POST['status'] ?? '';
    $permitidos = ['Aberto', 'Em andamento', 'Concluído'];
    if ($cid > 0 && in_array($status, $permitidos)) {
        $conn->prepare("UPDATE chamados SET status = ? WHERE id = ?")->execute([$status, $cid]);
        $mensagem = 'Status atualizado.';
    }
}

// ── Consultas de estatísticas ─────────────────────────────────────────────
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

// Lista de estabelecimentos (para select e exibição)
$estabelecimentos = $conn->query("SELECT id, nome FROM estabelecimentos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
$estabMap = array_column($estabelecimentos, 'nome', 'id');

// Lista de usuários
if ($isAdmin) {
    $usuarios = $conn->query("
        SELECT u.*, e.nome AS estab_nome
        FROM usuarios u
        LEFT JOIN estabelecimentos e ON u.lotacao_id = e.id
        ORDER BY u.tipo, u.situacao, u.nome
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $conn->prepare("
        SELECT u.*, e.nome AS estab_nome
        FROM usuarios u
        LEFT JOIN estabelecimentos e ON u.lotacao_id = e.id
        WHERE u.lotacao_id = ?
        ORDER BY u.situacao, u.nome
    ");
    $stmt->execute([$lotacaoId]);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Últimos 15 chamados
$chamados = $conn->query("
    SELECT c.id, c.tipo, c.subtipo, c.titulo, c.status, c.criado_em, u.nome AS usuario_nome
    FROM chamados c JOIN usuarios u ON c.usuario_id = u.id
    ORDER BY c.criado_em DESC LIMIT 15
")->fetchAll(PDO::FETCH_ASSOC);
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
    .card-stat.usuarios  { border-color: #6f42c1; }
    .card-stat.aberto    { border-color: #dc3545; }
    .card-stat.andamento { border-color: #ffc107; }
    .card-stat.concluido { border-color: #198754; }
    .card-stat.eventos   { border-color: #0d6efd; }
    tr.inativo td { opacity: .55; }
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
    <p class="text-muted">Gestão de usuários e visão geral — <?= date('d/m/Y') ?></p>

    <?php if ($mensagem): ?>
      <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Cards de resumo (apenas admin sistema) -->
    <?php if ($isAdmin): ?>
    <div class="row g-3 mb-4">
      <div class="col-6 col-md-3 col-lg-2">
        <div class="card card-stat usuarios h-100">
          <div class="card-body">
            <div class="text-muted small">Total Usuários</div>
            <div class="fs-2 fw-bold" style="color:#6f42c1"><?= $totalUsuarios ?></div>
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
    <?php endif; ?>

    <div class="row g-4">

      <!-- Coluna: Usuários -->
      <div class="col-12 <?= $isAdmin ? 'col-xl-7' : '' ?>">
        <div class="card mb-4">
          <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <span>&#128100; Profissionais Cadastrados</span>
            <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
              + Novo Profissional
            </button>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0 align-middle">
                <thead class="table-light">
                  <tr>
                    <th>#</th>
                    <th>Nome / CPF</th>
                    <th>Ocupação</th>
                    <th>Reg. Classe</th>
                    <th>Perfil</th>
                    <th>Lotação</th>
                    <th>Regime</th>
                    <th>Situação</th>
                    <th>Cadastrado</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($usuarios as $u):
                    $inativo = ($u['situacao'] ?? 'ativo') === 'inativo';
                  ?>
                  <tr class="<?= $inativo ? 'inativo' : '' ?>">
                    <td><?= (int)$u['id'] ?></td>
                    <td>
                      <div class="fw-semibold"><?= htmlspecialchars($u['nome']) ?></div>
                      <?php if (!empty($u['cpf'])): ?>
                        <small class="text-muted"><?= htmlspecialchars($u['cpf']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= htmlspecialchars($u['ocupacao'] ?? '') ?></td>
                    <td class="text-nowrap"><?= htmlspecialchars($u['registro_classe'] ?? '') ?></td>
                    <td>
                      <span class="badge bg-<?= $perfilBadge[$u['perfil'] ?? 'profissional'] ?? 'secondary' ?>">
                        <?= $perfilLabel[$u['perfil'] ?? 'profissional'] ?? htmlspecialchars($u['perfil'] ?? '') ?>
                      </span>
                    </td>
                    <td class="text-nowrap"><?= htmlspecialchars($u['estab_nome'] ?? '—') ?></td>
                    <td class="text-nowrap text-capitalize"><?= htmlspecialchars($u['regime_contratacao'] ?? '—') ?></td>
                    <td>
                      <span class="badge bg-<?= $inativo ? 'secondary' : 'success' ?>">
                        <?= $inativo ? 'Inativo' : 'Ativo' ?>
                      </span>
                      <?php if ($inativo && !empty($u['inativado_em'])): ?>
                        <div class="text-muted" style="font-size:.7rem;"><?= date('d/m/Y', strtotime($u['inativado_em'])) ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="text-nowrap">
                      <?php if (!empty($u['cadastrado_em'])): ?>
                        <small><?= date('d/m/Y', strtotime($u['cadastrado_em'])) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                        <div class="d-flex flex-column gap-1">
                          <button type="button"
                            class="btn btn-sm btn-outline-primary py-0 btn-editar-usuario"
                            data-id="<?= (int)$u['id'] ?>"
                            data-nome="<?= htmlspecialchars($u['nome'],           ENT_QUOTES) ?>"
                            data-cpf="<?= htmlspecialchars($u['cpf']  ?? '',      ENT_QUOTES) ?>"
                            data-nascimento="<?= htmlspecialchars($u['data_nascimento'] ?? '', ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars($u['email'],          ENT_QUOTES) ?>"
                            data-perfil="<?= htmlspecialchars($u['perfil'] ?? 'profissional', ENT_QUOTES) ?>"
                            data-ocupacao="<?= htmlspecialchars($u['ocupacao'] ?? '', ENT_QUOTES) ?>"
                            data-registro="<?= htmlspecialchars($u['registro_classe'] ?? '', ENT_QUOTES) ?>"
                            data-lotacao="<?= (int)($u['lotacao_id'] ?? 0) ?>"
                            data-carga="<?= htmlspecialchars($u['carga_horaria'] ?? '', ENT_QUOTES) ?>"
                            data-regime="<?= htmlspecialchars($u['regime_contratacao'] ?? '', ENT_QUOTES) ?>"
                          >&#9998; Editar</button>
                          <?php if (!$inativo): ?>
                            <form method="POST" onsubmit="return confirm('Inativar este profissional?')">
                              <input type="hidden" name="acao" value="inativar">
                              <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-warning py-0 w-100">Inativar</button>
                            </form>
                          <?php else: ?>
                            <form method="POST">
                              <input type="hidden" name="acao" value="reativar">
                              <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-outline-success py-0 w-100">Reativar</button>
                            </form>
                          <?php endif; ?>
                        </div>
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

      <!-- Coluna: Chamados recentes (apenas admin) -->
      <?php if ($isAdmin): ?>
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
      <?php endif; ?>

    </div><!-- /row -->
  </div><!-- /flex-grow-1 -->
</div><!-- /d-flex -->

<!-- Modal: Novo Profissional -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-labelledby="modalNovoUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="cadastrar">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="modalNovoUsuarioLabel">&#128100; Cadastrar Novo Profissional</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <h6 class="text-muted fw-semibold mb-3">Dados Pessoais</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">CPF <span class="text-danger">*</span></label>
              <input type="text" name="cpf" id="cpfNovo" class="form-control cpf-mask" placeholder="000.000.000-00" maxlength="14" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Data de Nascimento</label>
              <input type="date" name="data_nascimento" class="form-control">
            </div>
          </div>

          <h6 class="text-muted fw-semibold mt-4 mb-3">Acesso ao Sistema</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Senha <span class="text-danger">*</span></label>
              <input type="password" name="senha" class="form-control" required>
            </div>
          </div>

          <h6 class="text-muted fw-semibold mt-4 mb-3">Dados Profissionais</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Ocupação <span class="text-danger">*</span></label>
              <select name="ocupacao" class="form-select" required>
                <option value="">— Selecione —</option>
                <?php foreach ($ocupacoes as $oc): ?>
                  <option value="<?= htmlspecialchars($oc) ?>"><?= htmlspecialchars($oc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Registro de Classe <small class="text-muted">(ex: CRM 12345)</small></label>
              <input type="text" name="registro_classe" class="form-control" placeholder="Ex: CRM 12345, COREN 54321">
            </div>
            <div class="col-md-4">
              <label class="form-label">Perfil <span class="text-danger">*</span></label>
              <select name="perfil_novo" class="form-select" required>
                <?php if ($isAdmin): ?>
                  <option value="admin">Administrador</option>
                  <option value="administrativo">Administrativo</option>
                <?php endif; ?>
                <option value="coordenador">Coordenador</option>
                <option value="profissional" selected>Profissional</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lotação</label>
              <?php if ($isAdmin): ?>
                <select name="lotacao_id" class="form-select">
                  <option value="">— Selecione —</option>
                  <?php foreach ($estabelecimentos as $est): ?>
                    <option value="<?= (int)$est['id'] ?>"><?= htmlspecialchars($est['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="hidden" name="lotacao_id" value="<?= $lotacaoId ?>">
                <input type="text" class="form-control" value="<?= htmlspecialchars($estabMap[$lotacaoId] ?? '—') ?>" disabled>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <label class="form-label">Carga Horária <small class="text-muted">(h/sem)</small></label>
              <input type="number" name="carga_horaria" class="form-control" min="1" max="60" placeholder="Ex: 40">
            </div>
            <div class="col-md-6">
              <label class="form-label">Regime de Contratação</label>
              <select name="regime_contratacao" class="form-select">
                <option value="">— Selecione —</option>
                <option value="efetivo">Efetivo</option>
                <option value="temporario">Temporário</option>
              </select>
            </div>
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

<!-- Modal: Editar Profissional -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="acao" value="editar">
        <input type="hidden" name="uid" value="">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title" id="modalEditarUsuarioLabel">&#9998; Editar Profissional</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <h6 class="text-muted fw-semibold mb-3">Dados Pessoais</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nome Completo <span class="text-danger">*</span></label>
              <input type="text" name="nome" class="form-control" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">CPF</label>
              <input type="text" name="cpf" id="cpfEdit" class="form-control cpf-mask" placeholder="000.000.000-00" maxlength="14">
            </div>
            <div class="col-md-3">
              <label class="form-label">Data de Nascimento</label>
              <input type="date" name="data_nascimento" class="form-control">
            </div>
          </div>

          <h6 class="text-muted fw-semibold mt-4 mb-3">Acesso ao Sistema</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Nova Senha <small class="text-muted">(deixe em branco para manter)</small></label>
              <input type="password" name="senha" class="form-control" autocomplete="new-password">
            </div>
          </div>

          <h6 class="text-muted fw-semibold mt-4 mb-3">Dados Profissionais</h6>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Ocupação <span class="text-danger">*</span></label>
              <select name="ocupacao" class="form-select" required>
                <option value="">— Selecione —</option>
                <?php foreach ($ocupacoes as $oc): ?>
                  <option value="<?= htmlspecialchars($oc) ?>"><?= htmlspecialchars($oc) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Registro de Classe <small class="text-muted">(ex: CRM 12345)</small></label>
              <input type="text" name="registro_classe" class="form-control" placeholder="Ex: CRM 12345, COREN 54321">
            </div>
            <div class="col-md-4">
              <label class="form-label">Perfil <span class="text-danger">*</span></label>
              <select name="perfil_novo" class="form-select" required>
                <?php if ($isAdmin): ?>
                  <option value="admin">Administrador</option>
                  <option value="administrativo">Administrativo</option>
                <?php endif; ?>
                <option value="coordenador">Coordenador</option>
                <option value="profissional">Profissional</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Lotação</label>
              <?php if ($isAdmin): ?>
                <select name="lotacao_id" class="form-select">
                  <option value="">— Selecione —</option>
                  <?php foreach ($estabelecimentos as $est): ?>
                    <option value="<?= (int)$est['id'] ?>"><?= htmlspecialchars($est['nome']) ?></option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <input type="hidden" name="lotacao_id" value="<?= $lotacaoId ?>">
                <input type="text" class="form-control" value="<?= htmlspecialchars($estabMap[$lotacaoId] ?? '—') ?>" disabled>
              <?php endif; ?>
            </div>
            <div class="col-md-4">
              <label class="form-label">Carga Horária <small class="text-muted">(h/sem)</small></label>
              <input type="number" name="carga_horaria" class="form-control" min="1" max="60" placeholder="Ex: 40">
            </div>
            <div class="col-md-6">
              <label class="form-label">Regime de Contratação</label>
              <select name="regime_contratacao" class="form-select">
                <option value="">— Selecione —</option>
                <option value="efetivo">Efetivo</option>
                <option value="temporario">Temporário</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">Salvar Alterações</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── CPF mask ─────────────────────────────────────────────────────────────────
function mascaraCPF(v) {
  v = v.replace(/\D/g, '').substring(0, 11);
  v = v.replace(/^(\d{3})(\d)/, '$1.$2');
  v = v.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
  v = v.replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
  return v;
}
document.querySelectorAll('.cpf-mask').forEach(function(el) {
  el.addEventListener('input', function() { this.value = mascaraCPF(this.value); });
});

// ── Pre-fill the edit modal ───────────────────────────────────────────────────
document.querySelectorAll('.btn-editar-usuario').forEach(function(btn) {
  btn.addEventListener('click', function() {
    var d = this.dataset;
    var modal = document.getElementById('modalEditarUsuario');
    modal.querySelector('[name="uid"]').value             = d.id;
    modal.querySelector('[name="nome"]').value            = d.nome;
    // format stored CPF digits back to masked display
    modal.querySelector('[name="cpf"]').value             = mascaraCPF(d.cpf);
    modal.querySelector('[name="data_nascimento"]').value = d.nascimento;
    modal.querySelector('[name="email"]').value           = d.email;
    modal.querySelector('[name="senha"]').value           = '';

    var selPerfil = modal.querySelector('[name="perfil_novo"]');
    Array.from(selPerfil.options).forEach(function(o){ o.selected = (o.value === d.perfil); });

    var selOcup = modal.querySelector('[name="ocupacao"]');
    Array.from(selOcup.options).forEach(function(o){ o.selected = (o.value === d.ocupacao); });

    modal.querySelector('[name="registro_classe"]').value = d.registro;
    modal.querySelector('[name="carga_horaria"]').value   = d.carga;

    var selLot = modal.querySelector('[name="lotacao_id"]');
    if (selLot && selLot.tagName === 'SELECT') {
      Array.from(selLot.options).forEach(function(o){ o.selected = (o.value == d.lotacao); });
    }

    var selReg = modal.querySelector('[name="regime_contratacao"]');
    Array.from(selReg.options).forEach(function(o){ o.selected = (o.value === d.regime); });

    var bsModal = bootstrap.Modal.getOrCreateInstance(modal);
    bsModal.show();
  });
});
</script>
</body>
</html>
