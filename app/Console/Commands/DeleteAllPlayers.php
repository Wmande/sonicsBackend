<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\Datastore\DatastoreClient;

class DeleteAllPlayers extends Command
{
    protected $signature = 'datastore:delete-players';
    protected $description = 'Delete all players from Datastore';

    public function handle()
    {
        $datastore = new DatastoreClient();
        $query = $datastore->query()->kind('players');
        $entities = $datastore->runQuery($query);

        $count = 0;
        foreach ($entities as $entity) {
            $datastore->delete($entity->key());
            $count++;
        }

        $this->info("Deleted $count entities from kind: players");
    }
}
