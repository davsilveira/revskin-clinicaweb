<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RelatorioExportRequest extends Model
{
    public const TYPE_AQUISICAO_PRODUTOS = 'aquisicao_produtos';
    public const TYPE_RECEITAS_MEDICO = 'receitas_medico';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'type',
        'format',
        'filters',
        'status',
        'file_path',
        'file_name',
        'total_records',
        'extra_emails',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'extra_emails' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => self::STATUS_PROCESSING]);
    }

    public function markAsCompleted(string $filePath, string $fileName, int $totalRecords): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'total_records' => $totalRecords,
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_AQUISICAO_PRODUTOS => 'Aquisição de Produtos',
            self::TYPE_RECEITAS_MEDICO => 'Receitas por Médico',
            default => $this->type,
        };
    }

    public function formatLabel(): string
    {
        return $this->format === 'pdf' ? 'PDF' : 'Excel';
    }
}
