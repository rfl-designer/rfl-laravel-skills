---
name: review-pr
description: Review the current branch's pull request in a single consolidated pass without spawning review sub-agents. Checks Laravel idioms, Livewire/Flux/a11y, Pest test quality, and spec compliance against originating issue acceptance criteria. Use before merging, when user says "review the PR", "review my changes", "/review-pr [number]", or after /simplify.
---

# Review PR

Revisa o PR atual em **uma passagem consolidada**, sem disparar sub-agents. O objetivo é reduzir consumo de token mantendo o gate essencial antes do merge: risco Laravel, apresentação Livewire/Flux, qualidade de testes Pest e aderência às issues fechadas.

## Princípio operacional

Review é **gate de merge**, não backlog infinito. Separe risco real de preferência para evitar o ciclo `tdd → review → tdd → review`.

Use esta régua:

- **BLOCKER** — merge inseguro: acceptance criterion faltando, comportamento end-to-end quebrado, bug/regressão provável, risco de dados/segurança, migration irreversível/perigosa, autorização/validação ausente, teste que mascara falha crítica, ou a11y que impede uso básico.
- **NIT** — vale corrigir, mas não impede merge: idiom Laravel/Livewire melhorável, clareza de teste, PR body incompleto, inconsistência pequena sem risco imediato.
- **NICE-TO-HAVE** — preferência, melhoria futura, limpeza ou oportunidade arquitetural.

O output deve produzir uma fila triável. Não peça "voltar ao TDD" genericamente; aponte qual blocker aceito exige nova slice comportamental e qual pode ser resolvido com patch mínimo.

## Pré-requisitos

- `gh` CLI autenticado
- Branch atual tem PR aberta, ou usuário passou número/URL como argumento

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
gh pr view "$PR" --json number,title,body,baseRefName,headRefName,closingIssuesReferences,files,additions,deletions
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

Se diff > 2000 linhas: avisar que a revisão única ficará menos precisa e sugerir revisar por subdiretório ou por commit. Não dispare sub-agents.

### 4. Coletar contexto da stack

Ler do projeto, quando existirem:

- `AGENTS.md` (boost) — pacotes e versões
- `CLAUDE.md` — convenções do projeto
- `CONTEXT.md` — vocabulário de domínio

### 5. Revisar em uma passagem

Percorra o diff uma vez, agrupando achados nestas dimensões:

#### Spec

- Cada acceptance criterion das issues fechadas está entregue?
- O PR implementa o `What to build` sem desviar para outro escopo?
- Há scope creep que deveria virar PR separada?
- O body do PR justifica qualquer desvio relevante?

#### Laravel

- N+1 provável, queries em loop, eager loading ausente
- Validação no lugar errado; Form Request ausente quando o fluxo exige
- Autorização ausente ou inconsistente com Policy
- Uso indevido de container, service locator ou facades em camada errada
- Migration perigosa, irreversível, sem defaults ou com risco de dados
- Debug artifacts (`dd`, `dump`, logs ruidosos, flags temporárias)

#### Livewire / Flux / Alpine

- Estado duplicado entre Livewire e Alpine
- `wire:model` sem modifier apropriado para Livewire 4
- `wire:key` ausente em listas dinâmicas
- Flux substituível por HTML cru nos controles principais
- A11y impeditiva: label ausente, foco quebrado, modal inacessível, contraste insuficiente
- Layout ou estado visual incoerente com o fluxo do usuário

#### Pest

- Teste acopla em implementação em vez de comportamento
- Assertion fraca que passaria mesmo com bug crítico
- Fake/mock mascarando integração que a issue exige validar
- Falta teste para blocker identificado no spec
- Uso não idiomático de factories, datasets, helpers Laravel ou Livewire test

### 6. Consolidar

Monte resposta única:

```markdown
# Review PR #<N>: "<título>"

`<branch>` → `<base>` · `<files> arquivos · +<adds>/-<dels> linhas`
Fechando: #<I1>, #<I2>

## BLOCKERS

| # | Dimensão | Arquivo | Achado |
|---|---|---|---|
| 1 | Spec | — | Issue #18 acceptance criteria 3 ("notifica autor por e-mail") não foi entregue |
| 2 | Laravel | `app/Actions/Foo.php:42` | N+1 provável ao carregar comentários dentro do loop |

(Se vazio: "Nenhum blocker encontrado.")

## NITS
<achados não bloqueantes>

## NICE-TO-HAVE
<melhorias futuras>

## Disposition Queue

| Achado | Disposição | Próximo passo |
|---|---|---|
| Spec #18 missing e-mail notification | ACCEPT_NOW | Nova slice TDD: teste de Notification fake + implementação |
| PR body sem screenshot | SPLIT_FOLLOW_UP | Não bloqueia merge; anexar se usuário pedir |
| Sugestão de extrair service | REJECT_FALSE_POSITIVE | Preferência sem risco neste diff |

## Spec Compliance

### Issue #18 — "Comentários em projeto"

**Acceptance criteria:**
- [x] Membro do projeto pode adicionar comentário — entregue
- [x] Comentário é exibido em ordem cronológica — entregue
- [ ] Notifica autor do projeto por e-mail — ausente
- [x] Validação de body vazio — entregue

**Veredicto:** PR cumpre 3/4 critérios. Notificação é blocker para fechar a issue.
```

### 7. Sugestões pós-review

- Para cada BLOCKER, proponha uma disposição: `ACCEPT_NOW`, `SPLIT_FOLLOW_UP`, `DOC_JUSTIFY`, ou `REJECT_FALSE_POSITIVE`.
- Se há BLOCKERS aceitos → "Resolva esses antes de mergear", listando o menor teste/gate a rerodar.
- Se há BLOCKERS apenas de Spec → "PR está tecnicamente OK mas incompleto vs issue. Decisão: terminar a slice OU dividir issue/PR para entregar o resto separadamente."
- Se só há NIT/NICE-TO-HAVE → "Pode mergear. Não precisa voltar ao TDD; abra follow-up se quiser preservar a sugestão."
- Se detectar padrão recorrente → "Considere virar guideline em `CLAUDE.md`."

### 8. Rerun controlado

Depois de correções, não reexecute o review completo automaticamente. Rode:

- o teste/gate local que cobre o patch;
- Pint se PHP/Blade mudou;
- uma revisão focada apenas no BLOCKER endereçado, quando a confirmação humana não for suficiente.

Pare quando não houver BLOCKER com disposição `ACCEPT_NOW` pendente. Não bloqueie merge por NIT/NICE.

## Notas

- **Não modifica nenhum arquivo.** Leitura pura — comenta no terminal, não no GitHub.
- **Não posta comentários no PR.** Output é local. Usuário decide o que vira comment ou commit.
- **Não dispara sub-agents.** Esta skill foi desenhada para reduzir consumo de token.
- **Funciona sem PR aberta?** Não. Para revisar branch sem PR, abra com `/open-pr --draft` antes ou compare manualmente com `git diff`.
- **Issues sem acceptance criteria?** Compare com `What to build` em prosa — menos preciso, mas ainda útil.
