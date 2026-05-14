# Laravel

Skills Laravel do plugin. `simplify` usa o agent `laravel-simplifier`; `review-pr` roda no agente principal para evitar custo extra de tokens.

- **[simplify](./simplify-with-agent/SKILL.md)** — invoca `laravel-simplifier` sobre o diff não-commitado, gated por Pest verde.
- **[review-pr](./review-pr/SKILL.md)** — revisa PR em uma passagem consolidada, sem sub-agents, cobrindo Laravel + Livewire/Flux + Pest + **spec compliance vs issue**.
