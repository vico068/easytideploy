---
description: Workflow de testes — verificar funcionalidade, reproduzir bugs, validar integração
---

**Para novas funcionalidades:**
1. Identifique os comportamentos esperados
2. Escreva testes que descrevam o comportamento:
   - **Go**: `go test ./...` no diretório do componente
   - **PHP**: `php artisan test` no container do panel
3. Implemente o código para passar os testes
4. Refatore mantendo os testes verdes

**Para correção de bugs (Padrão Prove-It):**
1. Reproduza o bug (via curl, logs, ou teste automatizado)
2. Confirme que o bug existe com evidência concreta
3. Implemente a correção
4. Confirme que o bug foi corrigido com a mesma verificação
5. Verifique que nada mais quebrou

**Verificações de integração EasyDeploy:**
- **Fluxo de deploy completo**: Panel -> Orchestrator -> Redis -> Agent -> Docker -> Traefik
- **Callback do orchestrator**: POST para `/api/internal/deployments/{id}/status`
- **Geração de config Traefik**: Verificar arquivo YAML em `/etc/traefik/dynamic/`
- **Health check de containers**: Verificar `health_status` no banco
- **Domínios**: Verificar que Traefik roteia corretamente com `curl -v`

**Cenários a cobrir para cada função:**

| Cenario | Exemplo |
|---------|---------|
| Caminho feliz | Input válido produz resultado esperado |
| Input vazio | String vazia, array vazio, null |
| Valores limite | Min, max, zero, negativo |
| Caminhos de erro | Input inválido, falha de rede, timeout |
| Tipos PostgreSQL | inet, nullable int, timestamp |
