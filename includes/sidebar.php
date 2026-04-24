<?php
// $basePath must be set by the including file (e.g. '..' for pages one level below root)
// $activePage must be set to one of: 'dashboard', 'cronograma', 'frequencia', 'chamado'
if (!isset($basePath))   { $basePath   = '..'; }
if (!isset($activePage)) { $activePage = ''; }
?>
<nav class="d-flex flex-column flex-shrink-0 bg-light border-end p-2" style="width:200px; min-height:calc(100vh - 56px);">
  <ul class="nav nav-pills flex-column mb-auto mt-2">
    <li class="nav-item">
      <a class="nav-link <?= ($activePage === 'dashboard') ? 'active' : 'text-dark' ?>"
         href="<?= $basePath ?>/dashboard/index.php">
        &#128202; Dashboard
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage === 'cronograma') ? 'active' : 'text-dark' ?>"
         href="<?= $basePath ?>/calendario/crono.php">
        &#128197; Cronograma
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage === 'frequencia') ? 'active' : 'text-dark' ?>"
         href="<?= $basePath ?>/frequencia/index.php">
        &#128203; Frequência
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage === 'chamado') ? 'active' : 'text-dark' ?>"
         href="<?= $basePath ?>/chamado/index.php">
        &#128295; Chamado
      </a>
    </li>
    <?php if (isset($_SESSION['usuario_tipo']) && $_SESSION['usuario_tipo'] === 'admin'): ?>
    <li class="nav-item">
      <a class="nav-link <?= ($activePage === 'admin') ? 'active' : 'text-dark' ?>"
         href="<?= $basePath ?>/admin/painel.php">
        &#9881; Painel Admin
      </a>
    </li>
    <?php endif; ?>
  </ul>
</nav>
