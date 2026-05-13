# Template do bloco a adicionar em CLAUDE.md ou AGENTS.md

Substitua `{...}` pelos valores detectados/escolhidos durante o setup.

```markdown
## Agent skills (rfl-laravel-skills)

Este projeto usa o plugin [`rfl-laravel-skills`](https://github.com/rfl-designer/rfl-laravel-skills).
Configurações em `.claude/*.json` e `docs/`.

### Stack

- Laravel {12.x}, Livewire {4.x}, Flux {Free|Pro 2.x}, Pest {4.x}, Pint {1.x}
- Convenção de commit: **Conventional Commits (EN)**
- Issue tracker: **GitHub Issues** (`gh` CLI)

### Convenções de validação e autorização

- Validação: sempre em **Form Request** (nunca inline em controller/Livewire)
- Autorização: **Policy** registrada + `authorize()` no Form Request
- Rotas: agrupar por contexto, middleware explícito

### Convenções de presentation

- **Flux UI** preferido sobre HTML cru: `<flux:input>`, `<flux:button>`, `<flux:modal>`
- **Livewire 4** padrão; **Volt** quando o componente é pequeno e local
- Alpine apenas para estado UI efêmero — não duplicar estado de Livewire

### Convenções de teste

- **Pest 4**, sempre `RefreshDatabase` em integration tests
- Factories sobre fixtures
- `Livewire::test(Component::class)` para componentes
- `Mail::fake`, `Queue::fake`, `Bus::fake`, `Event::fake`, `Http::fake`, `Storage::fake`
- Asserts contra estado/dados — nunca contra HTML cru

### Documentação

- `CONTEXT.md` — glossário de domínio (mantido por `/grill-with-docs`)
- `docs/adr/` — decisões arquiteturais
- `docs/prd/active/` — PRDs em andamento
- `docs/prd/done/` — PRDs entregues
- `docs/roadmap/index.html` — gerado por `/update-roadmap`
- `docs/runbooks/` — operacional

### Skills disponíveis

| Comando | Quando usar |
|---|---|
| `/grill-with-docs` | Estressar plano contra domínio antes de começar |
| `/to-prd` | Sintetizar conversa em PRD + issue GitHub |
| `/to-issues` | Quebrar PRD em slices verticais (4 camadas) |
| `/tdd` | Red-green-refactor com Pest |
| `/simplify` | Refatorar diff não-commitado (gated por Pest verde) |
| `/review-branch` | 3 reviewers em paralelo (Laravel + Livewire/Flux + Pest) |
| `/open-pr` | Abrir PR com título derivado de Conventional Commits |
| `/organize-docs` | Varredura interativa de docs pós-PRs |
| `/update-roadmap` | Gerar `docs/roadmap/index.html` por ondas |
```
