<?php

namespace App\Jobs\Imports;

use App\Models\Import;
use App\Services\Imports\ImportProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $importId)
    {
        $this->onQueue('imports');
    }

    public function handle(ImportProcessingService $processingService): void
    {
        $import = Import::query()->find($this->importId);

        if (! $import) {
            return;
        }

        $processingService->process($import);
    }
}
