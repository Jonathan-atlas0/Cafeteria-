<?php
session_start();
include_once("Conexao.php");
require_once("bootstrap.php");

$nome = $_SESSION['nome'] ?? null;
if (!$nome) {
    header("Location: Login.php");
    exit;
}

// Modo Admin: exibe todos os pedidos finalizados
if (strtolower($nome) === 'admin') {
    $pedidos = buscarPedidosFinalizados($conn);
    echo "<h2>Painel Admin — Pedidos Finalizados</h2><ul>";
    foreach ($pedidos as $p) {
        echo "<li>{$p['nome']} — {$p['produto']} — R$ " . number_format($p['valor'], 2, ',', '.') . "</li>";
    }
    echo "</ul>";
    exit;
}

// Modo usuário: finaliza o pedido
try {
    finalizarPedido($conn, $nome);
    $_SESSION['mensagem'] = "Pedido realizado com sucesso ☕😋";
} catch (\Throwable $e) {
    $_SESSION['mensagem'] = "Erro ao finalizar: " . $e->getMessage();
}

header("Location: carrinho.php");
exit;
