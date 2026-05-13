---
name: simplify
description: Run the laravel-simplifier sub-agent over uncommitted Laravel changes to apply idiomatic refactors while preserving behavior. Gated by passing Pest suite. Use after /tdd green or whenever the user wants to clean up recent Laravel code without changing functionality.
---

# Simplify (com laravel-simplifier)

Wrapper que invoca o sub-agent [`laravel-simplifier`](../../../agents/laravel-simplifier.md) no escopo das mudanças não-commitadas. Garante que a suite Pest está verde antes de mexer — refatorar com testes vermelhos é uma forma de mascarar bugs.

## Pré-requisitos

- Mudanças no working tree (staged ou unstaged) — senão não há o que simplificar
- `vendor/bin/pest` instalado
- Working tree NÃO precisa estar limpa, mas precisa de mudanças PHP

## Processo

### 1. Identificar escopo

```bash
git diff --name-only HEAD                 # unstaged
git diff --name-only --staged             # staged
```

Filtrar:
- Apenas arquivos `.php` (Models, Actions, Form Requests, Livewire components, Jobs, etc.)
- Excluir `vendor/`, `node_modules/`, `database/migrations/` (migrations já mergeadas não devem ser editadas)
- Excluir testes — `simplifier` é para código de produção; testes têm regras próprias

Se nenhum arquivo PHP de produção mudou: avise e pare.

### 2. Rodar Pest no escopo (gate)

Se houver testes para os arquivos modificados, rode-os primeiro:

```bash
vendor/bin/pest --filter=<inferir do nome dos arquivos>
```

Se a suite filtrada falhar: **abortar**. Mensagem:
> Não vou simplificar enquanto há testes vermelhos. Resolva os testes primeiro com `/tdd` ou `/diagnose`.

Se não há testes para os arquivos modificados: avise (pode simplificar mas não há rede de segurança) e peça confirmação.

### 3. Invocar o sub-agent

Use a ferramenta `Agent` com `subagent_type: "laravel-simplifier"`. Passe no prompt:

```
Simplifique os seguintes arquivos Laravel mantendo comportamento idêntico:

<lista de arquivos>

Diff atual:
<colar git diff dos arquivos>

Convenções específicas deste projeto (de AGENTS.md / CLAUDE.md):
<resumir 5-10 itens relevantes>

Restrições:
- Não toque em assinaturas públicas de Action/Service (testes dependem)
- Não remova logs ou throws — só consolide
- Preserve type hints; adicione onde estiverem faltando
- Se identificar um bug enquanto simplifica, NÃO corrija — reporte separadamente
```

### 4. Re-rodar Pest pós-simplificação

```bash
vendor/bin/pest
```

Se algo quebrou: mostrar diff entre antes/depois das mudanças do agent, perguntar se reverte ou investiga.

### 5. Reportar ao usuário

Mostrar:
- Arquivos modificados pelo simplifier
- Resumo das mudanças (3-5 bullets do que mudou e por quê)
- Bugs detectados mas NÃO corrigidos (se houver) — sugerir abrir issue ou rodar `/diagnose`

NÃO commitar automaticamente. Usuário decide se aceita as mudanças.

## Quando NÃO rodar

- Código que ainda está RED no `/tdd` — primeiro passe ao GREEN
- Mudanças que envolvem migrations já mergeadas
- Refactors de larga escala (mover módulos, renomear conceitos) — use `/improve-codebase-architecture` upstream
