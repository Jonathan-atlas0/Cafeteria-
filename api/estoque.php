<?php
/**
 * API de Estoque — /api/estoque.php
 * GET    /api/estoque.php            -> lista todos os produtos em estoque
 * GET    /api/estoque.php?produto=X -> retorna produto X
 * POST   /api/estoque.php            -> cria/atualiza produto (body: produto, quantidade)
 * PATCH  /api/estoque.php?produto=X -> ajusta quantidade (body: delta)
 * DELETE /api/estoque.php?produto=X -> remove produto do estoque
 */

declare(strict_types=1);

require_once __DIR__ . '/../Conexao.php';
require_once __DIR__ . '/../bootstrap.php';

use Cafeteria\Observability\LoggerService;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$method = $_SERVER['REQUEST_METHOD'];

// Certifica-se que a tabela exista
$conn->exec("CREATE TABLE IF NOT EXISTS Estoque (
    id SERIAL PRIMARY KEY,
    produto VARCHAR(100) UNIQUE,
    quantidade INTEGER DEFAULT 0
)");
$seedParam = $_GET['seed'] ?? null;

// Endpoint helper: popular tabela com produtos de exemplo quando solicitado via ?seed=1
if ($seedParam === '1' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $samples = [
        ['produto' => 'Capucino', 'quantidade' => 10],
        ['produto' => 'Mocha', 'quantidade' => 10],
        ['produto' => 'Macchiato', 'quantidade' => 10],
        ['produto' => 'Expresso', 'quantidade' => 10],
        ['produto' => 'Leite e Caramelo', 'quantidade' => 10],
        ['produto' => 'Cafe', 'quantidade' => 10],
    ];
    $ins = $conn->prepare('INSERT INTO Estoque (produto, quantidade) VALUES (:produto, :quantidade) ON CONFLICT (produto) DO NOTHING');
    foreach ($samples as $s) {
        $ins->execute([':produto' => $s['produto'], ':quantidade' => $s['quantidade']]);
    }
    $rows = [];
    $stmt = $conn->query('SELECT produto, quantidade FROM Estoque ORDER BY produto');
    while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
    http_response_code(201);
    echo json_encode(['mensagem' => 'Amostra inserida', 'produtos' => $rows]);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$produto = $_GET['produto'] ?? null;

try {
    if ($method === 'GET') {
        if ($produto) {
            $stmt = $conn->prepare('SELECT produto, quantidade FROM Estoque WHERE produto = :produto');
            $stmt->execute([':produto' => $produto]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['erro' => 'Produto não encontrado']);
                exit;
            }
            echo json_encode($row);
            exit;
        }

        $rows = [];
        $stmt = $conn->query('SELECT produto, quantidade FROM Estoque ORDER BY produto');
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $rows[] = $r;
        echo json_encode($rows);
        exit;
    }

    if ($method === 'POST') {
        if (empty($body['produto']) || !isset($body['quantidade'])) {
            http_response_code(400);
            echo json_encode(['erro' => 'Campos obrigatórios: produto, quantidade']);
            exit;
        }
        $stmt = $conn->prepare('INSERT INTO Estoque (produto, quantidade) VALUES (:produto, :quantidade) ON CONFLICT (produto) DO UPDATE SET quantidade = EXCLUDED.quantidade');
        $stmt->execute([':produto' => $body['produto'], ':quantidade' => (int)$body['quantidade']]);
        http_response_code(201);
        echo json_encode(['mensagem' => 'Produto criado/atualizado']);
        exit;
    }

    if ($method === 'PATCH') {
        if (!$produto) {
            http_response_code(400);
            echo json_encode(['erro' => 'Informe ?produto=nome']);
            exit;
        }
        if (!isset($body['delta'])) {
            http_response_code(400);
            echo json_encode(['erro' => 'Enviar body JSON com campo "delta" (inteiro)']);
            exit;
        }
        $delta = (int)$body['delta'];
        $conn->beginTransaction();
        $stmt = $conn->prepare('SELECT quantidade FROM Estoque WHERE produto = :produto FOR UPDATE');
        $stmt->execute([':produto' => $produto]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['erro' => 'Produto não encontrado']);
            exit;
        }
        $nova = (int)$row['quantidade'] + $delta;
        if ($nova < 0) {
            $conn->rollBack();
            http_response_code(400);
            echo json_encode(['erro' => 'Quantidade insuficiente']);
            exit;
        }
        $upd = $conn->prepare('UPDATE Estoque SET quantidade = :q WHERE produto = :produto');
        $upd->execute([':q' => $nova, ':produto' => $produto]);
        $conn->commit();
        echo json_encode(['mensagem' => 'Quantidade ajustada', 'quantidade' => $nova]);
        exit;
    }

    if ($method === 'DELETE') {
        if (!$produto) {
            http_response_code(400);
            echo json_encode(['erro' => 'Informe ?produto=nome']);
            exit;
        }
        $stmt = $conn->prepare('DELETE FROM Estoque WHERE produto = :produto');
        $stmt->execute([':produto' => $produto]);
        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['erro' => 'Produto não encontrado']);
            exit;
        }
        echo json_encode(['mensagem' => 'Produto removido']);
        exit;
    }

    http_response_code(405);
    echo json_encode(['erro' => 'Método não permitido']);
} catch (\Throwable $e) {
    LoggerService::error('Erro na API de estoque', ['exception' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
