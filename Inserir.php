<?php
session_start();
include_once("Conexao.php");   // fornece $conn
require_once("bootstrap.php");

$nome = $_SESSION['nome'] ?? null;
if (!$nome) {
    header("Location: Login.php");
    exit;
}

try {
    adicionarAoCarrinho(
        $conn,
        $_POST['produto'],
        (float) $_POST['valor'],
        $_POST['imagem'],
        $nome
    );
    $_SESSION['mensagem'] = "✅ Pedido adicionado ao carrinho!";
} catch (\Throwable $e) {
    $_SESSION['mensagem'] = "Erro ao adicionar pedido: " . $e->getMessage();
}

header("Location: menu.php");
exit;
