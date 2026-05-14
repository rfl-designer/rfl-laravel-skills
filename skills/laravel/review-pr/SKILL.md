---
name: review-pr
description: Review the current branch's pull request along FOUR axes in parallel — Laravel idioms, Livewire/Flux/a11y, Pest test quality, AND spec compliance against the originating issue's acceptance criteria. Use before merging, when user says "review the PR", "review my changes", "/review-pr [number]", or after /simplify.
---

# Review PR

Dispara quatro sub-agents em paralelo sobre o diff do PR atual e consolida o output. Cobre framework Laravel, presentation Livewire/Flux/Alpine, qualidade de testes Pest **e — diferencial crítico — verifica se o código entregue cumpre os acceptance criteria das issues que o PR fecha**.

## Princípio operacional

Review é **gate de merge**, não backlog infinito. O output deve separar risco real de preferência para evitar o ciclo `tdd → review → tdd → review`.

Use esta régua:

- **BLOCKER** — merge inseguro: acceptance criterion faltando, comportamento end-to-end quebrado, bug/regressão provável, risco de dados/segurança, migration irreversível/perigosa, autorização/validação ausente, teste que mascara falha crítica, ou a11y que impede uso básico.
- **NIT** — vale corrigir, mas não impede merge: idiom Laravel/Livewire melhorável, clareza de teste, PR body incompleto, inconsistência pequena sem risco imediato.
- **NICE-TO-HAVE** — preferência, melhoria futura, limpeza ou oportunidade arquitetural.

O review deve produzir uma fila triável. Não peça "voltar ao TDD" genericamente; aponte qual blocker aceito exige nova slice comportamental e qual pode ser resolvido com patch mínimo.

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
  severity: BLOCKER somente para risco de merge; preferências ficam como NIT/NICE

Agent #2: livewire-flux-reviewer
  prompt: <stack context> + <diff>
  scope: Presentation (Livewire 4, Flux, Alpine, a11y)
  severity: BLOCKER somente para UX quebrada, a11y impeditiva, estado incorreto ou regressão provável

Agent #3: pest-test-writer (review mode)
  prompt: <stack context> + <diff>
  scope: Test quality
  severity: BLOCKER somente quando o teste mascara comportamento crítico ou dá falsa segurança sobre a issue

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
    severity: missing acceptance criteria are BLOCKER; adjacent follow-ups are NIT unless they prevent closing the issue.
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

## ✅ Disposition Queue

| Achado | Disposição | Próximo passo |
|---|---|---|
| Spec #18 missing e-mail notification | ACCEPT_NOW | Nova slice TDD: teste de Notification fake + implementação |
| Livewire `wire:key` missing | ACCEPT_NOW | Patch mínimo + teste Livewire afetado |
| PR body sem screenshot | SPLIT_FOLLOW_UP | Não bloqueia merge; anexar se usuário pedir |
| Sugestão de extrair service | REJECT_FALSE_POSITIVE | Preferência sem risco neste diff |

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

- Para cada BLOCKER, proponha uma disposição: `ACCEPT_NOW`, `SPLIT_FOLLOW_UP`, `DOC_JUSTIFY`, ou `REJECT_FALSE_POSITIVE`.
- Se há BLOCKERS aceitos de qualquer dimensão → "Resolva esses antes de mergear", listando o menor teste/gate a rerodar.
- Se há BLOCKERS apenas de Spec → "PR está tecnicamente OK mas incompleto vs issue. Decisão: terminar a slice OU dividir issue/PR para entregar o resto separadamente."
- Se só há NIT/NICE-TO-HAVE → "Pode mergear. Não precisa voltar ao TDD; abra follow-up se quiser preservar a sugestão."
- Se um reviewer detectou padrão recorrente → "Considere virar guideline em `CLAUDE.md`."

### 8. Rerun controlado

Depois de correções, não reexecute o review completo automaticamente. Rode:

- o teste/gate local que cobre o patch;
- Pint se PHP/Blade mudou;
- apenas o reviewer cujo BLOCKER foi endereçado, quando a confirmação humana não for suficiente.

Pare quando não houver BLOCKER com disposição `ACCEPT_NOW` pendente. Não bloqueie merge por NIT/NICE.

## Notas

- **Não modifica nenhum arquivo.** Leitura pura — comenta no terminal, não no GitHub.
- **Não posta comentários no PR.** Output é local. Usuário decide o que vira comment ou commit.
- **Funciona sem PR aberta?** Não. Para revisar branch sem PR, abra com `/open-pr --draft` antes ou compare manualmente com `git diff`.
- **Issues sem acceptance criteria?** O `pr-spec-reviewer` ainda compara com `What to build` em prosa — menos preciso, mas ainda útil.
