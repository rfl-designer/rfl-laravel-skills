# Laravel

Skills wrapper que disparam sub-agents especializados em Laravel/Livewire/Flux/Pest. Os agents vivem em [`../../agents/`](../../agents/).

- **[simplify](./simplify-with-agent/SKILL.md)** — invoca `laravel-simplifier` sobre o diff não-commitado, gated por Pest verde.
- **[review-branch](./review-branch/SKILL.md)** — dispara `laravel-reviewer` + `livewire-flux-reviewer` + `pest-test-writer` em paralelo e consolida output em 3 colunas.
