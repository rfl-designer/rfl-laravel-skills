# Convenções deste repo

Este repo é o plugin Claude Code `rfl-laravel-skills`. Skills são organizadas em buckets sob `skills/`:

- `setup/` — bootstrap one-shot do plugin no projeto consumidor (`setup-rfl-laravel-skills`)
- `process/` — workflow de desenvolvimento (grill, prd, issues, tdd, pr)
- `laravel/` — wrappers que disparam agents do diretório `agents/` (simplifier, reviewers)
- `docs/` — manutenção da documentação do projeto consumidor (organize-docs, update-roadmap)

## Regras

1. Toda skill em `setup/`, `process/`, `laravel/` ou `docs/` precisa de:
   - Entrada no `README.md` do bucket (link `nome → SKILL.md`).
   - Entrada no `README.md` do root (link `nome → SKILL.md`).
   - Entrada em `.claude-plugin/plugin.json` (lista `skills`).

2. Todo agent em `agents/` precisa de:
   - Entrada no `README.md` do root.
   - Entrada em `.claude-plugin/plugin.json` (lista `agents`).

3. Frontmatter do `SKILL.md`:
   - `name:` em kebab-case, EN.
   - `description:` em EN (afeta triggering do modelo). Curto, específico, orientado a quando usar.
   - Corpo em PT-BR.

4. Skills vendored de `mattpocock/skills` mantêm crédito no topo do `SKILL.md` quando modificadas. Skills sem modificação ficam idênticas ao upstream.

5. Skills que tocam código Laravel pressupõem que `AGENTS.md` (gerado por `laravel/boost`) está presente no projeto consumidor — leem dele para conhecer pacotes e versões em vez de duplicar guidelines.

6. Conventional Commits (EN) obrigatórios: `feat:` `fix:` `refactor:` `docs:` `test:` `chore:` (+ scope opcional).
