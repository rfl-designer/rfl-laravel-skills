---
name: tdd
description: Test-driven development for Laravel with Pest 3/4. Red-green-refactor loop applied to vertical slices that cut Migration → Action → Livewire/Flux → Pest test. Use when user wants to build features or fix bugs test-first, mentions "TDD", "Pest", "red-green-refactor", or asks for integration tests.
---

> **Crédito:** filosofia e estrutura de [`mattpocock/skills` — `tdd`](https://github.com/mattpocock/skills) (MIT). Exemplos e helpers reescritos para Pest 3/4 + Laravel 12 + Livewire 4.

# Test-Driven Development (Pest)

## Filosofia

**Princípio central:** testes verificam **comportamento** através das **interfaces públicas**, não detalhes de implementação. O código pode mudar inteiramente; os testes não deveriam.

**Bons testes** são integration-style: exercitam caminhos reais do código pelas APIs públicas. Descrevem _o que_ o sistema faz, não _como_. Um bom teste se lê como uma especificação — `it('lets a user check out with a valid cart')` te conta exatamente qual capacidade existe. Esses testes sobrevivem a refactors porque não se importam com estrutura interna.

**Maus testes** são acoplados à implementação. Eles mockam colaboradores internos, testam métodos privados, ou verificam por meios externos (tipo bater direto no banco em vez de usar a interface). O sinal de alerta: seu teste quebra quando você refatora, mas o comportamento não mudou. Se você renomeia um método interno e testes falham, esses testes estavam testando implementação, não comportamento.

Veja [tests.md](tests.md) para exemplos e [mocking.md](mocking.md) para regras de mock.

## Anti-padrão: Horizontal Slices

**NÃO escreva todos os testes primeiro, depois toda a implementação.** Isso é "horizontal slicing" — tratar RED como "escreva todos os testes" e GREEN como "escreva todo o código".

Isso produz **testes ruins**:

- Testes escritos em massa testam comportamento _imaginado_, não _real_
- Você acaba testando a _forma_ das coisas (estruturas de dados, assinaturas de método) em vez de comportamento de usuário
- Testes ficam insensíveis a mudanças reais — passam quando comportamento quebra, falham quando comportamento está bom
- Você ultrapassa seus faróis, comprometendo-se com estrutura de teste antes de entender a implementação

**Abordagem correta:** vertical slices via tracer bullets. Um teste → uma implementação → repete. Cada teste responde ao que você aprendeu no ciclo anterior. Como você acabou de escrever o código, sabe exatamente qual comportamento importa e como verificar.

```
ERRADO (horizontal):
  RED:   teste1, teste2, teste3, teste4, teste5
  GREEN: impl1, impl2, impl3, impl4, impl5

CERTO (vertical):
  RED→GREEN: teste1→impl1
  RED→GREEN: teste2→impl2
  RED→GREEN: teste3→impl3
  ...
```

## Slice vertical Laravel

Numa app Laravel, **uma slice = um ciclo RED→GREEN que atravessa as 4 camadas**:

```
1. Migration + Model (ou alteração de schema existente)
2. Action / Form Request / Policy (regra de negócio + autorização)
3. Livewire/Volt component + view Blade + Flux UI
4. Pest test (feature ou Livewire test) cobrindo o caminho feliz
```

Em cada ciclo, você escreve UM teste Pest que demonstra a slice ponta-a-ponta. Slices que não cortam as 4 camadas geralmente são sub-tarefas — agrupe.

## Anti-padrões que viram BLOCKER em PR

Quando a slice fechar e você rodar `/open-pr`, o `/review-pr` dispara **4 reviewers em paralelo** sobre o diff. Internalize estes padrões durante o RED→GREEN para que o PR já saia review-clean. Cada item abaixo é um BLOCKER recorrente — fix preventivo enquanto o código ainda está fresco custa segundos; fix reativo após review custa um round-trip.

### Camada 1 — Migration + Model (`laravel-reviewer`)

- [ ] FK column tem índice (use `->constrained()` ou `->index()` explícito)
- [ ] Toda FK tem política `->onDelete('cascade'|'set null'|'restrict')` decidida
- [ ] Nova tabela tem `id`, `created_at`, `updated_at`
- [ ] `string('field')` com limite explícito quando o tamanho importa
- [ ] Model com `$fillable` ou `$guarded` declarado (sem mass-assignment aberto)
- [ ] Sem `Model::all()` seguido de `->filter()` em PHP — empurre para SQL com `where()`
- [ ] Sem `where('id', $id)->first()` — use `find($id)`

### Camada 2 — Action / Form Request / Policy (`laravel-reviewer`)

- [ ] Validação **só** em Form Request — nunca inline em controller/Livewire
- [ ] Form Request `authorize()` **não** é blanket-true (delega à Policy ou checa contexto)
- [ ] Toda mutação de model relevante tem Policy registrada + `can()` no caller
- [ ] Sem `auth()->user()->id === $model->user_id` ad-hoc — use Policy
- [ ] Dependências por **constructor injection** — sem `app(Foo::class)` mid-method
- [ ] `env()` somente em `config/*.php` — no resto, `config('foo.bar')`
- [ ] Sem `dd()`, `dump()`, `ray()`, `var_dump()`, `Log::debug()` deixados no diff
- [ ] Sem código comentado (use git history)

### Camada 3 — Livewire/Volt + Flux + Alpine (`livewire-flux-reviewer`)

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

### Camada 4 — Pest test (`pest-test-writer`)

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

### Aderência à issue (`pr-spec-reviewer`)

Esses BLOCKERs são detectados pelo reviewer de spec compliance comparando PR com a issue fechada. Antes de abrir PR, valide:

- [ ] Cada checkbox de `## Acceptance criteria` da issue tem código no diff que o entrega
- [ ] PR entrega o **comportamento end-to-end** descrito em `## What to build` — não só uma camada
- [ ] Sem **scope creep**: refactor de módulo não-relacionado, dependência nova sem justificativa, migration tocando tabela não mencionada
- [ ] Sem **spec drift**: API contract, componente Flux, ou abordagem que diverge da issue sem justificativa explícita no PR body
- [ ] Artefatos prometidos pela issue estão no diff: ADR atualizada, `CONTEXT.md` atualizado, migration nomeada como acordado

> Se aparecer scope creep legítimo (ex.: bug fix descoberto no caminho), abra **PR separada**. O reviewer marca como BLOCKER quando `gh pr view --json closingIssuesReferences` aponta a uma issue cujo escopo não cobre as mudanças.

## Workflow

### 1. Planejamento

Quando explorar o codebase, use o glossário de domínio do projeto (`CONTEXT.md`) para que nomes de teste e vocabulário de interface batam com a linguagem do projeto. Respeite ADRs na área que você está tocando. Leia `AGENTS.md` (boost) para conhecer versões de Pest, Livewire, Flux.

Antes de escrever qualquer código:

- [ ] Confirmar com o usuário quais mudanças de interface são necessárias
- [ ] Confirmar com o usuário quais comportamentos testar (priorizar)
- [ ] Identificar oportunidades de [deep modules](deep-modules.md) (interface pequena, implementação profunda)
- [ ] Desenhar interfaces para [testabilidade](interface-design.md)
- [ ] Listar os comportamentos a testar (não passos de implementação)
- [ ] Conseguir aprovação do usuário no plano

Pergunte: "Como deve ser a interface pública? Quais comportamentos são mais importantes de testar?"

**Você não pode testar tudo.** Confirme com o usuário exatamente quais comportamentos importam mais. Foque esforço de teste em caminhos críticos e lógica complexa, não em todo edge case possível.

### 2. Tracer Bullet

Escreva UM teste que confirma UMA coisa sobre o sistema:

```
RED:   Escreva teste para o primeiro comportamento → teste falha
GREEN: Escreva o código mínimo para passar → teste passa
```

Esta é sua tracer bullet — prova que o caminho funciona ponta-a-ponta.

### 3. Loop incremental

Para cada comportamento restante:

```
RED:   Próximo teste → falha
GREEN: Código mínimo para passar → passa
```

Regras:

- Um teste de cada vez
- Apenas código suficiente para passar o teste atual
- Não antecipe testes futuros
- Mantenha testes focados em comportamento observável

### 4. Refactor

Depois que todos os testes passam, procure [candidatos a refactor](refactoring.md):

- [ ] Extrair duplicação
- [ ] Aprofundar módulos (mover complexidade atrás de interfaces simples)
- [ ] Aplicar SOLID onde for natural
- [ ] Considerar o que código novo revela sobre código existente
- [ ] Rodar testes após cada passo de refactor

**Nunca refatore enquanto RED.** Chegue ao GREEN primeiro.

### 5. Commit (fim de ciclo) — obrigatório

Cada ciclo RED→GREEN encerra com **um commit** que captura a slice testada. Não acumule múltiplas slices num único commit — granularidade fina é o que torna `git bisect`, code review e roll-back cirúrgicos.

**Quando commitar:**

- Imediatamente após o teste ficar GREEN (e antes de começar o próximo RED).
- Se você fez refactor pós-GREEN, **dois commits**: um `feat:`/`fix:` da slice, depois um `refactor:` separado.
- **Nunca commit em RED.** Se o teste está vermelho, não empilha o commit — ou o teste anterior estava broken e você precisa entender o quê.

**Convenção de mensagem (Conventional Commits, EN):**

- `feat(<scope>):` para comportamento novo — default da slice
- `fix(<scope>):` quando a slice corrige bug
- `refactor(<scope>):` para limpeza pós-GREEN, sempre commit separado
- `test(<scope>):` apenas se for teste sem código de produção (raro em TDD vertical)

O título do commit reflete o comportamento testado — mesmo verbo do `it(...)`:

| Teste | Commit |
|---|---|
| `it('lets project member leave a comment')` | `feat(comments): allow project member to leave comment` |
| `it('rejects comment from non-member')` | `feat(comments): reject comment from non-member` |
| `it('extracts CommentPolicy from inline check')` | `refactor(comments): extract policy from inline check` |

**Gate antes do commit:**

```bash
vendor/bin/pest --filter=<slice-keyword>   # slice está verde
vendor/bin/pint                             # estilo aplicado
git add <arquivos-da-slice>                 # nunca git add -A neste ciclo
git commit -m "feat(<scope>): <one-line>"
```

Adicione apenas os arquivos da slice — `git add -A` arrasta lixo de outras áreas e quebra a granularidade. Os 4 arquivos típicos de uma slice Laravel:

```
database/migrations/<timestamp>_<name>.php
app/Models/<Model>.php  (ou Action/FormRequest/Policy)
app/Livewire/<Component>.php  +  resources/views/livewire/<view>.blade.php
tests/Feature/<Slice>Test.php
```

**Por que commit fino:**

- `/open-pr` deriva o título do PR por precedência de Conventional Commits — slices granulares dão um histórico de PR legível e categorização correta.
- `git bisect` localiza regressões em segundos quando cada commit é um único comportamento testado.
- Reviewer pedindo reverter parte do trabalho → `git revert <hash>` cirúrgico, sem desempacotar diff.
- O `/review-pr` (especialmente o `pr-spec-reviewer`) consegue mapear cada commit a um critério de aceitação da issue.

## Checklist por ciclo

```
[ ] Teste descreve comportamento, não implementação
[ ] Teste usa apenas interface pública
[ ] Teste sobreviveria a refactor interno
[ ] Código é mínimo para este teste
[ ] Sem features especulativas
[ ] Commit feito após GREEN (Conventional Commits, escopo da slice)
[ ] Refactor (se houver) commitado separado do feat/fix
```

## Checklist Laravel-específico

```
[ ] Usa RefreshDatabase (não mocka schema)
[ ] Usa factories (Model::factory()), não fixtures hardcoded
[ ] Componente Livewire testado via Livewire::test(Component::class)
[ ] Validação testada via Form Request, não via assert em mensagem HTML
[ ] Http::fake() apenas para integrações externas — não para o próprio app
[ ] Sem ->set() em propriedades privadas via reflection
[ ] Sem ->get() seguido de assert direto no banco — verificar pela interface
```

## Comandos úteis

```bash
# Roda toda a suite
vendor/bin/pest

# Roda apenas testes que casam com filtro
vendor/bin/pest --filter=ProjectComment

# Roda em paralelo (Pest 3+)
vendor/bin/pest --parallel

# Coverage (precisa de Xdebug ou PCOV)
vendor/bin/pest --coverage --min=80
```
