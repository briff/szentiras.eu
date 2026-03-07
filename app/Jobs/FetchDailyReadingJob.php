<?php

namespace SzentirasHu\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Service\DailyReadingService;
use SzentirasHu\Models\DailyReading;

class FetchDailyReadingJob extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public readonly string $date)
    {
    }

    public function handle(DailyReadingService $dailyReadingService): void
    {
        $date = new \DateTimeImmutable($this->date);
        $reading = $dailyReadingService->fetchAndStore($date);

        if ($reading === null) {
            Log::warning("FetchDailyReadingJob: fetch failed for {$this->date}, commentaries will not be queued.");
            return;
        }

        QueueDailyReadingCommentariesJob::dispatch($reading->id);
    }
}
