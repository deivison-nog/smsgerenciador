<?php
session_start();
require_once 'includes/db.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && md5($senha) === $usuario['senha']) {
        $situacao = $usuario['situacao'] ?? 'ativo';
        if ($situacao === 'inativo') {
            $erro = "Usuário inativo. Entre em contato com o administrador.";
        } else {
            $_SESSION['usuario_id']         = $usuario['id'];
            $_SESSION['usuario_nome']       = $usuario['nome'];
            $_SESSION['usuario_tipo']       = $usuario['tipo'];
            $_SESSION['usuario_perfil']     = $usuario['perfil'] ?? ($usuario['tipo'] === 'admin' ? 'admin' : 'profissional');
            $_SESSION['usuario_lotacao_id'] = $usuario['lotacao_id'] ?? null;

            $perfil = $_SESSION['usuario_perfil'];
            if ($usuario['tipo'] === 'admin' || $perfil === 'administrativo') {
                header("Location: admin/painel.php");
            } elseif ($perfil === 'coordenador') {
                header("Location: dashboard/index.php");
            } elseif ($usuario['tipo'] === 'estabelecimento') {
                header("Location: dashboard/index.php");
            } else {
                // profissional
                header("Location: calendario/crono.php");
            }
            exit;
        }
    } else {
        $erro = "E-mail ou senha inválidos.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <title>Login - Smsgerenciador</title>
  <style>
    body { font-family: Arial, sans-serif; background: #f4f4f4; }
    .login-container {
      width: 300px;
      margin: 100px auto;
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 0 10px #ccc;
    }
    input[type="email"], input[type="password"] {
      width: -webkit-fill-available; padding: 10px; margin: 10px 0;
    }
    input[type="submit"] {
      width: 100%; padding: 10px; background: #28a745; color: white; border: none;
    }
    .erro { color: red; font-size: 14px; text-align: center; }
  </style>
</head>
<body>
  <div class="login-container">
    <h2>Login</h2>
    <?php if ($erro): ?>
      <p class="erro"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>
    <form method="POST">
      <label>E-mail:</label>
      <input type="email" name="email" required>
      <label>Senha:</label>
      <input type="password" name="senha" required>
      <input type="submit" value="Entrar">
    </form>
  </div>
</body>
</html>
