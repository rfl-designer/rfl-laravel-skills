---
name: review-pr
description: Review the current branch's pull request along FOUR axes in parallel — Laravel idioms, Livewire/Flux/a11y, Pest test quality, AND spec compliance against the originating issue's acceptance criteria. Use before merging, when user says "review the PR", "review my changes", "/review-pr [number]", or after /simplify.
---

# Review PR

Dispara quatro sub-agents em paralelo sobre o diff do PR atual e consolida o output. Cobre framework Laravel, presentation Livewire/Flux/Alpine, qualidade de testes Pest **e — diferencial crítico — verifica se o código entregue cumpre os acceptance criteria das issues que o PR fecha**.

## Pré-requisitos

- `gh` CLI autenticado
- Branch atual tem PR aberta (ou usuário passou número/URL como argumento)
- Sub-agents disponíveis: `laravel-reviewer`, `livewire-flux-reviewer`, `pest-test-writer`, `pr-spec-reviewer`

## Processo

### 1. Determinar o PR

Resolução por prioridade:

1. Se argumento explícito (`/review-pr 123` ou `/review-pr https://github.com/.../pull/123`) → usar esse.
2. Senão, detectar PR da branch atual:
   ```bash
   gh pr view --json number,title,body,baseRefName,headRefName,closingIssuesReferences,files
   ```
3. Se a branch não tem PR aberta → avisar e oferecer rodar `/open-pr` primeiro.

### 2. Buscar PR + issues fechadas

```bash
gh pr view "$PR" --json number,title,body,baseRefName,closingIssuesReferences,files,additions,deletions
```

Capturar:
- **PR body** — descrição, screenshots, checklist
- **closingIssuesReferences** — issues que serão fechadas (`Closes #N` parseado pelo gh)
- **baseRefName** — base branch para o diff
- **files** — lista de arquivos tocados

Para cada issue em `closingIssuesReferences`:
```bash
gh issue view "$N" --json number,title,body,labels,milestone,state
```

Capturar especialmente as seções `## Acceptance criteria`, `## What to build`, `## Camadas tocadas` (template do `/to-issues`).

### 3. Coletar diff

```bash
git fetch origin
git diff "origin/$BASE..HEAD" --stat
git diff "origin/$BASE..HEAD"
```

Se diff > 2000 linhas: avisar e oferecer dividir por subdiretório antes de mandar aos reviewers.

### 4. Coletar contexto da stack

Ler do projeto:
- `AGENTS.md` (boost) — pacotes e versões
- `CLAUDE.md` — convenções do projeto
- `CONTEXT.md` — vocabulário de domínio

### 5. Disparar os 4 sub-agents EM PARALELO

Uma única mensagem com 4 chamadas paralelas via Agent tool:

```
Agent #1: laravel-reviewer
  prompt: <stack context> + <diff>
  scope: Application/Persistence (idioms Laravel)

Agent #2: livewire-flux-reviewer
  prompt: <stack context> + <diff>
  scope: Presentation (Livewire 4, Flux, Alpine, a11y)

Agent #3: pest-test-writer (review mode)
  prompt: <stack context> + <diff>
  scope: Test quality

Agent #4: pr-spec-reviewer  ← O DIFERENCIAL
  prompt:
    Issue(s) being closed by this PR:
    <issue body 1>
    <issue body 2>
    ...

    PR title: <title>
    PR body: <body>
    PR diff:
    <diff>

    Check: does the PR deliver what each issue's "Acceptance criteria"
    and "What to build" demanded? Flag missing criteria, scope creep,
    or partial implementation.
```

**Importante:** uma mensagem com 4 tool calls simultâneas. NÃO serial.

### 6. Consolidar

Quando os 4 retornarem, montar resposta única:

```markdown
# Review PR #<N>: "<título>"

`<branch>` → `<base>` · `<files> arquivos · +<adds>/-<dels> linhas`
Fechando: #<I1>, #<I2>

## 🚫 BLOCKERS

| # | Dimensão | Arquivo | Achado |
|---|---|---|---|
| 1 | Spec | — | Issue #18 acceptance criteria 3 ("notifica autor por e-mail") não foi entregue |
| 2 | Laravel | `app/Actions/Foo.php:42` | N+1 detectado |
| 3 | Livewire | `resources/views/comment.blade.php:18` | `wire:model` sem modifier — usar `.blur` |

(Se vazio: "Nenhum blocker encontrado.")

## ⚠️ NITS
<consolidados dos 4 reviewers>

## 💡 NICE-TO-HAVE
<consolidados dos 4 reviewers>

---

## 📋 Spec Compliance (vs issues fechadas)

### Issue #18 — "Comentários em projeto"

**Acceptance criteria:**
- [x] Membro do projeto pode adicionar comentário ✅ entregue
- [x] Comentário é exibido em ordem cronológica ✅ entregue
- [ ] Notifica autor do projeto por e-mail ❌ AUSENTE
- [x] Validação de body vazio ✅ entregue
- [x] `vendor/bin/pest --filter=Comment` passa ✅ verificado

**What to build:** "Membros do projeto podem deixar comentários em entregas para discutir feedback sem sair da plataforma."
→ Entregue parcialmente: UX de comentar funciona, falta notificação.

**Veredicto:** PR cumpre 4/5 critérios. **Notificação é blocker para fechar a issue.**

### (repetir por issue)

---

## Por reviewer

### Laravel
<output bruto>

### Livewire / Flux / Alpine
<output bruto>

### Pest
<output bruto>

### Spec compliance
<output bruto do pr-spec-reviewer>
```

### 7. Sugestões pós-review

- Se há BLOCKERS de qualquer dimensão → "Resolva antes de mergear."
- Se há BLOCKERS apenas de Spec → "PR está tecnicamente OK mas incompleto vs issue. Decisão: terminar a slice OU dividir issue para entregar o resto separadamente."
- Se só há nits/nice-to-have → "Pode mergear. Considere endereçar nits no mesmo PR ou abrir follow-up."
- Se um reviewer detectou padrão recorrente → "Considere virar guideline em `CLAUDE.md`."

## Notas

- **Não modifica nenhum arquivo.** Leitura pura — comenta no terminal, não no GitHub.
- **Não posta comentários no PR.** Output é local. Usuário decide o que vira comment ou commit.
- **Funciona sem PR aberta?** Não. Para revisar branch sem PR, abra com `/open-pr --draft` antes ou compare manualmente com `git diff`.
- **Issues sem acceptance criteria?** O `pr-spec-reviewer` ainda compara com `What to build` em prosa — menos preciso, mas ainda útil.
