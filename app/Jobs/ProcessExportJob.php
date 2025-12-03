<?php

namespace App\Jobs;

use App\Mail\ExportReadyMail;
use App\Models\ExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ProcessExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public ExportRequest $exportRequest
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        try {
            $this->exportRequest->markAsProcessing();

            // Generate export data
            // This is a placeholder - implement your own export logic
            $data = $this->generateExportData();

            // Create the export file
            $fileName = $this->generateFileName();
            $filePath = $this->saveExportFile($data, $fileName);

            // Mark as completed
            $this->exportRequest->markAsCompleted(
                $filePath,
                $fileName,
                count($data)
            );

            // Send notification email
            $this->sendNotificationEmail();

            Log::info("Export completed successfully", [
                'export_request_id' => $this->exportRequest->id,
                'file_name' => $fileName,
                'total_records' => count($data),
            ]);

        } catch (\Exception $e) {
            Log::error("Export failed", [
                'export_request_id' => $this->exportRequest->id,
                'error' => $e->getMessage(),
            ]);

            $this->exportRequest->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    protected function generateExportData(): array
    {
        // Placeholder implementation
        // Override this method in your application to generate actual export data
        // based on $this->exportRequest->type, filters, selected_fields, etc.

        return [
            ['id' => 1, 'name' => 'Example 1', 'created_at' => now()->toDateTimeString()],
            ['id' => 2, 'name' => 'Example 2', 'created_at' => now()->toDateTimeString()],
        ];
    }

    protected function generateFileName(): string
    {
        $type = $this->exportRequest->type;
        $timestamp = now()->format('Y-m-d_His');

        return "{$type}_{$timestamp}.csv";
    }

    protected function saveExportFile(array $data, string $fileName): string
    {
        $disk = Storage::disk('local');
        $directory = 'exports';
        $filePath = "{$directory}/{$fileName}";

        // Ensure directory exists
        if (!$disk->exists($directory)) {
            $disk->makeDirectory($directory);
        }

        // Generate CSV content
        $csvContent = $this->arrayToCsv($data);

        // Save to disk
        $disk->put($filePath, $csvContent);

        return $filePath;
    }

    protected function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write header
        fputcsv($output, array_keys($data[0]));

        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    protected function sendNotificationEmail(): void
    {
        $user = $this->exportRequest->user;

        if ($user && $user->email) {
            Mail::to($user->email)->queue(new ExportReadyMail($this->exportRequest));
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->exportRequest->markAsFailed($exception->getMessage());

        Log::error("Export job failed permanently", [
            'export_request_id' => $this->exportRequest->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

