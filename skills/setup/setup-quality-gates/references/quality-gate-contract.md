# Quality Gate Contract

## Regra de ouro

Cada PR pode adicionar codigo, mas nao pode piorar nenhuma metrica versionada no baseline:

- cobertura nao pode cair;
- mutation score nao pode cair quando houver relatorio;
- mutantes sobreviventes nao podem subir quando houver relatorio;
- complexidade ciclomatica maxima nao pode subir;
- quantidade de unidades complexas nao pode subir;
- duplicacao nao pode subir;
- ciclos de dependencia entre classes `App\...` nao podem subir;
- findings de seguranca nao podem subir;
- total de arquivos grandes nao pode subir;
- arquivo que ja estava grande nao pode crescer;
- violacoes agregadas de lint/static analysis nao podem subir quando relatórios existirem;
- arquivos novos acima do limite falham imediatamente.

Use `composer quality:ratchet` para melhorar a baseline no sentido correto. Ele so grava metricas melhores e recusa rodar se o gate estiver falhando. Use `composer quality:baseline` apenas para uma recalibracao explicita.

## Limites padrao

Os limites sao intencionalmente conservadores para Laravel 12+ com Livewire 4:

| Padrao | Limite |
|---|---:|
| `app/Livewire/**/*.php` | 350 linhas |
| `resources/views/livewire/**/*.blade.php` | 350 linhas |
| `resources/views/**/*.blade.php` | 300 linhas |
| `app/Actions/**/*.php` | 250 linhas |
| `app/**/*.php` | 400 linhas |
| `routes/**/*.php` | 250 linhas |
| `database/**/*.php` | 300 linhas |
| `tests/**/*.php` | 500 linhas |
| `resources/js/**/*.{js,ts,vue}` | 300 linhas |
| `resources/css/**/*.css` | 400 linhas |

Se um projeto ja possui arquivos maiores, eles entram no baseline. A catraca barra crescimento, nao exige refatoracao imediata.

## Relatorios consumidos

O script procura automaticamente:

- cobertura: `build/logs/clover.xml`, `coverage/clover.xml`, `coverage/lcov.info`, `build/logs/lcov.info`;
- mutation testing: `build/logs/infection-summary.json`, `build/logs/infection.json`, `infection-summary.json`;
- linters: `build/logs/eslint.json`, `storage/app/eslint.json`, `build/logs/phpstan.json`, `build/logs/pint.json`.
- seguranca: `build/logs/composer-audit.json`, `build/logs/npm-audit.json` e segredos obvios no codigo.

Ferramentas externas sao bem-vindas. O contrato do gate e simples: gerar relatorios antes de `composer quality:gate`.

## CI recomendado

Em GitHub Actions, rode:

1. `composer install`
2. `npm ci` se houver `package-lock.json`
3. `npm run build --if-present`
4. `composer audit --locked --format=json > build/logs/composer-audit.json || true`
5. `npm audit --audit-level=high --json > build/logs/npm-audit.json || true`
6. `vendor/bin/pint --test`
7. `php artisan test --coverage-clover=build/logs/clover.xml`
8. `composer quality:gate`
9. upload de `.ai/quality-gates/*` e `build/logs/*`

Se o projeto nao consegue gerar cobertura ainda, o gate continua medindo as outras metricas. Quando Clover/LCOV aparecer pela primeira vez, atualize o baseline num PR proprio.

## Gates avancados citados nos conteudos

Alguns riscos precisam de teste ou instrumentacao propria. Quando o usuario pedir para endurecer ainda mais o projeto, implemente como PRs separados:

- N+1: em Laravel, habilitar `Model::preventLazyLoading()` em ambiente local/teste e adicionar teste para telas/queries criticas usando contagem de queries.
- Race condition: criar testes concorrentes ou property-based tests para invariantes como saldo nunca negativo, reserva unica, contador consistente.
- Memory leak: adicionar profiling em rotas/jobs long-running e budget simples de memoria para comandos ou workers.
- Arquitetura: adicionar Deptrac ou PHPStan rules quando o projeto ja tiver camadas bem nomeadas. Nao inventar camadas genericas sem entender o dominio.
- Mutation testing: adicionar Infection PHP como etapa opcional primeiro em escopo pequeno; so depois colocar threshold global.
