<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'position' => 'required|string|in:Center,Power Forward,Small Forward,Point Guard,Shooting Guard',
                'team' => 'required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
                'height' => 'required|string|max:255',
                'age' => 'required|integer|min:0',
                'status' => 'required|string|in:Active,Inactive,Injury,Pending',
                'image' => 'nullable|image|max:2048',
            ]);

            $datastore = new DatastoreClient();
            $key = $datastore->key('players');
            $entityData = [
                'Name' => $validated['name'],
                'Position' => $validated['position'],
                'Height' => $validated['height'],
                'Team' => $validated['team'],
                'Age' => (int) $validated['age'],
                'Status' => $validated['status'],
                'createdAt' => new \DateTime(),
            ];

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $storage = new StorageClient();
                $bucket = $storage->bucket('app-one-da1ad.appspot.com');
                $file = $request->file('image');
                $fileName = 'sonicsplayers/' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->getRealPath();

                $object = $bucket->upload(fopen($filePath, 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                ]);

                $entityData['imagePath'] = $fileName;
                $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
            }

            $entity = $datastore->entity($key, $entityData);
            $datastore->upsert($entity);

            return response()->json([
                'success' => true,
                'message' => 'Form data and image saved successfully',
                'imageUrl' => $imageUrl,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validation failed in store');
            return response()->json([
                'success' => false,
                'error' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }



public function index(Request $request)
{
    try {
        $team = $request->get('team');
        
        // Clear cache key when no team specified to ensure we get all data
        $cacheKey = $team ? "players_team_" . md5($team) : "players_all";
        
        // Temporarily disable cache to test if that's the issue
        // $submissions = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($team) {
        
        $datastore = new DatastoreClient();
        $storage = new StorageClient();
        $bucket = $storage->bucket('app-one-da1ad.appspot.com');

        $query = $datastore->query()->kind('players');

        // Only apply team filter if team is specified
        if ($team) {
            $query->filter('Team', '=', $team);
        }

        $results = $datastore->runQuery($query);
        $players = [];

        foreach ($results as $entity) {
            $imageUrl = null;
            if ($entity['imagePath'] ?? null) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                }
            }

            $players[] = [
                'id' => $entity->key()->pathEnd()['id'],
                'Name' => $entity['Name'] ?? '',
                'Position' => $entity['Position'] ?? '',
                'Team' => $entity['Team'] ?? '',
                'Height' => $entity['Height'] ?? '',
                'Age' => $entity['Age'] ?? 0,
                'Status' => $entity['Status'] ?? '',
                'createdAt' => $entity['createdAt'] instanceof \DateTimeInterface
                    ? $entity['createdAt']->format('c') : null,
                'imageUrl' => $imageUrl,
            ];
        }

        // return $players; // Temporarily disable cache
        // });

        return response()->json([
            'success' => true,
            'data' => $players,
        ]);
    } catch (\Exception $e) {
        Log::error('Datastore fetch error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}


    public function destroy(Request $request, $id)
    {
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            $key = $datastore->key('players', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }

            if (isset($entity['imagePath'])) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $object->delete();
                }
            }

            $datastore->delete($key);

            return response()->json([
                'success' => true,
                'message' => 'User data and image deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            $key = $datastore->key('players', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'error' => 'Submission not found',
                ], 404);
            }

            $imageUrl = null;
            if ($entity['imagePath'] ?? null) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                }
            }

            $submission = [
                'id' => $entity->key()->pathEnd()['id'],
                'Name' => $entity['Name'] ?? '',
                'Position' => $entity['Position'] ?? '',
                'Team' => $entity['Team'] ?? '',
                'Height' => $entity['Height'] ?? '',
                'Age' => $entity['Age'] ?? 0,
                'Status' => $entity['Status'] ?? '',
                'createdAt' => $entity['createdAt'] instanceof \DateTimeInterface ? $entity['createdAt']->format('c') : null,
                'imageUrl' => $imageUrl,
            ];

            return response()->json([
                'success' => true,
                'data' => $submission,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'string|max:255',
                'position' => 'string|max:255',
                'team' => 'string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
                'height' => 'string|max:255',
                'age' => 'integer|min:0',
                'status' => 'string|max:255',
                'image' => 'nullable|image|max:2048',
            ]);

            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            $key = $datastore->key('players', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'error' => 'Submission not found',
                ], 404);
            }

            $entityData = [
                'Name' => $validated['name'] ?? $entity['Name'],
                'Position' => $validated['position'] ?? $entity['Position'],
                'Team' => $validated['team'] ?? $entity['Team'],
                'Height' => $validated['height'] ?? $entity['Height'],
                'Age' => isset($validated['age']) ? (int) $validated['age'] : $entity['Age'],
                'Status' => $validated['status'] ?? $entity['Status'],
                'createdAt' => $entity['createdAt'],
            ];

            $imageUrl = null;
            if ($request->hasFile('image')) {
                if (isset($entity['imagePath'])) {
                    $oldObject = $bucket->object($entity['imagePath']);
                    if ($oldObject->exists()) {
                        $oldObject->delete();
                    }
                }

                $file = $request->file('image');
                $fileName = 'sonicsplayers/' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->getRealPath();

                $object = $bucket->upload(fopen($filePath, 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                ]);

                $entityData['imagePath'] = $fileName;
                $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
            } else {
                $entityData['imagePath'] = $entity['imagePath'] ?? null;
                if ($entityData['imagePath']) {
                    $imageUrl = $bucket->object($entityData['imagePath'])->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                }
            }

            $updatedEntity = $datastore->entity($key, $entityData);
            $datastore->upsert($updatedEntity);

            return response()->json([
                'success' => true,
                'message' => 'Submission updated successfully',
                'data' => [
                    'id' => $id,
                    'Name' => $entityData['Name'],
                    'Position' => $entityData['Position'],
                    'Team' => $entityData['Team'],
                    'Height' => $entityData['Height'],
                    'Age' => $entityData['Age'],
                    'Status' => $entityData['Status'],
                    'createdAt' => $entityData['createdAt'] instanceof \DateTimeInterface ? $entityData['createdAt']->format('c') : null,
                    'imageUrl' => $imageUrl,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getPlayersByTeam(Request $request)
{
    $team = $request->query('team');

    $datastore = new DatastoreClient();
    $query = $datastore->query()
        ->kind('players')
        ->filter('Team', '=', $team);


    $results = $datastore->runQuery($query);
    $players = [];

    foreach ($results as $player) {
        $data = $player->get();
        $data['id'] = $player->key()->pathEndIdentifier(); // ensure unique ID
        $players[] = $data;
    }

    return response()->json(['data' => $players]);
}

    public function getAllTeams()
    {
        try {
            $teams = Cache::remember('unique_teams', now()->addMinutes(10), function () {
                $datastore = new DatastoreClient();
                $query = $datastore->query()->kind('players');
                $results = $datastore->runQuery($query);

                $teamNames = [];
                foreach ($results as $entity) {
                    if (isset($entity['Team'])) {
                        $teamNames[] = $entity['Team'];
                    }
                }

                $uniqueTeams = array_values(array_unique($teamNames));
                sort($uniqueTeams);
                return $uniqueTeams;
            });

            return response()->json([
                'success' => true,
                'teams' => $teams,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching team names: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch team names',
            ], 500);
        }
    }

}
