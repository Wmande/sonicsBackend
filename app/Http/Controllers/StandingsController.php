<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StandingsController extends Controller
{
    protected $datastore;

    public function __construct()
    {
        $this->datastore = new DatastoreClient([
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ]);
    }

    // 🟢 Create a new standing entry
    public function store(Request $request)
    {
        $request->validate([
            'team' => 'required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
            'gamesPlayed' => 'required|integer|min:0',
            'won' => 'required|integer|min:0',
            'lost' => 'required|integer|min:0',
            'points' => 'required|integer|min:0',
            'conference' => 'required|string|in:Kimilili,Bungoma',
        ]);

        try {
            $key = $this->datastore->key('standings', $request->input('team')); // Use team as ID

            $entity = $this->datastore->entity($key, [
                'team' => $request->input('team'),
                'conference' => $request->input('conference'),
                'gamesPlayed' => (int)$request->input('gamesPlayed'),
                'won' => (int)$request->input('won'),
                'lost' => (int)$request->input('lost'),
                'points' => (int)$request->input('points'),
                'created_at' => now()->toDateTimeString(),
            ]);

            $this->datastore->upsert($entity);

            return response()->json([
                'success' => true,
                'message' => 'Standing saved successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store standing: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save standing',
            ], 500);
        }
    }

    // 🔵 Get all standings, ordered by points
    public function index()
    {
        try {
            $query = $this->datastore->query()
                ->kind('standings')
                ->order('points', 'DESCENDING');

            $results = $this->datastore->runQuery($query);

            $standings = [];
            foreach ($results as $entity) {
                $standings[] = $entity->get();
            }

            return response()->json([
                'success' => true,
                'data' => $standings,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch standings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching standings',
            ], 500);
        }
    }

    // 🟡 Show one team’s standing
    public function show($team)
    {
        try {
            $key = $this->datastore->key('standings', $team);
            $entity = $this->datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $entity->get(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch team: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching team standing',
            ], 500);
        }
    }

    // 🟠 Update a team’s standing
    public function update(Request $request, $team)
    {
        $request->validate([
            'gamesPlayed' => 'sometimes|required|integer|min:0',
            'won' => 'sometimes|required|integer|min:0',
            'lost' => 'sometimes|required|integer|min:0',
            'points' => 'sometimes|required|integer|min:0',
        ]);

        try {
            $key = $this->datastore->key('standings', $team);
            $entity = $this->datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                ], 404);
            }

            $data = $entity->get();

            // Update only provided fields
            $data['gamesPlayed'] = $request->input('gamesPlayed', $data['gamesPlayed']);
            $data['won'] = $request->input('won', $data['won']);
            $data['lost'] = $request->input('lost', $data['lost']);
            $data['points'] = $request->input('points', $data['points']);

            $updatedEntity = $this->datastore->entity($key, $data);
            $this->datastore->update($updatedEntity, ['allowOverwrite' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Team standing updated',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update team: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error updating team',
            ], 500);
        }
    }

    // 🔴 Delete a team’s standing
    public function destroy($team)
    {
        try {
            $key = $this->datastore->key('standings', $team);
            $entity = $this->datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Team not found',
                ], 404);
            }

            $this->datastore->delete($key);

            return response()->json([
                'success' => true,
                'message' => 'Team standing deleted',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete team: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error deleting team',
            ], 500);
        }
    }
}
