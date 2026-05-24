# CCS Sit-In Monitoring System — Backend Documentation Summary

This briefing outlines the engineering state, design paradigms, security architecture, and AI operational capabilities of the CCS Sit-In Monitoring System backend.

---

## 1. Executive Summary & Core Stack

The CCS Sit-In Monitoring System backend is a production-grade, lightweight, stateless API designed to handle laboratory student sit-in session lifecycles. Built as a "Pure PHP" micro-architecture, it prioritizes maximum computational performance and minimal dependencies.

### Core Stack
*   **Backend Runtime:** PHP 8+
*   **Database:** PostgreSQL 14+ (leveraging spatial data types, JSON arrays, and UUID keys)
*   **Primary Library Dependencies:** `firebase/php-jwt` (v6.0) for cryptographic token signing and decryption.
*   **Routing System:** Direct file-system routing mapping requests directly to functional scripts.

---

## 2. Directory Topology

```
C:\xampp\htdocs\sitIn/
├── api/                             # Controllers / HTTP Entry Points
│   ├── admin/                       # Administrative endpoints (CRUD labs, rules)
│   ├── ai/                          # AI endpoints (chat.php, student_insights.php, report_summary.php)
│   ├── auth/                        # Security endpoints (login, register, logout)
│   └── student/                     # Student profile, booking, and session actions
├── config/                          # Configuration files
│   ├── ai.php                       # Global AI provider toggles
│   ├── ai_limits.php                # AI quotas, burst, and daily budgets
│   └── ai_rate_limits_cache.json    # LLM request headers cache (local telemetry)
├── docs/                            # Internal guides and specifications
├── includes/                        # Core bootstrapping, middleware, and helpers
│   ├── initialize.php               # System bootstrap, response helpers, autoloader
│   ├── validate_token.php           # JWT extraction and validation middleware
│   └── validator.php                # Centralized request sanitization
├── src/
│   ├── database/                    # Migrations and seeders for database version control
│   └── models/                      # Data Access Objects (DAOs) encapsulating business logic
└── .env                             # Environment secrets (JWT_SECRET, DB_PASSWORD) - [REDACTED]
```

---

## 3. Core Architectural Highlights

1.  **"Skinny Controller, Fat Model" Pattern:** API files serve strictly as Controllers—parsing HTTP queries, invoking authentication guards, and returning standard JSON. All SQL preparation, sanitization, and complex logical checks reside securely in model DAO classes (`src/models/`).
2.  **Stateless Session Lifecycle:** Eliminates standard server-side cookie sessions. Replaces them with cryptographically signed JSON Web Tokens (JWT) combined with real-time active session DB matching.
3.  **Database Security Safeguards:** All database operations strictly use PDO prepared statements with parameter binding to prevent SQL injection. Manual raw queries inside Controllers are strictly forbidden.

---

## 4. Security Posture

The backend implements a highly resilient multi-layer security matrix:

*   **Stateless Session Hijack Protection:** Combines token validation with live DB session checks, absolute token expiration windows, and active IP + User Agent fingerprinting telemetry checks.
*   **Request Integrity & Replay Protections:** Cryptographic request signing via HMAC SHA-256 for all critical AI endpoints prevents payload manipulation. Timestamps are verified against a strict 30-second sliding window to thwart replay attacks.
*   **Adversarial Prompt Injection Detection:** A dual-layer regex and keyword detection pipeline intercepts incoming AI messages, block-logs malicious students for 300 seconds, and issues temporary access lockouts.
*   **Sanitization Layer:** The centralized `Validator` utility strictly filters all client-supplied parameters to defend against Cross-Site Scripting (XSS) and injection vectors.

---

## 5. AI Telemetry & Cost Containment Readiness

The AI integration layer provides production-grade scalability:

*   **Dual LLM Engine Architecture:** Conversational queries are routed through Groq Cloud (Llama 3), while structured analysis tasks (student/admin insights, reports) are executed by Gemini 2.5 Flash.
*   **Proactive Rate-Limiting Telemetry:** Provider rate limits and token quotas are persisted in a local cache. The backend evaluates this cache *before* sending external requests, immediately short-circuiting rate-limited states without incurring network overhead.
*   **Gemini-to-Groq Fallback:** Automatically switches analysis requests to Groq (Llama 3) if Gemini returns a 429 or 5xx code, guaranteeing system uptime.
*   **Global Daily Financial Budgets:** Daily call budgets are hardcoded in `config/ai_limits.php` and verified against daily usage logs (`ai_global_budget` table) to restrict unexpected API bills.
*   **Student Cooldown Safeguards:** Implements a sliding cooldown (5s for chat, 30s for insights) and burst limit constraints to prevent excessive quota consumption.
