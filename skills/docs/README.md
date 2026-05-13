# Docs

Skills que mantêm a documentação do projeto consumidor (`docs/adr/`, `docs/prd/`, `docs/roadmap/`, `CONTEXT.md`) higienizada e atualizada.

- **[organize-docs](./organize-docs/SKILL.md)** — varredura interativa pós-PRs: propõe ADRs, novos termos para `CONTEXT.md`, arquiva PRDs entregues, detecta links quebrados, ADRs com status estale.
- **[update-roadmap](./update-roadmap/SKILL.md)** — gera `docs/roadmap/index.html` por **ondas** (sort topológico do DAG de dependências), cards em grid de 3 colunas com impacto-usuário e badges de dependência.
