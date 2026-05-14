# Reviewer baseline — anti-padrões que viram BLOCKER em PR

Quando a feature inteira fechar e você rodar `/open-pr`, o `/review-pr` faz uma **revisão consolidada sem sub-agents** sobre o diff:

- Application/Persistence (Eloquent, validação, autorização, container)
- Presentation (Livewire 4, Volt, Flux UI, Alpine, Tailwind, a11y)
- qualidade dos testes Pest
- aderência da PR aos critérios da issue fechada

Os checklists abaixo enumeram os BLOCKERs mais frequentes por camada. Internalize-os durante o RED→GREEN: fix preventivo enquanto o código está fresco custa segundos; fix reativo após review custa um round-trip de PR.

> **Quando consultar este arquivo:** antes de iniciar a slice (orientação), ou ao terminar o ciclo (auto-revisão antes do `/open-pr`). É a baseline preventiva do `/review-pr`, não substitui o diff review final.

## Camada 1 — Migration + Model

- [ ] FK column tem índice (use `->constrained()` ou `->index()` explícito)
- [ ] Toda FK tem política `->onDelete('cascade'|'set null'|'restrict')` decidida
- [ ] Nova tabela tem `id`, `created_at`, `updated_at`
- [ ] `string('field')` com limite explícito quando o tamanho importa
- [ ] Model com `$fillable` ou `$guarded` declarado (sem mass-assignment aberto)
- [ ] Sem `Model::all()` seguido de `->filter()` em PHP — empurre para SQL com `where()`
- [ ] Sem `where('id', $id)->first()` — use `find($id)`

## Camada 2 — Action / Form Request / Policy

- [ ] Validação **só** em Form Request — nunca inline em controller/Livewire
- [ ] Form Request `authorize()` **não** é blanket-true (delega à Policy ou checa contexto)
- [ ] Toda mutação de model relevante tem Policy registrada + `can()` no caller
- [ ] Sem `auth()->user()->id === $model->user_id` ad-hoc — use Policy
- [ ] Dependências por **constructor injection** — sem `app(Foo::class)` mid-method
- [ ] `env()` somente em `config/*.php` — no resto, `config('foo.bar')`
- [ ] Sem `dd()`, `dump()`, `ray()`, `var_dump()`, `Log::debug()` deixados no diff
- [ ] Sem código comentado (use git history)

## Camada 3 — Livewire/Volt + Flux + Alpine

- [ ] Inputs usam `<flux:input>`, `<flux:select>`, `<flux:textarea>`, `<flux:checkbox>` — não `<input>` cru
- [ ] Botões usam `<flux:button variant="...">` com variant adequada
- [ ] Modais via `<flux:modal>` (não Alpine puro)
- [ ] Inputs com label/erro envolvidos em `<flux:field>`
- [ ] `wire:model` usa modifier adequado: `.blur` (default), `.live` (parcimônia), `.debounce.500ms` (search)
- [ ] `wire:key` em itens dentro de `@foreach`
- [ ] `wire:loading` / `wire:dirty` onde a UX assíncrona importa
- [ ] Propriedades públicas só pro que a view consome — derivados em `#[Computed]`
- [ ] Alpine `x-data` é UI efêmera (open/close, hover) — **não** duplica estado do servidor
- [ ] `@class([...])` para classes condicionais — sem string concat
- [ ] `<button type="button">` explícito quando não submete form
- [ ] `<img>` com `loading="lazy"` + dimensões (CLS)

## Camada 4 — Pest test

- [ ] Nome do teste descreve **WHAT** (`it('lets user check out')`), não **HOW**
- [ ] **Sem mock de classe interna** (Action, Service do próprio app)
- [ ] **Sem query direta no banco** para verificar estado — use `Model::find` / `->refresh()`
- [ ] **Sem assert contra HTML cru** (`assertSeeHtml('<div class="card">')`) — use `assertSee` ou estado de componente
- [ ] `RefreshDatabase` em integration tests
- [ ] `Model::factory()->create()` para setup — não array hardcoded
- [ ] `Livewire::test(Component::class)` + `actingAs($user)` para componentes autenticados
- [ ] Fakes Laravel (`Mail::fake`, `Queue::fake`, `Bus::fake`, `Event::fake`, `Http::fake`, `Storage::fake`, `Notification::fake`) — não Mockery em facade
- [ ] Cada teste independente — sem ordem implícita
- [ ] `Carbon::setTestNow()` sempre tem reset (ou usa `freezeTime()`)
- [ ] Datasets (`->with([...])`) só quando o **mesmo** comportamento roda sobre input variado — não para esconder N testes diferentes

## Aderência à issue

Esses BLOCKERs são detectados comparando PR com a issue fechada. Antes de abrir PR, valide:

- [ ] Cada checkbox de `## Acceptance criteria` da issue tem código no diff que o entrega
- [ ] PR entrega o **comportamento end-to-end** descrito em `## What to build` — não só uma camada
- [ ] Sem **scope creep**: refactor de módulo não-relacionado, dependência nova sem justificativa, migration tocando tabela não mencionada
- [ ] Sem **spec drift**: API contract, componente Flux, ou abordagem que diverge da issue sem justificativa explícita no PR body
- [ ] Artefatos prometidos pela issue estão no diff: ADR atualizada, `CONTEXT.md` atualizado, migration nomeada como acordado

> Se aparecer scope creep legítimo (ex.: bug fix descoberto no caminho), abra **PR separada**. O `/review-pr` marca como BLOCKER quando `gh pr view --json closingIssuesReferences` aponta a uma issue cujo escopo não cobre as mudanças.
