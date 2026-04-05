---
description: Implementar a próxima tarefa incrementalmente — construir, testar, verificar, commitar
---

Pegue a próxima tarefa pendente do plano. Para cada tarefa:

1. Leia os critérios de aceite da tarefa
2. Carregue o contexto relevante (código existente, padrões, tipos)
3. Implemente o código necessário seguindo as convenções do CLAUDE.md
4. Verifique compilação:
   - **Go**: `go build ./...` no diretório do componente (orchestrator ou agent)
   - **PHP**: `php artisan route:list` ou verificação manual de sintaxe
5. Execute verificações:
   - **Go**: `go vet ./...`
   - **PHP**: Verificação de rotas, config, etc.
6. Teste manualmente se necessário (curl, logs, etc.)
7. Commite com mensagem descritiva (`feat:`, `fix:`, `refactor:`)
8. Marque a tarefa como concluída e passe para a próxima

**Se algo falhar:**
- Erro de compilação Go: Verifique imports, tipos, assinaturas
- Erro de proto: Rode `make proto` se mudou `agent.proto`
- Erro de SQL/migration: Verifique tipos PostgreSQL (inet, nullable, etc.)
- Erro de runtime: Consulte logs com `docker compose logs -f <servico>`

**Princípios:**
- Simplicidade primeiro — não engenheirar demais
- Toque apenas o que a tarefa requer — disciplina de escopo
- Mantenha compilável a cada passo
- Um commit por tarefa concluída
