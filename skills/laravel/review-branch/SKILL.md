---
name: review-branch
description: Run laravel-reviewer + livewire-flux-reviewer + pest-test-writer in parallel against the current branch and consolidate their findings into a single 3-column checklist. Use before /open-pr, after /simplify, or when user says "review my changes".
---

# Review Branch

Dispara três sub-agents em paralelo sobre o diff da branch atual e consolida o output. Cobre framework Laravel, presentation Livewire/Flux/Alpine e qualidade de testes Pest — três escopos disjuntos para minimizar overlap e maximizar profundidade.

## Pré-requisitos

- Branch com pelo menos um commit além da base
- `git` funcionando
- Sub-agents disponíveis: `laravel-reviewer`, `livewire-flux-reviewer`, `pest-test-writer`

## Processo

### 1. Determinar a base

```bash
BASE=${1:-$(git symbolic-ref refs/remotes/origin/HEAD 2>/dev/null | sed 's@^refs/remotes/origin/@@' || echo "main")}
```

Se o usuário passou um ref como argumento (`/review-branch develop` ou `/review-branch HEAD~3`), use-o.

### 2. Coletar o diff

```bash
git diff "$BASE..HEAD" --stat                        # overview
git diff "$BASE..HEAD"                               # diff completo
git diff "$BASE..HEAD" --name-only                   # lista de arquivos
```

Se o diff é vazio: avise e pare.

Se o diff é gigante (>2000 linhas): avise o usuário, pergunte se quer dividir por subdiretório ou por tipo de arquivo antes de mandar para os reviewers.

### 3. Coletar contexto da stack

Ler:
- `AGENTS.md` (boost) — pacotes e versões
- `CLAUDE.md` — convenções do projeto
- `CONTEXT.md` — vocabulário de domínio (para reviewers usarem terminologia correta)

### 4. Disparar os 3 sub-agents EM PARALELO

Usar a ferramenta `Agent` em **uma única mensagem com 3 chamadas paralelas**:

```
Agent #1: laravel-reviewer
  prompt:
    Review the following diff for Laravel 12 idioms.
    Project context: <conteúdo de AGENTS.md + CLAUDE.md resumido>
    Diff:
    <output de git diff>

Agent #2: livewire-flux-reviewer
  prompt:
    Review the following diff for Livewire 4 + Flux + Alpine.
    Project context: <mesmo>
    Diff:
    <mesmo>

Agent #3: pest-test-writer (review mode)
  prompt:
    Review the following diff for Pest 3/4 test quality.
    Project context: <mesmo>
    Diff:
    <mesmo>
    Mode: review (não escrever testes novos, só revisar)
```

Importante: paralelo de verdade — uma mensagem com 3 tool calls. Não faça serial.

### 5. Consolidar

Quando os três retornarem, monte uma resposta única:

```markdown
# Review da branch `<branch>` vs `<base>`

`<N> arquivos · +<adds>/-<dels> linhas`

## 🚫 BLOCKERS

| # | Camada | Arquivo | Achado |
|---|---|---|---|
| 1 | Laravel | `app/Actions/Foo.php:42` | <descrição> |
| 2 | Livewire | `resources/views/...` | <descrição> |

(Se vazio: "Nenhum blocker encontrado.")

## ⚠️ NITS

<igual, todos os nits dos 3 reviewers em ordem por arquivo>

## 💡 NICE-TO-HAVE

<igual>

---

## Por reviewer

### Laravel
<output bruto do laravel-reviewer>

### Livewire / Flux / Alpine
<output bruto do livewire-flux-reviewer>

### Pest
<output bruto do pest-test-writer>
```

### 6. Sugestões pós-review

Ao final, sugerir:

- Se há BLOCKERS: "Resolva antes de `/open-pr`."
- Se só há NITS/NICE-TO-HAVE: "Pode abrir o PR com `/open-pr`. Considere endereçar nits no mesmo PR."
- Se um reviewer encontrou padrão que pode virar guideline: "Considere adicionar ao `CLAUDE.md` para futuros agents pegarem."

## Notas

- Não modificar nenhum arquivo. Apenas reportar.
- Não rodar Pest/Pint — `/open-pr` faz isso. `/review-branch` é leitura pura.
- Output deve caber no terminal — se ficar gigante, mostrar resumo + link para arquivo `.scratch/review-<branch>.md` salvo localmente.
