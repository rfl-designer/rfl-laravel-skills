---
name: to-prd
description: Synthesize the current conversation into a Laravel-flavored PRD and publish it as a GitHub issue. Use after a /grill-with-docs session when the user wants to formalize the plan, or when the user explicitly asks for a PRD, spec, or product requirements document.
---

> **Crédito:** baseada em [`mattpocock/skills` — `to-prd`](https://github.com/mattpocock/skills) (MIT). Template `Implementation Decisions` reescrito para refletir a estratificação Laravel (Domain / Application / Presentation / Persistence).

# To PRD (Laravel)

Esta skill pega o contexto da conversa atual e o entendimento do código e produz um PRD. **Não entreviste o usuário** — apenas sintetize o que você já sabe. Se faltar contexto, rode `/grill-with-docs` antes.

## Pré-requisitos

- `gh` CLI autenticado (`gh auth status`)
- Projeto com remote GitHub configurado
- Idealmente: `AGENTS.md` (boost) presente — informa versões de Laravel/Livewire/Pest/Flux
- Idealmente: `CONTEXT.md` presente — fornece o vocabulário de domínio

## Processo

### 1. Leia o contexto da stack e do domínio

Antes de sintetizar:

- [ ] Ler `AGENTS.md` (se existir) — saber pacotes e versões
- [ ] Ler `CONTEXT.md` (se existir) — usar o vocabulário de domínio do projeto
- [ ] Escanear `docs/adr/` em busca de ADRs relevantes na área que será tocada

### 2. Explore o repo (se ainda não fez)

Entenda o estado atual do código. Use o glossário de domínio em todo o PRD. Respeite ADRs existentes.

### 3. Esboce os módulos a construir/modificar

Procure ativamente por oportunidades de extrair **deep modules** — encapsular muita funcionalidade atrás de uma interface simples e estável. Em Laravel, isso costuma virar:

- Uma **Action** invocável (`__invoke`) que aceita dependências por construtor
- Um **Form Request** que carrega validação + autorização da rota
- Um **Service** ou **Repository** quando lógica é compartilhada entre contextos

Confira com o usuário que esses módulos batem com o que ele tinha em mente. Pergunte para quais módulos ele quer testes escritos.

### 4. Escreva o PRD usando o template abaixo

Salve em `docs/prd/<slug>.md` no repo. Em paralelo, publique como issue no GitHub via:

```bash
gh issue create \
  --title "PRD: <título>" \
  --label "ready-for-agent,prd" \
  --body-file docs/prd/<slug>.md
```

Aplique o label `ready-for-agent` — não precisa de triagem adicional.

<prd-template>

## Problema

O problema que o usuário enfrenta, da perspectiva dele.

## Solução

A solução para o problema, da perspectiva do usuário.

## User stories

Lista LONGA e numerada de user stories. Cada uma no formato:

1. Como <ator>, eu quero <feature>, para que <benefício>

<exemplo>
1. Como cliente da plataforma, eu quero baixar minhas propostas em PDF, para arquivar em meu sistema interno
</exemplo>

A lista deve ser extensiva e cobrir todos os aspectos da feature.

## Implementation Decisions

Decisões tomadas durante o grilling, organizadas pela estratificação Laravel. **Não inclua caminhos de arquivo nem snippets de código** — eles ficam desatualizados rápido. *Exceção:* se um protótipo produziu um snippet que codifica uma decisão melhor que prosa (state machine, schema, type shape), inline-o e marque como vindo do protótipo.

### Domain (Models / Migrations / Casts / Enums)

- Novos models, atributos, relacionamentos
- Mudanças de schema (campos novos, índices, FKs)
- Enums e value objects que materializam estados ou unidades

### Application (Actions / Form Requests / Policies / Jobs / Events)

- Actions invocáveis e suas responsabilidades (single-purpose)
- Form Requests — onde a validação mora (NUNCA no controller)
- Policies / Gates — regras de autorização explícitas
- Jobs — trabalho assíncrono, fila escolhida
- Events / Listeners — comunicação entre contextos

### Presentation (Livewire / Volt / Flux / Routes)

- Componentes Livewire (class vs Volt — declare a escolha e por quê)
- Props, listeners, computed properties
- Componentes Flux usados (`<flux:input>`, `<flux:modal>`, slots nomeados)
- Rotas novas e middlewares aplicados

### Persistence / Integrations

- Mudanças no banco (PostgreSQL/MySQL específicas, índices compostos)
- APIs externas, queues, broadcasting
- Cache (chaves, TTL, invalidação)

## Testing Decisions

- O que torna um bom teste: **comportamento via interface pública**, não detalhes de implementação
- Quais módulos serão testados (priorizar Actions, Form Requests, componentes Livewire críticos)
- Prior art — testes similares no codebase a usar como referência
- Helpers Laravel a usar: `RefreshDatabase`, factories, `Livewire::test()`, `Mail::fake()`, `Http::fake()`

## Out of Scope

O que NÃO faz parte deste PRD. Lista explícita evita scope creep.

## Further Notes

Notas adicionais — riscos, dependências externas, prazos, links pra ADRs.

</prd-template>

## Pós-PRD

Após publicar:

1. Pergunte ao usuário se quer rodar `/to-issues` agora para quebrar o PRD em slices verticais.
2. Se houver decisões hard-to-reverse no PRD que ainda não viraram ADR, sugira `/grill-with-docs` para formalizá-las.
