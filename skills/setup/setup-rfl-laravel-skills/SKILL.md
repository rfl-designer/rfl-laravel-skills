---
name: setup-rfl-laravel-skills
description: Bootstrap a Laravel project to use the rfl-laravel-skills plugin — verify prerequisites (gh CLI, Pest, Pint, laravel/boost), create docs/ tree (adr, prd, roadmap, runbooks), seed .claude/<config>.json files for each skill, and add an "Agent skills" block to CLAUDE.md/AGENTS.md. Run ONCE per repo before first use of /grill-with-docs, /to-prd, /to-issues, /open-pr, /organize-docs, or /update-roadmap.
disable-model-invocation: true
---

# Setup rfl-laravel-skills

Configura um projeto Laravel para usar o plugin. Valida pré-requisitos, cria estrutura de `docs/`, semeia arquivos de configuração que cada skill espera, e adiciona um bloco `## Agent skills` em `CLAUDE.md` ou `AGENTS.md`.

**Roda uma vez por repo.** Re-rodar é seguro — vai detectar o que já existe e perguntar antes de sobrescrever.

Esta skill é prompt-driven, não script. Explore, apresente, confirme com o usuário, escreva.

## Processo

### 1. Detectar estado atual

Em paralelo, verificar:

| Item | Como detectar |
|---|---|
| Repo é Laravel? | `composer.json` tem `"laravel/framework"`? |
| Versão do Laravel | `composer show laravel/framework` |
| Boost instalado? | `composer show laravel/boost` (sem erro) |
| Pest instalado? | `vendor/bin/pest --version` ou `composer show pestphp/pest` |
| Pint instalado? | `vendor/bin/pint --version` ou `composer show laravel/pint` |
| Livewire versão | `composer show livewire/livewire` |
| Flux versão | `composer show livewire/flux` (Free) ou `livewire/flux-pro` |
| `gh` autenticado? | `gh auth status` |
| Remote GitHub? | `git remote -v` |
| `AGENTS.md` ou `CLAUDE.md`? | `ls AGENTS.md CLAUDE.md` |
| `CONTEXT.md`? | `ls CONTEXT.md` |
| `docs/` tree? | `ls docs/` |
| `.claude/` configs? | `ls .claude/*.json` |
| Plugin instalado via `/plugin install`? | `ls ~/.claude/plugins/` ou config do Claude Code |
| Skills em `.claude/skills/`? | `ls .claude/skills/ ~/.claude/skills/` |
| Agents em `.claude/agents/`? | `ls .claude/agents/ ~/.claude/agents/` |

Apresentar resumo no formato:

```
✅ Laravel 12.x detectado
✅ Boost 1.x instalado
✅ Pest 4.x instalado
✅ Pint 1.x instalado
⚠️  Livewire 3.x detectado — plugin assume Livewire 4. Confirmar?
✅ Flux Free 2.x instalado
✅ gh autenticado como rfl-designer
✅ Remote: github.com:rfl-designer/foo.git
❌ AGENTS.md ausente — boost não foi instalado completamente?
❌ CONTEXT.md ausente — vou propor criar
❌ docs/ ausente — vou propor criar tree completa
❌ .claude/ ausente — vou propor criar configs
```

### 2. Resolver pré-requisitos faltantes

Para cada `❌` ou `⚠️`, perguntar ao usuário antes de continuar:

**Pré-requisitos críticos (parar se ausentes):**

- `gh` não autenticado → instruir `gh auth login` e parar
- Não é repo Laravel → parar com mensagem
- Sem remote GitHub → instruir criar remote ou pular skills que dependem (`/open-pr`, `/to-issues`, `/to-prd`, `/update-roadmap`, `/organize-docs`)

**Pré-requisitos sugeridos (oferecer ação):**

- Boost ausente → propor `composer require laravel/boost --dev && php artisan boost:install`
- Pest ausente → propor `composer require pestphp/pest --dev --with-all-dependencies`
- Pint ausente → propor `composer require laravel/pint --dev`
- AGENTS.md ausente mas boost instalado → propor rodar `php artisan boost:install`

NÃO rodar nenhum comando composer/artisan automaticamente — só sugerir e aguardar o usuário rodar.

### 3. Confirmar configuração — uma seção de cada vez

Apresente cada bloco abaixo separadamente, espere resposta, então passe ao próximo. Não despeje tudo de uma vez.

#### Seção A — Domain (CONTEXT.md)

> O `CONTEXT.md` é um glossário do domínio do seu projeto (não do framework). As skills `/grill-with-docs`, `/tdd`, `/to-prd`, `/to-issues` leem ele para usar a terminologia do projeto em vez de nomes genéricos.

Se `CONTEXT.md` não existe, perguntar:
- Quer criar um esqueleto agora?
- Se sim, perguntar o **nome do contexto** (geralmente nome do produto/empresa) e **1 frase descrevendo o produto**.
- Gerar esqueleto com seções `## Language`, `## Relationships`, `## Flagged ambiguities` vazias para o usuário preencher conforme `/grill-with-docs` for rodando.

Se já existe → marcar OK.

#### Seção B — Conventional Commits

> A skill `/open-pr` deriva o título do PR a partir de Conventional Commits (`feat:`, `fix:`, `refactor:`, etc.). Se seu repo não segue esse padrão, ela vai abortar quando você tentar abrir um PR.

Verificar últimos 10 commits (`git log -10 --pretty=%s`). Quantos seguem padrão `<type>(<scope>): <desc>`?

- 100% → "Repo já usa Conventional Commits ✅"
- ≥50% → "Repo parcialmente usa. `/open-pr` vai assumir que daqui pra frente todos seguem."
- <50% → "Repo não usa Conventional Commits. Quer adotar agora? Posso adicionar uma seção a `CLAUDE.md` documentando o padrão."

Se aceitar adoção, escrever em `CLAUDE.md`:

```markdown
## Convenções de commit

Este repo usa Conventional Commits (EN). Tipos válidos:
`feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`, `build`, `ci`.
Scope opcional, entre parênteses. Exemplo:
`feat(comments): add thread replies`
```

#### Seção C — docs/ tree

> As skills `/to-prd`, `/organize-docs`, `/update-roadmap` esperam uma árvore `docs/` específica.

Mostrar o que existe vs o que falta. Layout esperado:

```
docs/
├── adr/                # decisões arquiteturais (criada por /grill-with-docs)
├── prd/
│   ├── active/         # PRDs em andamento
│   └── done/           # PRDs entregues, arquivados por ano
├── roadmap/            # gerado por /update-roadmap
└── runbooks/           # operacional (deploy, restore, oncall)
```

Perguntar: criar tree completa? Criar só algumas? Pular?

Criar diretórios escolhidos com `.gitkeep` em cada (para versionar pasta vazia).

#### Seção D — Configs `.claude/<skill>.json`

> Cada skill aceita configuração opcional em `.claude/<skill>-config.json`. Vou semear com defaults sensatos; você pode editar depois.

Apresentar os 3 configs lado a lado (ver templates em [`configs/`](./configs/)):

- `.claude/open-pr-config.json` — base_branch, draft_on_todos, auto_run_pint_fix
- `.claude/organize-docs-config.json` — window_days, paths, auto_archive_prds
- `.claude/roadmap-config.json` — title (perguntar), subtitle (perguntar), label, theme, wave_names

Para `roadmap-config.json`, **perguntar título e subtítulo** — o resto pode ficar default.

#### Seção E — Instalação dos agents

> Os 5 agents do plugin (`laravel-simplifier`, `laravel-reviewer`, `livewire-flux-reviewer`, `pest-test-writer`, `pr-spec-reviewer`) precisam estar em `.claude/agents/` (project) ou `~/.claude/agents/` (global) para o Claude Code reconhecer.
>
> Se você instalou via `/plugin install <path>`, agents já estão registrados — **pule esta seção**.
>
> Se você instalou via `npx skills add` (universal), só as skills foram copiadas — agents precisam ser linkados manualmente.

Detectar:
- Existe `~/.claude/plugins/rfl-laravel-skills/` ou similar? (instalado como plugin) → pular
- Existem os 5 agents em `.claude/agents/` ou `~/.claude/agents/`? → pular
- Senão → oferecer instalar

Se for instalar, perguntar:
- **Escopo**: project (`.claude/agents/`) ou global (`~/.claude/agents/`)?
- **Método**: symlink (recomendado, atualiza junto com `git pull` no clone) ou copy?

Precisa do **path do clone local** do plugin. Se o usuário não tiver, sugerir:
```bash
git clone https://github.com/rfl-designer/rfl-laravel-skills.git ~/plugins/rfl-laravel-skills
```

Aplicar:
```bash
# symlink global
mkdir -p ~/.claude/agents
ln -sf <PLUGIN_PATH>/agents/*.md ~/.claude/agents/

# OU symlink project
mkdir -p .claude/agents
ln -sf <PLUGIN_PATH>/agents/*.md .claude/agents/

# OU cópia (se symlink não for opção)
cp <PLUGIN_PATH>/agents/*.md ~/.claude/agents/
```

Confirmar via `ls .claude/agents/` que os 5 arquivos estão lá.

#### Seção F — `.gitignore`

Verificar se `.gitignore` ignora `.claude/` (configs locais) ou se a intenção é versionar (compartilhar com time). Perguntar:

- **Ignorar `.claude/`** — cada dev configura o próprio. Default se você trabalha solo.
- **Versionar `.claude/`** — time inteiro usa os mesmos configs e agents. Default se equipe >1.

Se versionar, recomendar versionar `.claude/agents/` e `.claude/skills/` (se project-scope) **mas ignorar** caches/state como `.claude/last-organize-docs`, `.claude/inferred-deps.json`. Sugerir `.gitignore`:

```
# Versionar
!.claude/
!.claude/agents/
!.claude/skills/
!.claude/*-config.json

# Mas ignorar state local
.claude/last-organize-docs
.claude/inferred-deps.json
```

### 4. Atualizar AGENTS.md ou CLAUDE.md com bloco Agent skills

**Arquivo a editar:**

- Se `CLAUDE.md` existe → editar ele.
- Senão se `AGENTS.md` existe → editar ele.
- Se nenhum existe → perguntar qual criar (não escolher por conta própria).

**Nunca criar `AGENTS.md` se `CLAUDE.md` existe** (e vice-versa) — boost gerencia `AGENTS.md`; usuário gerencia `CLAUDE.md`. Se ambos existem, editar `CLAUDE.md` — boost regenera `AGENTS.md` e perderia edits.

Adicionar (ou atualizar in-place se já existir) um bloco `## Agent skills (rfl-laravel-skills)`. Template em [`agents-block.md`](./agents-block.md).

### 5. Mostrar diff e gravar

Para cada arquivo a criar/editar, mostrar diff e pedir confirmação. Após aprovação:

- Criar diretórios `docs/*` com `.gitkeep`
- Escrever `.claude/*.json`
- Escrever `CONTEXT.md` esqueleto se aplicável
- Atualizar `CLAUDE.md` ou `AGENTS.md`
- Atualizar `.gitignore` conforme escolha da Seção E

### 6. Mensagem final

```
✅ Setup completo!

Próximos passos:
  1. Edite CONTEXT.md preenchendo os termos do seu domínio
     (ou rode /grill-with-docs e deixe ele guiar)
  2. Para sua primeira feature: /grill-with-docs → /to-prd → /to-issues
  3. Para implementar: /tdd → /simplify → /review-branch → /open-pr
  4. Periodicamente: /organize-docs e /update-roadmap

Configs criados em .claude/ — edite à vontade.
Re-rodar /setup-rfl-laravel-skills é seguro: detecta o que já existe.
```

## Notas

- **Não força nada.** Cada decisão é confirmada. Skill termina sem mudanças se o usuário pular tudo.
- **Idempotente.** Re-rodar não duplica blocos nem sobrescreve configs sem perguntar.
- **Não roda comandos com side effect** (`composer require`, `php artisan boost:install`, `git commit`) — só sugere. Usuário decide quando rodar.
