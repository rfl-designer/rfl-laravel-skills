---
name: grill-with-docs
description: Grilling session that challenges your plan against the existing domain model, sharpens terminology, and updates documentation (CONTEXT.md, ADRs) inline as decisions crystallise. Use when user wants to stress-test a plan against their project's language and documented decisions.
---

> **Crédito:** baseada em [`mattpocock/skills` — `grill-with-docs`](https://github.com/mattpocock/skills) (MIT). Conteúdo praticamente idêntico ao upstream; adicionada apenas a nota de stack Laravel ao final do bloco `<supporting-info>`.

<what-to-do>

Me entreviste impiedosamente sobre cada aspecto deste plano até chegarmos a um entendimento compartilhado. Caminhe por cada ramo da árvore de decisão, resolvendo dependências entre decisões uma de cada vez. Para cada pergunta, ofereça sua resposta recomendada.

Faça as perguntas uma de cada vez, esperando feedback antes de continuar.

Se uma pergunta puder ser respondida explorando o código, explore o código primeiro.

</what-to-do>

<supporting-info>

## Consciência de domínio

Durante a exploração do código, procure também por documentação existente:

### Estrutura de arquivos

A maioria dos repos tem um único contexto:

```
/
├── CONTEXT.md
├── docs/
│   └── adr/
│       ├── 0001-event-sourced-orders.md
│       └── 0002-postgres-for-write-model.md
└── src/
```

Se existir um `CONTEXT-MAP.md` na raiz, o repo tem múltiplos contextos. O mapa aponta para onde cada um vive:

```
/
├── CONTEXT-MAP.md
├── docs/
│   └── adr/                          ← decisões do sistema
├── src/
│   ├── ordering/
│   │   ├── CONTEXT.md
│   │   └── docs/adr/                 ← decisões do contexto
│   └── billing/
│       ├── CONTEXT.md
│       └── docs/adr/
```

Crie arquivos de forma preguiçosa — só quando tiver algo para escrever. Se não existir `CONTEXT.md`, crie um quando o primeiro termo for resolvido. Se não existir `docs/adr/`, crie quando o primeiro ADR for necessário.

## Durante a sessão

### Cobre o glossário

Quando o usuário usar um termo que conflita com a linguagem existente em `CONTEXT.md`, aponte na hora. "Seu glossário define 'cancelamento' como X, mas você parece dizer Y — qual é?"

### Afie linguagem vaga

Quando o usuário usar termos vagos ou sobrecarregados, proponha um termo canônico preciso. "Você está dizendo 'conta' — quer dizer Cliente ou Usuário? São coisas diferentes."

### Discuta cenários concretos

Quando relacionamentos de domínio estiverem sendo discutidos, estresse-os com cenários específicos. Invente cenários que sondam casos extremos e forçam o usuário a ser preciso sobre as fronteiras entre conceitos.

### Cruze com o código

Quando o usuário declarar como algo funciona, verifique se o código concorda. Se encontrar uma contradição, traga à tona: "Seu código cancela Pedidos inteiros, mas você acabou de dizer que cancelamento parcial é possível — qual está certo?"

### Atualize CONTEXT.md inline

Quando um termo for resolvido, atualize `CONTEXT.md` ali mesmo. Não acumule — capture na hora. Use o formato em [CONTEXT-FORMAT.md](./CONTEXT-FORMAT.md).

`CONTEXT.md` deve ser totalmente desprovido de detalhes de implementação. Não trate `CONTEXT.md` como spec, rascunho ou repositório de decisões de implementação. É um glossário e nada mais.

### Ofereça ADRs com parcimônia

Só ofereça criar um ADR quando os três forem verdadeiros:

1. **Difícil de reverter** — o custo de mudar de ideia depois é significativo
2. **Surpreendente sem contexto** — um leitor futuro vai se perguntar "por que fizeram assim?"
3. **Resultado de um trade-off real** — havia alternativas genuínas e você escolheu uma por motivos específicos

Se algum dos três faltar, pule o ADR. Use o formato em [ADR-FORMAT.md](./ADR-FORMAT.md).

## Stack Laravel

Quando o projeto consumidor tiver `AGENTS.md` (gerado por [`laravel/boost`](https://github.com/laravel/boost)), **leia-o antes de começar a entrevista**. Ele lista pacotes e versões em uso (Laravel 12, Livewire 4, Pest 4, Flux UI, etc.) — você precisa conhecer essa stack para fazer perguntas certas.

Termos canônicos da stack Laravel **já existem** e não devem ser redefinidos no `CONTEXT.md` do projeto a menos que o projeto dê a eles um sentido específico:

- **Model**, **Migration**, **Factory**, **Seeder**, **Cast**
- **Form Request**, **Action**, **Policy**, **Gate**, **Job**, **Event**, **Listener**
- **Livewire Component**, **Volt component**, **Computed property**, **Listener**
- **Flux component** (`<flux:input>`, `<flux:button>`, `<flux:modal>`, etc.)

`CONTEXT.md` do projeto Laravel deve focar em conceitos de **domínio** (Proposta, Cliente, Módulo, Feature, Parcela), não nessa estrutura de framework.

</supporting-info>
