---
name: to-issues
description: Break a Laravel PRD or plan into independently-grabbable GitHub issues using vertical slices that cut Migration → Action → Livewire/Flux → Pest test. Use when user wants to convert a plan into issues, create implementation tickets, or break down work into AFK-ready tasks.
---

> **Crédito:** baseada em [`mattpocock/skills` — `to-issues`](https://github.com/mattpocock/skills) (MIT). Regra de slice vertical adaptada para a estratificação Laravel (4 camadas) e template de issue com critério de aceitação Pest.

# To Issues (Laravel)

Quebra um plano em issues independentes via **vertical slices** (tracer bullets) onde cada slice atravessa as 4 camadas de uma app Laravel.

## Pré-requisitos

- `gh` CLI autenticado
- Repo com remote GitHub
- PRD ou plano disponível (no contexto, em arquivo, ou referenciado por número de issue)

## Processo

### 1. Reúna contexto

Trabalhe com o que já está na conversa. Se o usuário passou referência de issue (número, URL, path), busque com:

```bash
gh issue view <N> --comments
```

### 2. Explore o codebase (opcional)

Se ainda não explorou, faça agora. Títulos e descrições de issue **devem usar o vocabulário de `CONTEXT.md`**. Respeite ADRs na área tocada. Leia `AGENTS.md` para conhecer versões.

### 3. Desenhe vertical slices

Quebre o plano em **tracer bullets**. Cada slice é uma fatia fina vertical que corta TODAS as camadas de integração ponta-a-ponta — NÃO uma fatia horizontal de uma única camada.

Slices podem ser **HITL** ou **AFK**:

- **HITL** (Human In The Loop) — exige interação humana: decisão arquitetural, design review, aprovação de copy
- **AFK** (Away From Keyboard) — pode ser implementada e mergeada sem interação

Prefira AFK sempre que possível.

<vertical-slice-rules-laravel>

Uma vertical slice Laravel típica corta ao menos 4 camadas:

1. **Domain** — Migration + Model (ou alteração de schema existente)
2. **Application** — Action / Form Request / Policy (regra de negócio + autorização)
3. **Presentation** — Livewire/Volt component + view Blade + Flux UI
4. **Test** — Pest test (feature ou Livewire test) cobrindo o caminho feliz

Slices que **não atravessam essas 4 camadas** geralmente são sub-tarefas de uma slice — agrupe-as.

**Exceções legítimas a 4 camadas:**

- Slices puras de domínio (ex.: novo cast, novo enum) — só Domain + Test
- Slices puras de manutenção (ex.: subir versão de pacote) — Chore, não vertical-slice
- Slices puras de UI (ex.: trocar Tailwind por Flux numa tela existente) — Presentation + Test, sem mudar Domain/Application

Marque-as no título: `[domain-only]`, `[chore]`, `[ui-only]`.

</vertical-slice-rules-laravel>

**Princípio:** uma slice completa é demoável sozinha. "Adiciona migration" sozinha não é demoável; "permite admin convidar membro por e-mail" é.

### 4. Quiz com o usuário

Apresente o breakdown como lista numerada. Para cada slice, mostre:

- **Título** — descritivo, curto, em PT-BR
- **Tipo** — HITL / AFK
- **Camadas** — quais das 4 essa slice toca (ex.: `Domain + Application + Presentation + Test`)
- **Bloqueada por** — quais outras slices precisam terminar antes
- **User stories cobertas** — quais user stories do PRD essa slice atende

Pergunte ao usuário:

- A granularidade está boa? (grossa demais / fina demais)
- As dependências estão certas?
- Alguma slice deve ser fundida ou dividida?
- Os marcadores HITL/AFK estão corretos?

Itere até o usuário aprovar.

### 5. Publique no GitHub

Para cada slice aprovada, publique uma issue. Publique em **ordem de dependência** (bloqueadores primeiro) para que você possa referenciar IDs reais no campo "Bloqueada por".

```bash
gh issue create \
  --title "<título da slice>" \
  --label "ready-for-agent,slice" \
  --body-file /tmp/slice-<n>.md
```

Use o label `ready-for-agent` — slices estão prontas para um agente AFK pegar.

<issue-template>

## Parent

Referência ao PRD pai no GitHub (ex.: `#42`). Omita se não veio de um PRD.

## What to build

Descrição concisa desta slice vertical. Descreva o **comportamento ponta-a-ponta**, não a implementação camada-por-camada.

Evite caminhos de arquivo e snippets de código — ficam desatualizados. *Exceção:* se um protótipo produziu snippet que codifica decisão (state machine, schema, type shape), inline-o aqui e marque como vindo do protótipo.

## Camadas tocadas

- [ ] Domain — `<descreva>` (ou "n/a")
- [ ] Application — `<descreva>` (ou "n/a")
- [ ] Presentation — `<descreva>` (ou "n/a")
- [ ] Test — `<descreva>` (ou "n/a")

## Acceptance criteria

- [ ] Critério 1 (comportamento observável pelo usuário)
- [ ] Critério 2
- [ ] `vendor/bin/pest --filter=<NomeDoTeste>` passa
- [ ] `vendor/bin/pint --test` passa
- [ ] Rota / componente acessível e funcional num browser local

## Bloqueada por

- `#<N>` (ou "Nenhuma — pode começar imediatamente")

## Notas técnicas

Coisas que o agente AFK precisa saber e que NÃO vão no `What to build`:

- Pacotes a usar (preferir Flux a Tailwind cru, Volt vs class component, etc.)
- Padrões do projeto a seguir (referenciar ADR se houver)
- Armadilhas conhecidas

</issue-template>

**Não modifique nem feche a issue pai (PRD).** Apenas referencie.

## Pós-publicação

Após publicar todas:

1. Mostrar lista das issues criadas com IDs e títulos.
2. Sugerir rodar `/update-roadmap` se já houver issues suficientes para regenerar o `docs/roadmap/index.html`.
3. Para começar a trabalhar numa slice: `/tdd` (na issue desejada).
