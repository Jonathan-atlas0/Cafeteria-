<?php
/**
 * Pagamento.php
 * Exibe o resumo do carrinho, coleta o método de pagamento,
 * registra o pagamento e finaliza o pedido.
 */

session_start();
include_once("Conexao.php");
require_once("bootstrap.php");

$nome = $_SESSION['nome'] ?? null;
if (!$nome) {
    header("Location: Login.php");
    exit;
}

// ── POST: processar pagamento ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodosValidos = ['pix', 'cartao', 'dinheiro'];
    $metodo = $_POST['metodo'] ?? '';

    if (!in_array($metodo, $metodosValidos, true)) {
        $_SESSION['mensagem'] = "Método de pagamento inválido.";
        header("Location: Pagamento.php");
        exit;
    }

    $itens = buscarCarrinho($conn, $nome);
    if (empty($itens)) {
        $_SESSION['mensagem'] = "Carrinho vazio.";
        header("Location: carrinho.php");
        exit;
    }

    $total = array_sum(array_column($itens, 'valor'));

    try {
        // 1. Registra o pagamento (status 'pendente')
        registrarPagamento($conn, $nome, $metodo, (float) $total);

        // 2. Finaliza o pedido (move carrinho → Finalizado)
        finalizarPedido($conn, $nome);

        $_SESSION['mensagem'] = "Pagamento registrado e pedido finalizado! ☕";
        header("Location: carrinho.php");
        exit;
    } catch (\Throwable $e) {
        $_SESSION['mensagem'] = "Erro ao processar pagamento: " . $e->getMessage();
        header("Location: Pagamento.php");
        exit;
    }
}

// ── GET: exibir tela de pagamento ────────────────────────────────────────────
$itens = buscarCarrinho($conn, $nome);
if (empty($itens)) {
    header("Location: carrinho.php");
    exit;
}

$total = array_sum(array_column($itens, 'valor'));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <title>Cafezin — Pagamento</title>
    <style>
        .pagamento-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 2rem;
        }

        .pagamento-card {
            background: rgba(1, 1, 3, 0.88);
            border: 0.1rem solid rgba(255, 255, 255, 0.12);
            border-radius: 1.2rem;
            padding: 3.6rem;
            width: 100%;
            max-width: 52rem;
            backdrop-filter: blur(8px);
        }

        .pagamento-card h2 {
            color: var(--main-color);
            font-size: 2.4rem;
            text-align: center;
            margin-bottom: 2.8rem;
            letter-spacing: 0.1em;
        }

        /* Resumo do pedido */
        .resumo {
            background: rgba(255,255,255,0.04);
            border: 0.1rem solid rgba(255,255,255,0.1);
            border-radius: 0.8rem;
            padding: 1.6rem 2rem;
            margin-bottom: 2.8rem;
        }

        .resumo-titulo {
            color: var(--main-color);
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 1.2rem;
        }

        .resumo-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: rgba(255,255,255,0.8);
            font-size: 1.4rem;
            padding: 0.6rem 0;
            border-bottom: 0.1rem solid rgba(255,255,255,0.06);
        }

        .resumo-item:last-of-type {
            border-bottom: none;
        }

        .resumo-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.2rem;
            padding-top: 1.2rem;
            border-top: 0.1rem solid rgba(211, 173, 127, 0.3);
        }

        .resumo-total span:first-child {
            color: var(--main-color);
            font-size: 1.4rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        .resumo-total span:last-child {
            color: #fff;
            font-size: 2rem;
            font-weight: 700;
        }

        /* Seleção de método */
        .metodos-titulo {
            color: var(--main-color);
            font-size: 1.2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 1.4rem;
        }

        .metodos-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.2rem;
            margin-bottom: 3rem;
        }

        .metodo-label {
            cursor: pointer;
        }

        .metodo-label input[type="radio"] {
            display: none;
        }

        .metodo-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.8rem;
            padding: 1.6rem 1rem;
            border: 0.15rem solid rgba(255,255,255,0.15);
            border-radius: 0.8rem;
            color: rgba(255,255,255,0.6);
            font-size: 1.3rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            transition: all 0.2s ease;
            background: rgba(255,255,255,0.03);
        }

        .metodo-box .icone {
            font-size: 2.4rem;
            line-height: 1;
        }

        .metodo-label input[type="radio"]:checked + .metodo-box {
            border-color: var(--main-color);
            color: var(--main-color);
            background: rgba(211, 173, 127, 0.08);
            box-shadow: 0 0 0 1px var(--main-color);
        }

        .metodo-box:hover {
            border-color: rgba(211, 173, 127, 0.5);
            color: rgba(255,255,255,0.85);
        }

        /* Botões */
        .botoes {
            display: flex;
            gap: 1.2rem;
        }

        .btn-pagar {
            flex: 1;
            background-color: var(--main-color);
            color: var(--black);
            font-size: 1.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 1.4rem;
            border: none;
            border-radius: 0.6rem;
            cursor: pointer;
            transition: opacity 0.2s;
        }

        .btn-pagar:hover { opacity: 0.88; }

        .btn-voltar {
            padding: 1.4rem 2rem;
            border: 0.1rem solid rgba(255,255,255,0.2);
            border-radius: 0.6rem;
            color: rgba(255,255,255,0.6);
            background: transparent;
            font-size: 1.4rem;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: border-color 0.2s, color 0.2s;
        }

        .btn-voltar:hover {
            border-color: rgba(255,255,255,0.5);
            color: #fff;
        }

        .aviso-metodo {
            color: #e57373;
            font-size: 1.2rem;
            text-align: center;
            margin-top: -2rem;
            margin-bottom: 1.6rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="caixa-video">
        <div class="mascara"></div>
        <img src="./img/images_1.jpg" alt="banner">
    </div>

    <div class="pagamento-wrapper">
        <div class="pagamento-card">
            <h2>☕ Pagamento</h2>

            <!-- Resumo do pedido -->
            <div class="resumo">
                <p class="resumo-titulo">Resumo do pedido</p>
                <?php foreach ($itens as $item): ?>
                    <div class="resumo-item">
                        <span><?= htmlspecialchars($item['produto']) ?></span>
                        <span>R$ <?= number_format((float)$item['valor'], 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
                <div class="resumo-total">
                    <span>Total</span>
                    <span>R$ <?= number_format($total, 2, ',', '.') ?></span>
                </div>
            </div>

            <!-- Formulário de pagamento -->
            <form method="post" action="Pagamento.php" onsubmit="return validar()">
                <p class="metodos-titulo">Forma de pagamento</p>

                <div class="metodos-grid">
                    <label class="metodo-label">
                        <input type="radio" name="metodo" value="pix">
                        <div class="metodo-box">
                            <span class="icone">📱</span>
                            Pix
                        </div>
                    </label>
                    <label class="metodo-label">
                        <input type="radio" name="metodo" value="cartao">
                        <div class="metodo-box">
                            <span class="icone">💳</span>
                            Cartão
                        </div>
                    </label>
                    <label class="metodo-label">
                        <input type="radio" name="metodo" value="dinheiro">
                        <div class="metodo-box">
                            <span class="icone">💵</span>
                            Dinheiro
                        </div>
                    </label>
                </div>

                <p class="aviso-metodo" id="aviso">Selecione uma forma de pagamento.</p>

                <div class="botoes">
                    <a href="carrinho.php" class="btn-voltar">← Voltar</a>
                    <button type="submit" class="btn-pagar">Confirmar pagamento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function validar() {
            const selecionado = document.querySelector('input[name="metodo"]:checked');
            if (!selecionado) {
                document.getElementById('aviso').style.display = 'block';
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
