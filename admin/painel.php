<?php
session_start();
require_once '../includes/db.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_tipo'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$mensagem = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $senha = md5($_POST['senha']);
    $tipo = $_POST['tipo'];
    $ocupacao = $_POST['ocupacao'];

    // Primeiro cadastra o usuário normalmente
    $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, tipo, ocupacao) VALUES (?, ?, ?, ?, ?)");
    $success = $stmt->execute([$nome, $email, $senha, $tipo, $ocupacao]);

    // Se for estabelecimento, cadastra também na tabela estabelecimentos
    if ($success && $tipo === 'estabelecimento') {
        $usuario_id = $conn->lastInsertId();
        // Adicione aqui outros campos de estabelecimento se desejar
        $stmt2 = $conn->prepare("INSERT INTO estabelecimentos (nome, email, usuario_id) VALUES (?, ?, ?)");
        $stmt2->execute([$nome, $email, $usuario_id]);
    }

    if ($success) {
        $mensagem = "Usuário cadastrado com sucesso!";
    } else {
        $mensagem = "Erro ao cadastrar usuário.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Painel do Administrador</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f2f2f2; }
    .container { width: 500px; margin: 50px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px #ccc; }
    input, select { width: 100%; padding: 10px; margin: 10px 0; }
    input[type="submit"] { background: #28a745; color: white; border: none; }
    .mensagem { color: green; font-weight: bold; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Painel do Administrador</h2>
    <p>Bem-vindo, <?= htmlspecialchars($_SESSION['usuario_nome']) ?> | <a href="../logout.php">Sair</a></p>

    <?php if ($mensagem): ?>
      <p class="mensagem"><?= htmlspecialchars($mensagem) ?></p>
    <?php endif; ?>

    <form method="POST">
      <label>Nome:</label>
      <input type="text" name="nome" required>

      <label>Ocupação:</label><br>
      <input type="text" name="ocupacao" required><br>

      <label>Email:</label>
      <input type="email" name="email" required>

      <label>Senha:</label>
      <input type="password" name="senha" required>

      <label>Tipo de Usuário:</label>
      <select name="tipo">
        <option value="funcionario">Funcionário</option>
        <option value="admin">Administrador</option>
        <option value="estabelecimento">Estabelecimento</option>
      </select>

      <input type="submit" value="Cadastrar Usuário">
    </form>
  </div>
</body>
</html>
