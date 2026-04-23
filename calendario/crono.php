<?php
session_start();
require_once '../includes/db.php';

// Redireciona se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../index.php');
    exit;
}

$usuarioTipo = $_SESSION['usuario_tipo'];
$isAdmin = $usuarioTipo === 'admin';
$isEstabelecimento = $usuarioTipo === 'estabelecimento';
$isFuncionario = $usuarioTipo === 'funcionario';

// Para admin
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE tipo = 'funcionario'");
    $stmt->execute();
    $funcionarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Para estabelecimento
if ($isEstabelecimento) {
    $stmt = $conn->prepare("SELECT id, nome FROM usuarios WHERE tipo = 'funcionario'");
    $stmt->execute();
    $funcionariosEstab = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Para funcionário, buscar todos os estabelecimentos
$estabelecimentosFuncionario = [];
if ($isFuncionario) {
    $stmt = $conn->prepare("SELECT id, nome FROM estabelecimentos ORDER BY nome");
    $stmt->execute();
    $estabelecimentosFuncionario = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Cronograma</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.20.1/moment.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/fullcalendar.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.9.0/locale/pt-br.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <!-- jsPDF + html2canvas -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f8f9fa; }
    #calendar { max-width: 100%; margin: 40px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #ccc; padding: 10px; }
    .fc-event { font-size: 1rem; white-space: pre-line !important; }
    .fc-day-header, .fc-day-number { color: #212529; }
    .fc-today { background: #e9ecef !important; }
    .form-label { margin-top: 10px; }
    .navbar { margin-bottom: 30px; }
    #pdf-button { display: block; margin: 15px auto 0 auto; }
    .floating-edit-div {
        position: fixed;
        top: 20%;
        left: 50%;
        transform: translate(-50%, 0);
        z-index: 9999;
        background: #fff;
        border: 2px solid #007bff;
        border-radius: 10px;
        box-shadow: 0 6px 30px #0008;
        padding: 30px 28px 20px 28px;
        min-width: 320px;
        max-width: 95vw;
        display: none;
    }
    .floating-edit-div .close-edit-div {
        position: absolute;
        top: 6px;
        right: 10px;
        font-size: 1.5rem;
        color: #888;
        cursor: pointer;
    }
    @media print {
        #pdf-button { display: none !important; }
        .floating-edit-div { display: none !important; }
    }
  </style>
</head>
<body>

<div id="cabecalho-calendario">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary rounded mb-4">
  <div class="container-fluid">
    <span class="navbar-brand">Cronograma - <?= htmlspecialchars($_SESSION['usuario_nome']) ?></span>
    <div class="d-flex">
      <a class="btn btn-light btn-sm" href="../logout.php">Sair</a>
    </div>
  </div>
</nav>

<div class="container">

  <?php if ($isAdmin): ?>
    <div class="mb-3 row">
      <label for="filtro-funcionario" class="form-label col-sm-2 col-form-label">Filtrar por Funcionário:</label>
      <div class="col-sm-6">
        <select id="filtro-funcionario" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($funcionarios as $func): ?>
            <option value="<?= $func['id'] ?>"><?= htmlspecialchars($func['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($isEstabelecimento): ?>
    <div class="mb-3 row">
      <label for="filtro-funcionario-estab" class="form-label col-sm-3 col-form-label">Escolha o Funcionário para destaque:</label>
      <div class="col-sm-6">
        <select id="filtro-funcionario-estab" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($funcionariosEstab as $func): ?>
            <option value="<?= $func['id'] ?>"><?= htmlspecialchars($func['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($isFuncionario): ?>
    <div class="mb-3 row">
      <label for="filtro-estabelecimento-func" class="form-label col-sm-3 col-form-label">Escolha o Estabelecimento:</label>
      <div class="col-sm-6">
        <select id="filtro-estabelecimento-func" class="form-select">
          <option value="">Todos</option>
          <?php foreach ($estabelecimentosFuncionario as $estab): ?>
            <option value="<?= $estab['id'] ?>"><?= htmlspecialchars($estab['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  <?php endif; ?>

  <div id="calendar"></div>
</div>
</div>

<!-- Floating Edit Event Div -->
<div id="edit-event-div" class="floating-edit-div">
  <span class="close-edit-div" onclick="$('#edit-event-div').hide();">&times;</span>
  <h5>Editar Evento</h5>
  <form id="edit-event-form">
    <input type="hidden" id="edit-event-id" name="id">
    <div class="mb-3">
      <label for="edit-event-title" class="form-label">Título</label>
      <input type="text" class="form-control" id="edit-event-title" name="titulo" required>
    </div>
    <div class="mb-3">
      <label for="edit-event-date" class="form-label">Data</label>
      <input type="date" class="form-control" id="edit-event-date" name="data" required>
    </div>
    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
  </form>
</div>

<button id="pdf-button" class="btn btn-danger">Salvar em PDF</button>

<script>
const usuarioTipo = <?= json_encode($usuarioTipo) ?>;
const isAdmin = usuarioTipo === 'admin';
const isEstabelecimento = usuarioTipo === 'estabelecimento';
const isFuncionario = usuarioTipo === 'funcionario';
const usuarioId = <?= json_encode($_SESSION['usuario_id']) ?>;

function openEditDiv(event) {
    $('#edit-event-id').val(event.id);
    $('#edit-event-title').val(event.title.split('\n')[0]);
    let date = event.start;
    if(typeof date === 'object' && typeof date.format === 'function') {
        date = date.format('YYYY-MM-DD');
    }
    $('#edit-event-date').val(date);

    // Bloqueia datas retroativas ao editar
    let today = new Date();
    let yyyy = today.getFullYear();
    let mm = String(today.getMonth() + 1).padStart(2, '0');
    let dd = String(today.getDate()).padStart(2, '0');
    let minDate = yyyy + '-' + mm + '-' + dd;
    $('#edit-event-date').attr('min', minDate);

    $('#edit-event-div').show();
}

$(document).ready(function () {
  $('#calendar').fullCalendar({
    locale: 'pt-br',
    defaultView: 'month',
    selectable: true,
    editable: false,
    eventLimit: true,
    events: function(start, end, timezone, callback) {
      let dataParams = {};
      if (isAdmin) {
        dataParams.funcionario_id = $('#filtro-funcionario').val();
      } else if (isEstabelecimento) {
        dataParams.estab_id = usuarioId;
      } else if (isFuncionario) {
        dataParams.funcionario_id = usuarioId;
        let estabValue = $('#filtro-estabelecimento-func').val();
        if (estabValue) {
          dataParams.estab_id = estabValue;
        }
      }

      $.ajax({
        url: 'eventos.php',
        type: 'GET',
        data: dataParams,
        success: function (data) {
          let eventos = JSON.parse(data);
          if (isFuncionario) {
            eventos = eventos.map(function(ev) {
              return {
                id: ev.id,
                title: ev.title + (ev.estabelecimento ? '\n' + ev.estabelecimento : ''),
                start: ev.start
              };
            });
          } else if (isEstabelecimento) {
            let selectedFuncionario = $('#filtro-funcionario-estab').val();
            eventos = eventos.map(function(ev) {
              let eventObj = {
                id: ev.id,
                title: (ev.title ? ev.title : '') + (ev.profissional ? '\n' + ev.profissional : ''),
                start: ev.start
              };
              if (selectedFuncionario && ev.profissional_id == selectedFuncionario) {
                eventObj.color = '#28a745';
              } else {
                eventObj.color = '#007bff';
              }
              return eventObj;
            });
          } else {
            eventos = eventos.map(function(ev) {
              return {
                id: ev.id,
                title: (ev.title ? ev.title : '') + (ev.profissional ? '\n' + ev.profissional : ''),
                start: ev.start
              };
            });
          }
          callback(eventos);
        }
      });
    },
    eventClick: function(calEvent, jsEvent, view) {
        openEditDiv(calEvent);
    },
    dayClick: function(date, jsEvent, view) {
      var today = moment().startOf('day');
      var selected = moment(date).startOf('day');
      if (selected.isBefore(today)) {
        alert('Não é permitido selecionar datas retroativas.');
        return;
      }
      if (isEstabelecimento) {
        const funcionarioId = $('#filtro-funcionario-estab').val();
        if (!funcionarioId) {
          alert('Selecione um funcionário para o evento!');
          return;
        }
        const titulo = prompt("Digite o título do evento:");
        if (titulo) {
          $.ajax({
            url: 'adicionar_evento.php',
            type: 'POST',
            data: {
              titulo: titulo,
              data: date.format(),
              funcionario_id: funcionarioId
            },
            success: function(response) {
              alert(response);
              $('#calendar').fullCalendar('refetchEvents');
            },
            error: function(xhr, status, error) {
              alert('Erro ao adicionar evento: ' + xhr.responseText);
            }
          });
        }
      } else if (isFuncionario) {
        const estabelecimentoId = $('#filtro-estabelecimento-func').val();
        if (!estabelecimentoId) {
          alert('Selecione o estabelecimento!');
          return;
        }
        const titulo = prompt("Digite o título do evento:");
        if (titulo) {
          $.ajax({
            url: 'adicionar_evento.php',
            type: 'POST',
            data: {
              titulo: titulo,
              data: date.format(),
              funcionario_id: usuarioId,
              estab_id: estabelecimentoId
            },
            success: function(response) {
              alert(response);
              $('#calendar').fullCalendar('refetchEvents');
            },
            error: function(xhr, status, error) {
              alert('Erro ao adicionar evento: ' + xhr.responseText);
            }
          });
        }
      } else {
        const titulo = prompt("Digite o título do evento:");
        if (titulo) {
          $.ajax({
            url: 'adicionar_evento.php',
            type: 'POST',
            data: {
              titulo: titulo,
              data: date.format()
            },
            success: function(response) {
              alert(response);
              $('#calendar').fullCalendar('refetchEvents');
            },
            error: function(xhr, status, error) {
              alert('Erro ao adicionar evento: ' + xhr.responseText);
            }
          });
        }
      }
    }
  });

  // Filtros
  if (isAdmin) {
    $('#filtro-funcionario').on('change', function () {
      $('#calendar').fullCalendar('refetchEvents');
    });
  }
  if (isEstabelecimento) {
    $('#filtro-funcionario-estab').on('change', function () {
      $('#calendar').fullCalendar('refetchEvents');
    });
  }
  if (isFuncionario) {
    $('#filtro-estabelecimento-func').on('change', function () {
      $('#calendar').fullCalendar('refetchEvents');
    });
  }

  // PDF
  $('#pdf-button').click(function () {
    $('#pdf-button').hide();
    html2canvas(document.querySelector("#cabecalho-calendario")).then(canvas => {
      const imgData = canvas.toDataURL('image/png');
      const pdf = new window.jspdf.jsPDF({
        orientation: 'landscape',
        unit: 'px',
        format: [canvas.width, canvas.height]
      });
      pdf.addImage(imgData, 'PNG', 0, 0, canvas.width, canvas.height);
      pdf.save("cronograma.pdf");
      $('#pdf-button').show();
    });
  });

  // Edit Event Form Submit
  $('#edit-event-form').submit(function(e) {
    e.preventDefault();
    var id = $('#edit-event-id').val();
    var titulo = $('#edit-event-title').val();
    var data = $('#edit-event-date').val();

    // Bloqueia envio de datas retroativas mesmo se usuário burlar o min do input
    var today = new Date();
    var dataSelecionada = new Date(data);
    today.setHours(0,0,0,0);
    dataSelecionada.setHours(0,0,0,0);
    if (dataSelecionada < today) {
        alert("Não é permitido selecionar datas retroativas.");
        return false;
    }

    $.ajax({
        url: 'editar_evento.php',
        type: 'POST',
        data: {
            id: id,
            titulo: titulo,
            data: data
        },
        success: function(response) {
            alert(response);
            $('#edit-event-div').hide();
            $('#calendar').fullCalendar('refetchEvents');
        },
        error: function(xhr) {
            alert('Erro ao editar evento: ' + xhr.responseText);
        }
    });
  });
});
</script>

</body>
</html>
