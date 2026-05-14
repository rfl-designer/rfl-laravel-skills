# Deep modules

Conceito tirado de "A Philosophy of Software Design" (John Ousterhout).

**Deep module** = interface pequena + implementação grande

```
┌─────────────────────┐
│   Interface pequena │  ← Poucos métodos, parâmetros simples
├─────────────────────┤
│                     │
│                     │
│   Implementação     │  ← Lógica complexa escondida
│      profunda       │
│                     │
└─────────────────────┘
```

**Shallow module** = interface grande + implementação rasa (evite)

```
┌─────────────────────────────────┐
│       Interface grande          │  ← Muitos métodos, parâmetros complexos
├─────────────────────────────────┤
│  Implementação fina             │  ← Apenas faz pass-through
└─────────────────────────────────┘
```

## Por que importa em TDD

Deep modules tendem a gerar **bons testes**:

- Interface pequena = poucos pontos de teste
- Lógica escondida = teste descreve **o que** o módulo faz, não **como**
- Refactor interno não quebra teste — porque o teste só conhece a interface

Shallow modules tendem a gerar **maus testes**:

- Interface grande = muitos testes só pra cobrir a superfície
- Implementação fina = teste acaba descrevendo a estrutura interna
- Qualquer mexida quebra o teste — porque ele está acoplado à forma

## Perguntas pra fazer ao desenhar uma interface

- Posso reduzir o número de métodos públicos?
- Posso simplificar os parâmetros (menos campos, tipos mais ricos)?
- Posso esconder mais complexidade aqui dentro em vez de empurrar pro caller?

## Exemplo Laravel

**Deep:**

```php
class CreateProject
{
    public function __invoke(User $owner, string $name): Project
    {
        // valida nome único pro owner, cria projeto, dispara evento,
        // gera slug, configura roles default — tudo escondido aqui
    }
}
```

Caller só precisa de `$owner` e `$name`. Tudo o resto é detalhe interno.

**Shallow:**

```php
class CreateProject
{
    public function validateName(...) { ... }
    public function generateSlug(...) { ... }
    public function persist(...) { ... }
    public function dispatchEvent(...) { ... }
    public function setupDefaultRoles(...) { ... }
}
```

Caller precisa orquestrar 5 chamadas. Cada uma vira um teste de detalhe interno. Refactor interno (ex.: trocar ordem das chamadas) quebra os testes.
