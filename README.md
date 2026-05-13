# rfl-laravel-skills

Plugin Claude Code que orquestra o ciclo de desenvolvimento **Laravel 12 + Livewire 4 + Tailwind + Alpine + Flux UI**.

Trabalha em conjunto com [`laravel/boost`](https://github.com/laravel/boost) — o boost fornece guidelines de stack (versões, padrões idiomáticos), este plugin fornece o **processo** (grilling, PRDs, issues, TDD, reviews, roadmap).

## Quickstart

Via [skills.sh](https://skills.sh) CLI (funciona com Claude Code, Codex, Cursor, OpenCode e outros).

### Modo interativo recomendado

Abra um terminal **normal** (fora do Claude Code/Codex) e rode:

```bash
npx skills add rfl-designer/rfl-laravel-skills
```

A CLI vai perguntar:

1. **Escopo** — Project (`./.claude/skills/`) ou Global (`~/.claude/skills/`)
2. **Quais skills** instalar (use espaço pra marcar, enter pra confirmar)
3. **Quais agents** instalar (claude-code, codex, cursor, opencode, etc.)
4. **Método** — symlink (recomendado) ou copy

> ⚠️ Quando rodado **de dentro** de um agent (ex.: o próprio Claude Code), a CLI detecta o ambiente e instala não-interativamente. Para ver o prompt de escopo, abra um terminal externo.

### Flags úteis (modo direto)

```bash
# Instala global, todas as skills, sem perguntar
npx skills add rfl-designer/rfl-laravel-skills -g --all

# Instala só algumas skills no projeto atual
npx skills add rfl-designer/rfl-laravel-skills --skill tdd --skill open-pr

# Lista as skills disponíveis sem instalar nada
npx skills add rfl-designer/rfl-laravel-skills --list

# Instala em agent específico
npx skills add rfl-designer/rfl-laravel-skills -a claude-code -g
```

### Via Claude Code nativo (path local)

Para instalar a partir de um clone local sem passar pelo CLI universal:

```
/plugin install /caminho/para/rfl-laravel-skills
```

## Fluxo

```
/setup-rfl-laravel-skills  →  bootstrap one-shot (uma vez por repo)
/grill-with-docs  →  CONTEXT.md + ADRs do que foi decidido
/to-prd           →  docs/prd/<slug>.md + issue PRD no GitHub
/to-issues        →  N issues vertical-slice no GitHub
(escolher uma)
/tdd              →  red-green-refactor em Pest, slice completa
/simplify         →  laravel-simplifier passa sobre o diff
/review-branch    →  3 reviewers em paralelo, checklist consolidado
/open-pr          →  PR no GitHub, linkada à issue
(PR merged)
/organize-docs    →  ADRs propostos, glossário atualizado
/update-roadmap   →  docs/roadmap/index.html atualizado por ondas
```

## Skills

### Setup (`skills/setup/`) — rode primeiro

- **[setup-rfl-laravel-skills](./skills/setup/setup-rfl-laravel-skills/SKILL.md)** — valida pré-requisitos (gh, Pest, Pint, boost), cria `docs/` tree, semeia configs em `.claude/`, adiciona bloco `## Agent skills` em `CLAUDE.md`/`AGENTS.md`. Idempotente — re-rodar é seguro.

### Process (`skills/process/`)

- **[grill-with-docs](./skills/process/grill-with-docs/SKILL.md)** — entrevista que estressa o plano contra o domínio e atualiza CONTEXT.md/ADRs inline.
- **[to-prd](./skills/process/to-prd/SKILL.md)** — sintetiza a conversa atual num PRD com template Laravel e publica como issue.
- **[to-issues](./skills/process/to-issues/SKILL.md)** — quebra um PRD em slices verticais Laravel (4 camadas) e abre uma issue por slice.
- **[tdd](./skills/process/tdd/SKILL.md)** — red-green-refactor com Pest 3/4. Slice vertical Laravel por ciclo.
- **[open-pr](./skills/process/open-pr/SKILL.md)** — abre PR no GitHub gated por Pest+Pint, com título derivado de Conventional Commits.

### Laravel (`skills/laravel/`)

- **[simplify](./skills/laravel/simplify-with-agent/SKILL.md)** — invoca `laravel-simplifier` sobre o diff não-commitado.
- **[review-branch](./skills/laravel/review-branch/SKILL.md)** — dispara 3 reviewers em paralelo e consolida.

### Docs (`skills/docs/`)

- **[organize-docs](./skills/docs/organize-docs/SKILL.md)** — varredura interativa pós-PRs: ADRs, termos novos, PRDs arquivados, links quebrados.
- **[update-roadmap](./skills/docs/update-roadmap/SKILL.md)** — gera `docs/roadmap/index.html` por ondas, cards em grid de 3 colunas.

## Agents

- **[laravel-simplifier](./agents/laravel-simplifier.md)** — refatora código PHP/Laravel preservando comportamento. (Importado de [laravel/agent-skills](https://github.com/laravel/agent-skills), Apache-2.0.)
- **[laravel-reviewer](./agents/laravel-reviewer.md)** — revisa Application/Persistence: N+1, validação, autorização, container, migrations.
- **[livewire-flux-reviewer](./agents/livewire-flux-reviewer.md)** — revisa Livewire 4 + Flux + Alpine + Tailwind + a11y.
- **[pest-test-writer](./agents/pest-test-writer.md)** — revisa qualidade dos testes Pest; pode escrever testes sob demanda.

## Status

Sprint A ✅ fundação (Etapas 0, 1, 4) — `grill-with-docs`, `tdd`.
Sprint B ✅ planejamento (Etapas 2, 3) — `to-prd`, `to-issues`.
Sprint C ✅ entrega (Etapas 5, 6, 7) — `open-pr`, `simplify`, `review-branch` + 4 agents.
Sprint D ✅ manutenção (Etapas 8, 9) — `organize-docs`, `update-roadmap`.
Bootstrap ✅ `setup-rfl-laravel-skills` — one-shot por repo.

**Plugin completo.** 10 skills + 4 agents. As 9 etapas do PLAN.md estão implementadas.

Veja [PLAN.md](../PLAN.md) para detalhes.

## Créditos

- Skills `grill-with-docs` e `tdd` derivadas de [mattpocock/skills](https://github.com/mattpocock/skills) (MIT) — `tdd` reescrita para Pest, `grill-with-docs` mantida com pequenos ajustes.
- Agent `laravel-simplifier` (a ser importado na Etapa 6) de [laravel/agent-skills](https://github.com/laravel/agent-skills) (Apache-2.0).

## Licença

MIT
