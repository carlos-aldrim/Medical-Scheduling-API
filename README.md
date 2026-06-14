# 🏥 Medical Scheduling API

> REST API para gerenciamento completo de agendamentos médicos — construída com **PHP 8.2**, **Symfony 7** e autenticação stateless via **JWT**, seguindo os princípios de Clean Architecture.

---

## 📋 Índice

- [Visão Geral](#visão-geral)
- [Tecnologias](#tecnologias)
- [Arquitetura](#arquitetura)
- [Padrões e Práticas de Código](#padrões-e-práticas-de-código)
- [Autenticação e Autorização](#autenticação-e-autorização)
- [Regras de Negócio](#regras-de-negócio)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e Execução](#instalação-e-execução)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Fluxo Completo da Aplicação](#fluxo-completo-da-aplicação)
- [Endpoints](#endpoints)
- [Documentação OpenAPI (Swagger)](#documentação-openapi-swagger)
- [Exemplos de Requisição](#exemplos-de-requisição)
- [Tratamento de Erros](#tratamento-de-erros)
- [Observability](#observability)
- [Testes](#testes)
- [Estrutura do Projeto](#estrutura-do-projeto)
- [Pontos de Destaque do Sistema](#pontos-de-destaque-do-sistema)

---

## Visão Geral

Um sistema de agendamento médico que vai além do CRUD básico. Ele gerencia pacientes, médicos, especialidades e consultas com controle fino de permissões por perfil — e a integridade dos dados é garantida em múltiplas camadas: validação de payload via DTOs, regras de negócio isoladas em Use Cases e queries especializadas nos Repositories.

A ideia central é que erros de negócio sejam detectados o mais cedo possível, com respostas consistentes e semânticas em todos os endpoints.

**O que o sistema faz:**

- Autenticação stateless com JWT (LexikJWTAuthenticationBundle)
- RBAC com três perfis distintos: Admin, Médico e Recepcionista
- Validação de CPF com verificação real dos dígitos verificadores
- Validação de CRM com suporte a quatro formatos nacionais diferentes
- Controle de conflito de horário com janela de 30 minutos por médico
- Limite diário de consultas configurável individualmente por médico
- Respostas padronizadas em toda a API via `ApiResponse`
- Tratamento centralizado de exceções com mapeamento automático para HTTP status codes
- Logging estruturado em JSON com correlation ID por requisição e health-check para operação em produção
- Documentação OpenAPI/Swagger gerada automaticamente a partir do código
- Suíte de testes unitários (Use Cases) e funcionais (HTTP end-to-end)

---

## Tecnologias

| Tecnologia | Versão | Papel |
|---|---|---|
| PHP | 8.2 | Linguagem principal |
| Symfony | 7.x | Framework HTTP, DI, Event Dispatcher |
| Doctrine ORM | 3.x | Mapeamento objeto-relacional |
| Doctrine Migrations | — | Versionamento do schema do banco |
| LexikJWTAuthenticationBundle | — | Emissão e validação de tokens JWT |
| Monolog | — | Logging estruturado (JSON) com correlation ID |
| NelmioApiDocBundle | ^5.10 | Documentação OpenAPI/Swagger automática a partir de atributos PHP |
| PostgreSQL | 16 | Banco de dados relacional |
| Docker / Compose | — | Containerização e orquestração |
| PHPUnit | 11.x | Testes unitários (Use Cases) e funcionais (API) |
| Nginx | Alpine | Servidor web / proxy reverso |
| RabbitMQ | 3.x | Message broker para domain events (opcional) |

---

## Arquitetura

O projeto segue uma arquitetura em camadas inspirada em **Clean Architecture**, com uma regra simples de dependência: camadas externas conhecem as internas, nunca o inverso. Isso significa que o banco de dados não contamina a lógica de negócio, e o HTTP não contamina nada além do Controller.

```
HTTP Request
     │
     ▼
┌─────────────┐
│ Controller  │  ← Recebe a requisição, injeta o DTO via #[MapRequestPayload]
└──────┬──────┘
       │ DTO (dados validados)
       ▼
┌─────────────┐
│   UseCase   │  ← Orquestra regras de negócio; única dependência: Repositories
└──────┬──────┘
       │ Entity (resultado persistido)
       ▼
┌──────────────┐
│  Repository  │  ← Abstrai o acesso ao banco; queries específicas por domínio
└──────┬───────┘
       │
       ▼
┌────────────┐
│   Entity   │  ← Modelo de dados + comportamentos de domínio (ex: cancel(), complete())
└────────────┘
       │
       ▼
  PostgreSQL
```

### Responsabilidades por camada

**Controller** — ponto de entrada HTTP. Não toma nenhuma decisão de negócio. Recebe o DTO já validado via `#[MapRequestPayload]`, delega ao Use Case e serializa a resposta com `ApiResponse`. Se você está escrevendo um `if` de regra de negócio num Controller, alguma coisa está no lugar errado.

**DTO (Data Transfer Object)** — carrega e valida os dados de entrada usando anotações do Symfony Validator (`#[Assert\*]`). É readonly por natureza, o que garante imutabilidade desde a deserialização. Nenhum dado mal formado chega ao UseCase.

**UseCase** — é aqui que a lógica de negócio vive. A camada mais testável do sistema: depende apenas de interfaces de repositório, então qualquer teste unitário pode mockar tudo sem precisar de banco de dados. Cada Use Case tem exatamente um método `execute()` e uma responsabilidade clara.

**Repository** — abstrai as queries ao banco. A ideia é que quem chama não saiba como a query foi feita — só sabe que pode perguntar `hasConflict`, `countByDoctorAndDate` ou `findByPatient`. Nada de queries genéricas espalhadas pelo código.

**Entity** — representa o modelo de dados e encapsula as transições de estado. Uma consulta não muda de status por um setter direto: ela tem métodos `cancel()` e `complete()` que refletem o que realmente acontece no domínio.

---

## Padrões e Práticas de Código

### Use Case Pattern

Cada operação de negócio é encapsulada numa classe com método único `execute()`. Isso garante responsabilidade única (SRP), facilidade de teste e rastreabilidade — ao ler o nome do Use Case, o comportamento fica imediatamente claro, sem precisar rastrear o fluxo de um Controller até o banco.

```php
// Toda regra de negócio de agendamento vive aqui, isolada
class CreateAppointmentUseCase
{
    public function execute(CreateAppointmentDTO $dto): Appointment { ... }
}
```

### Enums com comportamento

Os enums aqui não são apenas rótulos para evitar magic strings. Eles encapsulam lógica de domínio diretamente, sendo a única fonte de verdade sobre o que cada estado pode ou não fazer:

```php
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isCancellable(): bool
    {
        return $this === self::Scheduled;
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }
}
```

Da mesma forma, `UserRole` sabe quais permissões cada perfil carrega:

```php
enum UserRole: string
{
    public function canManageScheduling(): bool
    {
        return match ($this) {
            self::Admin, self::Receptionist => true,
            default => false,
        };
    }
}
```

### ApiResponse — Response Object Pattern

Em vez de instanciar `JsonResponse` espalhado pelos Controllers, toda resposta passa pela classe `ApiResponse`. O envelope, os status codes e os códigos de erro semânticos ficam num único lugar. Mudar o formato de resposta da API inteira é alterar um arquivo só.

```php
// Sucesso
ApiResponse::ok($data);                    // 200
ApiResponse::created($data);               // 201
ApiResponse::collection($data, $total);    // 200 com meta.total

// Erros
ApiResponse::notFound($msg);      // 404 NOT_FOUND
ApiResponse::conflict($msg);      // 409 CONFLICT
ApiResponse::unprocessable($msg); // 422 UNPROCESSABLE
ApiResponse::badRequest($msg);    // 400 BAD_REQUEST
```

### Tratamento Global de Exceções

O `ExceptionSubscriber` intercepta todas as exceções via `KernelEvents::EXCEPTION`, mapeando automaticamente `HttpExceptionInterface` para o status code correto e `ValidationFailedException` para um payload estruturado de erros por campo. Nenhum `try/catch` nos Controllers — a exceção é lançada em qualquer camada e chega ao cliente com o formato certo.

### Custom Validators como Attributes

Validações de domínio complexas (CPF, CRM) são implementadas como Symfony Constraints reutilizáveis. O resultado é uma sintaxe limpa nos DTOs, sem lógica de validação inline:

```php
#[CpfConstraint]
public readonly string $cpf,

#[CrmConstraint]
public readonly string $crm,
```

### Value Objects (Cpf, Crm, AppointmentSlot)

CPF, CRM e horário de agendamento não circulam pelo código como strings soltas. Eles são Value Objects imutáveis em `src/ValueObject/`, e carregam sua própria lógica de normalização e validação:

```php
$cpf = new Cpf('123.456.789-09');   // normaliza e valida dígitos verificadores
$cpf->value();                      // "12345678909"
$cpf->formatted();                  // "123.456.789-09"

$crm = new Crm('sp-12345');
$crm->formatted();                  // "CRM-SP-12345" (normalizado)

$slot = AppointmentSlot::fromString('2026-07-01 14:00:00')
    ->ensureIsInTheFuture();
$slot->windowStart();               // janela de conflito (-29min)
$slot->windowEnd();                 // janela de conflito (+29min)
$slot->dayStart();                  // limite do dia, usado no limite diário
```

Os Validators (`CpfConstraint`, `CrmConstraint`) delegam para `Cpf::isValid()` e `Crm::isValid()`. Os setters das entidades (`Doctor::setCrm()`, `Patient::setCpf()`) constroem o Value Object internamente — garantindo que não existe CPF ou CRM inválido no banco, independente de como o setter foi chamado. O `CreateAppointmentUseCase` usa `AppointmentSlot` para parsing em UTC, validação de "não pode ser no passado" e cálculo das janelas de conflito e limite diário, sem duplicar lógica de datas em nenhum outro lugar.

### Injeção de Dependência via Constructor Promotion

Todos os serviços usam constructor promotion com `readonly` implícito. Sem boilerplate, sem atribuições manuais, dependências explícitas na assinatura:

```php
public function __construct(
    private AppointmentRepository $appointmentRepository,
    private DoctorRepository $doctorRepository,
    private PatientRepository $patientRepository,
) {}
```

### Serialization Groups

A serialização de entidades usa grupos nomeados para controlar exatamente o que aparece em cada contexto. Sem over-fetching, sem referências circulares acidentais:

```php
#[Groups(['appointment'])]                  // campos básicos
#[Groups(['appointment_with_relations'])]   // inclui doctor e patient aninhados
#[Groups(['doctor_with_specialty'])]        // inclui specialty aninhada
```

### Domain Events e Notificações Assíncronas (Symfony Messenger)

Criar e cancelar uma consulta dispara eventos de domínio — `AppointmentCreatedEvent` e `AppointmentCancelledEvent` — através do `MessageBusInterface` do Symfony Messenger. Os Use Cases publicam "isto aconteceu" e param por aí; eles não sabem como a notificação é entregue nem se existe uma:

```php
// dentro do CreateAppointmentUseCase, após persistir
$this->eventBus->dispatch(AppointmentCreatedEvent::fromAppointment($appointment));
```

Os handlers em `src/MessageHandler/`, marcados com `#[AsMessageHandler]`, simulam envio de e-mail ou SMS de confirmação e cancelamento (via log). A configuração em `config/packages/messenger.yaml` roteia os eventos para um transporte `async`. O padrão usa `doctrine://` (armazena as mensagens na tabela `messenger_messages`, sem dependências externas), mas pode ser trocado para RabbitMQ sem alterar uma linha de código da aplicação:

```env
# Doctrine (padrão, sem infra extra)
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

# RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages
```

Para processar as mensagens em background:

```bash
docker compose exec app php bin/console messenger:consume async -vv
```

Mensagens que falham após as tentativas de retry (`max_retries: 3`, multiplicador 2x) vão para a fila `failed`, reprocessável com `messenger:consume failed`.

#### Usando RabbitMQ como transporte

Para testar com RabbitMQ em vez do Doctrine, siga os passos abaixo.

**1. Adicione o serviço no `docker-compose.yml`:**

```yaml
rabbitmq:
  image: rabbitmq:3-management-alpine
  container_name: medical_rabbitmq
  ports:
    - "5672:5672"
    - "15672:15672"
  environment:
    RABBITMQ_DEFAULT_USER: guest
    RABBITMQ_DEFAULT_PASS: guest
  networks:
    - medical_network
```

A rede `medical_network` é obrigatória — sem ela o container fica isolado e o `app` não consegue alcançar o RabbitMQ.

**2. Instale a extensão `ext-amqp` no `Dockerfile`:**

```dockerfile
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    librabbitmq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
```

**3. Instale o pacote do Symfony:**

```bash
docker compose exec app composer require symfony/amqp-messenger
```

**4. Troque o DSN no `.env`:**

```dotenv
# Doctrine (padrão)
# MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0

# RabbitMQ
MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages
```

**5. Rebuilde e suba tudo:**

```bash
docker compose down
docker compose up --build -d
```

**6. Rode o consumer:**

```bash
docker compose exec app php bin/console messenger:consume async -vv
```

**7. Monitore pelo painel:**

Acesse `http://localhost:15672` com `guest` / `guest`. Em **Queues and Streams** você vê a fila `messages` com os contadores de mensagens em tempo real. Para inspecionar o payload antes de consumir, clique na fila, role até **Get messages**, mantenha o **Ack Mode** como `Nack message requeue true` e clique em **Get Message(s)** — a mensagem volta para a fila após a visualização.

Para voltar ao Doctrine é só descomentar o DSN original no `.env` e reiniciar os containers — nenhuma linha de código muda.

---

## Autenticação e Autorização

### Fluxo JWT

A autenticação é stateless e baseada em tokens JWT assinados com par de chaves RSA. O token expira em **1 hora** (`token_ttl: 3600`). Sem sessão, sem estado no servidor.

```
Cliente                          API
  │                               │
  │  POST /auth/login             │
  │  { email, password }          │
  │ ─────────────────────────────►│
  │                               │  Valida credenciais
  │                               │  Gera JWT (RS256, TTL 1h)
  │◄─────────────────────────────│
  │  { token: "eyJ..." }          │
  │                               │
  │  GET /appointments            │
  │  Authorization: Bearer eyJ... │
  │ ─────────────────────────────►│
  │                               │  Valida assinatura JWT
  │                               │  Extrai email (user_id_claim)
  │                               │  Carrega User do banco
  │◄─────────────────────────────│
  │  { success: true, data: [...]}│
```

### Perfis e Permissões (RBAC)

| Rota | ROLE_ADMIN | ROLE_RECEPTIONIST | ROLE_DOCTOR |
|---|:---:|:---:|:---:|
| `POST /auth/login` | ✅ | ✅ | ✅ |
| `GET /auth/me` | ✅ | ✅ | ✅ |
| `POST /auth/register` | ✅ | ❌ | ❌ |
| `GET /doctors` | ✅ | ✅ | ✅ |
| `POST /doctors` | ✅ | ❌ | ❌ |
| `PATCH /doctors/{id}/deactivate` | ✅ | ❌ | ❌ |
| `GET /specialties` | ✅ | ✅ | ✅ |
| `POST /specialties` | ✅ | ❌ | ❌ |
| `GET /patients` | ✅ | ✅ | ❌ |
| `POST /patients` | ✅ | ✅ | ❌ |
| `PATCH /patients/{id}/deactivate` | ✅ | ✅ | ❌ |
| `GET /appointments` | ✅ | ✅ | ❌ |
| `POST /appointments` | ✅ | ✅ | ❌ |
| `PATCH /appointments/{id}/cancel` | ✅ | ✅ | ❌ |

### Geração das chaves JWT

```bash
# Gerar chave privada
openssl genrsa -out config/jwt/private.pem 4096

# Derivar chave pública
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem
```

Ou via Symfony CLI (recomendado):

```bash
docker compose exec app php bin/console lexik:jwt:generate-keypair
```

---

## Regras de Negócio

### Consultas (Appointments)

| Regra | Comportamento |
|---|---|
| Agendamento no passado | `400 BAD_REQUEST` — "Cannot schedule appointment in the past" |
| Conflito de horário | `400 BAD_REQUEST` — janela de ±29 minutos por médico, excluindo canceladas |
| Limite diário atingido | `400 BAD_REQUEST` — limite configurável por médico (padrão 10) |
| Médico inativo | `400 BAD_REQUEST` — médico não aceita novos agendamentos |
| Paciente inativo | `400 BAD_REQUEST` — paciente não pode agendar consultas |
| Cancelar consulta completada | `400 BAD_REQUEST` — status terminal, irreversível |
| Cancelar já cancelada | `400 BAD_REQUEST` — idempotência explícita |

### Médicos (Doctors)

- CRM único no sistema, validado em múltiplos formatos (`12345`, `CRM12345`, `CRM-SP-12345`, `SP-12345`)
- Cada médico está vinculado obrigatoriamente a uma especialidade
- `maxAppointmentsPerDay` é configurável individualmente na criação

### Pacientes (Patients)

- CPF único e validado com algoritmo de dígitos verificadores — sequências homogêneas como `111.111.111-11` são explicitamente rejeitadas
- Telefone é opcional

### Usuários (Users)

- Apenas administradores podem registrar novos usuários (`ROLE_ADMIN` no `POST /auth/register`)
- Senhas seguem política rigorosa: mínimo 8 caracteres, ao menos uma maiúscula, uma minúscula e um dígito

---

## Pré-requisitos

- [Docker](https://www.docker.com/get-started) 24+
- [Docker Compose](https://docs.docker.com/compose/) 2+

---

## Instalação e Execução

### 1. Clone o repositório

```bash
git clone https://github.com/carlos-aldrim/Medical-Scheduling-API
cd Medical-Scheduling-API
```

### 2. Configure as variáveis de ambiente

```bash
cp .env.example .env
```

Edite o `.env` conforme necessário (veja [Variáveis de Ambiente](#variáveis-de-ambiente)).

### 3. Suba os containers

```bash
docker compose up --build -d
```

Isso iniciará três serviços:
- `medical_app` — PHP-FPM com a aplicação Symfony
- `medical_nginx` — Nginx como proxy reverso (porta `APP_PORT`, padrão `9000`)
- `medical_db` — PostgreSQL 16 com healthcheck

### 4. Gere as chaves JWT

```bash
docker compose exec app php bin/console lexik:jwt:generate-keypair
```

### 5. Execute as migrations

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

### 5.1. Configure a fila de mensagens

O `MESSENGER_TRANSPORT_DSN` usa `auto_setup=0`, então a tabela `messenger_messages` precisa ser criada manualmente:

```bash
docker compose exec app php bin/console messenger:setup-transports
```

### 6. Crie o primeiro usuário administrador

Como `POST /auth/register` exige `ROLE_ADMIN`, o primeiro admin precisa ser inserido diretamente no banco. Primeiro gere o hash da senha:

```bash
docker compose exec app php -r "echo password_hash('Admin@123', PASSWORD_BCRYPT) . PHP_EOL;"
```

Depois entre no psql e execute o insert com o hash gerado:

```bash
docker compose exec db psql -U admin -d medical_scheduling
```

```sql
INSERT INTO "user" (id, name, email, password, roles)
VALUES (
  gen_random_uuid(),
  'Admin',
  'admin@hospital.com',
  '$2y$10$COLE_O_HASH_GERADO_AQUI',
  '["ROLE_ADMIN"]'
);
```

### 7. Acesse a API

```
http://localhost:9000
```

### Alternativa: servidor embutido do PHP (sem Docker)

Para desenvolvimento local rápido, sem subir containers, é possível usar o servidor embutido do PHP apontando para o front controller:

```bash
php -S 127.0.0.1:9000 -t public public/index.php
```

```
http://127.0.0.1:9000
```

> Esse modo é suficiente para desenvolvimento e para explorar a documentação OpenAPI (`/api/doc`), mas não é recomendado para produção — use Nginx + PHP-FPM ou os containers Docker descritos acima. O processo roda em foreground: o terminal precisa permanecer aberto enquanto o servidor estiver em uso.

---

## Variáveis de Ambiente

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `dev` | Ambiente (`dev`, `prod`, `test`) |
| `APP_SECRET` | — | Chave secreta do Symfony (mínimo 32 chars) |
| `APP_PORT` | `9000` | Porta exposta pelo Nginx |
| `POSTGRES_DB` | `medical_scheduling` | Nome do banco de dados |
| `POSTGRES_USER` | `admin` | Usuário do PostgreSQL |
| `POSTGRES_PASSWORD` | `secret` | Senha do PostgreSQL |
| `DB_PORT` | `5432` | Porta do PostgreSQL |
| `DATABASE_URL` | _(derivado)_ | DSN completo do Doctrine |
| `JWT_SECRET_KEY` | `config/jwt/private.pem` | Caminho para a chave privada RSA |
| `JWT_PUBLIC_KEY` | `config/jwt/public.pem` | Caminho para a chave pública RSA |
| `JWT_PASSPHRASE` | _(vazio)_ | Passphrase da chave privada (se houver) |
| `DEFAULT_URI` | `http://localhost:9000` | URI base da aplicação |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | Transporte do Symfony Messenger — suporta `amqp://...` sem alteração de código |

---

## Fluxo Completo da Aplicação

O fluxo abaixo ilustra um cenário real de ponta a ponta: da criação dos cadastros até o ciclo completo de uma consulta.

### Passo 1 — Autenticação

```http
POST /auth/login
Content-Type: application/json

{
  "email": "admin@hospital.com",
  "password": "Admin@123"
}
```

**Resposta:**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

Use o token como `Authorization: Bearer <token>` em todas as requisições seguintes.

---

### Passo 2 — Registrar usuário recepcionista (Admin only)

```http
POST /auth/register
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "name": "Maria Silva",
  "email": "maria@hospital.com",
  "password": "Recepc@123",
  "role": "ROLE_RECEPTIONIST"
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1234-...",
    "name": "Maria Silva",
    "email": "maria@hospital.com",
    "roles": ["ROLE_RECEPTIONIST", "ROLE_USER"]
  }
}
```

---

### Passo 3 — Criar especialidade (Admin only)

```http
POST /specialties
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "name": "Cardiologia",
  "description": "Especialidade do coração e sistema cardiovascular"
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1235-...",
    "name": "Cardiologia",
    "description": "Especialidade do coração e sistema cardiovascular"
  }
}
```

---

### Passo 4 — Cadastrar médico (Admin only)

```http
POST /doctors
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "name": "Dr. Gregory House",
  "crm": "CRM-SP-12345",
  "specialtyId": "018f1235-...",
  "maxAppointmentsPerDay": 8
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1236-...",
    "name": "Dr. Gregory House",
    "crm": "CRM-SP-12345",
    "maxAppointmentsPerDay": 8,
    "isActive": true
  }
}
```

---

### Passo 5 — Cadastrar paciente (Admin ou Recepcionista)

```http
POST /patients
Authorization: Bearer <receptionist_token>
Content-Type: application/json

{
  "name": "João da Silva",
  "cpf": "52998224725",
  "birthDate": "1985-03-20",
  "phone": "85999990000"
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1237-...",
    "name": "João da Silva",
    "cpf": "52998224725",
    "birthDate": "1985-03-20",
    "phone": "85999990000",
    "isActive": true
  }
}
```

---

### Passo 6 — Agendar consulta (Admin ou Recepcionista)

```http
POST /appointments
Authorization: Bearer <receptionist_token>
Content-Type: application/json

{
  "doctorId": "018f1236-...",
  "patientId": "018f1237-...",
  "scheduledAt": "2025-12-20 14:00:00",
  "notes": "Primeira consulta, paciente com histórico de hipertensão"
}
```

**Resposta `201 Created`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1238-...",
    "scheduledAt": "2025-12-20T14:00:00+00:00",
    "status": "scheduled",
    "notes": "Primeira consulta, paciente com histórico de hipertensão",
    "createdAt": "2025-06-12T10:00:00+00:00",
    "doctor": {
      "id": "018f1236-...",
      "name": "Dr. Gregory House",
      "crm": "CRM-SP-12345"
    },
    "patient": {
      "id": "018f1237-...",
      "name": "João da Silva",
      "cpf": "52998224725"
    }
  }
}
```

---

### Passo 7 — Consultar histórico do paciente

```http
GET /appointments/patient/018f1237-...
Authorization: Bearer <receptionist_token>
```

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": [...],
  "meta": { "total": 1 }
}
```

---

### Passo 8 — Cancelar consulta

```http
PATCH /appointments/018f1238-.../cancel
Authorization: Bearer <receptionist_token>
```

**Resposta `200 OK`:**
```json
{
  "success": true,
  "data": {
    "id": "018f1238-...",
    "status": "cancelled",
    ...
  }
}
```

---

## Endpoints

### Auth

| Método | Rota | Autenticação | Roles |
|---|---|---|---|
| `POST` | `/auth/login` | Pública | — |
| `POST` | `/auth/register` | JWT | ROLE_ADMIN |
| `GET` | `/auth/me` | JWT | Qualquer autenticado |

### Specialties

| Método | Rota | Roles |
|---|---|---|
| `GET` | `/specialties` | Qualquer autenticado |
| `GET` | `/specialties/{id}` | Qualquer autenticado |
| `POST` | `/specialties` | ROLE_ADMIN |

### Doctors

| Método | Rota | Roles |
|---|---|---|
| `GET` | `/doctors` | Qualquer autenticado |
| `GET` | `/doctors/{id}` | Qualquer autenticado |
| `POST` | `/doctors` | ROLE_ADMIN |
| `PATCH` | `/doctors/{id}/deactivate` | ROLE_ADMIN |

### Patients

| Método | Rota | Roles |
|---|---|---|
| `GET` | `/patients` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `GET` | `/patients/{id}` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `POST` | `/patients` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `PATCH` | `/patients/{id}/deactivate` | ROLE_ADMIN, ROLE_RECEPTIONIST |

### Appointments

| Método | Rota | Roles |
|---|---|---|
| `GET` | `/appointments` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `GET` | `/appointments/{id}` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `GET` | `/appointments/patient/{patientId}` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `POST` | `/appointments` | ROLE_ADMIN, ROLE_RECEPTIONIST |
| `PATCH` | `/appointments/{id}/cancel` | ROLE_ADMIN, ROLE_RECEPTIONIST |

### Health & Docs

| Método | Rota | Autenticação | Descrição |
|---|---|---|---|
| `GET` | `/health` | Pública | Health-check (banco + opcache), `200`/`503` |
| `GET` | `/api/doc` | Pública | Swagger UI |
| `GET` | `/api/doc.json` | Pública | Especificação OpenAPI 3 (JSON) |

---

## Documentação OpenAPI (Swagger)

A API é autodocumentada via [NelmioApiDocBundle](https://github.com/nelmio/NelmioApiDocBundle), que gera uma especificação OpenAPI 3 em tempo real a partir dos atributos PHP (`#[OA\...]`) declarados em Controllers e DTOs — não há arquivos de spec mantidos manualmente, então a documentação nunca fica desatualizada em relação ao código.

| Rota | Descrição |
|---|---|
| `GET /api/doc` | Swagger UI — interface interativa para explorar e testar os endpoints |
| `GET /api/doc.json` | Especificação OpenAPI 3 em JSON, consumível por Postman, Insomnia, geradores de SDK, etc. |

Ambas as rotas são públicas (`PUBLIC_ACCESS`), permitindo integração de times externos sem necessidade de autenticação prévia apenas para consultar o contrato.

A spec inclui:

- Todos os endpoints agrupados por tag (`Auth`, `Doctors`, `Patients`, `Specialties`, `Appointments`, `Health`)
- Schemas de request body gerados a partir dos DTOs (`CreateAppointmentDTO`, `RegisterDTO`, etc.), incluindo as constraints de validação
- Respostas documentadas por status code (200, 201, 400, 401, 403, 404, 409, 422, 503)
- Esquema de segurança `bearerAuth` (JWT), com botão **Authorize** no Swagger UI para testar endpoints autenticados diretamente na interface

Para acessar localmente:

```bash
php -S 127.0.0.1:9000 -t public public/index.php
```

```
http://127.0.0.1:9000/api/doc
```

---

## Exemplos de Requisição

### Validação de senha (RegisterDTO)

A senha precisa satisfazer todos os critérios ao mesmo tempo:

```http
POST /auth/register
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "name": "Dr. Wilson",
  "email": "wilson@hospital.com",
  "password": "Weak",
  "role": "ROLE_DOCTOR"
}
```

**Resposta `422 Unprocessable Entity`:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": [
      { "field": "password", "message": "Password must be at least 8 characters" }
    ]
  }
}
```

### Formatos de CRM aceitos

```json
{ "crm": "12345" }          ✅ Numérico simples
{ "crm": "CRM12345" }       ✅ Prefixo CRM
{ "crm": "CRM-SP-12345" }   ✅ Com estado
{ "crm": "SP-12345" }       ✅ Estado-número
{ "crm": "XPTO999" }        ❌ Formato inválido
```

### Tentativa de conflito de horário

```http
POST /appointments
{
  "doctorId": "...",
  "patientId": "...",
  "scheduledAt": "2025-12-20 14:15:00"
}
```

O horário acima está a 15 minutos de uma consulta existente às 14:00, dentro da janela de ±29 minutos.

**Resposta `400 Bad Request`:**
```json
{
  "success": false,
  "error": {
    "code": "BAD_REQUEST",
    "message": "Doctor already has an appointment in this time slot"
  }
}
```

---

## Tratamento de Erros

Todos os erros seguem o mesmo envelope JSON, o que facilita o tratamento no cliente — não importa onde o erro ocorreu, a estrutura é sempre a mesma:

```json
{
  "success": false,
  "error": {
    "code": "CÓDIGO_SEMÂNTICO",
    "message": "Descrição legível do erro"
  }
}
```

Para erros de validação, o campo `errors` detalha os problemas por campo:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Validation failed",
    "errors": [
      { "field": "cpf",   "message": "The CPF \"000.000.000-00\" is not valid." },
      { "field": "email", "message": "Email \"abc\" is not a valid email address" }
    ]
  }
}
```

### Tabela de códigos de erro

| HTTP | Código | Quando ocorre |
|---|---|---|
| 400 | `BAD_REQUEST` | Violação de regra de negócio |
| 401 | `UNAUTHORIZED` | Token ausente, expirado ou inválido |
| 403 | `FORBIDDEN` | Usuário autenticado sem permissão |
| 404 | `NOT_FOUND` | Recurso não encontrado |
| 409 | `CONFLICT` | Duplicidade (email, CPF, CRM) |
| 422 | `VALIDATION_ERROR` | Payload inválido (campos, formatos) |
| 503 | `SERVICE_UNAVAILABLE` | Health-check detectou dependência indisponível (ex: banco) |
| 500 | `INTERNAL_SERVER_ERROR` | Erro não tratado na aplicação |

---

## Observability

A API expõe os três pilares básicos de observabilidade exigidos em produção: logs estruturados e correlacionáveis, métricas operacionais via health-check e visibilidade do estado de dependências críticas.

### Logging estruturado com Monolog

Todos os logs são emitidos em formato JSON via Monolog, prontos para ingestão por ferramentas como ELK, Loki ou CloudWatch Logs. Cada entrada de log carrega automaticamente:

- **Correlation ID** — um UUID v4 gerado (ou propagado, se o cliente enviar o header `X-Correlation-Id`) por requisição, injetado em *todas* as mensagens de log daquela requisição via `CorrelationIdProcessor`. O mesmo ID é devolvido no header de resposta, permitindo rastrear uma requisição de ponta a ponta entre cliente, API e logs.
- **Contexto da aplicação** — nome do serviço (`medical-scheduling-api`) e ambiente (`dev`, `prod`, `test`).
- **Eventos de requisição/resposta** — o `RequestLogSubscriber` registra `http.request` (método, path, query string, IP, user agent) e `http.response` (status, duração em ms), com nível ajustado automaticamente conforme o status code (`info` para 2xx/3xx, `warning` para 4xx, `error` para 5xx).

Exemplo de log gerado:

```json
{
  "message": "http.response",
  "context": {
    "method": "POST",
    "path": "/appointments",
    "status": 201,
    "duration_ms": 42.31,
    "ip": "172.18.0.1"
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "app",
  "extra": {
    "correlation_id": "f47ac10b-58cc-4372-a567-0e02b2c3d479",
    "app": "medical-scheduling-api",
    "env": "prod"
  }
}
```

Em produção, o handler `fingers_crossed` mantém um buffer e só grava o log completo (incluindo o contexto de debug) quando ocorre um erro real, evitando ruído desnecessário sem perder visibilidade quando algo falha.

### Health Check

```http
GET /health
```

Endpoint público (sem autenticação), pensado para liveness/readiness probes de orquestradores (Kubernetes, Docker Swarm, ECS) e load balancers. Verifica:

- Conectividade real com o PostgreSQL (`SELECT 1`)
- Status do OPcache

**Resposta `200 OK` (saudável):**
```json
{
  "status": "ok",
  "checks": {
    "database": { "status": "ok" },
    "opcache": { "status": "ok", "enabled": true }
  }
}
```

**Resposta `503 Service Unavailable` (degradado):**
```json
{
  "status": "error",
  "checks": {
    "database": { "status": "error", "detail": "SQLSTATE[08006]..." },
    "opcache": { "status": "ok", "enabled": true }
  }
}
```

---

## Testes

O projeto possui duas suítes complementares: testes **unitários** dos Use Cases (mocks, sem I/O) e testes **funcionais de API** (HTTP end-to-end, com kernel real e banco PostgreSQL).

### Estrutura

```
tests/
├── Api/                          # Testes funcionais (WebTestCase)
│   ├── ApiTestCase.php           # Base: client HTTP, fixtures, helpers de autenticação
│   ├── ApiDocControllerTest.php  # /api/doc e /api/doc.json
│   ├── AppointmentControllerTest.php
│   ├── AuthControllerTest.php
│   ├── DoctorControllerTest.php
│   └── HealthControllerTest.php
└── UseCase/                       # Testes unitários (mocks)
    ├── Appointment/
    ├── Doctor/
    ├── Patient/
    └── Specialty/
```

### Testes Unitários (Use Cases)

Cobrem exclusivamente a camada de Use Cases — onde a lógica de negócio vive. Repositórios e entidades são mockados com `createMock()`, garantindo testes unitários puros: sem banco de dados, sem I/O, sem estado compartilhado entre casos de teste. A suíte roda em milissegundos em qualquer máquina.

Cada teste segue a convenção `test_should_<comportamento_esperado>`, tornando os nomes dos métodos uma documentação viva do sistema — ler os testes é suficiente para entender o que cada Use Case garante.

**Cobertura dos Use Cases:**

- **CreateAppointmentUseCase** — cria consulta com sucesso; lança exceção quando médico/paciente não encontrado ou inativo; lança exceção ao agendar no passado, em conflito de horário ou ao atingir limite diário; persiste notas quando fornecidas
- **CancelAppointmentUseCase** — cancela consulta agendada com sucesso; lança exceção quando não encontrada, já cancelada ou já completada
- **CreateDoctorUseCase** — cria médico com sucesso; lança exceção quando especialidade não encontrada ou CRM já cadastrado
- **DeactivateDoctorUseCase** — desativa médico com sucesso; lança exceção quando não encontrado ou já inativo
- **CreatePatientUseCase** — cria paciente com sucesso (com e sem telefone); lança exceção quando CPF já cadastrado
- **DeactivatePatientUseCase** — desativa paciente com sucesso; lança exceção quando não encontrado ou já inativo
- **CreateSpecialtyUseCase** — cria especialidade com sucesso; lança exceção quando nome já cadastrado

### Testes de Integração e API (WebTestCase)

Sobem o kernel real do Symfony e exercitam os endpoints HTTP de ponta a ponta — incluindo autenticação JWT, autorização por role, persistência real no PostgreSQL de teste e cenários de erro.

O que essa suíte cobre:

- **Fluxo de autenticação completo**: login com credenciais válidas/inválidas, emissão de JWT, acesso a `/auth/me`, registro de usuários com controle de role
- **Autorização (RBAC)**: cada endpoint é testado com tokens de diferentes roles, validando `401` (sem token) e `403` (role insuficiente)
- **Cenários de erro reais**: `404` para recursos inexistentes (com `error.code: NOT_FOUND`), `422` com `error.errors[]` detalhado por campo para payloads inválidos, `409` para conflitos (e-mail duplicado), `400` para violações de regra de negócio (agendamento no passado)
- **Persistência e estado**: criação, listagem paginada (`meta.total`, `meta.hasMore`), cancelamento de consultas, desativação de médicos/pacientes — tudo contra um banco PostgreSQL real, truncado entre testes via `ApiTestCase::truncateDatabase()`
- **Contrato de resposta**: o envelope `{ success, data, error, meta }` é validado em todos os cenários, garantindo consistência entre endpoints
- **Documentação**: `/api/doc` e `/api/doc.json` acessíveis sem autenticação e com a spec OpenAPI válida

### Executando

```bash
# apenas testes unitários (Use Cases)
docker compose exec app vendor/bin/phpunit --testsuite Unit

# apenas testes funcionais/API (requer banco de teste configurado, ver .env.test)
docker compose exec app vendor/bin/phpunit --testsuite Api

# suíte completa
docker compose exec app vendor/bin/phpunit
```

A suíte `Api` requer um PostgreSQL acessível conforme `DATABASE_URL` do `.env.test`, com schema migrado (`doctrine:migrations:migrate --env=test`).

Para ver a cobertura de código (requer Xdebug ou PCOV):

```bash
docker compose exec app vendor/bin/phpunit --coverage-text
```

---

## Estrutura do Projeto

```
medical-scheduling-api/
├── config/
│   ├── jwt/
│   │   ├── private.pem          # Chave privada RSA (não commitar)
│   │   └── public.pem           # Chave pública RSA
│   └── packages/
│       ├── lexik_jwt_authentication.yaml
│       ├── messenger.yaml       # Transporte async para domain events
│       ├── monolog.yaml         # Logging estruturado (JSON) por ambiente
│       ├── nelmio_api_doc.yaml  # Configuração do Swagger/OpenAPI
│       └── security.yaml        # Firewalls, access_control, RBAC
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
├── migrations/
├── src/
│   ├── Controller/
│   │   ├── AppointmentController.php
│   │   ├── AuthController.php
│   │   ├── DoctorController.php
│   │   ├── HealthController.php  # GET /health
│   │   ├── PatientController.php
│   │   └── SpecialtyController.php
│   ├── DTO/
│   │   ├── Appointment/CreateAppointmentDTO.php
│   │   ├── Auth/RegisterDTO.php
│   │   ├── Doctor/CreateDoctorDTO.php
│   │   ├── Patient/CreatePatientDTO.php
│   │   └── Specialty/CreateSpecialtyDTO.php
│   ├── Entity/
│   │   ├── Appointment.php      # Encapsula cancel() e complete()
│   │   ├── Doctor.php
│   │   ├── Patient.php
│   │   ├── Specialty.php
│   │   └── User.php             # Implementa UserInterface + PasswordAuthenticatedUserInterface
│   ├── Enum/
│   │   ├── AppointmentStatus.php # isCancellable(), isTerminal()
│   │   └── UserRole.php          # canManageScheduling(), isAdmin()
│   ├── Event/
│   │   ├── AppointmentCreatedEvent.php
│   │   └── AppointmentCancelledEvent.php
│   ├── EventSubscriber/
│   │   ├── CorrelationIdSubscriber.php  # Gera/propaga X-Correlation-Id
│   │   ├── ExceptionSubscriber.php      # Tratamento global de exceções
│   │   └── RequestLogSubscriber.php     # Loga http.request / http.response
│   ├── Http/
│   │   └── ApiResponse.php      # Envelope padronizado de resposta
│   ├── Logger/
│   │   └── CorrelationIdProcessor.php   # Injeta correlation_id em todos os logs
│   ├── MessageHandler/
│   │   ├── SendAppointmentConfirmationHandler.php  # Simula e-mail/SMS de confirmação
│   │   └── SendAppointmentCancellationHandler.php  # Simula e-mail/SMS de cancelamento
│   ├── OpenApi/
│   │   └── AuthLoginDocs.php    # Documentação OpenAPI de /auth/login (sem controller)
│   ├── Repository/
│   │   ├── AppointmentRepository.php # hasConflict(), countByDoctorAndDate()
│   │   ├── DoctorRepository.php
│   │   ├── PatientRepository.php
│   │   ├── SpecialtyRepository.php
│   │   └── UserRepository.php
│   ├── UseCase/
│   │   ├── Appointment/
│   │   │   ├── CancelAppointmentUseCase.php
│   │   │   └── CreateAppointmentUseCase.php
│   │   ├── Doctor/
│   │   │   ├── CreateDoctorUseCase.php
│   │   │   └── DeactivateDoctorUseCase.php
│   │   ├── Patient/
│   │   │   ├── CreatePatientUseCase.php
│   │   │   └── DeactivatePatientUseCase.php
│   │   └── Specialty/
│   │       └── CreateSpecialtyUseCase.php
│   ├── Validator/
│   │   ├── CpfConstraint.php    # Validação completa com dígitos verificadores
│   │   └── CrmConstraint.php    # Suporte a 4 formatos nacionais
│   └── ValueObject/
│       ├── Cpf.php              # CPF normalizado e validado
│       ├── Crm.php              # CRM normalizado (CRM-UF-NUMERO)
│       └── AppointmentSlot.php  # Slot de agendamento (UTC, janelas de conflito)
├── tests/
│   ├── Api/                       # Testes funcionais HTTP end-to-end
│   │   ├── ApiTestCase.php
│   │   ├── ApiDocControllerTest.php
│   │   ├── AppointmentControllerTest.php
│   │   ├── AuthControllerTest.php
│   │   ├── DoctorControllerTest.php
│   │   └── HealthControllerTest.php
│   └── UseCase/                   # Testes unitários (mocks)
│       ├── Appointment/
│       ├── Doctor/
│       ├── Patient/
│       └── Specialty/
├── .env.example
├── .env.test
├── docker-compose.yml
└── README.md
```

---

## Pontos de Destaque do Sistema

**Separação de preocupações real** — Controllers não têm `if`s de negócio. Use Cases não sabem o que é HTTP. Repositories não sabem o que é um DTO. Cada camada tem exatamente uma razão para mudar, e você sente isso quando precisa alterar algo: a mudança fica contida.

**Segurança por design** — o controle de acesso é declarado no `security.yaml` e reforçado com `#[IsGranted]` nos Controllers. Nenhuma rota fica desprotegida por esquecimento; a permissão precisa ser concedida explicitamente, não assumida.

**Validação em duas camadas** — a primeira (DTOs + Symfony Validator) rejeita payloads malformados antes mesmo de tocar o UseCase. A segunda (UseCase) aplica as regras de negócio. Erros de cada camada retornam formatos distintos e semânticos, facilitando o diagnóstico no cliente.

**Enums com comportamento** — o estado de uma entidade não é apenas um valor armazenado: é um objeto que sabe o que pode fazer. `AppointmentStatus::Scheduled->isCancellable()` é mais robusto e expressivo do que comparar strings espalhadas pelo código.

**Testes em múltiplas camadas** — a suíte unitária roda em milissegundos, sem banco, sem containers, sem variáveis de ambiente, validando a lógica de negócio isoladamente. A suíte funcional valida o contrato HTTP real — rotas, autenticação, autorização, serialização e status codes — fechando a lacuna entre "a regra de negócio está certa" e "a API entrega essa regra corretamente ao cliente".

**Observabilidade de ponta a ponta** — cada requisição carrega um correlation ID propagado por toda a stack de logs (via Monolog), permitindo correlacionar uma chamada do cliente com as entradas de log correspondentes. O `/health` expõe o estado real de dependências críticas para orquestradores e load balancers, e o `ExceptionSubscriber` garante que nenhuma stack trace vaza para o cliente em produção — o envelope padronizado torna o tratamento de erros no frontend previsível, independente do endpoint ou da camada onde a exceção ocorreu.

**Contrato de API auto-documentado** — a especificação OpenAPI é gerada a partir do próprio código (`#[OA\...]` em Controllers e DTOs), eliminando o risco clássico de documentação desatualizada. O Swagger UI em `/api/doc` serve tanto como referência para times de frontend/integração quanto como ferramenta de exploração e teste manual da API.

**Domínio rico, não anêmico** — `Cpf`, `Crm` e `AppointmentSlot` são Value Objects que encapsulam validação, normalização e regras de negócio (janelas de conflito, limite diário, validação de data futura). Essa lógica não está duplicada nos Use Cases, nos Validators ou nos setters das entidades — está em um lugar só.

**Arquitetura orientada a eventos** — criar ou cancelar uma consulta dispara eventos de domínio processados de forma assíncrona por handlers dedicados que simulam notificações por e-mail e SMS. Os Use Cases não são acoplados aos detalhes de entrega: eles publicam o evento e seguem em frente.
