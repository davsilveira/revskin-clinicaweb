<?php

namespace App\Services;

use App\Jobs\CancelarPedidoTinyJob;
use App\Jobs\CriarNegociacaoRdStationJob;
use App\Jobs\CriarPedidoTinyJob;
use App\Jobs\MarcarNegociacaoPerdidaRdJob;
use App\Jobs\ProcessWebhookRdJob;
use App\Jobs\ProcessWebhookTinyJob;
use App\Jobs\PullPacientesTinyJob;
use App\Jobs\SyncClienteTinyJob;
use App\Jobs\SyncProdutosTinyJob;
use App\Jobs\SyncVendaTinyJob;
use Illuminate\Contracts\Database\ModelIdentifier;

class IntegrationJobFingerprint
{
    /** @var list<class-string> */
    public const MANAGED_CLASSES = [
        CriarNegociacaoRdStationJob::class,
        MarcarNegociacaoPerdidaRdJob::class,
        ProcessWebhookRdJob::class,
        CriarPedidoTinyJob::class,
        CancelarPedidoTinyJob::class,
        ProcessWebhookTinyJob::class,
        SyncClienteTinyJob::class,
        SyncVendaTinyJob::class,
        SyncProdutosTinyJob::class,
        PullPacientesTinyJob::class,
    ];

    public const INTEGRATION_QUEUES = [
        'rd-sync',
        'rd-webhooks',
        'tiny-sync',
        'tiny-webhooks',
    ];

    /** @var array<class-string, string> */
    public const JOB_LABELS = [
        CriarNegociacaoRdStationJob::class => 'Criar negociação',
        MarcarNegociacaoPerdidaRdJob::class => 'Marcar negociação perdida',
        ProcessWebhookRdJob::class => 'Webhook negociação RD',
        CriarPedidoTinyJob::class => 'Criar pedido',
        CancelarPedidoTinyJob::class => 'Cancelar pedido',
        ProcessWebhookTinyJob::class => 'Webhook pedido',
        SyncClienteTinyJob::class => 'Sync cliente',
        SyncVendaTinyJob::class => 'Sync venda',
        SyncProdutosTinyJob::class => 'Sync produtos',
        PullPacientesTinyJob::class => 'Importar pacientes',
    ];

    /** @var array<class-string, string> Jobs ativos no filtro da UI */
    public const FILTER_JOB_LABELS = [
        CriarNegociacaoRdStationJob::class => 'Criar negociação',
        MarcarNegociacaoPerdidaRdJob::class => 'Marcar negociação perdida',
        ProcessWebhookRdJob::class => 'Webhook negociação RD',
        SyncClienteTinyJob::class => 'Sync cliente',
        SyncProdutosTinyJob::class => 'Sync produtos',
        PullPacientesTinyJob::class => 'Importar pacientes',
    ];

    /**
     * @return list<array{value: class-string, label: string}>
     */
    public static function jobOptions(): array
    {
        return collect(self::FILTER_JOB_LABELS)
            ->map(fn (string $label, string $class) => [
                'value' => class_basename($class),
                'label' => $label,
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * @return array{fingerprint: string, class: class-string, instance: object}|null
     */
    public static function parsePayload(string $payload): ?array
    {
        try {
            $data = json_decode($payload, true);
            if (! is_array($data)) {
                return null;
            }

            $displayName = $data['displayName'] ?? $data['data']['commandName'] ?? null;
            if (! is_string($displayName) || ! in_array($displayName, self::MANAGED_CLASSES, true)) {
                return null;
            }

            $command = $data['data']['command'] ?? null;
            if (! is_string($command) || ! str_starts_with($command, 'O:')) {
                return null;
            }

            $instance = self::buildSyntheticInstance($displayName, $command);
            $fingerprint = self::fingerprintForInstance($displayName, $instance);
            if ($fingerprint === null) {
                return null;
            }

            return [
                'fingerprint' => $fingerprint,
                'class' => $displayName,
                'instance' => $instance,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Extrai metadados do payload serializado sem restaurar models Eloquent
     * (SerializesModels usa firstOrFail e quebra se o registro foi removido).
     */
    private static function buildSyntheticInstance(string $class, string $command): object
    {
        $job = new \stdClass;

        match ($class) {
            CriarNegociacaoRdStationJob::class,
            MarcarNegociacaoPerdidaRdJob::class,
            CriarPedidoTinyJob::class => self::assignModelRef($job, 'receita', self::extractModelId($command, 'receita')),
            SyncClienteTinyJob::class => self::assignModelRef($job, 'paciente', self::extractModelId($command, 'paciente')),
            SyncVendaTinyJob::class => self::assignModelRef($job, 'atendimento', self::extractModelId($command, 'atendimento')),
            ProcessWebhookTinyJob::class => self::assignWebhookScalars($job, $command),
            ProcessWebhookRdJob::class => self::assignRdWebhookScalars($job, $command),
            default => null,
        };

        return $job;
    }

    private static function assignModelRef(object $job, string $property, ?int $id): void
    {
        if ($id !== null) {
            $job->{$property} = (object) ['id' => $id];
        }
    }

    private static function assignWebhookScalars(object $job, string $command): void
    {
        if (($pedidoId = self::extractScalar($command, 'pedidoId')) !== null) {
            $job->pedidoId = $pedidoId;
        }

        if (self::hasSerializedProperty($command, 'situacao')) {
            $job->situacao = self::extractScalar($command, 'situacao');
        }
    }

    private static function assignRdWebhookScalars(object $job, string $command): void
    {
        if (($dealId = self::extractScalar($command, 'dealId')) !== null) {
            $job->dealId = $dealId;
        }

        if (self::hasSerializedProperty($command, 'status')) {
            $job->status = self::extractScalar($command, 'status');
        }

        if (($transactionUuid = self::extractScalar($command, 'transactionUuid')) !== null) {
            $job->transactionUuid = $transactionUuid;
        }
    }

    private static function hasSerializedProperty(string $command, string $property): bool
    {
        $len = strlen($property);

        return (bool) preg_match('/s:'.$len.':"'.preg_quote($property, '/').'";/', $command);
    }

    private static function extractModelId(string $command, string $property): ?int
    {
        $len = strlen($property);
        $pattern = '/s:'.$len.':"'.preg_quote($property, '/').'";O:\d+:"Illuminate\\\\Contracts\\\\Database\\\\ModelIdentifier":\d+:\{[^}]*s:2:"id";(?:i:(\d+)|s:\d+:"(\d+)");/s';

        if (! preg_match($pattern, $command, $matches)) {
            return null;
        }

        $id = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');

        return $id !== '' ? (int) $id : null;
    }

    private static function extractScalar(string $command, string $property): ?string
    {
        $len = strlen($property);
        $escaped = preg_quote($property, '/');

        if (preg_match('/s:'.$len.':"'.$escaped.'";s:\d+:"([^"]*)";/', $command, $matches)) {
            return $matches[1];
        }

        if (preg_match('/s:'.$len.':"'.$escaped.'";i:(-?\d+);/', $command, $matches)) {
            return $matches[1];
        }

        if (preg_match('/s:'.$len.':"'.$escaped.'";N;/', $command)) {
            return null;
        }

        return null;
    }

    /**
     * @return array{fingerprint: string, class: class-string}|null
     */
    public static function fromFailedJobPayload(string $payload): ?array
    {
        $parsed = self::parsePayload($payload);
        if ($parsed === null) {
            return null;
        }

        return [
            'fingerprint' => $parsed['fingerprint'],
            'class' => $parsed['class'],
        ];
    }

    /**
     * @return array{
     *     job_class: class-string,
     *     job_label: string,
     *     receita_id: int|null,
     *     receita_numero: string|null,
     *     paciente_id: int|null,
     *     paciente_nome: string|null,
     *     atendimento_id: int|null,
     *     pedido_id: string|null,
     *     situacao: string|null,
     *     context_label: string|null
     * }
     */
    public static function describe(string $class, object $job): array
    {
        $base = [
            'job_class' => $class,
            'job_label' => self::JOB_LABELS[$class] ?? class_basename($class),
            'receita_id' => null,
            'receita_numero' => null,
            'paciente_id' => null,
            'paciente_nome' => null,
            'atendimento_id' => null,
            'pedido_id' => null,
            'situacao' => null,
            'context_label' => null,
        ];

        return match ($class) {
            CriarNegociacaoRdStationJob::class,
            MarcarNegociacaoPerdidaRdJob::class,
            CriarPedidoTinyJob::class => array_merge($base, [
                'receita_id' => self::modelId($job->receita ?? null),
                'context_label' => self::modelId($job->receita ?? null)
                    ? 'Receita #'.self::modelId($job->receita ?? null)
                    : null,
            ]),
            SyncClienteTinyJob::class => array_merge($base, [
                'paciente_id' => self::modelId($job->paciente ?? null),
                'context_label' => self::modelId($job->paciente ?? null)
                    ? 'Paciente #'.self::modelId($job->paciente ?? null)
                    : null,
            ]),
            SyncVendaTinyJob::class => array_merge($base, [
                'atendimento_id' => self::modelId($job->atendimento ?? null),
                'context_label' => self::modelId($job->atendimento ?? null)
                    ? 'Atendimento #'.self::modelId($job->atendimento ?? null)
                    : null,
            ]),
            ProcessWebhookTinyJob::class => array_merge($base, [
                'pedido_id' => isset($job->pedidoId) ? (string) $job->pedidoId : null,
                'situacao' => isset($job->situacao) ? (string) $job->situacao : null,
                'context_label' => isset($job->pedidoId)
                    ? 'Pedido Tiny #'.$job->pedidoId
                    : null,
            ]),
            ProcessWebhookRdJob::class => array_merge($base, [
                'context_label' => isset($job->dealId)
                    ? 'Negociação RD #'.$job->dealId
                    : null,
            ]),
            SyncProdutosTinyJob::class,
            PullPacientesTinyJob::class => array_merge($base, [
                'context_label' => 'Job global',
            ]),
            default => $base,
        };
    }

    private static function modelId(mixed $model): ?int
    {
        if ($model instanceof ModelIdentifier) {
            return (int) $model->id;
        }
        if (is_object($model) && isset($model->id)) {
            return (int) $model->id;
        }

        return null;
    }

    private static function fingerprintForInstance(string $class, object $job): ?string
    {
        return match ($class) {
            CriarNegociacaoRdStationJob::class,
            MarcarNegociacaoPerdidaRdJob::class,
            CriarPedidoTinyJob::class => self::make($class, 'receita', self::modelId($job->receita ?? null) ?? 0),

            ProcessWebhookTinyJob::class => self::make(
                $class,
                'webhook',
                (string) ($job->pedidoId ?? ''),
                (string) ($job->situacao ?? '')
            ),
            ProcessWebhookRdJob::class => self::make(
                $class,
                'rd-webhook',
                (string) ($job->dealId ?? ''),
                (string) ($job->status ?? ''),
                (string) ($job->transactionUuid ?? '')
            ),
            SyncClienteTinyJob::class => self::make($class, 'paciente', self::modelId($job->paciente ?? null) ?? 0),
            SyncVendaTinyJob::class => self::make($class, 'atendimento', self::modelId($job->atendimento ?? null) ?? 0),
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
