---
name: open-pr
description: Open a GitHub pull request for the current branch with title derived from Conventional Commits, body referencing closed issues, and gated by Pest + Pint. Use when the user finishes a slice and wants to open a PR, says "abrir PR", "open pr", or "ship this".
---

# Open PR

Encerra o loop "código pronto → PR aberta". Não faz commit por você (use `git commit` antes); assume que a branch já está com tudo committado.

## Pré-requisitos

- `gh` CLI autenticado (`gh auth status`)
- Branch atual diferente da branch base (`main` ou configurada)
- Pelo menos um commit seguindo Conventional Commits
- `vendor/bin/pest` e `vendor/bin/pint` instalados no projeto

## Processo

### 1. Validações preliminares

```bash
git status                              # working tree limpo?
git rev-parse --abbrev-ref HEAD         # nome da branch atual
git rev-parse --abbrev-ref --symbolic-full-name @{u} 2>/dev/null
                                        # branch tem upstream?
```

Se houver mudanças não-committadas: avise o usuário e pare. NÃO commite por ele.

Se a branch não tem upstream: rode `git push -u origin <branch>` antes de abrir o PR.

### 2. Garantir verde

```bash
vendor/bin/pest
vendor/bin/pint --test
```

Se algum falhar: aborte e mostre a saída. NÃO abra PR com suite vermelha.

### 3. Coletar commits

```bash
BASE=$(git symbolic-ref refs/remotes/origin/HEAD | sed 's@^refs/remotes/origin/@@' || echo "main")
git log "origin/$BASE..HEAD" --pretty="%s" --reverse
```

Filtre os que seguem Conventional Commits (`<type>(<scope>): <desc>`):

- Tipos válidos: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `build`, `ci`
- Scope opcional, entre parênteses

Se **nenhum** commit segue Conventional, aborte com:
> Branch sem Conventional Commits. Renomeie ao menos um commit com `git commit --amend` ou `git rebase -i` antes de abrir o PR.

### 4. Derivar título do PR

1. Conte commits por tipo.
2. Se um único tipo domina (>50% dos commits) → use esse tipo.
3. Empate ou pluralidade → ordem de precedência: `feat` > `fix` > `refactor` > `perf` > `docs` > `test` > `chore` > `style` > `build` > `ci`.
4. Use o **primeiro commit** desse tipo como título completo (com scope, se tiver).

Exemplo:
```
commits:
  feat(livewire): add comment thread to project view
  test(livewire): cover comment thread edge cases
  refactor(action): extract notification dispatch

→ Título PR: feat(livewire): add comment thread to project view
```

### 5. Identificar issues fechadas

Procure nos commits e na descrição agregada por:

```
Closes #N
Fixes #N
Resolves #N
```

Coletar todos os Ns. Para cada um:

```bash
gh issue view <N> --json number,title,labels
```

Se o título do PR não menciona nenhuma issue mas você encontrou referências, inclua-as como `Closes #N` no body.

### 6. Detectar ADRs/PRDs tocados

```bash
git diff --name-only "origin/$BASE..HEAD" | grep -E '^docs/(adr|prd)/'
```

Liste-os no body — sinaliza ao reviewer humano onde olhar primeiro.

### 7. Detectar TODOs / WIP no diff

```bash
git diff "origin/$BASE..HEAD" | grep -iE '^\+.*(TODO|FIXME|WIP|XXX)' || true
```

Se houver, abrir o PR como **draft** (`--draft`).

### 8. Gerar body do PR

```markdown
## Resumo

- <bullet 1>
- <bullet 2>
- <bullet 3>

## Issues fechadas

Closes #<N>
Closes #<M>

## Como testar

```bash
vendor/bin/pest --filter=<NomeRelevante>
# ou
php artisan serve  # acessar http://localhost:8000/<rota>
```

Cenários a verificar:
- [ ] <cenário 1 do acceptance criteria da issue>
- [ ] <cenário 2>

## Screenshots

<placeholder se PR toca UI; senão omita a seção>

## ADRs / PRDs tocados

- `docs/adr/0007-event-sourced-comments.md` (novo)
- `docs/prd/comment-thread.md` (parcialmente entregue)

## Checklist

- [x] Pest passa (`vendor/bin/pest`)
- [x] Pint passa (`vendor/bin/pint --test`)
- [ ] Revisado num browser (UI)
- [ ] Documentação atualizada (se aplicável)
```

Os 3 bullets do **Resumo** devem ser derivados das mensagens de commit + corpo das issues fechadas. Foque no **valor para o usuário**, não no detalhe técnico.

### 9. Abrir o PR

```bash
gh pr create \
  --title "<título derivado>" \
  --body-file /tmp/pr-body.md \
  --base "$BASE" \
  $DRAFT_FLAG
```

Onde `$DRAFT_FLAG` é `--draft` se houver TODOs detectados na etapa 7.

### 10. Pós-criação

```bash
gh pr view --web
```

Imprima ao usuário:
- URL do PR
- Status (draft ou ready)
- Issues que serão fechadas no merge
- Próximos passos sugeridos: aguardar review, rodar `/review-branch` localmente para checklist prévio

## Configuração opcional

`.claude/open-pr-config.json`:

```json
{
  "base_branch": "main",
  "auto_run_pint_fix": false,
  "draft_on_todos": true,
  "always_include_screenshots_section": false
}
```

Se `auto_run_pint_fix: true`, rodar `vendor/bin/pint` (sem `--test`) e fazer commit `style: pint autofix` antes de abrir o PR. **Off por padrão** — alterar código sem permissão explícita é arriscado.
