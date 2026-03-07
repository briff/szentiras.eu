<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Jobs\FetchDailyReadingJob;

class FetchDailyReading extends Command
{
    protected $signature = 'szentiras:fetch-daily-reading
                            {--date= : Date in Y-m-d format (defaults to today)}
                            {--sync : Run synchronously instead of dispatching a job}';

    protected $description = 'Fetch daily reading data, store refs, and queue commentary generation.';

    public function handle(): int
    {
        $dateString = $this->option('date') ?? now()->format('Y-m-d');

        try {
            $date = new \DateTimeImmutable($dateString);
        } catch (\Exception $e) {
            $this->error("Invalid date format: {$dateString}. Use Y-m-d.");
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->info("Fetching daily reading for {$dateString} synchronously...");
            app(\SzentirasHu\Service\DailyReadingService::class)->fetchAndStore($date);
            $this->info('Done.');
        } else {
            FetchDailyReadingJob::dispatch($dateString);
            $this->info("Daily reading fetch job dispatched for {$dateString}.");
        }

        return self::SUCCESS;
    }
}
