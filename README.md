# Sistema de Gestão de Promissórias

Sistema desenvolvido em Laravel para gerenciar vendas com promissórias, permitindo o controle de clientes, valores, datas de vencimento, sistema de permissões com roles, auditoria de ações e envio automático de notificações por email.

## 📋 Funcionalidades

### Gestão de Clientes
- CRUD completo de clientes com validação de CPF
- Validação customizada de CPF
- Paginação na listagem

### Gestão de Promissórias
- Cadastro de promissórias vinculadas a clientes
- Controle de status usando Enum (pendente, paga, vencida)
- Filtros por status, cliente, vencidas e próximas do vencimento
- Marcar promissória como paga
- Resumo de vencimentos com estatísticas

### Sistema de Permissões
- **Roles (Papéis)**:
  - **Admin**: Acesso total ao sistema
  - **Operador**: Acesso limitado (sem permissão de deletar)
- **Permissões por ação**:
  - `clientes.listar`, `clientes.visualizar`, `clientes.criar`, `clientes.editar`, `clientes.deletar`
  - `promissorias.listar`, `promissorias.visualizar`, `promissorias.criar`, `promissorias.editar`, `promissorias.deletar`, `promissorias.marcar-paga`
- **Laravel Policies** para autorização automática
- **Gerenciamento de permissões** via API

### Auditoria de Ações
- Registro automático de todas as ações (create, update, delete, view)
- Logs com informações do usuário, IP, user agent e timestamps
- Histórico completo de alterações (valores antigos e novos)

### Notificações Automáticas
- Envio automático de emails quando promissórias estão próximas do vencimento (3 dias antes por padrão)
- Notificação de promissórias vencidas
- Comando agendado que roda diariamente às 8h (horário de Brasília)
- Notificações contínuas para promissórias vencidas até serem pagas

### API RESTful
- Endpoints completos para integração com frontend
- Respostas padronizadas com `status_code` e `success`
- Tratamento genérico de erros (404, 401, 403, 422, 500)
- Autenticação via Laravel Sanctum (tokens)

## 🚀 Instalação

1. **Clone o repositório**
```bash
git clone <repository-url>
cd debit-system
```

2. **Instale as dependências**
```bash
composer install
```

3. **Configure o arquivo `.env`**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Configure o banco de dados no `.env`**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=debit_system
DB_USERNAME=root
DB_PASSWORD=
```

5. **Execute as migrations**
```bash
php artisan migrate
```

6. **Execute os seeders**
```bash
php artisan db:seed
```

Isso criará:
- Usuário Admin: `test@example.com` / `password123` (role: admin)
- Usuário Operador: `operador@example.com` / `password123` (role: operador)
- Roles e Permissions

7. **Configure o email no `.env` (para envio de notificações)**
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

**Ou use MailHog para testes locais:**
```bash
docker-compose up -d mailhog
```

Configure no `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
```

## 📡 Endpoints da API

### Autenticação
- `POST /api/login` - Login e obtenção de token
- `POST /api/logout` - Logout (requer autenticação)

**Credenciais de teste:**
- **Admin**: Email: `test@example.com` | Senha: `password123`
- **Operador**: Email: `operador@example.com` | Senha: `password123`

**Resposta do Login:**
```json
{
  "success": true,
  "status_code": 200,
  "message": "Login realizado com sucesso",
  "token": "1|xyz...",
  "user": {
    "id": 1,
    "name": "Usuário Admin",
    "email": "test@example.com",
    "roles": ["admin"],
    "permissions": ["clientes.listar", "clientes.criar", ...]
  }
}
```

### Clientes
- `GET /api/clientes` - Listar clientes (com paginação)
  - Query params: `per_page` (padrão: 15)
- `POST /api/clientes` - Criar cliente
- `GET /api/clientes/{id}` - Exibir cliente
- `PUT /api/clientes/{id}` - Atualizar cliente
- `DELETE /api/clientes/{id}` - Remover cliente (apenas admin)

**Permissões:**
- `clientes.listar`, `clientes.visualizar`, `clientes.criar`, `clientes.editar` → Admin e Operador
- `clientes.deletar` → Apenas Admin

### Promissórias
- `GET /api/promissorias` - Listar promissórias
  - Query params: `status`, `cliente_id`, `vencidas`, `proximas_vencimento`, `dias`, `per_page`
- `POST /api/promissorias` - Criar promissória
- `GET /api/promissorias/{id}` - Exibir promissória
- `PUT /api/promissorias/{id}` - Atualizar promissória
- `DELETE /api/promissorias/{id}` - Remover promissória (apenas admin)
- `POST /api/promissorias/{id}/marcar-como-paga` - Marcar promissória como paga
- `GET /api/promissorias/resumo/vencimento` - Resumo de promissórias próximas do vencimento e vencidas

**Permissões:**
- `promissorias.listar`, `promissorias.visualizar`, `promissorias.criar`, `promissorias.editar`, `promissorias.marcar-paga` → Admin e Operador
- `promissorias.deletar` → Apenas Admin

### Permissões (Gerenciamento)
- `GET /api/permissoes/minhas` - Retorna permissões do usuário autenticado
- `GET /api/permissoes/usuarios` - Lista todos os usuários com suas roles e permissões (apenas admin)
- `GET /api/permissoes/usuarios/{id}` - Consulta permissões detalhadas de um usuário
- `GET /api/permissoes/roles` - Lista todas as roles disponíveis (apenas admin)
- `GET /api/permissoes/permissoes` - Lista todas as permissões disponíveis (apenas admin)
- `POST /api/permissoes/usuarios/{id}/role` - Atribui role a um usuário (apenas admin)
- `DELETE /api/permissoes/usuarios/{id}/role` - Remove role de um usuário (apenas admin)
- `POST /api/permissoes/usuarios/{id}/permissao` - Atribui permissão direta a um usuário (apenas admin)
- `DELETE /api/permissoes/usuarios/{id}/permissao` - Remove permissão direta de um usuário (apenas admin)

## 🔐 Sistema de Permissões

### Roles Disponíveis

| Role | Descrição | Permissões |
|------|-----------|------------|
| **Admin** | Administrador do sistema | Todas as permissões |
| **Operador** | Operador do sistema | Todas exceto `*.deletar` |

### Permissões Disponíveis

#### Clientes
- `clientes.listar` - Listar clientes
- `clientes.visualizar` - Visualizar cliente
- `clientes.criar` - Criar cliente
- `clientes.editar` - Editar cliente
- `clientes.deletar` - Deletar cliente (apenas admin)

#### Promissórias
- `promissorias.listar` - Listar promissórias
- `promissorias.visualizar` - Visualizar promissória
- `promissorias.criar` - Criar promissória
- `promissorias.editar` - Editar promissória
- `promissorias.deletar` - Deletar promissória (apenas admin)
- `promissorias.marcar-paga` - Marcar promissória como paga

### Como Atribuir Permissões

**Via API:**
```bash
# Atribuir role admin a um usuário
POST /api/permissoes/usuarios/2/role
{
  "role": "admin"
}

# Atribuir permissão direta
POST /api/permissoes/usuarios/2/permissao
{
  "permission": "clientes.deletar"
}
```

**Via Tinker:**
```bash
php artisan tinker

$user = User::find(2);
$user->assignRole('admin');
$user->givePermissionTo('clientes.deletar');
```

## 📊 Auditoria

O sistema registra automaticamente todas as ações realizadas pelos usuários:

- **Create** - Criação de registros
- **Update** - Atualização de registros (com valores antigos e novos)
- **Delete** - Exclusão de registros
- **View** - Visualização de registros

**Campos registrados:**
- Usuário que executou a ação
- Ação realizada
- Model e ID do registro
- Valores antigos e novos (para updates)
- IP address e User Agent
- Timestamp

## 📧 Notificações

O sistema envia automaticamente emails quando promissórias estão próximas do vencimento ou já vencidas.

### Tipos de Notificação

1. **Promissória Próxima do Vencimento** - Enviado 3 dias antes do vencimento
2. **Promissória Vencida** - Enviado diariamente para promissórias vencidas até serem pagas

### Executar Manualmente

```bash
php artisan promissorias:verificar-vencimento
```

Com número de dias customizado:
```bash
php artisan promissorias:verificar-vencimento --dias=5
```

### Configurar Agendamento

O agendamento já está configurado em `routes/console.php` para rodar diariamente às 8h (horário de Brasília).

**Linux/Mac (Cron):**
```bash
* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1
```

**Windows (Task Scheduler):**
Configure uma tarefa agendada para executar:
```
php artisan schedule:run
```

## 🧪 Testes

O projeto possui testes unitários e de feature.

### Executar Todos os Testes
```bash
php artisan test
```

### Executar Apenas Testes Unitários
```bash
php artisan test --testsuite=Unit
```

### Executar Apenas Testes Feature
```bash
php artisan test --testsuite=Feature
```

### Executar Teste Específico
```bash
php artisan test --filter=ClienteControllerTest
```

**Cobertura de Testes:**
- **Unit Tests**: 35 testes (Enums, DTOs, Models, Rules)
- **Feature Tests**: 42 testes (Controllers, rotas, permissões)

## 📝 Exemplos de Uso

### Criar uma Promissória
```json
POST /api/promissorias
{
  "cliente_id": 1,
  "valor": 500.00,
  "data_vencimento": "2026-01-20",
  "observacoes": "Venda de produtos diversos"
}
```

### Filtrar Promissórias Próximas do Vencimento
```
GET /api/promissorias?proximas_vencimento=1&dias=3
```

### Filtrar Promissórias Vencidas
```
GET /api/promissorias?vencidas=1
```

### Consultar Minhas Permissões
```
GET /api/permissoes/minhas
Authorization: Bearer {token}
```

### Atribuir Role a um Usuário
```json
POST /api/permissoes/usuarios/2/role
{
  "role": "admin"
}
```

## 🏗️ Arquitetura

O projeto segue os padrões de arquitetura do Laravel com separação de responsabilidades:

### Camadas
- **Controllers** - Recebem requisições HTTP e coordenam ações
- **Services** - Lógica de negócio
- **Repositories** - Acesso a dados (abstração do Eloquent)
- **Policies** - Autorização (permissões)
- **DTOs** - Transferência de dados entre camadas
- **Form Requests** - Validação e autorização de requisições
- **Models** - Entidades do domínio

### Estrutura de Pastas
```
app/
├── Console/Commands/          # Comandos Artisan
├── DTOs/                      # Data Transfer Objects
├── Enums/                     # Enumeradores
├── Http/
│   ├── Controllers/           # Controllers
│   ├── Middleware/            # Middlewares
│   └── Requests/              # Form Requests
├── Models/                    # Models Eloquent
├── Notifications/             # Notificações de email
├── Policies/                  # Policies de autorização
├── Providers/                 # Service Providers
├── Repositories/              # Repositories
│   └── Contracts/             # Interfaces dos Repositories
├── Rules/                     # Regras de validação customizadas
└── Services/                  # Services (lógica de negócio)
```

## 🔧 Tecnologias Utilizadas

- **Laravel 12** - Framework PHP
- **Laravel Sanctum** - Autenticação API (tokens)
- **Spatie Laravel Permission** - Sistema de permissões e roles
- **MySQL/SQLite** - Banco de dados
- **Laravel Notifications** - Sistema de notificações
- **Laravel Task Scheduling** - Agendamento de tarefas
- **PHPUnit** - Framework de testes
- **Docker Compose** - MailHog para testes de email

## 📦 Coleção Postman

O projeto inclui uma coleção completa do Postman com todas as rotas configuradas:

1. Importe o arquivo `postman_collection.json` no Postman
2. Importe o arquivo `postman_environment.json` (opcional)
3. Configure a variável `base_url` no ambiente (padrão: `http://localhost:8000`)
4. Faça login através da rota "Login" para obter o token automaticamente

A coleção inclui:
- 25 rotas configuradas
- Scripts automáticos para gerenciar tokens
- Exemplos de requisições e respostas
- Todas as rotas de permissões

## 📄 Licença

Este projeto é open-source e está disponível sob a licença MIT.
