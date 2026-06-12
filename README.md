# 🏥 Medical Scheduling API

API REST para gerenciamento de agendamentos médicos, desenvolvida com **PHP 8.2** e **Symfony 7**, seguindo os princípios de Clean Architecture com separação em camadas de Controllers, Use Cases e Repositories.

---

## 📋 Índice

- [Sobre o Projeto](#sobre-o-projeto)
- [Tecnologias](#tecnologias)
- [Arquitetura](#arquitetura)
- [Regras de Negócio](#regras-de-negócio)
- [Pré-requisitos](#pré-requisitos)
- [Instalação e Execução](#instalação-e-execução)
- [Variáveis de Ambiente](#variáveis-de-ambiente)
- [Endpoints](#endpoints)
- [Exemplos de Requisição](#exemplos-de-requisição)
- [Testes](#testes)
- [Estrutura do Projeto](#estrutura-do-projeto)

---

## Sobre o Projeto

Sistema de agendamento médico que permite o gerenciamento de pacientes, médicos, especialidades e consultas. A API garante a integridade dos agendamentos através de validações de conflito de horário, limite diário de consultas por médico e controle de status das consultas.

---

## Tecnologias

| Tecnologia | Versão | Uso |
|---|---|---|
| PHP | 8.2 | Linguagem principal |
| Symfony | 7.x | Framework web |
| Doctrine ORM | 3.x | Mapeamento objeto-relacional |
| Doctrine Migrations | — | Versionamento do schema |
| PostgreSQL | 16 | Banco de dados |
| Docker / Compose | — | Containerização |
| PHPUnit | 11.x | Testes unitários |
| Nginx | Alpine | Servidor web |

---

## Arquitetura

O projeto segue uma arquitetura em camadas inspirada em **Clean Architecture**, onde cada camada tem responsabilidade única e bem definida:

```
Request → Controller → UseCase → Repository → Entity
                ↓
             Response
```

- **Controller** — recebe a requisição HTTP, valida o payload via DTO e delega ao UseCase
- **DTO** — valida e transporta os dados de entrada com anotações do Symfony Validator
- **UseCase** — contém toda a lógica e regras de negócio, isolada e testável
- **Repository** — abstrai o acesso ao banco de dados com queries específicas por domínio
- **Entity** — representa o modelo de dados e encapsula comportamentos do domínio

---

## Regras de Negócio

### Consultas (Appointments)
- Não é possível agendar uma consulta no passado
- O médico não pode ter duas consultas em uma janela de 30 minutos (conflito de horário)
- O médico possui um limite máximo de consultas por dia (padrão: 10, configurável por médico)
- Consultas com status `completed` não podem ser canceladas
- Consultas já `cancelled` não podem ser canceladas novamente

### Médicos (Doctors)
- O CRM deve ser único no sistema
- Médicos inativos não aceitam novos agendamentos
- Cada médico está vinculado a uma especialidade

### Pacientes (Patients)
- O CPF deve ser único e válido (validação de dígitos verificadores)
- Pacientes inativos não podem ter novas consultas agendadas

---

## Pré-requisitos

- [Docker](https://www.docker.com/get-started) 24+
- [Docker Compose](https://docs.docker.com/compose/) 2+

---

## Instalação e Execução

**1. Clone o repositório**
```bash
git clone https://github.com/seu-usuario/medical-scheduling-api.git
cd medical-scheduling-api
```

**2. Configure as variáveis de ambiente**
```bash
cp .env.example .env
```

**3. Suba os containers**
```bash
docker compose up --build -d
```

**4. Execute as migrations**
```bash
docker compose exec app php bin/console doctrine:migrations:migrate
```

**5. Acesse a API**
```
http://localhost:9000
```

---

## Variáveis de Ambiente

Copie o arquivo `.env.example` para `.env` e ajuste conforme necessário:

| Variável | Padrão | Descrição |
|---|---|---|
| `APP_ENV` | `dev` | Ambiente da aplicação |
| `APP_SECRET` | — | Chave secreta da aplicação |
| `APP_PORT` | `9000` | Porta exposta pelo Nginx |
| `POSTGRES_DB` | `medical_scheduling` | Nome do banco de dados |
| `POSTGRES_USER` | `admin` | Usuário do banco |
| `POSTGRES_PASSWORD` | `secret` | Senha do banco |
| `DB_PORT` | `5432` | Porta do PostgreSQL |
| `DEFAULT_URI` | `http://localhost:9000` | URI base da aplicação |

---

## Endpoints

### Specialties
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/specialties` | Lista todas as especialidades |
| `GET` | `/specialties/{id}` | Detalha uma especialidade |
| `POST` | `/specialties` | Cadastra uma especialidade |

### Doctors
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/doctors` | Lista todos os médicos |
| `GET` | `/doctors/{id}` | Detalha um médico |
| `POST` | `/doctors` | Cadastra um médico |
| `PATCH` | `/doctors/{id}/deactivate` | Desativa um médico |

### Patients
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/patients` | Lista todos os pacientes |
| `GET` | `/patients/{id}` | Detalha um paciente |
| `POST` | `/patients` | Cadastra um paciente |
| `PATCH` | `/patients/{id}/deactivate` | Desativa um paciente |

### Appointments
| Método | Rota | Descrição |
|---|---|---|
| `GET` | `/appointments` | Lista todas as consultas |
| `GET` | `/appointments/{id}` | Detalha uma consulta |
| `POST` | `/appointments` | Agenda uma consulta |
| `PATCH` | `/appointments/{id}/cancel` | Cancela uma consulta |
| `GET` | `/appointments/patient/{patientId}` | Histórico de consultas do paciente |

---

## Exemplos de Requisição

### Cadastrar especialidade
```http
POST /specialties
Content-Type: application/json

{
  "name": "Cardiology",
  "description": "Heart and cardiovascular specialist"
}
```

### Cadastrar médico
```http
POST /doctors
Content-Type: application/json

{
  "name": "Dr. Gregory House",
  "crm": "CRM12345",
  "specialtyId": "uuid-da-especialidade",
  "maxAppointmentsPerDay": 8
}
```

### Cadastrar paciente
```http
POST /patients
Content-Type: application/json

{
  "name": "John Doe",
  "cpf": "52998224725",
  "birthDate": "1990-05-15",
  "phone": "85999990000"
}
```

### Agendar consulta
```http
POST /appointments
Content-Type: application/json

{
  "doctorId": "uuid-do-medico",
  "patientId": "uuid-do-paciente",
  "scheduledAt": "2025-12-20 14:00:00",
  "notes": "Primeira consulta, paciente com histórico de hipertensão"
}
```

### Cancelar consulta
```http
PATCH /appointments/{id}/cancel
```

---

## Testes

Execute a suíte de testes unitários:

```bash
docker compose exec app php vendor/bin/phpunit
```

Os testes cobrem todos os Use Cases com os seguintes cenários:

- **CreateAppointmentUseCase** — agendamento com sucesso, médico não encontrado, médico inativo, paciente não encontrado, paciente inativo, agendamento no passado, conflito de horário, limite diário atingido
- **CancelAppointmentUseCase** — cancelamento com sucesso, consulta não encontrada, já cancelada, já completada
- **CreateDoctorUseCase** — criação com sucesso, especialidade não encontrada, CRM duplicado
- **DeactivateDoctorUseCase** — desativação com sucesso, não encontrado, já inativo
- **CreatePatientUseCase** — criação com sucesso, CPF duplicado, sem telefone
- **DeactivatePatientUseCase** — desativação com sucesso, não encontrado, já inativo
- **CreateSpecialtyUseCase** — criação com sucesso, especialidade duplicada

---

## Estrutura do Projeto

```
medical-scheduling-api/
├── docker/
│   ├── Dockerfile
│   └── nginx.conf
├── migrations/
├── src/
│   ├── Controller/
│   │   ├── AppointmentController.php
│   │   ├── DoctorController.php
│   │   ├── PatientController.php
│   │   └── SpecialtyController.php
│   ├── DTO/
│   │   ├── Appointment/CreateAppointmentDTO.php
│   │   ├── Doctor/CreateDoctorDTO.php
│   │   ├── Patient/CreatePatientDTO.php
│   │   └── Specialty/CreateSpecialtyDTO.php
│   ├── Entity/
│   │   ├── Appointment.php
│   │   ├── Doctor.php
│   │   ├── Patient.php
│   │   └── Specialty.php
│   ├── EventSubscriber/
│   │   └── ExceptionSubscriber.php
│   ├── Repository/
│   │   ├── AppointmentRepository.php
│   │   ├── DoctorRepository.php
│   │   ├── PatientRepository.php
│   │   └── SpecialtyRepository.php
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
│   └── Validator/
│       └── CpfConstraint.php
├── tests/
│   └── UseCase/
│       ├── Appointment/
│       ├── Doctor/
│       ├── Patient/
│       └── Specialty/
├── .env.example
├── docker-compose.yml
└── README.md
```
