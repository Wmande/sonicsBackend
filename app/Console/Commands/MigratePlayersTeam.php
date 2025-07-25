<?php

namespace App\Console\Commands;

use Google\Cloud\Datastore\DatastoreClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigratePlayersTeam extends Command
{
    protected $signature = 'migrate:players-team';
    protected $description = 'Add Team property to existing players entities in Datastore';

    public function handle()
    {
        try {
            $datastore = new DatastoreClient();
            $query = $datastore->query()->kind('players');
            $results = $datastore->runQuery($query);

            $updatedCount = 0;

            foreach ($results as $entity) {
                if (!isset($entity['Team'])) {
                    $key = $entity->key();
                    $entityData = $entity->get();
                    $entityData['Team'] = 'Unknown'; // Set default value or derive based on other fields

                    // Optionally, derive Team based on other logic (e.g., based on Name or other properties)
                    // Example: $entityData['Team'] = $this->deriveTeam($entityData);

                    Log::info('Updating entity:', ['id' => $key->pathEnd()['id'], 'entityData' => $entityData]);

                    $datastore->upsert($datastore->entity($key, $entityData));
                    $updatedCount++;
                }
            }

            $this->info("Migration completed. Updated $updatedCount entities with Team property.");
        } catch (\Google\Cloud\Core\Exception\GoogleException $e) {
            Log::error('Datastore error during migration: ' . $e->getMessage());
            $this->error('Migration failed: Datastore operation failed');
        } catch (\Exception $e) {
            Log::error('Migration error: ' . $e->getMessage());
            $this->error('Migration failed: ' . $e->getMessage());
        }
    }

    // Optional: Add logic to derive Team value if needed
    /*
    private function deriveTeam(array $entityData): string
    {
        // Example logic to assign Team based on other fields
        return 'Unknown';
    }
    */
}