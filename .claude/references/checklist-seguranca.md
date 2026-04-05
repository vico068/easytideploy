# Checklist de Segurança — EasyDeploy

Referência rápida de segurança para a plataforma EasyDeploy. Adaptado para o contexto de PaaS com Docker, gRPC e multi-tenancy.

## Pre-Commit

- [ ] Sem secrets no código (`git diff --cached | grep -i "password\|secret\|api_key\|token"`)
- [ ] `.gitignore` cobre: `.env`, `.env.local`, `*.pem`, `*.key`, `docker-compose.override.yml`
- [ ] `.env.example` usa valores placeholder (não secrets reais)

## Autenticação

- [ ] Senhas hasheadas com bcrypt (>= 12 rounds) via Laravel Hash
- [ ] Sessões com cookies: `httpOnly`, `secure`, `sameSite: lax`
- [ ] Expiração de sessão configurada
- [ ] Rate limiting em endpoint de login
- [ ] Tokens de reset com tempo limitado e uso único

## Autorização

- [ ] Todo endpoint protegido verifica autenticação
- [ ] Todo acesso a recurso verifica ownership/role (previne IDOR)
- [ ] Endpoints admin requerem verificação de role admin
- [ ] API keys com escopo mínimo necessário
- [ ] Endpoints internos (`/api/internal/*`) autenticados por Bearer token

## Validação de Input

- [ ] Todo input de usuário validado nas fronteiras (rotas API, form handlers)
- [ ] Validação usa allowlists (não denylists)
- [ ] Strings com tamanho restrito (min/max)
- [ ] Ranges numéricos validados
- [ ] Queries SQL parametrizadas (sem concatenação de string)
- [ ] Output HTML encodado (usar auto-escaping do Blade/Filament)

## Headers de Segurança

```
Content-Security-Policy: default-src 'self'; script-src 'self'
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
```

## Proteção de Dados

- [ ] Campos sensíveis excluídos de respostas API (password_hash, tokens, etc.)
- [ ] Dados sensíveis não logados (senhas, tokens, API keys completas)
- [ ] Variáveis de ambiente de apps dos usuários criptografadas no banco (EnvironmentVariable model)
- [ ] HTTPS para toda comunicação externa
- [ ] Backups de banco criptografados

## Segurança de Dependências

```bash
# Go
go list -m -json all | go-mod-outdated    # verificar desatualizadas
govulncheck ./...                          # verificar vulnerabilidades

# PHP
composer audit                             # verificar vulnerabilidades
composer outdated                          # verificar desatualizadas
```

## Tratamento de Erros

```go
// Produção: erro genérico, sem internals para o usuário
// Go — zerolog para logs internos
log.Error().Err(err).Str("deployment_id", id).Msg("falha ao criar container")
// Resposta HTTP: apenas mensagem genérica
http.Error(w, "internal server error", http.StatusInternalServerError)
```

```php
// PHP — nunca expor detalhes internos
// Laravel já faz isso por padrão em produção (APP_DEBUG=false)
```

## Específico EasyDeploy

### Docker
- [ ] Socket Docker montado apenas nos containers que precisam (orchestrator)
- [ ] Containers de usuários sem acesso ao Docker socket
- [ ] Containers de usuários com limites de CPU/memória
- [ ] Imagens de usuários buildadas em ambiente isolado
- [ ] Registry Docker com acesso controlado

### Comunicação Inter-Serviços
- [ ] Panel -> Orchestrator: API key via Bearer token
- [ ] Orchestrator -> Agent: gRPC (considerar mTLS em produção)
- [ ] Orchestrator -> Panel (callback): API key validada
- [ ] Redis: acesso restrito por rede Docker interna

### Traefik
- [ ] Configuração dinâmica não permite injection de rotas por usuários
- [ ] Certificados SSL gerenciados automaticamente (Let's Encrypt)
- [ ] Middleware de segurança (headers) aplicado em todas as rotas
- [ ] Rate limiting configurado por rota/domínio

### Multi-Tenancy
- [ ] Isolamento de rede entre containers de diferentes apps
- [ ] Dados de um tenant não acessíveis por outro
- [ ] Logs de containers de usuários não expõem dados de outros
- [ ] Variáveis de ambiente de apps separadas e criptografadas

## OWASP Top 10 — Referência Rápida

| # | Vulnerabilidade | Prevenção |
|---|---|---|
| 1 | Broken Access Control | Verificação de auth em todo endpoint, validação de ownership |
| 2 | Falhas Criptográficas | HTTPS, hashing forte, sem secrets no código |
| 3 | Injeção | Queries parametrizadas, validação de input |
| 4 | Design Inseguro | Threat modeling, desenvolvimento orientado por spec |
| 5 | Configuração Insegura | Headers de segurança, permissões mínimas, auditar deps |
| 6 | Componentes Vulneráveis | `composer audit`, `govulncheck`, manter deps atualizadas |
| 7 | Falhas de Autenticação | Senhas fortes, rate limiting, gestão de sessão |
| 8 | Falhas de Integridade | Verificar dependências, artefatos assinados |
| 9 | Falhas de Logging | Logar eventos de segurança, não logar secrets |
| 10 | SSRF | Validar/allowlist URLs, restringir requests de saída |
