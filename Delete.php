<?php
session_start();
include_once("Conexao.php");
require_once("bootstrap.php");

$nome = $_SESSION['nome'] ?? null;
if (!$nome) {
    header("Location: Login.php");
    exit;
}

limparCarrinho($conn, $nome);

header("Location: carrinho.php");
exit;
