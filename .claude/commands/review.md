---
description: Revisão de código em cinco eixos — correção, legibilidade, arquitetura, segurança, performance
---

Revise as mudanças atuais (staged ou commits recentes) nos cinco eixos:

### 1. Correção
- O código faz o que a spec/tarefa diz?
- Casos de borda tratados (null, vazio, valores limite, caminhos de erro)?
- Testes verificam o comportamento esperado?
- Existem race conditions, off-by-one, ou inconsistências de estado?

### 2. Legibilidade
- Outro engenheiro entende sem explicação?
- Nomes são descritivos e consistentes com as convenções do projeto?
- Fluxo de controle é direto (sem aninhamento profundo)?
- Código bem organizado (código relacionado agrupado, limites claros)?

### 3. Arquitetura
- A mudança segue padrões existentes ou introduz um novo?
- Se padrão novo, é justificado e documentado?
- Limites de módulo mantidos? Dependências circulares?
- Nível de abstração apropriado (não over-engineered, não acoplado demais)?

### 4. Segurança
- Input do usuário validado e sanitizado nas fronteiras do sistema?
- Secrets fora do código, logs e versionamento?
- Autenticação/autorização verificada onde necessário?
- Queries parametrizadas? Output encodado?
- Especialmente para EasyDeploy: acesso ao Docker socket seguro? API keys protegidas? gRPC autenticado?

### 5. Performance
- Queries N+1 no panel Laravel?
- Loops sem limite ou fetch de dados sem constraint?
- Operações síncronas que deveriam ser async?
- Paginação em endpoints de lista?
- Pools de conexão PostgreSQL configurados?

**Classifique cada achado como:**
- **Critico** — Deve corrigir antes de merge (vulnerabilidade, risco de perda de dados, funcionalidade quebrada)
- **Importante** — Deveria corrigir antes de merge (teste faltando, abstração errada, tratamento de erro ruim)
- **Sugestao** — Considerar melhoria (naming, estilo, otimização opcional)

Inclua referências específicas `arquivo:linha` e recomendações de correção.
