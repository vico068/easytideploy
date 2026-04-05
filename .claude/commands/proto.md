---
description: Workflow de mudança em definições gRPC — alterar proto, regenerar código, atualizar orchestrator e agent
---

Alterar definição gRPC entre orchestrator e agent.

Contexto: $ARGUMENTS

## Workflow de Mudança Proto

### 1. Entender a Arquitetura gRPC Atual

```
orchestrator/pkg/proto/agent.proto         ← Definição canônica
orchestrator/pkg/proto/agent_grpc.go       ← Tipos e cliente Go (orchestrator)
agent/pkg/proto/agent_grpc.go              ← Tipos e servidor Go (agent)
agent/internal/grpc/codec.go               ← JSON codec (agent)
orchestrator/internal/scheduler/agent_client.go ← Cliente que chama o agent
agent/internal/grpc/server.go              ← Handler que recebe chamadas
```

**IMPORTANTE**: O EasyDeploy usa JSON codec (não protobuf binário) para gRPC. Os tipos NÃO implementam `proto.Message`. As struct tags JSON controlam a serialização.

### 2. Editar o Proto

Modificar `orchestrator/pkg/proto/agent.proto` com as mudanças desejadas.

### 3. Atualizar os Tipos Go

Como o projeto usa JSON codec manual, atualizar as structs Go em DOIS lugares:

**Orchestrator** (`orchestrator/pkg/proto/agent_grpc.go`):
- Atualizar Request/Response structs
- Manter struct tags `json:"NomeExato"` matching EXATO entre client e server
- Verificar se o client em `agent_client.go` usa os campos novos corretamente

**Agent** (`agent/pkg/proto/agent_grpc.go` ou types equivalente):
- Atualizar as MESMAS structs com as MESMAS tags JSON
- Atualizar handler em `agent/internal/grpc/server.go`

### 4. Verificar Alinhamento

Checklist critico — JSON tags devem ser IDÊNTICAS:

```go
// orchestrator/pkg/proto/agent_grpc.go
type CreateContainerResponse struct {
    Success     bool   `json:"Success"`
    ContainerId string `json:"ContainerId"`
    HostPort    int32  `json:"HostPort"`       // ← tag EXATA
}

// agent/pkg/proto/agent_grpc.go (ou types.go)
type CreateContainerResponse struct {
    Success     bool   `json:"Success"`
    ContainerId string `json:"ContainerId"`
    HostPort    int32  `json:"HostPort"`       // ← IGUAL ao orchestrator
}
```

Se as tags forem diferentes, o campo será silenciosamente ignorado na deserialização JSON. Isso causa bugs difíceis de rastrear (como o host_port=0 que corrigimos).

### 5. Compilar Ambos

```bash
# Orchestrator
cd orchestrator && go build ./...

# Agent
cd agent && go build ./...
```

### 6. Deploy

Ambos os serviços precisam ser rebuilded e restartados:

```bash
# Servidor de controle (orchestrator)
sshpass -p 'EasyTI@2026' ssh root@deploy.easyti.cloud "cd /opt/easydeploy && git pull && docker compose build orchestrator && docker compose up -d orchestrator"

# Servidor worker (agent)
sshpass -p 'EasyTI@2026' ssh root@177.85.77.175 "cd /opt/easydeploy && git pull && docker compose build agent && docker compose up -d agent"
```

### Armadilhas

| Armadilha | Prevenção |
|-----------|-----------|
| Tags JSON diferentes entre orchestrator e agent | Copiar structs literalmente, comparar lado a lado |
| Campo novo no Response mas handler não popula | Verificar handler do agent que monta a resposta |
| Protobuf binary codec em vez de JSON | EasyDeploy usa JSON codec — NÃO usar `proto.Marshal` |
| Só rebuildar um lado | SEMPRE rebuildar orchestrator E agent juntos |
| Esquecer de testar a chamada gRPC | Fazer um deploy de teste e verificar logs de ambos |
