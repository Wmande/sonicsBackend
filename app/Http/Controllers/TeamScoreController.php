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
}
