---
name: setup-quality-gates
description: Install ratcheting quality gates in Laravel 12+ projects using Livewire 4, Alpine.js, and Tailwind CSS v4. Use when setting up AI-safe code quality automation, creating a baseline for coverage/mutation/complexity/duplication/security/dependency/file-size regressions, adding GitHub Actions quality gates, or making agents babysit PR quality instead of relying on manual review.
---

# Setup Quality Gates

Instale uma catraca de qualidade no projeto Laravel: o estado atual vira baseline e cada PR pode empatar ou melhorar, nunca piorar. A skill cria scripts locais, baseline JSON, comandos Composer/NPM e workflow GitHub Actions quando o projeto usa GitHub.

## Processo

### 1. Detectar o projeto

Verifique em paralelo:

```bash
composer.json
package.json
php artisan --version
composer show laravel/framework livewire/livewire pestphp/pest laravel/pint
git remote -v
ls .github/workflows
```

Pare se não houver `composer.json` ou se o projeto claramente não for Laravel. Para Laravel 12+ com Livewire 4, siga em frente. Para Livewire 3 ou ausente, instale mesmo assim se o usuário quer o gate para o backend, mas registre a divergência.

### 2. Instalar os arquivos

Execute o instalador bundled a partir da raiz do projeto alvo:

```bash
php <skill-dir>/scripts/install-quality-gates.php .
```

Use `--force` apenas quando o usuário aceitar sobrescrever arquivos gerados anteriormente:

```bash
php <skill-dir>/scripts/install-quality-gates.php . --force
```

O instalador cria/atualiza:

- `tools/quality/quality-gate.php`
- `.ai/quality-gates/baseline.json`
- `.ai/quality-gates/metrics-summary.json`
- `.ai/quality-gates/summary.md`
- `.github/workflows/quality-gates.yml` se não existir
- `composer.json` com scripts `quality:baseline`, `quality:gate`, `quality:ratchet`, `quality:summary`
- `package.json` com scripts auxiliares quando existir

### 3. Conferir o baseline

O baseline inicial representa a qualidade atual do projeto. Leia:

```bash
cat .ai/quality-gates/baseline.json
cat .ai/quality-gates/summary.md
```

Explique ao usuário as métricas relevantes:

- `coverage_percent`: cobertura a partir de Clover ou LCOV, quando disponível.
- `mutation_score` e `escaped_mutants`: mutation testing quando houver relatório do Infection.
- `duplicate_percent`: duplicação aproximada por blocos normalizados em PHP, Blade, JS/TS e CSS.
- `max_cyclomatic_complexity` e `complex_units`: funções/arquivos com muitos caminhos internos.
- `dependency_cycles`: ciclos simples entre classes `App\...`.
- `security_findings`: advisories de Composer/NPM quando houver relatório e segredos óbvios no código.
- `oversized_files`: arquivos acima dos limites definidos para Laravel, Livewire, Blade, JS/TS e CSS.
- `oversized_file_lines`: mapa de arquivos grandes; arquivo já grande não pode crescer.
- `lint_violations`: total agregado de relatórios JSON conhecidos, quando existirem.

### 4. Rodar o gate local

Depois da instalação:

```bash
composer quality:gate
```

Para melhorar a baseline sem risco de afrouxar o gate:

```bash
composer quality:ratchet
```

Se o projeto tiver Pest/Pint, rode também:

```bash
vendor/bin/pint --test
php artisan test
```

Se houver suporte a cobertura, gere Clover antes do gate:

```bash
php artisan test --coverage-clover=build/logs/clover.xml
composer quality:gate
```

### 5. Usar em PRs com agentes

Ao abrir PR, instrua o agente a fazer babysitting:

1. Abrir/atualizar o PR.
2. Aguardar CI.
3. Ler `.ai/quality-gates/summary.md` e artefatos do workflow.
4. Corrigir regressões sem relaxar o baseline.
5. Resolver conversas do GitHub somente depois de endereçar cada comentário.

Nunca atualize `.ai/quality-gates/baseline.json` para passar um PR de feature. Atualize baseline apenas em PR explícito de melhoria ou recalibração:

```bash
composer quality:baseline
```

Prefira `composer quality:ratchet` quando o objetivo for só apertar a catraca depois de uma melhoria.

## Ajustes

Leia [`references/quality-gate-contract.md`](references/quality-gate-contract.md) quando precisar ajustar limites, adaptar CI, ou explicar a regra da catraca para outro agente.
