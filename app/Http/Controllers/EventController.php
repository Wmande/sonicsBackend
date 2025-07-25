<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
   protected $bucketName = 'app-one-da1ad.appspot.com';

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:255',
                'location' => 'required|string|max:255',
                'date' => 'required|string|max:255',
                'image' => 'nullable|image|max:2048',
            ]);

            $datastore = new DatastoreClient();
            $key = $datastore->key('Events');
            $now = new \DateTime();

            $imageUrl = null;
            $imagePath = null;

            if ($request->hasFile('image')) {
                $storage = new StorageClient();
                $bucket = $storage->bucket($this->bucketName);
                $file = $request->file('image');
                $fileName = 'laravel/' . uniqid() . '.' . $file->getClientOriginalExtension();

                $object = $bucket->upload(fopen($file->getRealPath(), 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                    'predefinedAcl' => 'publicRead', // 👈 Make it public
                ]);

                $imagePath = $fileName;
                $imageUrl = "https://storage.googleapis.com/{$this->bucketName}/{$fileName}";
            }

            $entityData = [
                'Name' => $validated['name'],
                'Description' => $validated['description'],
                'Location' => $validated['location'],
                'Date' => $validated['date'],
                'createdAt' => $now,
                'updatedAt' => $now,
                'imagePath' => $imagePath,
                'imageUrl' => $imageUrl,
            ];

            $entity = $datastore->entity($key, $entityData);
            $datastore->upsert($entity);

            Cache::forget('events_list');

            return response()->json([
                'success' => true,
                'message' => 'Event saved successfully',
                'imageUrl' => $imageUrl,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', $e->errors());
            return response()->json(['success' => false, 'error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error in store: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string|max:255',
                'location' => 'sometimes|required|string|max:255',
                'date' => 'sometimes|required|string|max:255',
                'image' => 'nullable|image|max:2048',
            ]);

            $datastore = new DatastoreClient();
            $key = $datastore->key('Events', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json(['success' => false, 'error' => 'Event not found'], 404);
            }

            $entityData = $entity->get();
            $now = new \DateTime();

            if ($request->has('name')) $entityData['Name'] = $validated['name'];
            if ($request->has('description')) $entityData['Description'] = $validated['description'];
            if ($request->has('location')) $entityData['Location'] = $validated['location'];
            if ($request->has('date')) $entityData['Date'] = $validated['date'];
            $entityData['updatedAt'] = $now;

            $imageUrl = $entityData['imageUrl'] ?? null;

            if ($request->hasFile('image')) {
                $storage = new StorageClient();
                $bucket = $storage->bucket($this->bucketName);

                if (isset($entityData['imagePath'])) {
                    $oldObject = $bucket->object($entityData['imagePath']);
                    if ($oldObject->exists()) {
                        $oldObject->delete();
                    }
                }

                $file = $request->file('image');
                $fileName = 'laravel/' . uniqid() . '.' . $file->getClientOriginalExtension();

                $object = $bucket->upload(fopen($file->getRealPath(), 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                    'predefinedAcl' => 'publicRead', // 👈 Make it public
                ]);

                $entityData['imagePath'] = $fileName;
                $imageUrl = "https://storage.googleapis.com/{$this->bucketName}/{$fileName}";
                $entityData['imageUrl'] = $imageUrl;
            }

            $entity->set($entityData);
            $datastore->upsert($entity);

            Cache::forget('events_list');

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'imageUrl' => $imageUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', $e->errors());
            return response()->json(['success' => false, 'error' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index()
    {
        try {
            $data = Cache::remember('events_list', 60, function () {
                $datastore = new DatastoreClient();
                $query = $datastore->query()
                    ->kind('Events')
                    ->order('updatedAt', 'DESCENDING');

                $results = $datastore->runQuery($query);

                $submissions = [];
                foreach ($results as $entity) {
                    $submissions[] = [
                        'id' => $entity->key()->pathEnd()['id'],
                        'name' => $entity['Name'] ?? '',
                        'description' => $entity['Description'] ?? '',
                        'location' => $entity['Location'] ?? '',
                        'date' => $entity['Date'] ?? '',
                        'createdAt' => isset($entity['createdAt']) ? $entity['createdAt']->format('c') : null,
                        'updatedAt' => isset($entity['updatedAt']) ? $entity['updatedAt']->format('c') : null,
                        'imageUrl' => $entity['imageUrl'] ?? null,
                    ];
                }

                return $submissions;
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching submissions: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket($this->bucketName);

            $key = $datastore->key('Events', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json(['success' => false, 'error' => 'Event not found'], 404);
            }

            if (isset($entity['imagePath'])) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $object->delete();
                }
            }

            $datastore->delete($key);
            Cache::forget('events_list'); // Invalidate cache

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        try {
            $datastore = new DatastoreClient();
            $key = $datastore->key('Events', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json(['success' => false, 'error' => 'Event not found'], 404);
            }

            $eventData = [
                'id' => $entity->key()->pathEnd()['id'],
                'name' => $entity['Name'] ?? '',
                'description' => $entity['Description'] ?? '',
                'location' => $entity['Location'] ?? '',
                'date' => $entity['Date'] ?? '',
                'createdAt' => isset($entity['createdAt']) ? $entity['createdAt']->format('c') : null,
                'updatedAt' => isset($entity['updatedAt']) ? $entity['updatedAt']->format('c') : null,
                'imageUrl' => $entity['imageUrl'] ?? null,
            ];

            return response()->json([
                'success' => true,
                'data' => $eventData,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
