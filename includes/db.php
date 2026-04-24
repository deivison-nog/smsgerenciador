<?php
$host = 'localhost';
$dbname = 'smsgerenciador';
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

// ─── Auto-migrations ─────────────────────────────────────────────────────────
$_migrations = [
    // New columns for usuarios
    "ALTER TABLE usuarios ADD COLUMN cpf VARCHAR(14) DEFAULT NULL AFTER nome",
    "ALTER TABLE usuarios ADD COLUMN data_nascimento DATE DEFAULT NULL AFTER cpf",
    "ALTER TABLE usuarios ADD COLUMN perfil VARCHAR(30) NOT NULL DEFAULT 'profissional' AFTER tipo",
    "ALTER TABLE usuarios ADD COLUMN registro_classe VARCHAR(50) DEFAULT NULL AFTER ocupacao",
    "ALTER TABLE usuarios ADD COLUMN lotacao_id INT DEFAULT NULL AFTER registro_classe",
    "ALTER TABLE usuarios ADD COLUMN carga_horaria VARCHAR(30) DEFAULT NULL AFTER lotacao_id",
    "ALTER TABLE usuarios ADD COLUMN regime_contratacao VARCHAR(20) DEFAULT NULL AFTER carga_horaria",
    "ALTER TABLE usuarios ADD COLUMN situacao VARCHAR(10) NOT NULL DEFAULT 'ativo' AFTER regime_contratacao",
    "ALTER TABLE usuarios ADD COLUMN cadastrado_em DATETIME DEFAULT CURRENT_TIMESTAMP AFTER situacao",
    "ALTER TABLE usuarios ADD COLUMN inativado_em DATETIME DEFAULT NULL AFTER cadastrado_em",
    // Ensure existing admins have perfil='admin'
    "UPDATE usuarios SET perfil='admin' WHERE tipo='admin' AND perfil='profissional'",
    // Chamados table (idempotent)
    "CREATE TABLE IF NOT EXISTS chamados (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        tipo VARCHAR(100) NOT NULL,
        subtipo VARCHAR(150) NOT NULL DEFAULT '',
        titulo VARCHAR(150) NOT NULL,
        descricao TEXT,
        status VARCHAR(30) NOT NULL DEFAULT 'Aberto',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // Frequencia table
    "CREATE TABLE IF NOT EXISTS frequencia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT NOT NULL,
        estabelecimento_id INT NOT NULL,
        ano SMALLINT NOT NULL,
        mes TINYINT NOT NULL,
        dia TINYINT NOT NULL,
        status ENUM('P','F','FJ','FO') NOT NULL,
        observacao VARCHAR(200) DEFAULT NULL,
        registrado_por INT NOT NULL,
        registrado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_freq (usuario_id, estabelecimento_id, ano, mes, dia)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];
foreach ($_migrations as $_sql) {
    try { $conn->exec($_sql); } catch (PDOException $e) { /* already applied */ }
}
unset($_migrations, $_sql);
