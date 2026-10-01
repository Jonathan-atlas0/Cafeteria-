<?php
if (!isset($pedidos)) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once __DIR__ . '/Conexao.php';
    require_once __DIR__ . '/bootstrap.php';
    try {
        $pedidos = buscarPedidosFinalizados($conn);
    } catch (\Throwable $e) {
        $pedidos = [];
    }
}

// Carrega estoque
try {
    $stmtEstoque = $conn->query("SELECT produto, quantidade FROM Estoque ORDER BY produto");
    $estoque = $stmtEstoque->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {
    $estoque = [];
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>☕ Painel Admin — Cafeteria</title>
    <link rel="stylesheet" href="/styles.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #1a0a00;
            color: #f5e6d3;
            min-height: 100vh;
        }

        .admin-header {
            background: linear-gradient(135deg, #2c1503, #4a2c0a);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #8B4513;
            box-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }

        .admin-header h1 { font-size: 1.6rem; color: #d4a056; }
        .admin-header .sub { font-size: 0.85rem; color: #a07040; margin-top: 4px; }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 20px;
            border-bottom: 2px solid #3a1f08;
        }

        .tab-btn {
            padding: 10px 20px;
            background: none;
            border: none;
            color: #a07040;
            font-family: 'Montserrat', sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s;
        }

        .tab-btn:hover { color: #d4a056; }
        .tab-btn.ativo { color: #d4a056; border-bottom-color: #d4a056; }

        .tab-content { display: none; }
        .tab-content.ativo { display: block; }

        .admin-body {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            padding: 28px 32px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .painel-live h2, .historico h2 {
            font-size: 1rem;
            color: #d4a056;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #4a2c0a;
        }

        .pedido-card {
            background: linear-gradient(135deg, #2c1503, #3a1f08);
            border: 1px solid #5a3010;
            border-left: 4px solid #d4a056;
            border-radius: 10px;
            padding: 16px 20px;
            margin-bottom: 12px;
            animation: slideIn 0.4s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .pedido-card .usuario { font-weight: 700; color: #d4a056; font-size: 1rem; }
        .pedido-card .detalhes { color: #a07040; font-size: 0.85rem; margin-top: 4px; }
        .pedido-card .total { font-size: 1.1rem; color: #4caf50; font-weight: 700; margin-top: 8px; }
        .pedido-card .horario { font-size: 0.75rem; color: #666; margin-top: 4px; }

        .historico {
            background: #200e00;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #3a1f08;
            max-height: 80vh;
            overflow-y: auto;
        }

        .historico-item {
            padding: 10px 0;
            border-bottom: 1px solid #2c1503;
            font-size: 0.85rem;
        }

        .historico-item:last-child { border-bottom: none; }
        .historico-item .h-user { color: #d4a056; font-weight: 600; }
        .historico-item .h-produto { color: #c8a070; }
        .historico-item .h-valor { color: #4caf50; float: right; font-weight: 700; }

        .stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: #200e00;
            border: 1px solid #3a1f08;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }

        .stat-box .num { font-size: 2rem; font-weight: 700; color: #d4a056; }
        .stat-box .label { font-size: 0.75rem; color: #666; margin-top: 4px; text-transform: uppercase; }

        /* Estoque */
        .estoque-tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .estoque-tabela th {
            text-align: left;
            padding: 10px 14px;
            color: #d4a056;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            border-bottom: 2px solid #3a1f08;
        }

        .estoque-tabela td {
            padding: 10px 14px;
            border-bottom: 1px solid #2c1503;
            color: #f5e6d3;
        }

        .estoque-tabela tr:hover td { background: #200e00; }

        .qtd-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .qtd-badge.ok     { background: #1a3a1a; color: #4caf50; }
        .qtd-badge.baixo  { background: #3a2a00; color: #ff9800; }
        .qtd-badge.zero   { background: #3a1010; color: #f44336; }

        .btn-estoque {
            padding: 4px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 4px;
        }

        .btn-mais  { background: #1a3a1a; color: #4caf50; }
        .btn-menos { background: #3a1010; color: #f44336; }
        .btn-mais:hover  { background: #4caf50; color: #fff; }
        .btn-menos:hover { background: #f44336; color: #fff; }

        .flash { animation: flash 0.5s; }
        @keyframes flash {
            0%,100% { background: linear-gradient(135deg, #2c1503, #3a1f08); }
            50%      { background: linear-gradient(135deg, #4a2c08, #6b3d10); }
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1a0a00; }
        ::-webkit-scrollbar-thumb { background: #4a2c0a; border-radius: 3px; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
</head>
<body>

<div class="admin-header">
    <div>
        <h1>☕ Painel Admin — Cafeteria</h1>
        <div class="sub">Pedidos finalizados e estoque</div>
    </div>
</div>

<div class="admin-body">

    <div class="painel-live">

        <div class="stats">
            <div class="stat-box">
                <div class="num" id="cnt-historico"><?= count($pedidos) ?></div>
                <div class="label">Total finalizado</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-btn ativo" onclick="trocarTab('estoque', this)">📦 Estoque</button>
        </div>

        <!-- Tab: Estoque -->
        <div id="tab-estoque" class="tab-content ativo">
            <table class="estoque-tabela" id="tabela-estoque">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($estoque)): ?>
                    <tr><td colspan="3" style="color:#555;text-align:center;padding:30px">Nenhum produto cadastrado no estoque.</td></tr>
                <?php else: ?>
                    <?php foreach ($estoque as $item): ?>
                        <?php
                            $qtd = (int)$item['quantidade'];
                            $badge = $qtd === 0 ? 'zero' : ($qtd <= 5 ? 'baixo' : 'ok');
                        ?>
                        <tr id="estoque-<?= htmlspecialchars($item['produto']) ?>">
                            <td><?= htmlspecialchars($item['produto']) ?></td>
                            <td><span class="qtd-badge <?= $badge ?>" id="qtd-<?= htmlspecialchars($item['produto']) ?>"><?= $qtd ?></span></td>
                            <td>
                                <button class="btn-estoque btn-mais"  onclick="ajustarEstoque('<?= htmlspecialchars($item['produto']) ?>', 1)">+1</button>
                                <button class="btn-estoque btn-menos" onclick="ajustarEstoque('<?= htmlspecialchars($item['produto']) ?>', -1)">-1</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <div class="historico">
        <h2>📋 Histórico (Banco)</h2>
        <?php if (empty($pedidos)): ?>
            <p style="color:#555;font-size:.85rem">Nenhum pedido finalizado ainda.</p>
        <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
                <div class="historico-item">
                    <span class="h-valor">R$ <?= number_format($p['valor'], 2, ',', '.') ?></span>
                    <div class="h-user"><?= htmlspecialchars($p['nome']) ?></div>
                    <div class="h-produto"><?= htmlspecialchars($p['produto']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</div>

<script>
    // ── Tabs ──────────────────────────────────────────────────────────────
    function trocarTab(nome, btn) {
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('ativo'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('ativo'));
        document.getElementById('tab-' + nome).classList.add('ativo');
        btn.classList.add('ativo');
    }

    // ── Estoque ───────────────────────────────────────────────────────────
    function ajustarEstoque(produto, delta) {
        fetch(`/api/estoque.php?produto=${encodeURIComponent(produto)}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delta })
        })
        .then(r => r.json())
        .then(data => {
            if (data.quantidade !== undefined) {
                const span = document.getElementById('qtd-' + produto);
                if (span) {
                    const qtd = data.quantidade;
                    span.textContent = qtd;
                    span.className = 'qtd-badge ' + (qtd === 0 ? 'zero' : qtd <= 5 ? 'baixo' : 'ok');
                }
            } else {
                alert(data.erro || 'Erro ao ajustar estoque');
            }
        })
        .catch(() => alert('Erro de conexão com a API de estoque'));
    }

</script>

</body>
</html>