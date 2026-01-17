# Sistema de Gestão de Promissórias

Sistema desenvolvido em Laravel para gerenciar vendas com promissórias, permitindo o controle de clientes, valores, datas de vencimento e envio automático de notificações por email quando as promissórias estão próximas do vencimento.

## 📋 Funcionalidades

- **Gestão de Clientes**: CRUD completo de clientes com validação de CPF
- **Gestão de Promissórias**: 
  - Cadastro de promissórias vinculadas a clientes
  - Controle de status (pendente, paga, vencida)
  - Filtros por status, cliente, vencidas e próximas do vencimento
- **Notificações Automáticas**: 
  - Envio automático de emails quando promissórias estão próximas do vencimento (3 dias antes por padrão)
  - Comando agendado que roda diariamente às 8h
- **API RESTful**: Endpoints completos para integração com frontend

## 🚀 Instalação

1. Clone o repositório
2. Instale as dependências:
```bash
composer install
```

3. Configure o arquivo `.env`:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure o banco de dados no `.env` (SQLite por padrão):
```env
DB_CONNECTION=sqlite
# ou configure MySQL/PostgreSQL
```

5. Execute as migrations:
```bash
php artisan migrate
```

6. Configure o email no `.env` (para envio de notificações):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-senha
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=seu-email@gmail.com
MAIL_FROM_NAME="Sistema de Promissórias"
```

**Nota**: Para testes, você pode usar `MAIL_MAILER=log` para ver os emails nos logs.

## 📡 Endpoints da API

### Autenticação
- `POST /api/login` - Login e obtenção de token
- `POST /api/logout` - Logout (requer autenticação)

**Credenciais de teste:**
- Email: `test@example.com`
- Senha: `password123`

> Para criar o usuário de teste, execute: `php artisan db:seed --class=UserSeeder`

### Clientes
- `GET /api/clientes` - Listar clientes (com paginação)
- `POST /api/clientes` - Criar cliente
- `GET /api/clientes/{id}` - Exibir cliente
- `PUT /api/clientes/{id}` - Atualizar cliente
- `DELETE /api/clientes/{id}` - Remover cliente

### Promissórias
- `GET /api/promissorias` - Listar promissórias
  - Query params: `status`, `cliente_id`, `vencidas`, `proximas_vencimento`, `dias`, `per_page`
- `POST /api/promissorias` - Criar promissória
- `GET /api/promissorias/{id}` - Exibir promissória
- `PUT /api/promissorias/{id}` - Atualizar promissória
- `DELETE /api/promissorias/{id}` - Remover promissória
- `POST /api/promissorias/{id}/marcar-como-paga` - Marcar promissória como paga

## 📧 Notificações

O sistema envia automaticamente emails quando promissórias estão próximas do vencimento. O comando é executado diariamente às 8h (horário de Brasília).

### Executar manualmente

Para testar ou executar manualmente a verificação de vencimentos:

```bash
php artisan promissorias:verificar-vencimento
```

Para verificar com um número diferente de dias:

```bash
php artisan promissorias:verificar-vencimento --dias=5
```

### Configurar agendamento

O agendamento já está configurado em `routes/console.php`. Para que funcione em produção, você precisa configurar o agendador de tarefas do sistema:

**Linux/Mac (Cron)**:
```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

**Windows (Task Scheduler)**:
Configure uma tarefa agendada para executar:
```
php artisan schedule:run
```

## 🧪 Testes

Execute os testes com:

```bash
php artisan test
```

## 📝 Exemplo de Uso

### Criar uma promissória

```json
POST /api/promissorias
{
  "cliente_id": 1,
  "valor": 500.00,
  "data_vencimento": "2026-01-20",
  "observacoes": "Venda de produtos diversos"
}
```

### Filtrar promissórias próximas do vencimento

```
GET /api/promissorias?proximas_vencimento=1&dias=3
```

### Filtrar promissórias vencidas

```
GET /api/promissorias?vencidas=1
```

## 🔧 Tecnologias Utilizadas

- Laravel 12
- Laravel Sanctum (Autenticação API)
- SQLite (pode ser alterado para MySQL/PostgreSQL)
- Sistema de Notificações do Laravel
- Agendamento de Tarefas (Task Scheduling)

## 📄 Licença

Este projeto é open-source e está disponível sob a licença MIT.
