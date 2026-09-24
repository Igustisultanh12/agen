# AI Coding Workspace (Multi-User)

> A modern, browser-based multi-user AI coding workspace powered by the **[Free Claude Code (FCC)](https://github.com/Alishahryar1/free-claude-code)** proxy engine.

[![Tests](https://img.shields.io/badge/PHPUnit-25%20Passed-emerald.svg)](#automated-testing)
[![Vue 3](https://img.shields.io/badge/Vue-3.x%20TypeScript-42b883.svg)](https://vuejs.org)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-ff2d20.svg)](https://laravel.com)
[![Monaco Editor](https://img.shields.io/badge/Editor-Monaco-007acc.svg)](https://microsoft.github.io/monaco-editor/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-CSS%20v4-38bdf8.svg)](https://tailwindcss.com)

---

## 📖 Overview

The **AI Coding Workspace** delivers an Antigravity / Cursor-like browser development experience without requiring users to install terminals or local CLIs. It enables software teams, developers, and students to run autonomous coding agents, stream code generation in real-time, inspect files with syntax highlighting, and analyze multi-file projects with strict token quota management and enterprise cost controls.

### Engine Attribution & Integration
The AI routing engine is integrated with **[Free Claude Code](https://github.com/Alishahryar1/free-claude-code)** developed by [Alishahryar1](https://github.com/Alishahryar1). Free Claude Code provides an Anthropic-compatible wire proxy (`POST /v1/messages`) that routes coding prompts to leading AI providers (NVIDIA NIM, OpenRouter, Groq, DeepSeek, Google Gemini, Ollama, LM Studio).

---

## ⚡ Key Features

### 1. Web-Based Coding Workspace (Antigravity Experience)
- **Monaco Code Editor**: Full-featured code editor with syntax highlighting for 50+ languages, file tree navigation, tabbed document editing, and line-level changes.
- **Realtime SSE Streaming**: Real-time server-sent events with interactive typing animations, Markdown parsing, and a dedicated **"Stop Generation"** cancellation trigger.
- **Multi-File Context Injection**: Attach workspace files or active editor code directly into agent prompts with token-budgeted truncation.
- **Custom AI Instructions**: Per-user system instruction customization (e.g. strict TypeScript style, functional paradigms) automatically injected into model prompts.

### 2. Multi-User Authentication & Authorization
- **Dual Role System**: Separate permission gates for standard `user` and `admin` portals.
- **Account Lifecycles**: Instant registration, email verification, password update, and immediate account suspension capabilities.
- **Security Throttling**: IP and email-based login rate limiting, path traversal guards (`..` rejection), and dangerous file upload blocking (`.exe`, `.sh`, `.bat`, etc.).

### 3. Tiered Plans & Atomic Quota Controls
- **User Groups / Plans**: Four pre-configured tiers (**Free**, **Standard**, **Developer**, **Premium**) controlling monthly token caps, daily token caps, max projects, concurrent sessions, and requests per minute (RPM).
- **Concurrency & Race Condition Defense**: Token deductions and limits are wrapped inside atomic database transactions using `DB::table()->lockForUpdate()`.
- **Dynamic Cost Accounting**: Per-model configurable pricing per 1 million tokens (`input`, `output`, `cached`, `reasoning`).

### 4. Resilient Fallback Chains & Multi-Provider Architecture
- **Provider Gateway Adapter**: Connects to NVIDIA NIM (Nemotron 120B default), OpenRouter (Claude 3.5 Sonnet / Haiku), Groq (Llama 3.3 70B), DeepSeek (V3 Chat), Gemini 2.5, and local offline models (Ollama, LM Studio).
- **Automated Fallbacks**: Configurable fallback model chains automatically attempt secondary providers if an upstream provider experiences downtime or rate limits.
- **Encrypted Provider Credentials**: Upstream provider API keys are encrypted at rest using AES-256 (`Crypt::encryptString`).

### 5. Coding Agent Harnesses
Pre-configured autonomous coding agent harnesses:
- **Claude Code**: Multi-file code generation and architectural refactoring.
- **Codex**: Rapid script synthesis and snippet expansion.
- **OpenCode**: Distributed open-weights architecture assistant.
- **Pi**: Deep reasoning and mathematical algorithmic agent.
- **Cline**: Autonomous autonomous task harness.
- **Hermes, DeepSeek Harness, Grok Build, Muse Code, Aider**.

### 6. Developer REST API (v1)
- Full external programmatic access (`/api/v1/chat`, `/api/v1/models`, `/api/v1/usage`, `/api/v1/projects`).
- Secure API key generation with SHA-256 hashed storage, key rotation, revocation, and granular permission scopes (`chat`, `models.read`, `projects.read`, `usage.read`).

### 7. Comprehensive Administrator Portal
- **Dashboard**: Real-time KPI summary (Active Users, Requests, Tokens, Costs), interactive Chart.js time-series timeline, and top users table.
- **User Management**: Search, filter, edit quota limits, inject bonus tokens, suspend/activate accounts.
- **Plans & Groups**: Live tier configuration (token caps, rate limits, storage limits).
- **Provider & Health Monitor**: One-click ping health checks for all AI gateways with latency reporting.
- **Model Pricing Matrix**: Real-time token price editor and fallback chain selector.
- **Audit Logs**: Full security audit trail tracking user logins, password changes, quota updates, and administrator actions.
- **Exporting**: One-click streaming CSV export of all AI usage logs.

---

## 🏗️ System Architecture

```mermaid
flowchart TD
    User([Browser Client]) -->|HTTPS / SSE| Frontend[Vue 3 + Monaco + Pinia]
    Frontend -->|REST API / Bearer Token| Laravel[Laravel 12 API Gateway]

    subgraph Laravel Application
        Auth[Sanctum Auth & RBAC]
        Quota[QuotaService with lockForUpdate]
        Workspace[WorkspaceService Sandboxed Storage]
        CostCalc[CostCalculatorService]
        Adapter[AI Gateway Adapter]
    end

    Laravel --> Auth
    Auth --> Quota
    Quota --> Workspace
    Workspace --> Adapter

    Adapter -->|POST /v1/messages| FCC[Free Claude Code Proxy :8082]

    subgraph Upstream AI Providers
        FCC --> NVIDIA[NVIDIA NIM - Nemotron 120B]
        FCC --> OpenRouter[OpenRouter - Claude 3.5]
        FCC --> Groq[Groq - Llama 3.3]
        FCC --> DeepSeek[DeepSeek V3]
        FCC --> Gemini[Google Gemini 2.5]
        FCC --> Local[Ollama / LM Studio Local]
    end
```

---

## 🚀 Getting Started

### Prerequisites
- **PHP 8.2 or 8.3** with `pdo_sqlite` or `pdo_mysql`, `curl`, `mbstring`, `openssl`
- **Composer** (v2.x)
- **Node.js** (v18+) & **npm**
- **Python 3.10+** (for Free Claude Code engine)

---

### Step 1: Clone the Repository
```bash
git clone https://github.com/Igustisultanh12/agen.git
cd agen
```

---

### Step 2: Install Dependencies
```bash
# Install PHP dependencies
composer install

# Install Frontend dependencies
npm install
```

---

### Step 3: Configure Environment
```bash
cp .env.example .env
php artisan key:generate
```

Configure `.env` if using MySQL:
```env
DB_CONNECTION=sqlite
# Or for MySQL:
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=ai_workspace
# DB_USERNAME=root
# DB_PASSWORD=secret

# Free Claude Code (FCC) Integration Engine
FCC_BASE_URL=http://127.0.0.1:8082
FCC_AUTH_TOKEN=freecc
```

---

### Step 4: Run Migrations and Seeders
```bash
# Creates all tables, user groups, AI providers, models, pricings, and demo accounts
php artisan migrate:fresh --seed
```

#### Pre-seeded Default Accounts:
| Role | Email | Password | Monthly Token Limit |
|---|---|---|---|
| **Admin** | `admin@example.com` | `admin123456` | Unlimited |
| **Demo User** | `user@example.com` | `user123456` | 10,000,000 tokens |

---

### Step 5: Start the Free Claude Code (FCC) Backend Engine
In a separate terminal, launch the Free Claude Code FastAPI proxy:
```bash
# Clone FCC reference repository if not already present
git clone https://github.com/Alishahryar1/free-claude-code.git
cd free-claude-code

# Install dependencies and start server
pip install -r requirements.txt
python main.py
# Running on http://127.0.0.1:8082
```

---

### Step 6: Build & Run the Application
```bash
# Build frontend assets (Vite + Vue 3 + Tailwind CSS)
npm run build

# Start the Laravel local development server
php artisan serve
```

Visit **`http://localhost:8000`** in your browser.

---

## 🧪 Automated Testing

The platform includes a test suite covering Authentication, atomic Quota consumption, Workspace filesystem isolation, Path Traversal protection, and the External API:

```bash
php artisan test
```

### Test Coverage Highlights:
- `Tests\Feature\AuthTest`: User registration, Sanctum token generation, password updates, account suspension gates.
- `Tests\Feature\QuotaTest`: Atomic token deductions, race-condition defenses, threshold checks, bonus token allocation.
- `Tests\Feature\WorkspaceTest`: Isolated workspace provisioning, Monaco file operations, path traversal prevention (`../../etc/passwd`), blocked executable uploads.
- `Tests\Feature\ApiKeyTest`: SHA-256 hashed API key validation, scope permission checks (`models.read`, `chat`), key rotation and revocation.
- `Tests\Feature\AiGatewayTest`: Model catalog grouping, multi-tier pricing calculation, free local model handling.

---

## 🛡️ Security & Privacy Architecture

- **Path Traversal Protection**: Every file access is strictly resolved against the project workspace folder (`storage/app/workspaces/{uuid}/`) using canonical path checking. Any request containing `..` or leading slashes outside the sandbox is rejected with `422 Unprocessable Content`.
- **MIME & File Extension Enforcement**: Disallowed extensions (`.exe`, `.dll`, `.bat`, `.cmd`, `.sh`, `.ps1`, `.vbs`) are permanently blocked from upload and creation.
- **Credential Encryption**: AI Provider keys are never saved in plaintext; they are encrypted via Laravel's AES-256 encryption (`Crypt::encryptString`).
- **External API Tokens**: User API keys are generated as random 40-character tokens (`fcc_...`), and only the cryptographic SHA-256 hash is persisted in the database.

---

## 📄 License
This project is open-source software licensed under the [MIT License](LICENSE).
Attribution to [Free Claude Code](https://github.com/Alishahryar1/free-claude-code) for the backend AI proxy engine.
