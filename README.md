# Cafeteria — API e App

Visão geral
- Projeto PHP/Apache com APIs para gestão de estoque, pagamentos e frontend estático.
- Usa PostgreSQL e logs estruturados (Monolog) via Docker Compose.

Requisitos
- Docker e Docker Compose (ou Docker Desktop) instalados.
- (Opcional) PHP CLI para testes locais sem Docker.

Rodando com Docker (recomendado)
1. Build e subir serviços:
```bash
docker-compose build --no-cache app
docker-compose up -d
```
2. Checar containers:
```bash
docker-compose ps
```
3. Verificar módulos PHP dentro do container `app` (confirma `pdo_pgsql`):
```bash
docker-compose exec app php -m
```

Seed (popular estoque)
- Endpoint para inserir itens de exemplo (insere apenas se não existirem):
  - `GET http://localhost:8080/api/estoque.php?seed=1`

Exemplo (curl):
```bash
curl -sS "http://localhost:8080/api/estoque.php?seed=1" | jq
```

API de Estoque (`api/estoque.php`)
- GET /api/estoque.php -> lista todos os produtos
- GET /api/estoque.php?produto=Nome -> busca um produto
- POST /api/estoque.php (JSON body: `{ "produto": "Nome", "quantidade": 5 }`) -> cria/atualiza
- PATCH /api/estoque.php?produto=Nome (JSON body: `{ "delta": -1 }`) -> ajusta quantidade
- DELETE /api/estoque.php?produto=Nome -> remove produto

A tabela usada é `Estoque` no banco `Cafeteria` (serviço `db` - Postgres).

Acessando o banco
- Consultar com `psql` dentro do container:
```bash
docker-compose exec db psql -U cafeteria -d Cafeteria -c "SELECT id, produto, quantidade FROM estoque ORDER BY id;"
```
- Abrir psql interativo:
```bash
docker-compose exec db psql -U cafeteria -d Cafeteria
# então: SELECT * FROM estoque;
```
- pgAdmin disponível em: http://localhost:8081

Rodar localmente sem Docker (rápido)
- Você pode iniciar o servidor PHP embutido (apenas para testes leves):
```bash
php -S localhost:8000 -t .
# então acessar http://localhost:8000/api/estoque.php?seed=1
```

Logs
- Logs em JSON gravados em `logs/` (Monolog, ver `observability/LoggerService.php`).
- Health check: `GET /health.php` (readiness) e `GET /health.php?live=1` (liveness).

Alterações que fiz na workspace
- Adicionei `?seed=1` em `api/estoque.php` para popular o estoque com itens de menu (Capucino, Mocha, Macchiato, Expresso, Leite e Caramelo, Cafe, etc.).

Próximos passos sugeridos
- Adicionar endpoint para reset/flush de estoque (`/api/estoque.php?reset=1`) se desejar re-seed fácil.
- Adicionar testes automatizados que validem o fluxo de seed e CRUD.

Se quiser, eu adiciono um endpoint de reset agora e gero exemplos de requisições para integração contínua.
