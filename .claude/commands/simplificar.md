---
description: Simplificar código para clareza e manutenibilidade — reduzir complexidade sem mudar comportamento
---

Simplifique o código alterado recentemente (ou o escopo especificado) preservando o comportamento exato:

1. Leia o CLAUDE.md e estude as convenções do projeto
2. Identifique o código alvo — mudanças recentes, salvo se um escopo mais amplo for especificado
3. Entenda o propósito do código, quem chama, casos de borda e cobertura de testes antes de tocar
4. Procure oportunidades de simplificação:
   - Aninhamento profundo -> guard clauses ou funções extraídas
   - Funções longas -> dividir por responsabilidade
   - Ternários aninhados -> if/else ou switch
   - Nomes genéricos -> nomes descritivos
   - Lógica duplicada -> funções compartilhadas
   - Código morto -> remover após confirmar que não é usado
5. Aplique cada simplificação incrementalmente — verifique compilação após cada mudança
6. Confirme que tudo compila, testes passam e o diff está limpo

**Regras:**
- Nunca mude comportamento — apenas simplifique a estrutura
- Entenda ANTES de modificar (Cerca de Chesterton — se existe uma razão, descubra antes de remover)
- Se algo quebrar após uma simplificação, reverta e reconsidere
- Siga as convenções do projeto (zerolog p/ Go, padrões Laravel p/ PHP)
- Um commit por simplificação significativa

**Especifico para EasyDeploy:**
- Go: Prefira `fmt.Errorf("contexto: %w", err)` para wrapping de erros
- Go: Use campos estruturados no zerolog: `log.Error().Str("field", val).Err(err).Msg("msg")`
- PHP: Mantenha consistência com Filament 3 patterns
- SQL: Use query builder quando possível, raw SQL apenas quando necessário
