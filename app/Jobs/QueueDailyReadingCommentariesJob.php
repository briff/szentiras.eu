<?php

namespace SzentirasHu\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use SzentirasHu\Models\DailyReading;

class QueueDailyReadingCommentariesJob extends Job implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function __construct(public readonly int $dailyReadingId)
    {
    }

    public function handle(): void
    {
        $reading = DailyReading::find($this->dailyReadingId);

        if (!$reading || empty($reading->processed_refs)) {
            Log::warning("QueueDailyReadingCommentariesJob: DailyReading {$this->dailyReadingId} not found or has no refs.");
            return;
        }

        $translationAbbrev = Config::get('settings.defaultTranslationAbbrev');
        $queued = 0;

        foreach ($reading->processed_refs as $refString) {
            $exitCode = Artisan::call('szentiras:generate-commentary', [
                'reference' => $refString,
                'translation' => $translationAbbrev,
            ]);

            if ($exitCode === 0) {
                $queued++;
            } else {
                Log::warning("QueueDailyReadingCommentariesJob: failed to dispatch commentary for '{$refString}'.");
            }
        }

        $reading->status = DailyReading::STATUS_COMMENTARIES_QUEUED;
        $reading->save();

        Log::info("QueueDailyReadingCommentariesJob: dispatched {$queued} commentary jobs for daily reading {$this->dailyReadingId}.");
    }
}
