---
description: Iniciar desenvolvimento orientado por spec — escrever uma especificação estruturada antes de codar
---

Comece entendendo o que o usuário quer construir. Faça perguntas de esclarecimento sobre:
1. Objetivo e quem vai usar
2. Funcionalidades principais e critérios de aceite
3. Stack e restrições técnicas (considere o monorepo: panel Laravel + orchestrator Go + agent Go)
4. Limites conhecidos (o que sempre fazer, o que perguntar antes, o que nunca fazer)

Depois gere uma spec estruturada cobrindo:
- **Objetivo**: O que será construído e por quê
- **Componentes afetados**: Quais partes do monorepo serão modificadas (panel, orchestrator, agent, proto, docker-compose)
- **Estrutura do projeto**: Arquivos que serão criados ou modificados
- **Estilo de código**: Seguir convenções do CLAUDE.md (zerolog para Go, Laravel para PHP)
- **Estratégia de testes**: Como verificar que funciona (endpoints, logs, curl, deploy de teste)
- **Limites**: O que está dentro e fora do escopo

Salve a spec como `SPEC.md` na raiz do projeto e confirme com o usuário antes de seguir.

**Contexto EasyDeploy:**
- Consulte o `CLAUDE.md` para arquitetura e convenções
- Considere o fluxo: Panel -> Orchestrator -> Redis -> Agent -> Docker -> Traefik
- Lembre que mudanças no orchestrator/agent requerem rebuild Docker
- Mudanças no panel precisam de restart do queue-worker
