<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ExportarBackupReimport extends Command
{
    protected $signature = 'migration:exportar-backup-reimport
                            {--output= : Diretório de saída (padrão: storage/app/private/migration-backups/A-m-d_His)}
                            {--incluir-produtos : Inclui produtos, assistente_tratamento_itens, assistente_regra_acoes e tabela_preco_itens (se existir)}';

    protected $description = 'Exporta tabelas afetadas pela reimportação em JSON (rode ANTES de migration:limpar-dados-reimport). Guarde cópia fora do servidor.';

    public function handle(): int
    {
        $this->info('=== Backup para reimportação (JSON) ===');
        $this->newLine();

        $ts = now()->format('Y-m-d_His');
        $optOut = $this->option('output');
        if ($optOut) {
            $dir = str_starts_with($optOut, DIRECTORY_SEPARATOR) ? $optOut : base_path($optOut);
        } else {
            $dir = storage_path('app/private/migration-backups/'.$ts);
        }

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $tables = [
            'receita_item_aquisicoes',
            'receita_itens',
            'receitas',
            'acompanhamentos_callcenter',
            'atendimentos_callcenter',
            'paciente_telefones',
            'pacientes',
            'user_medico',
            'users',
            'medico_enderecos',
            'clinica_medico',
            'medicos',
            'clinicas',
        ];

        if ($this->option('incluir-produtos')) {
            $tables = array_merge($tables, [
                'assistente_tratamento_itens',
                'assistente_regra_acoes',
                'produtos',
            ]);
            foreach (['tabela_preco_itens'] as $t) {
                if (Schema::hasTable($t)) {
                    $tables[] = $t;
                }
            }
        }

        $manifest = ['exported_at' => now()->toIso8601String(), 'tables' => []];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("Tabela inexistente (ignorada): {$table}");

                continue;
            }

            $rows = DB::table($table)->get();
            $data = $rows->map(fn ($r) => (array) $r)->values()->all();

            if ($table === 'users') {
                $data = array_map(static function (array $row): array {
                    $row['password'] = '[REDACTED]';

                    return $row;
                }, $data);
            }

            $path = rtrim($dir, '/').'/'.$table.'.json';
            file_put_contents(
                $path,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $count = \count($data);
            $manifest['tables'][$table] = $count;
            $this->line("   {$table}.json ({$count} linhas)");
        }

        file_put_contents(
            rtrim($dir, '/').'/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->newLine();
        $this->info('Backup gravado em: '.$dir);
        $this->warn('Guarde uma cópia desta pasta fora do servidor antes de limpar dados.');

        return 0;
    }
}
