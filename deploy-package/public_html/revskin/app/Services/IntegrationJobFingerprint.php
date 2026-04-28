<?php

namespace App\Services;

use App\Jobs\CriarNegociacaoRdStationJob;
use App\Jobs\CriarPedidoTinyJob;
use App\Jobs\ProcessWebhookTinyJob;
use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncClienteTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Jobs\SyncVendaTinyJob;

class IntegrationJobFingerprint
{
    /** @var list<class-string> */
    public const MANAGED_CLASSES = [
        CriarNegociacaoRdStationJob::class,
        CriarPedidoTinyJob::class,
        ProcessWebhookTinyJob::class,
        SyncClienteTinyJob::class,
        SyncVendaTinyJob::class,
        SyncProdutosTinyJob::class,
        PullPacientesTinyJob::class,
    ];

    public const INTEGRATION_QUEUES = [
        'rd-sync',
        'tiny-sync',
        'tiny-webhooks',
    ];

    /**
     * @return array{fingerprint: string, class: class-string}|null
     */
    public static function fromFailedJobPayload(string $payload): ?array
    {
        $data = json_decode($payload, true);
        if (! is_array($data)) {
            return null;
        }
        $displayName = $data['displayName'] ?? null;
        if (! is_string($displayName) || ! in_array($displayName, self::MANAGED_CLASSES, true)) {
            return null;
        }
        $command = $data['data']['command'] ?? null;
        if (! is_string($command)) {
            return null;
        }
        if (str_starts_with($command, 'O:')) {
            $instance = @unserialize($command, ['allowed_classes' => true]);
        } else {
            return null;
        }
        if (! is_object($instance) || $instance instanceof \__PHP_Incomplete_Class) {
            return null;
        }

        $fingerprint = self::fingerprintForInstance($displayName, $instance);
        if ($fingerprint === null) {
            return null;
        }

        return ['fingerprint' => $fingerprint, 'class' => $displayName];
    }

    private static function fingerprintForInstance(string $class, object $job): ?string
    {
        return match ($class) {
            CriarNegociacaoRdStationJob::class,
            CriarPedidoTinyJob::class => self::make($class, 'receita', (int) ($job->receita->id ?? 0)),

            ProcessWebhookTinyJob::class => self::make(
                $class,
                'webhook',
                (string) $job->pedidoId,
                (string) ($job->situacao ?? '')
            ),
            SyncClienteTinyJob::class => self::make($class, 'paciente', (int) ($job->paciente->id ?? 0)),
            SyncVendaTinyJob::class => self::make($class, 'atendimento', (int) ($job->atendimento->id ?? 0)),
            SyncProdutosTinyJob::class,
            PullPacientesTinyJob::class => self::make($class, 'singleton', '1'),
            default => null,
        };
    }

    private static function make(string $class, string $kind, string|int ...$rest): string
    {
        $parts = array_merge([$class, $kind], array_map('strval', $rest));

        return sha1(implode("\0", $parts));
    }
}
