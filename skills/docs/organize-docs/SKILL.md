---
name: organize-docs
description: Keep the project's docs/ tree clean after merged PRs — propose ADRs for hard-to-reverse decisions, surface new domain terms missing from CONTEXT.md, archive delivered PRDs, fix dead links. Always interactive — never writes without confirmation. Use when user says "organize docs", "limpar docs", "atualizar documentação", or after a batch of PRs merge.
---

# Organize Docs

Skill de **manutenção** da documentação do projeto consumidor. Roda periodicamente (semanal/quinzenal, ou após batch de PRs mergeadas) e mantém a árvore `docs/` em ordem sem deixar entropia se acumular.

**Sempre interativa.** Nunca aplica mudanças sem o usuário ler e aprovar cada uma.

## Layout esperado no projeto Laravel

```
docs/
├── adr/                      # decisões arquiteturais (formato grill-with-docs)
│   ├── 0001-event-sourced-orders.md
│   └── 0002-postgres-write-model.md
├── prd/                      # PRDs gerados por /to-prd
│   ├── active/               # em andamento
│   └── done/                 # entregues, arquivados por ano
│       └── 2026/
├── roadmap/                  # gerado por /update-roadmap
│   └── index.html
└── runbooks/                 # operacional (deploy, restore, oncall)
CONTEXT.md
```

Se a árvore não existe, oferecer criá-la na primeira execução.

## Pré-requisitos

- `git` funcionando, repo com histórico
- `gh` CLI autenticado (para correlacionar PRs com issues)

## Processo

### 1. Determinar a janela de análise

```bash
LAST_RUN=$(test -f .claude/last-organize-docs && cat .claude/last-organize-docs || echo "30 days ago")
git log --since="$LAST_RUN" --merges --pretty="%H %s"
```

Se nunca rodou, usar últimos 30 dias como default. Janela é configurável.

### 2. Coletar PRs mergeadas no período

```bash
gh pr list --state merged --search "merged:>$LAST_RUN_ISO" --json number,title,body,closedAt,mergedAt,labels,files
```

Para cada PR, coletar:
- Issues fechadas (`Closes #N` no body)
- Arquivos tocados
- ADRs ou PRDs referenciados no body

### 3. Cinco verificações em paralelo

#### A. ADRs candidatos

Procurar nos PRs sinais de decisão hard-to-reverse:

- Mudou `composer.json` (dependência nova ou major version bump) → candidato
- Adicionou tabela ou mudou schema crítico → candidato
- PR body menciona "decidi", "optamos", "escolhi", "em vez de", "trade-off" → candidato
- Mudou config (`config/database.php`, `config/queue.php`, `config/auth.php`) → candidato

Para cada candidato, aplicar os 3 critérios do `grill-with-docs`:
1. Hard to reverse?
2. Surpreendente sem contexto?
3. Resultado de trade-off real?

Se 3/3 → propor ADR. Se 2/3 → mencionar como "consideração". Se ≤1 → ignorar.

Output:
```
📋 ADRs sugeridos:

1. PR #142 "Switch sessions from cookie to Redis-backed"
   3 critérios atendidos. Sugestão: docs/adr/0008-redis-sessions.md
   Conteúdo proposto:
   ─────────────────
   # Sessions backed by Redis instead of cookies
   ...
   ─────────────────
   [a]ceitar / [e]ditar / [p]ular
```

#### B. Termos novos para `CONTEXT.md`

```bash
git diff "$LAST_RUN_REF..HEAD" -- 'app/**/*.php' | grep -E '^\+(class|enum|interface|trait) ' | sort -u
```

Para cada classe/enum/interface novo:
- Existe no `CONTEXT.md`?
- É um termo de domínio (não framework)? Heurística: NÃO é Controller, Request, Resource, ServiceProvider, Listener.
- Se domínio E ausente → propor adicionar.

```
📚 Termos novos detectados:

- Proposal (app/Models/Proposal.php) — não está em CONTEXT.md
- ProposalStatus (app/Enums/ProposalStatus.php) — não está em CONTEXT.md
- ProposalSent (app/Events/ProposalSent.php) — provavelmente domínio, propor adicionar?

Sugestão para CONTEXT.md:

**Proposal**:
Documento comercial enviado a um cliente potencial, contendo módulos e features cotados.
_Avoid_: orçamento, cotação

[a]ceitar / [e]ditar / [p]ular
```

#### C. PRDs a arquivar

Para cada arquivo em `docs/prd/active/`:
- Issue PRD (referenciada no início do arquivo) está fechada?
- Todas as issues-slice filhas estão fechadas?

Se sim → propor mover para `docs/prd/done/<ano>/`.

```
📦 PRDs a arquivar:

- docs/prd/active/comment-thread.md
  Issue PRD #42: closed
  3 slices filhas (#43, #44, #45): todas closed
  Propor mover para docs/prd/done/2026/comment-thread.md
  [a]ceitar / [p]ular
```

#### D. Links quebrados

Escanear todo arquivo `.md` em `docs/`, `CONTEXT.md`, `README.md` por:
- Links relativos (`./foo.md`, `../bar.md`) que apontam para arquivos inexistentes
- Wikilinks `[[name]]` sem arquivo correspondente em memory ou docs
- Issues mencionadas (`#N`) que retornam 404 no `gh issue view`

```
🔗 Links quebrados:

- docs/adr/0003-foo.md:12 → ./bar.md (não existe)
- README.md:45 → #999 (issue não encontrada — talvez referência errada?)
```

NÃO autocorrigir. Apenas reportar — o usuário decide se renomeia, cria o destino, ou remove o link.

#### E. ADRs com status estale

Para cada ADR:
- Status `proposed` há mais de 60 dias → sugerir transicionar para `accepted` ou `superseded`
- Status `superseded` mas sem `superseded by ADR-NNNN` no frontmatter → sugerir adicionar

```
📜 ADRs com status estale:

- docs/adr/0003-event-sourcing.md — status: proposed (há 87 dias)
  Sugerir: alguém implementou? Atualizar para accepted ou marcar como rejected?
```

### 4. Apresentar tudo num batch

Mostrar todas as 5 categorias numeradas. Usuário pode:
- Aceitar tudo (`a`)
- Aceitar item específico (`1`, `2`, etc.)
- Pular tudo (`p`)
- Editar antes de aceitar (`e <numero>`)

### 5. Aplicar e marcar

Após aplicar:
- Atualizar `.claude/last-organize-docs` com timestamp atual
- Sugerir commit com mensagem `docs: organize-docs sweep <data>`
- Sugerir rodar `/update-roadmap` se PRDs foram arquivados ou issues mudaram de status

## Configuração opcional

`.claude/organize-docs-config.json`:

```json
{
  "window_days": 30,
  "skip_adr_suggestions": false,
  "skip_term_suggestions": false,
  "auto_archive_prds": false,
  "context_md_path": "CONTEXT.md",
  "domain_class_paths": ["app/Models", "app/Enums", "app/Events", "app/Actions"]
}
```

`auto_archive_prds: true` move PRDs done sem perguntar (ainda mostra o que moveu).

## Quando NÃO rodar

- Durante uma slice ativa de `/tdd` — espera o PR mergear
- Em repo sem `docs/` — primeiro pergunta se quer criar a estrutura
