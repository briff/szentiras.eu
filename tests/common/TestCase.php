<?php

namespace SzentirasHu\Test\Common;

class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    /**
     * The base URL to use while testing the application.
     *
     * @var string
     */
    protected $baseUrl = 'http://localhost';

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Reset all PostgreSQL sequences to start after the highest existing ID.
     * This prevents ID collisions when seeders create records with specific IDs.
     */
    protected function resetPostgresSequences(): void
    {
        $connection = \DB::connection('bible');
        $prefix = config('database.connections.bible.prefix');

        // Get all sequences for tables with the test prefix
        $sequences = $connection->select(
            "SELECT sequence_name FROM information_schema.sequences WHERE sequence_name LIKE ?",
            [$prefix . '%']
        );

        foreach ($sequences as $sequence) {
            // Extract table name from sequence name (e.g., testing_translations_id_seq -> testing_translations)
            $tableName = preg_replace('/_id_seq$/', '', $sequence->sequence_name);
            
            // Check if table exists first
            $tableExists = $connection->selectOne(
                "SELECT EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = ?)",
                [$tableName]
            );
            
            if (!$tableExists->exists) {
                continue;
            }
            
            // Get the maximum ID in the table
            $maxId = $connection->selectOne(
                "SELECT COALESCE(MAX(id), 0) as max_id FROM {$tableName}"
            );
            
            // Set sequence to start after the maximum ID
            $nextId = ($maxId->max_id ?? 0) + 1;
            $connection->statement("ALTER SEQUENCE {$sequence->sequence_name} RESTART WITH {$nextId}");
        }
    }
}
