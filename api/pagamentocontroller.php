<?php

/**
 * API de Pagamentos — /api/pagamentocontroller.php
 * Instrumentada com Monolog (logs).
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
$inicio = microtime(true);

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$nome   = $_GET['nome']   ?? null;
$status = $_GET['status'] ?? null;
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ── GET ─────────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    try {
        $pagamentos = buscarPagamentos($conn, $nome, $status);
        echo json_encode($pagamentos);
    } catch (\Throwable $e) {
        LoggerService::error('Erro ao buscar pagamentos', ['exception' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ── POST ────────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $campos = ['nome', 'metodo', 'valor'];
    foreach ($campos as $campo) {
        if (!isset($body[$campo])) {
            http_response_code(400);
            echo json_encode(['erro' => "Campo obrigatório ausente: $campo"]);
            exit;
        }
    }

    $metodosValidos = ['pix', 'cartao', 'dinheiro'];
    if (!in_array($body['metodo'], $metodosValidos, true)) {
        http_response_code(400);
        echo json_encode(['erro' => "Método inválido. Use: " . implode(', ', $metodosValidos)]);
        exit;
    }

    try {
        registrarPagamento($conn, $body['nome'], $body['metodo'], (float) $body['valor']);

        LoggerService::info('Pagamento registrado', [
            'usuario' => $body['nome'],
            'metodo'  => $body['metodo'],
            'valor'   => $body['valor'],
            'duracao_ms' => round((microtime(true) - $inicio) * 1000, 2),
        ]);

        http_response_code(201);
        echo json_encode(['mensagem' => 'Pagamento registrado com sucesso.', 'status' => 'pendente']);
    } catch (\Throwable $e) {
        LoggerService::error('Erro ao registrar pagamento', [
            'usuario'   => $body['nome'] ?? 'unknown',
            'exception' => $e->getMessage(),
        ]);
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ── PATCH ───────────────────────────────────────────────────────────────────
if ($method === 'PATCH') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Informe o id na URL: ?id=1']);
        exit;
    }

    $statusValidos = ['pendente', 'aprovado', 'recusado'];
    if (!isset($body['status']) || !in_array($body['status'], $statusValidos, true)) {
        http_response_code(400);
        echo json_encode(['erro' => 'Status inválido. Use: ' . implode(', ', $statusValidos)]);
        exit;
    }

    try {
        $stmt = $conn->prepare('UPDATE Pagamentos SET Status = :status WHERE id = :id');
        $stmt->execute([':status' => $body['status'], ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['erro' => 'Pagamento não encontrado.']);
        } else {
            LoggerService::info('Status de pagamento atualizado', [
                'id'     => $id,
                'status' => $body['status'],
            ]);
            echo json_encode(['mensagem' => "Status atualizado para '{$body['status']}'."]);
        }
    } catch (\Throwable $e) {
        LoggerService::error('Erro ao atualizar status', ['id' => $id, 'exception' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

// ── DELETE ──────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    if (!$id) {
        http_response_code(400);
        echo json_encode(['erro' => 'Informe o id na URL: ?id=1']);
        exit;
    }

    try {
        $stmt = $conn->prepare('DELETE FROM Pagamentos WHERE id = :id');
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['erro' => 'Pagamento não encontrado.']);
        } else {
            LoggerService::info('Pagamento removido', ['id' => $id]);
            echo json_encode(['mensagem' => 'Pagamento removido.']);
        }
    } catch (\Throwable $e) {
        LoggerService::error('Erro ao remover pagamento', ['id' => $id, 'exception' => $e->getMessage()]);
        http_response_code(500);
        echo json_encode(['erro' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['erro' => 'Método não permitido.']);
