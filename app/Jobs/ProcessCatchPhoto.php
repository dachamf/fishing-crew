<?php

namespace App\Jobs;

use App\Models\CatchPhoto;
use App\Services\PhotoProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCatchPhoto implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;
    public string $queue = 'images';

    public function __construct(public int $photoId) {}

    public function handle(PhotoProcessor $processor): void
    {
        $photo = CatchPhoto::find($this->photoId);
        if (!$photo || !$photo->path) return;

        $processor->generateVariants($photo);
    }
}
