---
name: tdd
description: Test-driven development for Laravel 12 + Livewire 4 + Pest 3/4 using vertical slices that cut Migration → Action/Form Request/Policy → Livewire/Volt+Flux → Pest test in one cycle. Use whenever the user wants to build a feature or fix a bug test-first, mentions TDD, Pest, red-green-refactor, "vertical slice", "test-first", "feature test", "Livewire test", or asks to write integration tests for a Laravel app. Prefer this over generic TDD guidance when the project is Laravel — this skill is opinionated about the 4-layer slice and pairs with /open-pr and /review-pr.
---

> **Crédito:** filosofia e estrutura de [`mattpocock/skills` — `tdd`](https://github.com/mattpocock/skills) (MIT). Exemplos, helpers e a noção de slice vertical Laravel reescritos para Pest 3/4 + Laravel 12 + Livewire 4 + Flux.

# Test-Driven Development (Pest, Laravel)

## Filosofia

**Princípio central:** testes verificam **comportamento** através das **interfaces públicas**, não detalhes de implementação. O código pode mudar inteiramente; os testes não deveriam.

**Bons testes** são integration-style: exercitam caminhos reais do código pelas APIs públicas. Descrevem _o que_ o sistema faz, não _como_. Um bom teste se lê como uma especificação — `it('lets a user check out with a valid cart')` te conta exatamente qual capacidade existe. Esses testes sobrevivem a refactors porque não se importam com estrutura interna.

**Maus testes** são acoplados à implementação. Eles mockam colaboradores internos, testam métodos privados, ou verificam por meios externos (tipo bater direto no banco em vez de usar a interface). O sinal de alerta: seu teste quebra quando você refatora, mas o comportamento não mudou. Se você renomeia um método interno e testes falham, esses testes estavam testando implementação, não comportamento.

Veja [references/tests.md](references/tests.md) para exemplos de bons vs maus testes e [references/mocking.md](references/mocking.md) para regras de mock (fronteira de sistema sim, classe interna não).

## Slice vertical Laravel

Numa app Laravel, **uma slice = um ciclo RED→GREEN que atravessa as 4 camadas**:

```
1. Migration + Model (ou alteração de schema existente)
2. Action / Form Request / Policy (regra de negócio + autorização)
3. Livewire/Volt component + view Blade + Flux UI
4. Pest test (feature ou Livewire test) cobrindo o caminho feliz
```

Em cada ciclo, você escreve UM teste Pest que demonstra a slice ponta-a-ponta. Para ver uma slice completa do começo ao fim — incluindo o commit final — leia [references/example-slice.md](references/example-slice.md).

### Slices que não cortam as 4 camadas

`/to-issues` autoriza marcadores quando a slice é legitimamente parcial:

- **`[domain-only]`** — alteração só em camada 1-2 (ex.: nova regra de cálculo numa Action existente, sem mudança de UI). RED→GREEN cobre 2 camadas + teste Feature/Unit.
- **`[ui-only]`** — alteração só em camada 3 (ex.: trocar um `<flux:input>` por `<flux:textarea>`, sem mudar dado). RED→GREEN cobre 1 camada + teste Livewire.
- **`[chore]`** — alteração de infra/dependência sem comportamento novo (ex.: bump de Pint, ajuste de pipeline). Geralmente não tem teste novo — pula este workflow e vai direto pra `/open-pr`.

Se a slice **não está marcada** com nenhum desses, ela deve atravessar as 4 camadas. Slices não-marcadas que cortam só uma ou duas camadas geralmente são sub-tarefas — agrupe com a slice irmã.

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
  RED→GREEN→commit: teste1→impl1→commit1
  RED→GREEN→commit: teste2→impl2→commit2
  RED→GREEN→commit: teste3→impl3→commit3
  ...
```

## Workflow

### 1. Planejamento

Quando explorar o codebase, use o glossário de domínio do projeto (`CONTEXT.md`) para que nomes de teste e vocabulário de interface batam com a linguagem do projeto. Respeite ADRs na área que você está tocando. Leia `AGENTS.md` (boost) para conhecer versões de Pest, Livewire, Flux.

Antes de escrever qualquer código:

- [ ] Confirmar com o usuário quais mudanças de interface são necessárias
- [ ] Confirmar com o usuário quais comportamentos testar (priorizar)
- [ ] Identificar oportunidades de [deep modules](references/deep-modules.md) (interface pequena, implementação profunda)
- [ ] Desenhar interfaces para [testabilidade](references/interface-design.md)
- [ ] Listar os comportamentos a testar (não passos de implementação)
- [ ] Conseguir aprovação do usuário no plano

Pergunte: "Como deve ser a interface pública? Quais comportamentos são mais importantes de testar?"

**Você não pode testar tudo.** Confirme com o usuário exatamente quais comportamentos importam mais. Foque esforço de teste em caminhos críticos e lógica complexa, não em todo edge case possível.

> **Operação autônoma (sem usuário no loop):** quando rodando sem confirmação humana possível, pule "Conseguir aprovação" e use o melhor julgamento. Documente as escolhas no commit body para revisão posterior.

### Review loop bounded

`/review-pr` é um **gate de merge**, não uma continuação infinita do TDD. O TDD produz slices verdes; o review classifica risco restante. Quando review apontar BLOCKER, não reinicie a feature inteira.

Para cada achado do review, escolha uma disposição antes de codar:

- **ACCEPT_NOW** — blocker real, dentro do escopo da issue/PR. Corrija com a menor slice possível.
- **SPLIT_FOLLOW_UP** — válido, mas fora do escopo do PR atual. Abra/aponte issue separada e ajuste o PR body se necessário.
- **DOC_JUSTIFY** — desvio intencional. Documente no PR body/ADR/issue em vez de mudar código.
- **REJECT_FALSE_POSITIVE** — achado incorreto ou preferência. Não altere código; registre a razão no resumo.

Só volte ao RED→GREEN quando a disposição for **ACCEPT_NOW** e houver comportamento novo/faltante ou regressão observável. Blocker técnico sem comportamento novo (ex.: `env()` fora de config, `wire:key` faltando, debug artifact) recebe patch mínimo + teste existente/afetado rodando; não exige uma nova slice completa.

NITs e NICE-TO-HAVE não entram no loop TDD. Corrija apenas se for barato e local; caso contrário, deixe como follow-up.

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

### 4. Refactor (per-cycle, ainda em GREEN)

Refactor acontece **dentro do ciclo**, não num passo separado depois de N testes. Após cada GREEN, antes do commit, olhe o que você acabou de escrever e pergunte: "isso já está limpo?"

Candidatos a procurar:

- **Duplicação** → extraia função/classe
- **Métodos longos** → quebre em helpers privados (testes ficam na interface pública)
- **Shallow modules** → combine ou aprofunde (veja [deep-modules.md](references/deep-modules.md))
- **Feature envy** → mova lógica pra onde os dados vivem
- **Primitive obsession** → introduza value objects
- **Código existente** que o código novo revela como problemático

Rode os testes após cada passo de refactor — se quebrou, desfaça e tente outra abordagem. **Nunca refatore enquanto RED.** Chegue ao GREEN primeiro.

Se houver refactor pós-GREEN, ele vira **commit separado** do `feat:`/`fix:` da slice (ver passo 5).

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
- O `/review-pr` consegue mapear cada commit a um critério de aceitação da issue.

### 6. Handoff — fim da feature

Quando todas as slices da feature estiverem prontas (todos os critérios da issue cobertos por commits `feat:`/`fix:`):

```
/open-pr
```

`/open-pr` é gated por Pest+Pint, deriva o título dos commits Conventional, e abre PR linkando à issue. Em seguida, rode `/review-pr` **uma vez** para obter o gate consolidado.

Se o review trouxer BLOCKERs:

1. Faça a disposição de cada achado (`ACCEPT_NOW`, `SPLIT_FOLLOW_UP`, `DOC_JUSTIFY`, `REJECT_FALSE_POSITIVE`).
2. Corrija apenas os `ACCEPT_NOW`.
3. Rode o menor conjunto de testes/gates que cobre os arquivos afetados.
4. Reexecute apenas uma revisão focada na dimensão corrigida quando precisar confirmar a correção; não reinicie a bateria completa por reflexo.

O loop termina quando não há **BLOCKER aceito** pendente e Pest/Pint estão verdes. Não persiga NITs até zero antes de mergear.

> **Auto-revisão antes do `/open-pr`:** consulte [references/reviewer-baseline.md](references/reviewer-baseline.md) — enumera os BLOCKERs típicos por camada que o `/review-pr` consolidado vai checar. Fix preventivo agora custa segundos; fix reativo após review custa um round-trip.

## Checklist por ciclo

Imprima esta lista mentalmente ao final de cada GREEN, antes do commit:

```
[ ] Teste descreve comportamento, não implementação
[ ] Teste usa apenas interface pública
[ ] Teste sobreviveria a refactor interno
[ ] Código é mínimo para este teste
[ ] Sem features especulativas
[ ] Pest + Pint passaram localmente
[ ] git add lista apenas arquivos da slice
[ ] Commit feito com Conventional Commits, escopo da slice
[ ] Refactor (se houve) commitado separado do feat/fix
```

Para a auto-revisão profunda antes de `/open-pr`, use os checklists detalhados em [references/reviewer-baseline.md](references/reviewer-baseline.md).

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

## Referências

- [references/example-slice.md](references/example-slice.md) — slice completa end-to-end com 4 commits
- [references/tests.md](references/tests.md) — bons vs maus testes (Pest + Laravel)
- [references/mocking.md](references/mocking.md) — quando mockar e quando usar fakes do Laravel
- [references/deep-modules.md](references/deep-modules.md) — interface pequena, implementação profunda
- [references/interface-design.md](references/interface-design.md) — desenhar para testabilidade
- [references/reviewer-baseline.md](references/reviewer-baseline.md) — anti-padrões BLOCKER pra auto-revisão antes de `/open-pr`
