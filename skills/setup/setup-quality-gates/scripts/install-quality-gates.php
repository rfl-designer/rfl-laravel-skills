#!/usr/bin/env php
<?php

declare(strict_types=1);

$args = array_values(array_slice($argv, 1));
$target = $args[0] ?? getcwd();
$force = in_array('--force', $args, true);
$skipWorkflow = in_array('--no-workflow', $args, true);

$root = realpath($target);

if ($root === false || ! is_dir($root)) {
    fwrite(STDERR, "Target directory not found: {$target}\n");
    exit(1);
}

$composerPath = $root.'/composer.json';

if (! is_file($composerPath)) {
    fwrite(STDERR, "composer.json not found. Run this from a Laravel project root.\n");
    exit(1);
}

$composer = readJson($composerPath);
$requires = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

if (! array_key_exists('laravel/framework', $requires)) {
    fwrite(STDERR, "laravel/framework was not found in composer.json. Refusing to install in a non-Laravel project.\n");
    exit(1);
}

ensureDirectory($root.'/tools/quality');
ensureDirectory($root.'/.ai/quality-gates');

writeFile($root.'/tools/quality/quality-gate.php', qualityGateScript(), $force);
updateComposerScripts($composerPath);
updatePackageScripts($root.'/package.json');

if (! $skipWorkflow) {
    ensureDirectory($root.'/.github/workflows');
    writeFile($root.'/.github/workflows/quality-gates.yml', workflowYaml(), $force);
}

passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg($root.'/tools/quality/quality-gate.php').' --init-baseline', $exitCode);

if ($exitCode !== 0) {
    exit($exitCode);
}

echo "\nQuality gates installed.\n";
echo "- Baseline: .ai/quality-gates/baseline.json\n";
echo "- Gate: composer quality:gate\n";
echo "- Improve baseline safely: composer quality:ratchet\n";
echo "- Rebaseline explicitly: composer quality:baseline\n";

function readJson(string $path): array
{
    $json = json_decode((string) file_get_contents($path), true);

    if (! is_array($json)) {
        fwrite(STDERR, "Invalid JSON: {$path}\n");
        exit(1);
    }

    return $json;
}

function writeJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function writeFile(string $path, string $contents, bool $force): void
{
    if (is_file($path) && ! $force) {
        echo "Kept existing {$path}\n";

        return;
    }

    file_put_contents($path, $contents);
    echo "Wrote {$path}\n";
}

function updateComposerScripts(string $path): void
{
    $composer = readJson($path);
    $composer['scripts'] ??= [];
    $composer['scripts']['quality:baseline'] = 'php tools/quality/quality-gate.php --init-baseline';
    $composer['scripts']['quality:gate'] = 'php tools/quality/quality-gate.php';
    $composer['scripts']['quality:ratchet'] = 'php tools/quality/quality-gate.php --ratchet';
    $composer['scripts']['quality:summary'] = 'php tools/quality/quality-gate.php --write-summary';

    writeJson($path, $composer);
    echo "Updated {$path}\n";
}

function updatePackageScripts(string $path): void
{
    if (! is_file($path)) {
        return;
    }

    $package = readJson($path);
    $package['scripts'] ??= [];
    $package['scripts']['quality:frontend'] ??= 'npm run lint --if-present';
    $package['scripts']['quality:build'] ??= 'npm run build --if-present';

    writeJson($path, $package);
    echo "Updated {$path}\n";
}

function workflowYaml(): string
{
    return <<<'YAML'
name: Quality Gates

on:
  pull_request:
  push:
    branches: [main]

jobs:
  quality:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          coverage: pcov
          tools: composer:v2

      - uses: actions/setup-node@v4
        if: ${{ hashFiles('package-lock.json') != '' }}
        with:
          node-version: 22
          cache: npm

      - name: Install PHP dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Install frontend dependencies
        if: ${{ hashFiles('package-lock.json') != '' }}
        run: npm ci

      - name: Build frontend
        if: ${{ hashFiles('package.json') != '' }}
        run: npm run build --if-present

      - name: Pint
        run: vendor/bin/pint --test

      - name: Prepare quality reports directory
        run: mkdir -p build/logs

      - name: Composer audit report
        if: ${{ hashFiles('composer.lock') != '' }}
        run: composer audit --locked --format=json > build/logs/composer-audit.json || true

      - name: NPM audit report
        if: ${{ hashFiles('package-lock.json') != '' }}
        run: npm audit --audit-level=high --json > build/logs/npm-audit.json || true

      - name: Tests with coverage
        env:
          XDEBUG_MODE: coverage
        run: php artisan test --coverage-clover=build/logs/clover.xml

      - name: Quality gate
        run: composer quality:gate

      - name: Job summary
        if: always()
        run: cat .ai/quality-gates/summary.md >> "$GITHUB_STEP_SUMMARY"

      - name: Upload quality artifacts
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: quality-gates
          path: |
            .ai/quality-gates/*
            build/logs/*
          if-no-files-found: ignore
YAML;
}

function qualityGateScript(): string
{
    return <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$baselinePath = $root.'/.ai/quality-gates/baseline.json';
$metricsPath = $root.'/.ai/quality-gates/metrics-summary.json';
$summaryPath = $root.'/.ai/quality-gates/summary.md';
$initBaseline = in_array('--init-baseline', $argv, true);
$writeSummaryOnly = in_array('--write-summary', $argv, true);
$ratchet = in_array('--ratchet', $argv, true);

ensureDirectory(dirname($baselinePath));

$metrics = collectMetrics($root);
writeJson($metricsPath, $metrics);

if ($initBaseline || ! is_file($baselinePath)) {
    writeJson($baselinePath, baselineFromMetrics($metrics));
    writeSummary($summaryPath, $metrics, baselineFromMetrics($metrics), []);
    echo "Quality baseline written to .ai/quality-gates/baseline.json\n";
    exit(0);
}

$baseline = readJson($baselinePath);
$failures = compareMetrics($metrics, $baseline);

if ($ratchet) {
    if ($failures !== []) {
        writeSummary($summaryPath, $metrics, $baseline, $failures);
        fwrite(STDERR, "Cannot ratchet baseline while quality gate is failing.\n");
        exit(1);
    }

    $ratcheted = ratchetBaseline($metrics, $baseline);
    writeJson($baselinePath, $ratcheted);
    writeSummary($summaryPath, $metrics, $ratcheted, []);
    echo "Quality baseline ratcheted. Only improved metrics were tightened.\n";
    exit(0);
}

writeSummary($summaryPath, $metrics, $baseline, $failures);

if ($writeSummaryOnly) {
    echo "Quality summary written to .ai/quality-gates/summary.md\n";
    exit(0);
}

if ($failures !== []) {
    fwrite(STDERR, "Quality gate failed:\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, "- {$failure}\n");
    }

    fwrite(STDERR, "\nSee .ai/quality-gates/summary.md\n");
    exit(1);
}

echo "Quality gate passed.\n";

function collectMetrics(string $root): array
{
    $files = projectFiles($root);
    $lineMetrics = lineMetrics($root, $files);
    $coverage = coveragePercent($root);
    $lintViolations = lintViolations($root);
    $duplicates = duplicateMetrics($root, $files);
    $complexity = complexityMetrics($root, $files);
    $dependency = dependencyMetrics($root, $files);
    $security = securityMetrics($root, $files);
    $mutation = mutationMetrics($root);

    return [
        'generated_at' => gmdate('c'),
        'coverage_percent' => $coverage,
        'mutation_score' => $mutation['mutation_score'],
        'escaped_mutants' => $mutation['escaped_mutants'],
        'duplicate_percent' => $duplicates['duplicate_percent'],
        'duplicate_blocks' => $duplicates['duplicate_blocks'],
        'max_cyclomatic_complexity' => $complexity['max_cyclomatic_complexity'],
        'complex_units' => $complexity['complex_units'],
        'dependency_cycles' => $dependency['dependency_cycles'],
        'lint_violations' => $lintViolations,
        'security_findings' => $security['security_findings'],
        'oversized_files' => count($lineMetrics['oversized_file_lines']),
        'oversized_file_lines' => $lineMetrics['oversized_file_lines'],
        'complexity_hotspots' => $complexity['complexity_hotspots'],
        'dependency_cycle_samples' => $dependency['dependency_cycle_samples'],
        'security_samples' => $security['security_samples'],
        'total_files_scanned' => count($files),
    ];
}

function baselineFromMetrics(array $metrics): array
{
    return [
        'coverage_percent' => $metrics['coverage_percent'],
        'mutation_score' => $metrics['mutation_score'],
        'escaped_mutants' => $metrics['escaped_mutants'],
        'duplicate_percent' => $metrics['duplicate_percent'],
        'duplicate_blocks' => $metrics['duplicate_blocks'],
        'max_cyclomatic_complexity' => $metrics['max_cyclomatic_complexity'],
        'complex_units' => $metrics['complex_units'],
        'dependency_cycles' => $metrics['dependency_cycles'],
        'lint_violations' => $metrics['lint_violations'],
        'security_findings' => $metrics['security_findings'],
        'oversized_files' => $metrics['oversized_files'],
        'oversized_file_lines' => $metrics['oversized_file_lines'],
    ];
}

function ratchetBaseline(array $metrics, array $baseline): array
{
    $ratcheted = $baseline;

    if (is_numeric($metrics['coverage_percent'] ?? null)) {
        $currentCoverage = (float) $metrics['coverage_percent'];
        $baselineCoverage = is_numeric($baseline['coverage_percent'] ?? null) ? (float) $baseline['coverage_percent'] : null;
        $ratcheted['coverage_percent'] = $baselineCoverage === null ? $currentCoverage : max($baselineCoverage, $currentCoverage);
    }

    if (is_numeric($metrics['mutation_score'] ?? null)) {
        $currentMutationScore = (float) $metrics['mutation_score'];
        $baselineMutationScore = is_numeric($baseline['mutation_score'] ?? null) ? (float) $baseline['mutation_score'] : null;
        $ratcheted['mutation_score'] = $baselineMutationScore === null ? $currentMutationScore : max($baselineMutationScore, $currentMutationScore);
    }

    foreach (lowerIsBetterMetrics() as $metric) {
        if (! array_key_exists($metric, $metrics)) {
            continue;
        }

        if (! array_key_exists($metric, $baseline) || $baseline[$metric] === null) {
            $ratcheted[$metric] = $metrics[$metric];

            continue;
        }

        $ratcheted[$metric] = min($baseline[$metric], $metrics[$metric]);
    }

    $ratchetedFiles = [];

    foreach (($baseline['oversized_file_lines'] ?? []) as $file => $baselineLines) {
        if (! isset($metrics['oversized_file_lines'][$file])) {
            continue;
        }

        $ratchetedFiles[$file] = min($baselineLines, $metrics['oversized_file_lines'][$file]);
    }

    $ratcheted['oversized_file_lines'] = $ratchetedFiles;

    return $ratcheted;
}

function compareMetrics(array $metrics, array $baseline): array
{
    $failures = [];

    if (is_numeric($baseline['coverage_percent'] ?? null) && is_numeric($metrics['coverage_percent'] ?? null)) {
        if (round((float) $metrics['coverage_percent'], 2) < round((float) $baseline['coverage_percent'], 2)) {
            $failures[] = sprintf('Coverage dropped from %.2f%% to %.2f%%.', $baseline['coverage_percent'], $metrics['coverage_percent']);
        }
    }

    if (is_numeric($baseline['mutation_score'] ?? null) && is_numeric($metrics['mutation_score'] ?? null)) {
        if (round((float) $metrics['mutation_score'], 2) < round((float) $baseline['mutation_score'], 2)) {
            $failures[] = sprintf('Mutation score dropped from %.2f%% to %.2f%%.', $baseline['mutation_score'], $metrics['mutation_score']);
        }
    }

    foreach (lowerIsBetterMetrics() as $metric) {
        if (! array_key_exists($metric, $baseline) || $baseline[$metric] === null || ($metrics[$metric] ?? null) === null) {
            continue;
        }

        if ($metrics[$metric] > $baseline[$metric]) {
            $failures[] = "{$metric} increased from {$baseline[$metric]} to {$metrics[$metric]}.";
        }
    }

    $baselineFiles = $baseline['oversized_file_lines'] ?? [];

    foreach (($metrics['oversized_file_lines'] ?? []) as $file => $lines) {
        $baselineLines = $baselineFiles[$file] ?? null;

        if ($baselineLines === null) {
            $failures[] = "New oversized file: {$file} ({$lines} lines).";

            continue;
        }

        if ($lines > $baselineLines) {
            $failures[] = "Oversized file grew: {$file} ({$baselineLines} -> {$lines} lines).";
        }
    }

    return $failures;
}

function lowerIsBetterMetrics(): array
{
    return [
        'duplicate_percent',
        'duplicate_blocks',
        'escaped_mutants',
        'max_cyclomatic_complexity',
        'complex_units',
        'dependency_cycles',
        'lint_violations',
        'security_findings',
        'oversized_files',
    ];
}

function projectFiles(string $root): array
{
    $include = [
        'app',
        'bootstrap',
        'config',
        'database',
        'routes',
        'resources/views',
        'resources/js',
        'resources/css',
        'tests',
    ];
    $extensions = ['php', 'blade.php', 'js', 'ts', 'vue', 'css'];
    $files = [];

    foreach ($include as $directory) {
        $path = $root.'/'.$directory;

        if (! is_dir($path)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = relativePath($root, $file->getPathname());

            if (str_contains($relative, '/vendor/') || str_contains($relative, '/node_modules/')) {
                continue;
            }

            foreach ($extensions as $extension) {
                if (str_ends_with($relative, '.'.$extension)) {
                    $files[] = $relative;

                    break;
                }
            }
        }
    }

    sort($files);

    return $files;
}

function lineMetrics(string $root, array $files): array
{
    $oversized = [];

    foreach ($files as $file) {
        $lines = countLines($root.'/'.$file);
        $limit = lineLimit($file);

        if ($lines > $limit) {
            $oversized[$file] = $lines;
        }
    }

    ksort($oversized);

    return ['oversized_file_lines' => $oversized];
}

function lineLimit(string $file): int
{
    $rules = [
        '#^app/Livewire/.+\.php$#' => 350,
        '#^resources/views/livewire/.+\.blade\.php$#' => 350,
        '#^resources/views/.+\.blade\.php$#' => 300,
        '#^app/Actions/.+\.php$#' => 250,
        '#^app/.+\.php$#' => 400,
        '#^routes/.+\.php$#' => 250,
        '#^database/.+\.php$#' => 300,
        '#^tests/.+\.php$#' => 500,
        '#^resources/js/.+\.(js|ts|vue)$#' => 300,
        '#^resources/css/.+\.css$#' => 400,
    ];

    foreach ($rules as $pattern => $limit) {
        if (preg_match($pattern, $file) === 1) {
            return $limit;
        }
    }

    return 400;
}

function duplicateMetrics(string $root, array $files): array
{
    $window = 6;
    $seen = [];
    $duplicateBlocks = 0;
    $duplicatedLines = 0;
    $totalLines = 0;

    foreach ($files as $file) {
        $lines = normalizedLines($root.'/'.$file);
        $totalLines += count($lines);

        if (count($lines) < $window) {
            continue;
        }

        for ($i = 0; $i <= count($lines) - $window; $i++) {
            $chunk = array_slice($lines, $i, $window);
            $hash = sha1(implode("\n", $chunk));

            if (isset($seen[$hash])) {
                $duplicateBlocks++;
                $duplicatedLines += $window;
            } else {
                $seen[$hash] = "{$file}:{$i}";
            }
        }
    }

    return [
        'duplicate_percent' => $totalLines === 0 ? 0.0 : round(min(100, ($duplicatedLines / $totalLines) * 100), 2),
        'duplicate_blocks' => $duplicateBlocks,
    ];
}

function normalizedLines(string $path): array
{
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    $normalized = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '//') || str_starts_with($line, '#') || str_starts_with($line, '*')) {
            continue;
        }

        $line = preg_replace('/\s+/', ' ', $line) ?? $line;
        $line = preg_replace('/(["\']).*?\1/', 'STR', $line) ?? $line;
        $line = preg_replace('/\b\d+(\.\d+)?\b/', 'NUM', $line) ?? $line;
        $normalized[] = $line;
    }

    return $normalized;
}

function complexityMetrics(string $root, array $files): array
{
    $max = 0;
    $complexUnits = 0;
    $hotspots = [];

    foreach ($files as $file) {
        if (! preg_match('/\.(php|blade\.php|js|ts|vue)$/', $file)) {
            continue;
        }

        foreach (complexityUnits($root.'/'.$file, $file) as $unit => $score) {
            $max = max($max, $score);

            if ($score > 12) {
                $complexUnits++;
                $hotspots[] = ['unit' => $unit, 'score' => $score];
            }
        }
    }

    usort($hotspots, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

    return [
        'max_cyclomatic_complexity' => $max,
        'complex_units' => $complexUnits,
        'complexity_hotspots' => array_slice($hotspots, 0, 10),
    ];
}

function complexityUnits(string $path, string $relative): array
{
    $contents = (string) file_get_contents($path);
    $lines = preg_split('/\R/', $contents) ?: [];
    $units = [];
    $current = $relative;
    $score = 1;

    foreach ($lines as $index => $line) {
        if (preg_match('/\bfunction\s+([A-Za-z0-9_]+)\s*\(/', $line, $match) === 1) {
            $units[$current] = $score;
            $current = $relative.'::'.$match[1].'()';
            $score = 1;
        }

        $score += preg_match_all('/\b(if|elseif|for|foreach|while|case|catch|match)\b/', $line);
        $score += preg_match_all('/(&&|\|\||\?\?)/', $line);
        $score += preg_match_all('/@(if|elseif|foreach|forelse|for|while|switch|case)\b/', $line);
    }

    $units[$current] = $score;

    return array_filter($units, fn (int $value): bool => $value > 1);
}

function dependencyMetrics(string $root, array $files): array
{
    $classes = [];
    $edges = [];

    foreach ($files as $file) {
        if (! str_starts_with($file, 'app/') || ! str_ends_with($file, '.php')) {
            continue;
        }

        $contents = (string) file_get_contents($root.'/'.$file);
        $class = phpClassName($contents);

        if ($class === null) {
            continue;
        }

        $classes[$class] = true;
        $edges[$class] = phpDependencies($contents);
    }

    $cycles = [];

    foreach (array_keys($classes) as $class) {
        findCycles($class, $class, $edges, [], $cycles);
    }

    $unique = [];

    foreach ($cycles as $cycle) {
        sort($cycle);
        $unique[implode('|', $cycle)] = $cycle;
    }

    return [
        'dependency_cycles' => count($unique),
        'dependency_cycle_samples' => array_slice(array_values($unique), 0, 5),
    ];
}

function phpClassName(string $contents): ?string
{
    if (preg_match('/namespace\s+([^;]+);/', $contents, $namespace) !== 1) {
        return null;
    }

    if (preg_match('/\b(class|interface|trait|enum)\s+([A-Za-z0-9_]+)/', $contents, $class) !== 1) {
        return null;
    }

    return trim($namespace[1]).'\\'.$class[2];
}

function phpDependencies(string $contents): array
{
    $dependencies = [];

    if (preg_match_all('/^use\s+(App\\\\[^;{]+);/m', $contents, $matches) > 0) {
        foreach ($matches[1] as $dependency) {
            $dependencies[] = trim($dependency);
        }
    }

    if (preg_match_all('/\\\\?(App\\\\[A-Za-z0-9_\\\\]+)/', $contents, $matches) > 0) {
        foreach ($matches[1] as $dependency) {
            $dependencies[] = trim($dependency, '\\');
        }
    }

    return array_values(array_unique($dependencies));
}

function findCycles(string $start, string $current, array $edges, array $path, array &$cycles): void
{
    if (in_array($current, $path, true)) {
        return;
    }

    $path[] = $current;

    foreach (($edges[$current] ?? []) as $next) {
        if ($next === $start && count($path) > 1) {
            $cycles[] = $path;

            continue;
        }

        if (! isset($edges[$next])) {
            continue;
        }

        findCycles($start, $next, $edges, $path, $cycles);
    }
}

function securityMetrics(string $root, array $files): array
{
    $samples = [];
    $findings = 0;

    foreach (['build/logs/composer-audit.json', 'build/logs/npm-audit.json'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $json = json_decode((string) file_get_contents($full), true);

        if (! is_array($json)) {
            continue;
        }

        if (isset($json['advisories']) && is_array($json['advisories'])) {
            foreach ($json['advisories'] as $package => $advisories) {
                $count = is_array($advisories) ? count($advisories) : 1;
                $findings += $count;
                $samples[] = "{$path}: {$package} has {$count} advisory item(s)";
            }
        }

        if (isset($json['metadata']['vulnerabilities']) && is_array($json['metadata']['vulnerabilities'])) {
            foreach (['critical', 'high'] as $level) {
                $count = (int) ($json['metadata']['vulnerabilities'][$level] ?? 0);
                $findings += $count;

                if ($count > 0) {
                    $samples[] = "{$path}: {$count} {$level} npm vulnerability item(s)";
                }
            }
        }
    }

    foreach ($files as $file) {
        if (preg_match('/\.(php|blade\.php|js|ts|vue|css)$/', $file) !== 1) {
            continue;
        }

        foreach (secretFindings($root.'/'.$file) as $finding) {
            $findings++;
            $samples[] = "{$file}: {$finding}";
        }
    }

    return [
        'security_findings' => $findings,
        'security_samples' => array_slice($samples, 0, 10),
    ];
}

function secretFindings(string $path): array
{
    $contents = (string) file_get_contents($path);
    $patterns = [
        'private key' => '/-----BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY-----/',
        'aws access key' => '/AKIA[0-9A-Z]{16}/',
        'github token' => '/gh[pousr]_[A-Za-z0-9_]{36,}/',
        'stripe secret key' => '/sk_(live|test)_[A-Za-z0-9]{20,}/',
    ];
    $findings = [];

    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $contents) === 1) {
            $findings[] = $label;
        }
    }

    return $findings;
}

function mutationMetrics(string $root): array
{
    foreach (['build/logs/infection-summary.json', 'build/logs/infection.json', 'infection-summary.json'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $json = json_decode((string) file_get_contents($full), true);

        if (! is_array($json)) {
            continue;
        }

        $score = firstNumericValue($json, ['mutationScoreIndicator', 'mutation_score', 'msi']);
        $escaped = firstNumericValue($json, ['escapedMutants', 'escaped_mutants', 'survived']);

        if ($score !== null || $escaped !== null) {
            return [
                'mutation_score' => $score,
                'escaped_mutants' => $escaped,
            ];
        }
    }

    return [
        'mutation_score' => null,
        'escaped_mutants' => null,
    ];
}

function firstNumericValue(array $data, array $keys): int|float|null
{
    foreach ($keys as $key) {
        if (isset($data[$key]) && is_numeric($data[$key])) {
            return $data[$key] + 0;
        }
    }

    foreach ($data as $value) {
        if (is_array($value)) {
            $found = firstNumericValue($value, $keys);

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

function coveragePercent(string $root): ?float
{
    foreach (['build/logs/clover.xml', 'coverage/clover.xml'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $xml = @simplexml_load_file($full);

        if ($xml === false) {
            continue;
        }

        $metrics = $xml->xpath('//project/metrics');
        $metrics = $metrics[0] ?? null;

        if ($metrics === null) {
            continue;
        }

        $elements = (int) $metrics['elements'];
        $covered = (int) $metrics['coveredelements'];

        return $elements === 0 ? null : round(($covered / $elements) * 100, 2);
    }

    foreach (['coverage/lcov.info', 'build/logs/lcov.info'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $found = 0;
        $hit = 0;

        foreach (file($full, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, 'LF:')) {
                $found += (int) substr($line, 3);
            }

            if (str_starts_with($line, 'LH:')) {
                $hit += (int) substr($line, 3);
            }
        }

        return $found === 0 ? null : round(($hit / $found) * 100, 2);
    }

    return null;
}

function lintViolations(string $root): int
{
    $total = 0;

    foreach (['build/logs/eslint.json', 'storage/app/eslint.json'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $json = json_decode((string) file_get_contents($full), true);

        if (! is_array($json)) {
            continue;
        }

        foreach ($json as $entry) {
            $total += count($entry['messages'] ?? []);
        }
    }

    foreach (['build/logs/phpstan.json', 'build/logs/pint.json'] as $path) {
        $full = $root.'/'.$path;

        if (! is_file($full)) {
            continue;
        }

        $json = json_decode((string) file_get_contents($full), true);

        if (! is_array($json)) {
            continue;
        }

        if (isset($json['totals']['errors']) && is_int($json['totals']['errors'])) {
            $total += $json['totals']['errors'];

            continue;
        }

        foreach (($json['files'] ?? []) as $file) {
            $total += count($file['messages'] ?? []);
        }
    }

    return $total;
}

function writeSummary(string $path, array $metrics, array $baseline, array $failures): void
{
    $status = $failures === [] ? 'PASS' : 'FAIL';
    $lines = [
        "# Quality Gate: {$status}",
        '',
        '| Metric | Baseline | Current |',
        '|---|---:|---:|',
    ];

    foreach (['coverage_percent', 'mutation_score', 'escaped_mutants', 'duplicate_percent', 'duplicate_blocks', 'max_cyclomatic_complexity', 'complex_units', 'dependency_cycles', 'lint_violations', 'security_findings', 'oversized_files'] as $metric) {
        $lines[] = '| `'.$metric.'` | '.formatMetric($baseline[$metric] ?? null).' | '.formatMetric($metrics[$metric] ?? null).' |';
    }

    if ($failures !== []) {
        $lines[] = '';
        $lines[] = '## Failures';

        foreach ($failures as $failure) {
            $lines[] = '- '.$failure;
        }
    }

    $oversized = $metrics['oversized_file_lines'] ?? [];

    if ($oversized !== []) {
        $lines[] = '';
        $lines[] = '## Oversized Files';

        foreach ($oversized as $file => $count) {
            $lines[] = "- `{$file}`: {$count} lines";
        }
    }

    foreach (($metrics['complexity_hotspots'] ?? []) as $hotspot) {
        $complexityLines[] = '- `'.$hotspot['unit'].'`: '.$hotspot['score'];
    }

    if (($complexityLines ?? []) !== []) {
        $lines[] = '';
        $lines[] = '## Complexity Hotspots';
        array_push($lines, ...$complexityLines);
    }

    if (($metrics['dependency_cycle_samples'] ?? []) !== []) {
        $lines[] = '';
        $lines[] = '## Dependency Cycle Samples';

        foreach ($metrics['dependency_cycle_samples'] as $cycle) {
            $lines[] = '- `'.implode(' -> ', $cycle).'`';
        }
    }

    if (($metrics['security_samples'] ?? []) !== []) {
        $lines[] = '';
        $lines[] = '## Security Samples';

        foreach ($metrics['security_samples'] as $sample) {
            $lines[] = '- '.$sample;
        }
    }

    file_put_contents($path, implode("\n", $lines)."\n");
}

function countLines(string $path): int
{
    $file = new SplFileObject($path, 'r');
    $file->seek(PHP_INT_MAX);

    return $file->key() + 1;
}

function formatMetric(mixed $value): string
{
    if ($value === null) {
        return 'n/a';
    }

    if (is_float($value)) {
        return number_format($value, 2);
    }

    return (string) $value;
}

function readJson(string $path): array
{
    $json = json_decode((string) file_get_contents($path), true);

    if (! is_array($json)) {
        fwrite(STDERR, "Invalid JSON: {$path}\n");
        exit(1);
    }

    return $json;
}

function writeJson(string $path, array $data): void
{
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

function ensureDirectory(string $path): void
{
    if (! is_dir($path)) {
        mkdir($path, 0775, true);
    }
}

function relativePath(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), '/');
}
PHP;
}
