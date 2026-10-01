<?php
session_start();
include_once("Conexao.php");
require_once("bootstrap.php");

$nome = $_SESSION['nome'] ?? null;
if (!$nome) {
    header("Location: Login.php");
    exit;
}

$itens = buscarCarrinho($conn, $nome);
$total = count($itens);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <title>Cafezin — Carrinho</title>
</head>
<body>
    <div class="caixa-video">
        <div class="mascara"></div>
        <img src="./img/images_1.jpg" alt="banner">
    </div>
    <header class="header">
       <section>
            <a href=""></a>
            <nav class="navbar">
                <a href="index.html">Home</a>
                <a href="menu.php">Menu</a>
                <a href="#address" onclick="mostrarModal()">Endereço</a>
            </nav>
            <div class="icons">
                <a href="carrinho.php">
                    <img style="height: min-content;" width="30" height="30" src="./img/shopping-cart--v1.png" alt="carrinho">
                </a>
                <a href="Login.php">
                    <img src="./img/icone-login1.png" alt="login" style="width: 30px; height: 30px;">
                </a>
            </div>
       </section>
    </header>

    <section class="menu">
        <div>
            <h3 class="titulo">SEU <span>CARRINHO</span> <?= htmlspecialchars($nome) ?></h3>
        </div>
    </section>

    <section class="menu" id="menu">
        <div class="menu-cardapio">
            <?php if ($total > 0): ?>
                <?php foreach ($itens as $item): ?>
                    <div class="cardapio">
                        <img src="<?= htmlspecialchars($item['imagem']) ?>" alt="item">
                        <h3><?= htmlspecialchars($item['produto']) ?></h3>
                        <div class="preço">R$ <?= number_format($item['valor'], 2, ',', '.') ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="cardapio">
                    <h2>Nenhum item no carrinho 😾☕</h2>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($total > 0 || strtolower($nome) === 'admin'): ?>
            <form action="Pagamento.php">
                <button type="submit" class="botao-link">Finalizar pedido</button>
            </form>
            <form action="Delete.php" method="post">
                <button type="submit" class="botao-link">Apagar carrinho</button>
            </form>
        <?php endif; ?>
    </section>

    <?php if (isset($_SESSION['mensagem'])): ?>
        <script>alert('<?= addslashes($_SESSION['mensagem']) ?>');</script>
        <?php unset($_SESSION['mensagem']); ?>
    <?php endif; ?>

    <div class="modal">
        <h3 class="titulo"><span>Nosso</span> Endereço</h3>
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d79504.29052471898!2d-34.93370605136719!3d-7.158351299999987!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x7acc29aa9ce26af%3A0xaf72a28cd6276079!2sUNIP%C3%8A%20-%20Centro%20Universit%C3%A1rio%20-%20Campus%20Jo%C3%A3o%20Pessoa!5e1!3m2!1spt-BR!2sbr!4v1762444279959!5m2!1spt-BR!2sbr"
            width="600" height="450" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
    <div class="mascara-modal" onclick="esconderModal()"></div>
    <script src="./scripts.js"></script>
</body>
</html>