---
name: update-roadmap
description: Generate or update docs/roadmap/index.html — a single-file HTML roadmap organized by execution waves (topological levels in the issue dependency DAG), with each issue rendered as a card showing user-facing impact and dependency badges. Use when user says "update roadmap", "atualizar roadmap", "regenerar roadmap", or after /to-issues batches.
---

# Update Roadmap (HTML por ondas)

Gera `docs/roadmap/index.html` standalone. Cada **onda** é um nível topológico no DAG de dependências entre issues — issues numa mesma onda podem ser desenvolvidas em paralelo. Cards exibem **impacto-usuário** (não jargão técnico) + badges das dependências.

Veja [`template.html`](./template.html) para o template renderizado.

## Pré-requisitos

- `gh` CLI autenticado
- Issues com label de roadmap (default: `roadmap`) no repo
- Ideal: issues geradas por `/to-issues` (já têm seção `Bloqueada por`)
- `docs/roadmap/` existe ou pode ser criada

## Processo

### 1. Carregar configuração

`.claude/roadmap-config.json` (criar se não existir, com defaults):

```json
{
  "title": "Roadmap",
  "subtitle": "",
  "label": "roadmap",
  "include_closed": false,
  "max_waves": null,
  "theme": "auto",
  "output_path": "docs/roadmap/index.html"
}
```

Pedir ao usuário para confirmar/preencher `title` e `subtitle` na primeira execução.

### 2. Coletar issues

```bash
gh issue list \
  --label "$LABEL" \
  --state "${INCLUDE_CLOSED:+all}${INCLUDE_CLOSED:-open}" \
  --limit 200 \
  --json number,title,body,labels,milestone,state,url
```

Se `include_closed: true`, recolher fechadas e marcá-las visualmente como entregues (`✅`) na onda em que estavam.

### 3. Extrair dependências declaradas

Para cada issue, parsear o body procurando seção `## Bloqueada por`:

```
## Bloqueada por
- #42
- #45
```

Capturar como dependência declarada. Se a seção diz "Nenhuma" ou está ausente, dependências = [].

### 4. Inferência de dependências adicionais

**Esta é a parte que justifica a skill** — issues do `/to-issues` podem ter dependências implícitas que o autor esqueceu de declarar.

Para cada par `(A, B)` de issues, marcar candidato a dependência se:

- A menciona o nome de um Model/Component/tabela que B cria. Heurística: extrair `\b[A-Z][a-zA-Z]+\b` dos bodies; se A menciona `Comment` e B é o título tipo "Adicionar model `Comment`", há sinal.
- A e B tocam o mesmo arquivo provável. Heurística: extrair `app/<path>/<File>.php` dos bodies (campo "Camadas tocadas" do template `/to-issues`).
- B diz "estende X" e A diz "cria X" / "introduz X".

**Apresentar ao usuário cada candidato individualmente:**

```
🔗 Dependências sugeridas:

1. #18 "Notificar autor de comentário" parece depender de #15 "Adicionar model Comment"
   Razão: #18 menciona "Comment" e #15 cria esse model.
   [a]ceitar / [r]ejeitar / [s]omente esta sessão (não persiste)

2. #21 "E-mail de confirmação" parece depender de #12 "Login com MFA"
   Razão: ambas tocam app/Notifications/AuthMail.php
   [a]ceitar / [r]ejeitar
```

Aceitas → adicionar à lista de dependências da issue. **Não modifica a issue no GitHub** (a skill é read-only no tracker) — só usa para o cálculo de ondas. Salvar em `.claude/inferred-deps.json` para persistir entre execuções e não perguntar de novo.

### 5. Calcular ondas (sort topológico)

```
Onda 1 = { issue : deps(issue) ⊆ ∅ }
Onda N = { issue ∉ ondas anteriores : deps(issue) ⊆ união das ondas anteriores }
```

Implementar Kahn's algorithm. Se durante o processo restarem issues sem caber em nenhuma onda → **ciclo detectado**:

```
❌ Ciclo detectado nas dependências:
  #18 → #21 → #18

Resolva o ciclo (remova uma das dependências) antes de gerar o roadmap.
Issues envolvidas: #18, #21
```

Abortar sem gerar HTML.

### 6. Sintetizar impacto-usuário por issue

Para cada issue, gerar 1–2 frases focadas em **valor para quem usa o produto**.

**Bom:** "Admin pode revogar convites pendentes em vez de esperar expirarem."
**Ruim:** "Adiciona método `revoke()` em `InviteAction` e botão na view."

Heurística:
1. Procurar seção `## What to build` no body — geralmente já está em linguagem de comportamento.
2. Se não existir, procurar primeiros parágrafos do body excluindo headers técnicos.
3. Se nada útil → perguntar ao usuário antes de inventar:

```
❓ Não consegui inferir o impacto-usuário de #34 "Refatorar middleware auth".
   Como descreveria em 1-2 frases o que muda para quem usa o produto?
   (ou pular — issue aparecerá com [SEM IMPACTO USUÁRIO])
```

### 7. Renderizar HTML

Carregar [`template.html`](./template.html) e interpolar:

- `{{TITLE}}` — título do roadmap
- `{{SUBTITLE}}` — subtítulo
- `{{GENERATED_AT}}` — data de geração
- `{{TOTAL_ISSUES}}` — contagem total
- `{{WAVES_HTML}}` — HTML das ondas, gerado dinamicamente

Para cada onda, gerar:

```html
<section class="wave" id="wave-{N}">
  <header class="wave-header">
    <h2>Onda {N} — {nome opcional}</h2>
    <span class="wave-count">{count} issues paralelas</span>
  </header>
  <div class="wave-grid">
    {cards}
  </div>
</section>
```

Para cada card:

```html
<article class="card" id="issue-{number}">
  <header class="card-header">
    <span class="issue-number">#{number}</span>
    <h3 class="issue-title">{title}</h3>
    <span class="issue-label label-{label}">{label}</span>
  </header>
  <p class="user-impact">{impact}</p>
  <footer class="card-footer">
    {if has_deps}
      <span class="deps-label">Depende de:</span>
      {for each dep} <a href="#issue-{dep}" class="badge">#{dep}</a> {/for}
    {else}
      <span class="no-deps">sem dependências</span>
    {/if}
  </footer>
</article>
```

Escapar HTML em todos os valores interpolados (XSS guard).

### 8. Diff e gravar

Se já existe `index.html`:

```
📊 Diff vs versão anterior:

  Onda 1: 3 → 4 issues (+#28 movida da Onda 2)
  Onda 2: 5 → 4 issues (-#28)
  Onda 3: 2 → 3 issues (+#34 nova)

Aceitar e sobrescrever? [s/n]
```

Se aceito, gravar e sugerir:

```bash
git add docs/roadmap/index.html
git commit -m "docs: regenerate roadmap"
open docs/roadmap/index.html  # macOS
```

### 9. Validação

Após gravar, executar checks rápidos:
- HTML é válido (parseável) — se possível, validar com tidy/htmlhint se disponível
- Todos os links âncora têm destino correspondente
- Sem `{{...}}` não-substituído no output (sanity check)

Se algum check falha, avisar o usuário.

## Configuração de nomes de onda (opcional)

`.claude/roadmap-config.json` aceita nomes humanos para ondas:

```json
{
  "wave_names": {
    "1": "Fundação",
    "2": "Colaboração",
    "3": "Pagamentos"
  }
}
```

Sem isso, ondas aparecem só como "Onda 1", "Onda 2", etc.

## Re-rodar com frequência

A skill é idempotente (mesma entrada → mesmo HTML). Rodar:
- Após cada batch de `/to-issues`
- Após cada PR mergeada que fecha issue do roadmap
- Semanalmente como cron `/loop` se quiser (ver skill `loop` upstream)
