<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Google\Cloud\Datastore\Query;



class TeamScoreController extends Controller
{
    protected $datastore;

    public function __construct()
    {
        $this->datastore = new DatastoreClient([
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ]);
    }

    // Store a recent match score (teamA, teamAscores, teamB, teamBscores)
    public function store(Request $request)
    {
        $request->validate([
            'teamA' => 'required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
            'teamAscores' => 'required|integer|min:0',
            'teamB' => 'required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
            'teamBscores' => 'required|integer|min:0',
            'match_date' => 'nullable|date', // optional for future expansion
        ]);

        try {
            $key = $this->datastore->key('recentScores');

            $entity = $this->datastore->entity($key, [
                'teamA' => $request->input('teamA'),
                'teamAscores' => (int)$request->input('teamAscores'),
                'teamB' => $request->input('teamB'),
                'teamBscores' => (int)$request->input('teamBscores'),
                'match_date' => $request->input('match_date') ?? now()->toDateString(),
                'created_at' => now()->toDateTimeString(),
            ]);

            $this->datastore->insert($entity);

            return response()->json([
                'success' => true,
                'message' => 'Match score saved successfully',
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to store recent score: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save recent score',
            ], 500);
        }
    }

    // Get all recent match scores (latest first)
    public function index()
    {
        try {
            $query = $this->datastore->query()
                ->kind('recentScores')
                ->order('created_at', 'DESCENDING');


            $results = $this->datastore->runQuery($query);

            $scores = [];
            foreach ($results as $entity) {
                $scores[] = $entity->get();
            }

            return response()->json([
                'success' => true,
                'data' => $scores,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch recent scores: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching scores',
            ], 500);
        }
    }

    public function update(Request $request, $id)
{
    $request->validate([
        'teamA' => 'sometimes|required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
        'teamAscores' => 'sometimes|required|integer|min:0',
        'teamB' => 'sometimes|required|string|in:Bungoma Poly,Cheptais Hunters,Chetambe Bulls,Team Seven,Jogoo Club,FSK Tigers,Kisiwa Rockets,Malaba Hawks',
        'teamBscores' => 'sometimes|required|integer|min:0',
        'match_date' => 'sometimes|date',
    ]);

    try {
        $key = $this->datastore->key('recentScores', (int)$id);
        $entity = $this->datastore->lookup($key);

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'Match score not found',
            ], 404);
        }

        // Only update provided fields
        foreach (['teamA', 'teamAscores', 'teamB', 'teamBscores', 'match_date'] as $field) {
            if ($request->filled($field)) {
                $entity[$field] = $request->input($field);
            }
        }

        $this->datastore->update($entity);

        return response()->json([
            'success' => true,
            'message' => 'Match score updated successfully',
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to update recent score: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to update score',
        ], 500);
    }
}

public function destroy($id)
{
    try {
        $key = $this->datastore->key('recentScores', (int)$id);
        $entity = $this->datastore->lookup($key);

        if (!$entity) {
            return response()->json([
                'success' => false,
                'message' => 'Match score not found',
            ], 404);
        }

        $this->datastore->delete($key);

        return response()->json([
            'success' => true,
            'message' => 'Match score deleted successfully',
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to delete recent score: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete score',
        ], 500);
    }
}

}
