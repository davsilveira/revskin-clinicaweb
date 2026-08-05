<?php

namespace App\Console\Commands;

use App\Services\Migration\LegadoIncrementalImporter;
use Illuminate\Console\Command;

class ImportarLegadoIncremental extends Command
{
    protected $signature = 'migration:importar-legado-incremental
                            {--sql= : Caminho do dump SQL CLW2 (absoluto ou relativo à base)}
                            {--medicos= : Lista separada por vírgula: CRM, CPF, e-mail ou id CLW3}
                            {--dry-run : Simula (default se --force omitido)}
                            {--force : Persiste as alterações}';

    protected $description = 'Importação incremental CLW2→CLW3 filtrada por médicos (merge + conciliação)';

    public function handle(LegadoIncrementalImporter $importer): int
    {
        $sqlOpt = $this->option('sql');
        if (! $sqlOpt) {
            $dumps = $importer->listSqlDumps();
            if ($dumps === []) {
                $this->error('Informe --sql= ou coloque dumps em '.$importer->sqlDirectory());

                return 1;
            }
            $sqlPath = $dumps[0]['path'];
            $this->warn('Usando dump mais recente: '.basename($sqlPath));
        } else {
            $sqlPath = str_starts_with($sqlOpt, '/') ? $sqlOpt : base_path($sqlOpt);
        }

        $medicosRaw = (string) $this->option('medicos');
        if (trim($medicosRaw) === '') {
            $this->error('Informe --medicos= (CRM, CPF, e-mail ou id CLW3, separados por vírgula)');

            return 1;
        }

        $tokens = array_values(array_filter(array_map('trim', explode(',', $medicosRaw))));
        $dryRun = ! $this->option('force');

        $this->info('=== Importação incremental CLW2 ===');
        $this->line('SQL: '.$sqlPath);
        $this->line('Médicos: '.implode(', ', $tokens));
        $this->line('Modo: '.($dryRun ? 'DRY-RUN' : 'FORCE (persistindo)'));
        $this->newLine();

        try {
            $report = $importer->run($sqlPath, $tokens, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        $this->info('Mapeamento médicos:');
        foreach ($report['mappings'] as $m) {
            if (! empty($m['ok'])) {
                $this->line(sprintf(
                    '  ✅ CLW3 #%s %s ↔ CLW2 #%s %s (%s)',
                    $m['clw3_id'],
                    $m['clw3_nome'] ?? '',
                    $m['legado_id'],
                    $m['legado_nome'] ?? '',
                    $m['dump_match'] ?? ''
                ));
            } else {
                $this->warn(sprintf(
                    '  ❌ CLW3 #%s %s — %s',
                    $m['clw3_id'] ?? '?',
                    $m['clw3_nome'] ?? ($m['token'] ?? ''),
                    $m['erro'] ?? 'falha'
                ));
            }
        }

        $this->newLine();
        $this->info('Stats:');
        foreach ($report['stats'] ?? [] as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $signals = $report['signals'] ?? [];
        $this->newLine();
        $this->info('Sinais / conflitos: '.count($signals));
        foreach (array_slice($signals, 0, 30) as $s) {
            $this->line('  - '.json_encode($s, JSON_UNESCAPED_UNICODE));
        }
        if (count($signals) > 30) {
            $this->line('  ... +'.(count($signals) - 30).' no report');
        }

        $this->newLine();
        $this->info('Report: '.($report['work_dir'] ?? '').'/report-latest.json');

        $failedMap = collect($report['mappings'] ?? [])->contains(fn ($m) => empty($m['ok']));

        return $failedMap ? 1 : 0;
    }
}
