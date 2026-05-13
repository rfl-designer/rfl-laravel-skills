# Laravel

Skills wrapper que disparam sub-agents especializados em Laravel/Livewire/Flux/Pest. Os agents vivem em [`../../agents/`](../../agents/).

- **[simplify](./simplify-with-agent/SKILL.md)** — invoca `laravel-simplifier` sobre o diff não-commitado, gated por Pest verde.
- **[review-pr](./review-pr/SKILL.md)** — dispara 4 reviewers em paralelo (Laravel + Livewire/Flux + Pest + **spec compliance vs issue**) e consolida output em tabela única.
