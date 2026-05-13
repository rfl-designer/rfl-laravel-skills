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

## Checklist por ciclo

```
[ ] Teste descreve comportamento, não implementação
[ ] Teste usa apenas interface pública
[ ] Teste sobreviveria a refactor interno
[ ] Código é mínimo para este teste
[ ] Sem features especulativas
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
