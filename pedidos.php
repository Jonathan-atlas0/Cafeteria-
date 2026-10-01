<?php
/**
 * pedidos.php
 * Funções de acesso ao banco (carrinho, pedidos e pagamentos).
 * Todas recebem a conexão PDO ($conn) criada em Conexao.php.
 */

function buscarCarrinho(PDO $conn, string $nome): array
{
    $stmt = $conn->prepare(
        "SELECT id,
                Produto AS produto,
                Valor   AS valor,
                Imagem  AS imagem,
                Nome    AS nome
         FROM tabela
         WHERE Nome = :nome
         ORDER BY id"
    );
    $stmt->execute([':nome' => $nome]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function adicionarAoCarrinho(PDO $conn, string $produto, float $valor, string $imagem, string $nome): void
{
    $stmt = $conn->prepare(
        "INSERT INTO tabela (Produto, Valor, Imagem, Nome)
         VALUES (:produto, :valor, :imagem, :nome)"
    );

    $ok = $stmt->execute([
        ':produto' => $produto,
        ':valor'   => $valor,
        ':imagem'  => $imagem,
        ':nome'    => $nome,
    ]);

    if (!$ok) {
        throw new RuntimeException("Falha ao inserir item no carrinho.");
    }
}

function limparCarrinho(PDO $conn, string $nome): void
{
    $stmt = $conn->prepare("DELETE FROM tabela WHERE Nome = :nome");
    $stmt->execute([':nome' => $nome]);
}

function buscarPedidosFinalizados(PDO $conn, ?string $nome = null): array
{
    if ($nome) {
        $stmt = $conn->prepare("SELECT * FROM Finalizado WHERE Nome = :nome ORDER BY id DESC");
        $stmt->execute([':nome' => $nome]);
    } else {
        $stmt = $conn->prepare("SELECT * FROM Finalizado ORDER BY id DESC");
        $stmt->execute();
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function registrarPagamento(PDO $conn, string $nome, string $metodo, float $valor): void
{
    $conn->beginTransaction();

    try {
        $conn->exec("CREATE TABLE IF NOT EXISTS Pagamentos (
            id               SERIAL PRIMARY KEY,
            Nome             VARCHAR(50)    NOT NULL,
            MetodoPagamento  VARCHAR(20)    NOT NULL,
            Valor            DECIMAL(10,2)  NOT NULL,
            Status           VARCHAR(20)    NOT NULL DEFAULT 'pendente',
            CriadoEm         TIMESTAMP      NOT NULL DEFAULT NOW()
        )");

        $stmt = $conn->prepare(
            "INSERT INTO Pagamentos (Nome, MetodoPagamento, Valor, Status)
             VALUES (:nome, :metodo, :valor, 'pendente')"
        );
        $stmt->execute([
            ':nome'   => $nome,
            ':metodo' => $metodo,
            ':valor'  => $valor,
        ]);

        $conn->commit();
    } catch (\Throwable $e) {
        $conn->rollBack();
        throw $e;
    }
}

function buscarPagamentos(PDO $conn, ?string $nome = null, ?string $status = null): array
{
    $where  = [];
    $params = [];

    if ($nome !== null) {
        $where[]         = 'Nome = :nome';
        $params[':nome'] = $nome;
    }

    if ($status !== null) {
        $where[]           = 'Status = :status';
        $params[':status'] = $status;
    }

    $sql = "SELECT id, Nome, MetodoPagamento, Valor, Status, CriadoEm FROM Pagamentos";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY CriadoEm DESC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Move o carrinho do usuário para a tabela Finalizado (em transação)
 * e dá baixa no estoque.
 */
function finalizarPedido(PDO $conn, string $nome): void
{
    $inicio = microtime(true);

    \Cafeteria\Observability\LoggerService::info('Finalizando pedido', [
        'usuario'   => $nome,
        'timestamp' => date('c'),
    ]);

    $conn->beginTransaction();

    try {
        $sel = $conn->prepare("SELECT * FROM tabela WHERE Nome = :nome");
        $sel->execute([':nome' => $nome]);
        $itens = $sel->fetchAll(PDO::FETCH_ASSOC);

        if (empty($itens)) {
            $conn->rollBack();
            \Cafeteria\Observability\LoggerService::warning('Tentativa de finalizar carrinho vazio', ['usuario' => $nome]);
            throw new RuntimeException("Carrinho vazio — nada a finalizar.");
        }

        $ins = $conn->prepare(
            "INSERT INTO Finalizado (Produto, Valor, Imagem, Nome)
             VALUES (:produto, :valor, :imagem, :nome)"
        );

        $total = 0.0;
        foreach ($itens as $item) {
            $ins->execute([
                ':produto' => $item['produto'] ?? $item['Produto'],
                ':valor'   => $item['valor']   ?? $item['Valor'],
                ':imagem'  => $item['imagem']  ?? $item['Imagem'] ?? '',
                ':nome'    => $item['nome']    ?? $item['Nome'],
            ]);
            $total += (float)($item['valor'] ?? $item['Valor']);
        }

        $del = $conn->prepare("DELETE FROM tabela WHERE Nome = :nome");
        $del->execute([':nome' => $nome]);

        $conn->commit();

        // Baixa no estoque, fora da transação principal para não bloquear
        // o pedido caso o produto não exista no estoque ainda.
        foreach ($itens as $item) {
            $produto = $item['produto'] ?? $item['Produto'];
            try {
                $patch = $conn->prepare(
                    "UPDATE Estoque SET quantidade = quantidade - 1
                     WHERE produto = :produto AND quantidade > 0"
                );
                $patch->execute([':produto' => $produto]);

                if ($patch->rowCount() === 0) {
                    \Cafeteria\Observability\LoggerService::warning('Produto não encontrado ou sem estoque', [
                        'produto' => $produto,
                    ]);
                }
            } catch (\Throwable $e) {
                \Cafeteria\Observability\LoggerService::error('Falha ao dar baixa no estoque', [
                    'produto'   => $produto,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        \Cafeteria\Observability\LoggerService::info('Pedido finalizado com sucesso', [
            'usuario'    => $nome,
            'itens'      => count($itens),
            'total'      => round($total, 2),
            'duracao_ms' => round((microtime(true) - $inicio) * 1000, 2),
        ]);

    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        \Cafeteria\Observability\LoggerService::error('Erro ao finalizar pedido', [
            'usuario'   => $nome,
            'exception' => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);
        throw $e;
    }
}
