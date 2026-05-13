# rfl-laravel-skills

Plugin Claude Code que orquestra o ciclo de desenvolvimento Laravel 12 + Livewire 4 + Tailwind + Alpine + Flux UI. Foca em **processo** e **agents de revisão** — guidelines de stack continuam vindo do `laravel/boost` (composer package).

## Language

**Skill**:
Slash-command empacotado num diretório com `SKILL.md`. Carregado pelo Claude Code via `plugin.json`. Cada skill cobre uma etapa do ciclo (grill, prd, issues, tdd, pr, etc.).
_Avoid_: command, prompt, comando

**Agent**:
Sub-agent invocável via `Agent` tool. Definido em `agents/<nome>.md`. Tem escopo estreito (simplificar, revisar, escrever testes) e roda isolado do contexto principal.
_Avoid_: bot, assistant secundário

**Bucket**:
Pasta de primeiro nível dentro de `skills/` que agrupa skills por domínio: `process/` (workflow), `laravel/` (wrappers que disparam agents), `docs/` (manutenção da documentação do projeto).
_Avoid_: categoria, grupo

**Slice vertical Laravel**:
Unidade de trabalho que atravessa, no mínimo, 4 camadas: Migration+Model → Action/Form Request/Policy → Livewire/Volt+Flux → Pest test. Critério usado por `/to-issues` para quebrar PRDs.
_Avoid_: feature, ticket, tarefa

**Stack guideline (boost)**:
Arquivo `.blade.php` em `vendor/laravel/boost/.ai/` que descreve como código Laravel-idiomático deve parecer numa versão específica de pacote. Renderizado em `AGENTS.md`/`CLAUDE.md` no projeto. Skills NUNCA duplicam — apenas leem.
_Avoid_: convenção, regra, padrão (genérico demais)

**Issue**:
Unidade rastreada no GitHub Issues. Pode ser PRD, slice vertical, bug ou chore. Manipulada via `gh` CLI pelas skills `/to-prd`, `/to-issues`, `/update-roadmap`.

**Triage role**:
Label que marca o estado atual de uma **Issue** no fluxo (`needs-triage`, `ready-for-agent`, `blocked`, `in-progress`, `roadmap`).

**ADR** (Architecture Decision Record):
Arquivo curto em `docs/adr/NNNN-slug.md` registrando decisão hard-to-reverse + contexto. Criada pelo `/grill-with-docs` quando os 3 critérios (irreversível, surpreendente, fruto de trade-off) são satisfeitos.

**PRD** (Product Requirements Document):
Documento gerado por `/to-prd` em `docs/prd/<slug>.md` e publicado como issue. Sintetiza problema, solução, user stories e implementation decisions com vocabulário Laravel.

**Onda** (wave):
Nível topológico no DAG de dependências entre **Issues**. Issues numa mesma onda podem ser desenvolvidas em paralelo. Usada por `/update-roadmap` para gerar `docs/roadmap/index.html`.

## Relationships

- Um **Bucket** contém uma ou mais **Skills**
- Uma **Skill** pode invocar um ou mais **Agents** (ex.: `/review-branch` dispara 3 reviewers em paralelo)
- Um **PRD** gera N **Issues** (slices verticais)
- Uma **Issue** pode bloquear ou ser bloqueada por outras **Issues**; o grafo resultante define as **Ondas**
- Toda **Skill** que toca código Laravel pressupõe que **Stack guidelines (boost)** estão presentes em `AGENTS.md`

## Flagged ambiguities

- "skill" vs "command" — resolvido: o termo canônico é **Skill**. "command" é o que aparece pro usuário (`/grill-with-docs`); a unidade de pacote é a **Skill**.
- "agent" vs "skill" — resolvido: **Skill** é orquestração/processo (carregada como slash-command); **Agent** é um worker isolado invocado por uma Skill ou pelo Claude principal.
