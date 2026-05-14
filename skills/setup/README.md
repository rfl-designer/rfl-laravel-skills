# Setup

Skills de bootstrap do plugin no projeto consumidor. Rode antes de qualquer outra skill.

- **[setup-rfl-laravel-skills](./setup-rfl-laravel-skills/SKILL.md)** — valida pré-requisitos (gh, Pest, Pint, boost), cria `docs/` tree, semeia configs em `.claude/`, adiciona bloco `## Agent skills` em `CLAUDE.md`/`AGENTS.md`. Idempotente.
- **[setup-quality-gates](./setup-quality-gates/SKILL.md)** — instala catraca de qualidade para Laravel 12+ com Livewire 4, Alpine e Tailwind: baseline, script local, Composer/NPM scripts e GitHub Actions.
