<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$perfil   = $_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_tipo'] === 'admin' ? 'admin' : 'profissional');
$isAdmin  = ($perfil === 'admin');
$temAcesso = in_array($perfil, ['admin', 'administrativo', 'coordenador']);

if (!$temAcesso) {
    header('Location: ../calendario/crono.php');
    exit;
}

// ── Determina o estabelecimento ───────────────────────────────────────────
$estabelecimentos = $conn->query("SELECT id, nome FROM estabelecimentos ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

if ($isAdmin) {
    $estab_id = (int) ($_GET['estab_id'] ?? ($estabelecimentos[0]['id'] ?? 0));
} else {
    $estab_id = (int) ($_SESSION['usuario_lotacao_id'] ?? 0);
}

$estab_nome = '';
if ($estab_id > 0) {
    $s = $conn->prepare("SELECT nome FROM estabelecimentos WHERE id = ?");
    $s->execute([$estab_id]);
    $estab_nome = $s->fetchColumn() ?: '';
}

// ── Mês / Ano ─────────────────────────────────────────────────────────────
$hoje    = new DateTime();
$ano     = (int) ($_GET['ano'] ?? $hoje->format('Y'));
$mes     = (int) ($_GET['mes'] ?? $hoje->format('n'));
if ($mes < 1)  { $mes = 12; $ano--; }
if ($mes > 12) { $mes = 1;  $ano++; }

$diasNoMes   = (int) (new DateTime("$ano-$mes-01"))->format('t');
$nomeMes     = strftime('%B', mktime(0, 0, 0, $mes, 1, $ano));   // pt fallback below
$nomesMeses  = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho',
                'Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$nomeMes     = $nomesMeses[$mes - 1];

// URLs de navegação
$prevMes = $mes - 1; $prevAno = $ano;
if ($prevMes < 1) { $prevMes = 12; $prevAno--; }
$nextMes = $mes + 1; $nextAno = $ano;
if ($nextMes > 12) { $nextMes = 1;  $nextAno++; }
$qEstab  = $isAdmin ? "&estab_id=$estab_id" : '';
$prevUrl = "index.php?ano=$prevAno&mes=$prevMes$qEstab";
$nextUrl = "index.php?ano=$nextAno&mes=$nextMes$qEstab";

// Abreviações dos dias da semana
$diasSemAbbr = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];

// ── POST: salvar frequência ───────────────────────────────────────────────
$mensagem = '';
$erro     = '';
$statusPermitidos = ['P', 'F', 'FJ', 'FO'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $estab_id > 0) {
    $dados = $_POST['f'] ?? [];
    foreach ($dados as $uid => $dias) {
        $uid = (int) $uid;
        if ($uid <= 0) continue;
        foreach ($dias as $dia => $status) {
            $dia = (int) $dia;
            if ($dia < 1 || $dia > $diasNoMes) continue;
            if ($status === '' || $status === null) {
                $conn->prepare("DELETE FROM frequencia
                    WHERE usuario_id=? AND estabelecimento_id=? AND ano=? AND mes=? AND dia=?")
                    ->execute([$uid, $estab_id, $ano, $mes, $dia]);
            } elseif (in_array($status, $statusPermitidos)) {
                $conn->prepare("INSERT INTO frequencia
                    (usuario_id, estabelecimento_id, ano, mes, dia, status, registrado_por)
                    VALUES (?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE status=VALUES(status),
                        registrado_por=VALUES(registrado_por),
                        registrado_em=CURRENT_TIMESTAMP")
                    ->execute([$uid, $estab_id, $ano, $mes, $dia, $status, $_SESSION['usuario_id']]);
            }
        }
    }
    $mensagem = 'Frequência salva com sucesso!';
}

// ── Carrega profissionais do estabelecimento ──────────────────────────────
$profissionais = [];
if ($estab_id > 0) {
    $s = $conn->prepare("
        SELECT id, nome, ocupacao, perfil
        FROM usuarios
        WHERE lotacao_id = ? AND tipo = 'funcionario' AND situacao = 'ativo'
        ORDER BY nome
    ");
    $s->execute([$estab_id]);
    $profissionais = $s->fetchAll(PDO::FETCH_ASSOC);
}

// ── Carrega registros do mês ─────────────────────────────────────────────
$registros = [];
if ($estab_id > 0 && !empty($profissionais)) {
    $s = $conn->prepare("
        SELECT usuario_id, dia, status
        FROM frequencia
        WHERE estabelecimento_id = ? AND ano = ? AND mes = ?
    ");
    $s->execute([$estab_id, $ano, $mes]);
    foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $registros[$r['usuario_id']][$r['dia']] = $r['status'];
    }
}

// Pré-calcular dia-da-semana para cada dia do mês
$diasInfo = [];
for ($d = 1; $d <= $diasNoMes; $d++) {
    $dow = (int) (new DateTime("$ano-$mes-$d"))->format('w'); // 0=Dom, 6=Sáb
    $diasInfo[$d] = ['dow' => $dow, 'fim_semana' => ($dow === 0 || $dow === 6)];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Frequência - Smsgerenciador</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; margin: 0; }
    .freq-table th, .freq-table td { white-space: nowrap; padding: 3px 4px; font-size: .82rem; }
    .col-nome { position: sticky; left: 0; background: #fff; z-index: 2; min-width: 160px; border-right: 2px solid #dee2e6 !important; }
    thead .col-nome { background: #e9ecef; z-index: 3; }
    .th-fim { background: #ced4da !important; color: #495057; }
    .td-fim { background: #f1f3f5; }
    .sel-freq {
      width: 52px; min-width: 52px; padding: 1px 2px;
      font-size: .78rem; border-radius: 3px; cursor: pointer;
      border: 1px solid #ced4da; text-align: center;
    }
    .total-col { font-weight: 600; min-width: 32px; text-align: center; }
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
  <?php $basePath = '..'; $activePage = 'frequencia'; require_once '../includes/sidebar.php'; ?>

  <div class="flex-grow-1 p-3">
    <h2>&#128203; Frequência</h2>

    <?php if ($mensagem): ?>
      <div class="alert alert-success alert-dismissible fade show py-2">
        <?= htmlspecialchars($mensagem) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if ($erro): ?>
      <div class="alert alert-danger alert-dismissible fade show py-2">
        <?= htmlspecialchars($erro) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <!-- Controles: estabelecimento (admin) + mês/ano -->
    <div class="card mb-3">
      <div class="card-body py-2">
        <div class="row g-2 align-items-end">

          <?php if ($isAdmin): ?>
          <div class="col-auto">
            <label class="form-label mb-1 small fw-semibold">Estabelecimento</label>
            <form method="GET" id="formEstab" class="d-flex gap-2 align-items-center">
              <input type="hidden" name="ano" value="<?= $ano ?>">
              <input type="hidden" name="mes" value="<?= $mes ?>">
              <select name="estab_id" class="form-select form-select-sm" onchange="document.getElementById('formEstab').submit()">
                <?php foreach ($estabelecimentos as $est): ?>
                  <option value="<?= (int)$est['id'] ?>" <?= $est['id'] == $estab_id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($est['nome']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
          <?php else: ?>
          <div class="col-auto">
            <span class="fw-semibold">&#127968; <?= htmlspecialchars($estab_nome) ?></span>
          </div>
          <?php endif; ?>

          <!-- Navegação de mês -->
          <div class="col-auto ms-auto">
            <div class="d-flex align-items-center gap-2">
              <a href="<?= htmlspecialchars($prevUrl) ?>" class="btn btn-sm btn-outline-secondary">&lsaquo; Anterior</a>
              <span class="fw-bold px-2"><?= "$nomeMes / $ano" ?></span>
              <a href="<?= htmlspecialchars($nextUrl) ?>" class="btn btn-sm btn-outline-secondary">Próximo &rsaquo;</a>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Legenda -->
    <div class="d-flex flex-wrap gap-3 mb-3 align-items-center">
      <strong class="small">Legenda:</strong>
      <span class="badge px-3 py-2" style="background:#d1e7dd;color:#0f5132;font-size:.82rem;">P — Presente</span>
      <span class="badge px-3 py-2" style="background:#f8d7da;color:#842029;font-size:.82rem;">F — Falta</span>
      <span class="badge px-3 py-2" style="background:#fff3cd;color:#664d03;font-size:.82rem;">FJ — Falta Justificada</span>
      <span class="badge px-3 py-2" style="background:#e2e3e5;color:#41464b;font-size:.82rem;">FO — Folga</span>
      <span class="badge px-3 py-2" style="background:#f8f9fa;color:#6c757d;border:1px solid #dee2e6;font-size:.82rem;">— Não registrado</span>
    </div>

    <?php if ($estab_id <= 0): ?>
      <div class="alert alert-warning">
        <?php if ($isAdmin): ?>
          Nenhum estabelecimento cadastrado no sistema.
        <?php else: ?>
          Você não possui lotação definida. Contate o administrador do sistema.
        <?php endif; ?>
      </div>
    <?php elseif (empty($profissionais)): ?>
      <div class="alert alert-info">
        Nenhum profissional ativo lotado em <strong><?= htmlspecialchars($estab_nome) ?></strong> este mês.
      </div>
    <?php else: ?>

    <form method="POST" action="index.php?ano=<?= $ano ?>&mes=<?= $mes ?><?= $qEstab ?>">

      <div class="table-responsive" style="max-height:70vh; overflow:auto;">
        <table class="table table-bordered freq-table mb-0">
          <thead>
            <!-- Linha 1: Cabeçalho com dias numerados -->
            <tr class="table-light">
              <th class="col-nome text-center">Profissional</th>
              <?php for ($d = 1; $d <= $diasNoMes; $d++):
                $info = $diasInfo[$d];
              ?>
                <th class="text-center <?= $info['fim_semana'] ? 'th-fim' : '' ?>">
                  <?= $d ?><br>
                  <span style="font-size:.7rem;font-weight:normal;"><?= $diasSemAbbr[$info['dow']] ?></span>
                </th>
              <?php endfor; ?>
              <th class="total-col text-success" title="Presentes">P</th>
              <th class="total-col text-danger"  title="Faltas">F</th>
              <th class="total-col text-warning"  title="Faltas Justificadas">FJ</th>
              <th class="total-col text-secondary" title="Folgas">FO</th>
              <th title="Marcar todos os dias úteis como Presente"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($profissionais as $p):
              $totalP = 0; $totalF = 0; $totalFJ = 0; $totalFO = 0;
            ?>
            <tr>
              <td class="col-nome">
                <div class="fw-semibold"><?= htmlspecialchars($p['nome']) ?></div>
                <small class="text-muted"><?= htmlspecialchars($p['ocupacao'] ?? '') ?></small>
              </td>
              <?php for ($d = 1; $d <= $diasNoMes; $d++):
                $info   = $diasInfo[$d];
                $status = $registros[$p['id']][$d] ?? '';
                if ($status === 'P')  $totalP++;
                elseif ($status === 'F')  $totalF++;
                elseif ($status === 'FJ') $totalFJ++;
                elseif ($status === 'FO') $totalFO++;
              ?>
                <td class="p-0 text-center <?= $info['fim_semana'] ? 'td-fim' : '' ?>"
                    data-uid="<?= (int)$p['id'] ?>" data-dia="<?= $d ?>"
                    data-fim="<?= $info['fim_semana'] ? '1' : '0' ?>">
                  <select name="f[<?= (int)$p['id'] ?>][<?= $d ?>]"
                          class="sel-freq"
                          data-uid="<?= (int)$p['id'] ?>" data-dia="<?= $d ?>">
                    <option value="">—</option>
                    <option value="P"  <?= $status === 'P'  ? 'selected' : '' ?>>P</option>
                    <option value="F"  <?= $status === 'F'  ? 'selected' : '' ?>>F</option>
                    <option value="FJ" <?= $status === 'FJ' ? 'selected' : '' ?>>FJ</option>
                    <option value="FO" <?= $status === 'FO' ? 'selected' : '' ?>>FO</option>
                  </select>
                </td>
              <?php endfor; ?>
              <td class="total-col text-success"><?= $totalP ?></td>
              <td class="total-col text-danger"><?= $totalF ?></td>
              <td class="total-col text-warning"><?= $totalFJ ?></td>
              <td class="total-col text-secondary"><?= $totalFO ?></td>
              <td class="p-0 text-center">
                <button type="button" class="btn btn-sm btn-outline-success py-0 btn-todos-p"
                        data-uid="<?= (int)$p['id'] ?>" title="Marcar dias úteis como Presente">P&#10003;</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">&#128190; Salvar Frequência</button>
        <button type="button" id="btnTodosP" class="btn btn-outline-success">
          Marcar todos (dias úteis) como Presente
        </button>
      </div>

    </form>

    <?php endif; ?>

  </div><!-- /.flex-grow-1 -->
</div><!-- /.d-flex -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Cores por status
const freqColors = {
  '':   '',
  'P':  '#d1e7dd',
  'F':  '#f8d7da',
  'FJ': '#fff3cd',
  'FO': '#e2e3e5',
};

function colorSelect(sel) {
  sel.style.backgroundColor = freqColors[sel.value] ?? '';
}

// Inicializa cores
document.querySelectorAll('.sel-freq').forEach(function(sel) {
  colorSelect(sel);
  sel.addEventListener('change', function() {
    colorSelect(this);
    updateRowTotals(this.dataset.uid);
  });
});

// Atualiza totais de uma linha (colunas P/F/FJ/FO)
function updateRowTotals(uid) {
  const sels = document.querySelectorAll('.sel-freq[data-uid="' + uid + '"]');
  let counts = {'P': 0, 'F': 0, 'FJ': 0, 'FO': 0};
  sels.forEach(function(s) { if (s.value) counts[s.value] = (counts[s.value] || 0) + 1; });
  // find the totals cells in the same row (after the day selects)
  const firstSel = sels[0];
  if (!firstSel) return;
  const row = firstSel.closest('tr');
  const totalCells = row.querySelectorAll('.total-col');
  if (totalCells.length >= 4) {
    totalCells[0].textContent = counts['P']  || 0;
    totalCells[1].textContent = counts['F']  || 0;
    totalCells[2].textContent = counts['FJ'] || 0;
    totalCells[3].textContent = counts['FO'] || 0;
  }
}

// Botão "Todos P" por linha (apenas dias úteis)
document.querySelectorAll('.btn-todos-p').forEach(function(btn) {
  btn.addEventListener('click', function() {
    const uid = this.dataset.uid;
    document.querySelectorAll('.sel-freq[data-uid="' + uid + '"]').forEach(function(sel) {
      const td = sel.closest('td');
      if (td && td.dataset.fim === '0') {  // não é fim de semana
        sel.value = 'P';
        colorSelect(sel);
      }
    });
    updateRowTotals(uid);
  });
});

// Botão "Marcar todos (dias úteis) como Presente"
document.getElementById('btnTodosP')?.addEventListener('click', function() {
  document.querySelectorAll('.sel-freq').forEach(function(sel) {
    const td = sel.closest('td');
    if (td && td.dataset.fim === '0') {
      sel.value = 'P';
      colorSelect(sel);
    }
  });
  document.querySelectorAll('.btn-todos-p').forEach(function(btn) {
    updateRowTotals(btn.dataset.uid);
  });
});
</script>
</body>
</html>
