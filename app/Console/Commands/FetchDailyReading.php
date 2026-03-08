<?php

namespace SzentirasHu\Console\Commands;

use Illuminate\Console\Command;
use SzentirasHu\Jobs\FetchDailyReadingJob;
use SzentirasHu\Models\DailyReading;

class FetchDailyReading extends Command
{
    protected $signature = 'szentiras:fetch-daily-reading
                            {--date= : Date in Y-m-d format (defaults to today)}
                            {--sync : Run synchronously instead of dispatching a job}
                            {--recreate : Delete the existing record for the date before fetching}';

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

        if ($this->option('recreate')) {
            $deleted = DailyReading::query()->where('date', $dateString)->delete();
            if ($deleted) {
                $this->info("Deleted existing daily reading record for {$dateString}.");
            }
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
