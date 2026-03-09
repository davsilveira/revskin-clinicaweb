<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class DeployPackageCommand extends Command
{
    protected $signature = 'deploy:package
                            {--fresh : Limpar a pasta de saída antes de gerar}
                            {--no-build : Pular npm run build (use se já tiver rodado ou em ambiente com Node 20+)}';

    protected $description = 'Gera pacote para deploy FTP: build de produção, dump SQL (opcional) e pastas public_html + app';

    public function handle(): int
    {
        $basePath = base_path();
        $outputDir = config('deploy.output_dir', 'deploy-package');
        $appDirName = config('deploy.app_dir_name', 'revskin');
        $outPath = $basePath.'/'.$outputDir;

        $this->info('Gerando pacote de deploy em '.$outputDir.'/');

        if (File::exists($outPath)) {
            if ($this->option('fresh') || $this->confirm('A pasta '.$outputDir.' já existe. Limpar e continuar?', true)) {
                File::deleteDirectory($outPath);
            } else {
                $this->warn('Operação cancelada.');

                return self::FAILURE;
            }
        }

        File::ensureDirectoryExists($outPath);

        // 1. Build do front (exige Node 20+)
        if (! $this->option('no-build')) {
            $this->info('Rodando npm run build...');
            $result = Process::timeout(120)->path($basePath)->run('npm run build');
            if (! $result->successful()) {
                $this->error('Falha no build:');
                $this->line($result->errorOutput());
                $this->line('Dica: use --no-build se já tiver rodado npm run build, ou atualize o Node para 20+.');

                return self::FAILURE;
            }
            $this->info('Build concluído.');
        } else {
            if (! File::exists($basePath.'/public/build/manifest.json')) {
                $this->error('Pasta public/build não encontrada. Rode npm run build antes ou remova --no-build.');

                return self::FAILURE;
            }
            $this->info('Build omitido (--no-build); usando public/build existente.');
        }

        // 2. Dump SQL (opcional)
        $schemaPath = null;
        $dbConfig = config('deploy.database');
        if (is_array($dbConfig) && ! empty($dbConfig['database'])) {
            $schemaPath = $this->runSchemaDump($basePath, $dbConfig);
            if ($schemaPath === false) {
                $this->warn('MySQL indisponível; gerando schema.sql via SQLite...');
                $schemaPath = $this->runSchemaDumpViaSqlite($basePath);
            }
        } else {
            $this->line('Gerando schema.sql via SQLite (sem MySQL configurado)...');
            $schemaPath = $this->runSchemaDumpViaSqlite($basePath);
        }

        $publicHtmlPath = $outPath.'/public_html';
        $appTargetPath = $publicHtmlPath.'/'.$appDirName;

        File::ensureDirectoryExists($publicHtmlPath);

        // 3. Copiar public/ -> public_html/
        $this->info('Copiando public/ para public_html/...');
        File::copyDirectory($basePath.'/public', $publicHtmlPath);

        // 4. Ajustar index.php: bootstrap aponta para public_html/revskin/ (tudo dentro de public_html para Hostinger)
        $indexPhp = $publicHtmlPath.'/index.php';
        if (File::exists($indexPhp)) {
            $content = File::get($indexPhp);
            $content = str_replace("__DIR__.'/../", "__DIR__.'/".$appDirName.'/', $content);
            File::put($indexPhp, $content);
        }

        // 5. Copiar raiz do app para public_html/revskin/ (com exclusões)
        $this->info('Copiando aplicação para public_html/'.$appDirName.'/...');
        $this->copyAppToPackage($basePath, $appTargetPath, $outputDir);

        // 5b. Copiar build para revskin/public/build/ (Laravel usa public_path() = revskin/public/)
        if (File::exists($basePath.'/public/build/manifest.json')) {
            $revskinPublicBuild = $appTargetPath.'/public/build';
            File::ensureDirectoryExists($revskinPublicBuild);
            File::copyDirectory($basePath.'/public/build', $revskinPublicBuild);
            $this->info('Build copiado para '.$appDirName.'/public/build/ (manifest para produção).');
        }

        // 6. Estrutura storage vazia no pacote
        $this->ensureStorageStructure($appTargetPath);

        // 7. .htaccess em revskin/ para bloquear acesso direto (Hostinger: tudo fica dentro de public_html)
        File::put($appTargetPath.'/.htaccess', "Require all denied\n");

        // 8. Copiar schema.sql para a raiz do pacote
        if ($schemaPath && File::exists($schemaPath)) {
            $schemaDest = $outPath.'/schema.sql';
            File::copy($schemaPath, $schemaDest);
            $this->info('schema.sql incluído no pacote.');
        }

        // 9. LEIA-ME
        $this->writeReadme($outPath, $appDirName);

        $this->newLine();
        $this->info('Pacote gerado em: '.$outPath);
        $this->line('Próximos passos: enviar TODO o conteúdo de public_html/ (FTP) para o public_html da Hostinger; importar schema.sql no phpMyAdmin; criar .env em public_html/'.$appDirName.'/ no servidor. Ver LEIA-ME.txt no pacote.');

        return self::SUCCESS;
    }

    private function runSchemaDump(string $basePath, array $dbConfig): string|false
    {
        $connection = [
            'driver' => 'mysql',
            'host' => $dbConfig['host'] ?? '127.0.0.1',
            'port' => $dbConfig['port'] ?? 3306,
            'database' => $dbConfig['database'],
            'username' => $dbConfig['username'] ?? 'root',
            'password' => $dbConfig['password'] ?? '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ];

        Config::set('database.connections.deploy', $connection);

        $this->ensureDeployDatabaseExists($connection);

        $this->info('Rodando migrations no banco de dump (deploy)...');
        $migrate = Process::timeout(60)->path($basePath)->run('php artisan migrate --database=deploy --force');
        if (! $migrate->successful()) {
            $this->error($migrate->errorOutput());

            return false;
        }

        $this->info('Gerando schema dump...');
        $dump = Process::timeout(30)->path($basePath)->run('php artisan schema:dump --database=deploy');
        if (! $dump->successful()) {
            $this->error($dump->errorOutput());

            return false;
        }

        $schemaFile = $basePath.'/database/schema/deploy-schema.sql';

        return File::exists($schemaFile) ? $schemaFile : false;
    }

    private function ensureDeployDatabaseExists(array $connection): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;charset=utf8mb4',
                $connection['host'],
                $connection['port']
            );
            $pdo = new \PDO($dsn, $connection['username'], $connection['password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $connection['database']).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            $this->warn('Não foi possível criar o banco ('.$e->getMessage().'). Tentando gerar schema via SQLite.');
        }
    }

    private function runSchemaDumpViaSqlite(string $basePath): string|false
    {
        $schemaDir = $basePath.'/database/schema';
        $mysqlFile = $schemaDir.'/mysql-from-sqlite.sql';

        File::ensureDirectoryExists($schemaDir);

        // Usar o banco padrão da aplicação (do .env)
        $defaultConnection = config('database.default');
        $defaultConfig = config('database.connections.'.$defaultConnection);

        if ($defaultConfig['driver'] !== 'sqlite') {
            $this->warn('Banco padrão não é SQLite. Configure DB_CONNECTION=sqlite no .env ou use MySQL para dump.');

            return false;
        }

        $sqliteFile = $defaultConfig['database'];
        if (! File::exists($sqliteFile)) {
            $this->warn('Banco SQLite não encontrado: '.$sqliteFile.'. Rode as migrations primeiro.');

            return false;
        }

        $this->info('Usando banco SQLite da aplicação: '.$sqliteFile);
        $this->info('Garantindo que migrations estão atualizadas...');
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        if ($exitCode !== 0) {
            $this->warn('Migrations falharam; continuando com o dump do banco atual.');
        }

        try {
            $pdo = new \PDO('sqlite:'.$sqliteFile);
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        } catch (\Throwable $e) {
            $this->error('Leitura do banco SQLite falhou: '.$e->getMessage());

            return false;
        }

        $mysql = "-- Dump completo do banco da aplicação (schema + dados reais)\n";
        $mysql .= "-- Revskin - importar no phpMyAdmin da Hostinger\n";
        $mysql .= "-- Exportado de: ".$sqliteFile."\n\n";
        $mysql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
        foreach ($tables as $row) {
            $name = $row['name'];
            $sql = $row['sql'];
            if (! $sql) {
                continue;
            }
            $mysql .= $this->convertSqliteCreateTableToMysql($sql, $name)."\n\n";
        }
        foreach ($tables as $row) {
            $name = $row['name'];
            if ($name === 'sqlite_sequence') {
                continue;
            }
            $inserts = $this->exportTableDataAsMysql($pdo, $name);
            if ($inserts !== '') {
                $mysql .= $inserts."\n\n";
            }
        }
        $mysql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        File::put($mysqlFile, $mysql);
        $this->info('Dump gerado com sucesso ('.count($tables).' tabelas).');

        return $mysqlFile;
    }

    private function convertSqliteCreateTableToMysql(string $sqliteSql, string $tableName): string
    {
        $sql = $sqliteSql;
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = str_replace('"', '`', $sql);
        $sql = $this->removeCheckConstraints($sql);
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\s+AUTOINCREMENT\b/i', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $sql = preg_replace('/\bINTEGER\s+PRIMARY\s+KEY\b/i', 'BIGINT UNSIGNED NOT NULL PRIMARY KEY', $sql);
        $sql = preg_replace('/\bAUTOINCREMENT\b/i', 'AUTO_INCREMENT', $sql);
        $sql = preg_replace('/\bREAL\b/i', 'DOUBLE', $sql);
        $sql = preg_replace('/\bINTEGER\b/i', 'BIGINT', $sql);
        $sql = preg_replace('/\bBIGINT\b(?!\s+UNSIGNED)/i', 'BIGINT UNSIGNED', $sql);
        $sql = preg_replace('/\bnumeric\b/i', 'DECIMAL(15,2)', $sql);
        $sql = preg_replace('/\b(varchar)\b(?!\s*\()/i', '${1}(255)', $sql);
        $sql = preg_replace('/(?<!\d)\)\s*(not null|default)/i', ' $1', $sql);
        $sql = preg_replace('/\bPRIMARY KEY\s+not null\b/i', 'PRIMARY KEY', $sql);
        $sql = preg_replace('/([,(\s])\'([a-zA-Z_][a-zA-Z0-9_]*)`/', '$1`$2`', $sql);
        if (! preg_match('/\s*;\s*$/', $sql)) {
            $sql = rtrim($sql).' ';
        }
        $sql = preg_replace('/\s*\)\s*;?\s*$/i', ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;', $sql);
        $sql = 'CREATE TABLE IF NOT EXISTS `'.$tableName.'` '.preg_replace('/^\s*CREATE\s+TABLE\s+`?[^`\s]+`?\s*\(/i', '(', $sql);

        return $sql;
    }

    private function removeCheckConstraints(string $sql): string
    {
        while (preg_match('/\s+check\s*\(/i', $sql)) {
            $start = preg_match('/\s+check\s*\(/i', $sql, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : false;
            if ($start === false) {
                break;
            }
            $parenStart = $start + strlen($m[0][0]) - 1;
            $depth = 1;
            $i = $parenStart + 1;
            $len = strlen($sql);
            while ($i < $len && $depth > 0) {
                $c = $sql[$i];
                if ($c === "'" || $c === '`') {
                    $quote = $c;
                    $i++;
                    while ($i < $len && $sql[$i] !== $quote) {
                        if ($sql[$i] === '\\') {
                            $i++;
                        }
                        $i++;
                    }
                    $i++;
                    continue;
                }
                if ($c === '(') {
                    $depth++;
                } elseif ($c === ')') {
                    $depth--;
                }
                $i++;
            }
            $sql = substr($sql, 0, $start).substr($sql, $i);
        }

        return $sql;
    }

    private function exportTableDataAsMysql(\PDO $pdo, string $tableName): string
    {
        $quoted = '"'.str_replace('"', '""', $tableName).'"';
        $stmt = $pdo->query('SELECT * FROM '.$quoted);
        if (! $stmt) {
            return '';
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if ($rows === []) {
            return '';
        }
        $columns = array_keys($rows[0]);
        $out = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $v = $row[$col];
                if ($v === null) {
                    $values[] = 'NULL';
                } elseif (is_numeric($v) && (int) $v == $v && strpos((string) $v, '.') === false) {
                    $values[] = (string) (int) $v;
                } elseif (is_numeric($v)) {
                    $values[] = (string) (float) $v;
                } else {
                    $values[] = "'".addslashes(str_replace(["\r", "\n"], ['\r', '\n'], (string) $v))."'";
                }
            }
            $out[] = 'INSERT INTO `'.str_replace('`', '``', $tableName).'` (`'.implode('`, `', array_map(function ($c) {
                return str_replace('`', '``', $c);
            }, $columns)).'`) VALUES ('.implode(', ', $values).');';
        }

        return implode("\n", $out);
    }

    private function copyAppToPackage(string $basePath, string $appTargetPath, string $outputDir): void
    {
        $excludeDirs = [
            'node_modules',
            '.git',
            'public',
            'tests',
            $outputDir,
        ];

        $excludeNames = [
            '.env', '.env.backup', '.env.production', '.env.example',
            '.DS_Store', '.phpunit.result.cache', 'Homestead.json', 'Homestead.yaml',
            'auth.json', 'npm-debug.log', 'yarn-error.log',
            'database/database.sqlite',
        ];

        $copyItems = [
            'app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes',
            'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
            'vite.config.js',
        ];

        foreach ($copyItems as $item) {
            $src = $basePath.'/'.$item;
            if (! File::exists($src)) {
                continue;
            }
            $dest = $appTargetPath.'/'.$item;
            if (File::isDirectory($src)) {
                if ($item === 'database') {
                    $this->copyDirectoryExcluding($src, $dest, ['schema']);
                } else {
                    File::copyDirectory($src, $dest);
                }
            } else {
                File::ensureDirectoryExists(dirname($dest));
                File::copy($src, $dest);
            }
        }

        // vendor (necessário no servidor)
        if (File::isDirectory($basePath.'/vendor')) {
            $this->info('Copiando vendor/ (pode demorar)...');
            File::copyDirectory($basePath.'/vendor', $appTargetPath.'/vendor');
        }
    }

    private function copyDirectoryExcluding(string $src, string $dest, array $excludeSubdirs): void
    {
        File::ensureDirectoryExists($dest);
        $items = File::directories($src);
        foreach ($items as $dir) {
            $name = basename($dir);
            if (in_array($name, $excludeSubdirs, true)) {
                continue;
            }
            File::copyDirectory($dir, $dest.'/'.$name);
        }
        foreach (File::files($src) as $file) {
            if ($file->getFilename() === 'database.sqlite') {
                continue;
            }
            File::copy($file->getPathname(), $dest.'/'.$file->getFilename());
        }
    }

    private function ensureStorageStructure(string $appTargetPath): void
    {
        $dirs = [
            'storage/app',
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'storage/pail',
        ];
        foreach ($dirs as $dir) {
            File::ensureDirectoryExists($appTargetPath.'/'.$dir);
        }
        $gitignore = "*\n!.gitignore\n";
        File::put($appTargetPath.'/storage/logs/.gitignore', $gitignore);
        File::put($appTargetPath.'/storage/framework/cache/data/.gitignore', $gitignore);
        File::put($appTargetPath.'/storage/framework/sessions/.gitignore', $gitignore);
        File::put($appTargetPath.'/storage/framework/views/.gitignore', $gitignore);
    }

    private function writeReadme(string $outPath, string $appDirName): void
    {
        $appUrl = config('deploy.app_url') ?: 'https://clinicaweb.revskin.com.br';
        $content = <<<TEXT
REVSKIN - Pacote de deploy para Hostinger (FTP)

Tudo fica dentro de public_html (a Hostinger não permite upload fora dele).
A pasta {$appDirName}/ fica DENTRO de public_html e é protegida por .htaccess.

1. FTP (FileZilla)
   - Enviar TODO o conteúdo da pasta public_html/ para o public_html do seu domínio na Hostinger.
   - Ou seja: arraste index.php, build/, images/, {$appDirName}/ para dentro do public_html remoto.
   - Não faça upload na raiz (DO_NOT_UPLOAD_HERE); use apenas public_html.

2. Banco de dados
   - No phpMyAdmin da Hostinger, criar um banco MySQL e importar o arquivo schema.sql (raiz deste pacote).

3. Configuração no servidor
   - Criar o arquivo .env em public_html/{$appDirName}/.env no servidor (use .env.example do projeto como base).
   - Definir APP_ENV=production e APP_DEBUG=false.
   - Definir APP_URL={$appUrl}
   - Definir APP_KEY= (gerar com: php artisan key:generate --show)
   - Definir LOG_LEVEL=error (não use "production"; valores válidos: debug, info, notice, warning, error, critical, alert, emergency).
   - Definir DB_* com os dados do MySQL da Hostinger.

4. Permissões
   - Garantir que o servidor possa escrever em public_html/{$appDirName}/storage/ e public_html/{$appDirName}/bootstrap/cache/.

5. Cron (Hostinger: Avançado -> Cron Jobs)
   - O caminho do artisan será algo como: .../public_html/{$appDirName}/artisan
   - schedule:run a cada minuto:
     * * * * * /usr/bin/php /home/SEU_USUARIO/public_html/{$appDirName}/artisan schedule:run >> /dev/null 2>&1
   - queue:work (processar fila) a cada minuto:
     * * * * * /usr/bin/php /home/SEU_USUARIO/public_html/{$appDirName}/artisan queue:work database --stop-when-empty --max-time=50 >> /dev/null 2>&1

Instruções completas: ver DEPLOY_HOSTINGER.md na raiz do projeto.
TEXT;
        File::put($outPath.'/LEIA-ME.txt', $content);
    }
}
