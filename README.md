# rfl-laravel-skills

Plugin Claude Code que orquestra o ciclo de desenvolvimento **Laravel 12 + Livewire 4 + Tailwind + Alpine + Flux UI**.

Trabalha em conjunto com [`laravel/boost`](https://github.com/laravel/boost) — o boost fornece guidelines de stack (versões, padrões idiomáticos), este plugin fornece o **processo** (grilling, PRDs, issues, TDD, reviews, roadmap).

## Quickstart

```bash
npx skills@latest add rfl-designer/rfl-laravel-skills
```

(durante o desenvolvimento, instale via path local: `/plugin install /caminho/para/rfl-laravel-skills`)

## Fluxo

```
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

**Plugin completo.** As 9 etapas do PLAN.md estão implementadas.

Veja [PLAN.md](../PLAN.md) para detalhes.

## Créditos

- Skills `grill-with-docs` e `tdd` derivadas de [mattpocock/skills](https://github.com/mattpocock/skills) (MIT) — `tdd` reescrita para Pest, `grill-with-docs` mantida com pequenos ajustes.
- Agent `laravel-simplifier` (a ser importado na Etapa 6) de [laravel/agent-skills](https://github.com/laravel/agent-skills) (Apache-2.0).

## Licença

MIT
