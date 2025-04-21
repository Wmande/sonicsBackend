<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EventController extends Controller
{
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
            $now = new \DateTime(); // Current timestamp
            $entityData = [
                'Name' => $validated['name'],
                'Description' => $validated['description'],
                'Location' => $validated['location'],
                'Date' => $validated['date'],
                'createdAt' => $now,
                'updatedAt' => $now, // Add updatedAt on creation
            ];

            $imageUrl = null;
            if ($request->hasFile('image')) {
                $storage = new StorageClient();
                $bucket = $storage->bucket('app-one-da1ad.appspot.com');
                $file = $request->file('image');
                $fileName = 'laravel/' . uniqid() . '.' . $file->getClientOriginalExtension();
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
                'message' => 'Events data and image saved successfully',
                'imageUrl' => $imageUrl,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', $e->errors());
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

    public function index()
    {
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            // Query events and order by updatedAt descending
            $query = $datastore->query()
                ->kind('Events')
                ->order('updatedAt', 'DESCENDING'); // Sort by updatedAt
            $results = $datastore->runQuery($query);

            $submissions = [];
            foreach ($results as $entity) {
                $imageUrl = null;
                if ($entity['imagePath'] ?? null) {
                    $object = $bucket->object($entity['imagePath']);
                    if ($object->exists()) {
                        $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                    }
                }

                $submissions[] = [
                    'id' => $entity->key()->pathEnd()['id'],
                    'name' => $entity['Name'] ?? '',
                    'description' => $entity['Description'] ?? '',
                    'location' => $entity['Location'] ?? '',
                    'date' => $entity['Date'] ?? '',
                    'createdAt' => $entity['createdAt'] instanceof \DateTimeInterface ? $entity['createdAt']->format('c') : null,
                    'updatedAt' => $entity['updatedAt'] instanceof \DateTimeInterface ? $entity['updatedAt']->format('c') : null, // Add updatedAt to response
                    'imageUrl' => $imageUrl,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $submissions,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching submissions: ' . $e->getMessage());
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
                return response()->json([
                    'success' => false,
                    'error' => 'Event not found',
                ], 404);
            }

            $entityData = $entity->get();
            $now = new \DateTime();

            // Update fields if provided
            if ($request->has('name')) $entityData['Name'] = $validated['name'];
            if ($request->has('description')) $entityData['Description'] = $validated['description'];
            if ($request->has('location')) $entityData['Location'] = $validated['location'];
            if ($request->has('date')) $entityData['Date'] = $validated['date'];
            $entityData['updatedAt'] = $now; // Always update updatedAt

            $imageUrl = $entity['imageUrl'] ?? null;
            if ($request->hasFile('image')) {
                $storage = new StorageClient();
                $bucket = $storage->bucket('app-one-da1ad.appspot.com');

                // Delete old image if it exists
                if (isset($entity['imagePath'])) {
                    $oldObject = $bucket->object($entity['imagePath']);
                    if ($oldObject->exists()) {
                        $oldObject->delete();
                    }
                }

                $file = $request->file('image');
                $fileName = 'laravel/' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->getRealPath();

                $object = $bucket->upload(fopen($filePath, 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                ]);

                $entityData['imagePath'] = $fileName;
                $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
            }

            $entity->set($entityData);
            $datastore->upsert($entity);

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'imageUrl' => $imageUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', $e->errors());
            return response()->json([
                'success' => false,
                'error' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        // No changes needed here; already works as expected
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            $key = $datastore->key('Events', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'error' => 'Event not found',
                ], 404);
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
                'message' => 'Event data and image deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        // Minor tweak to include updatedAt
        try {
            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            $key = $datastore->key('Events', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'error' => 'Event not found',
                ], 404);
            }

            $imageUrl = null;
            if (isset($entity['imagePath'])) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                }
            }

            $eventData = [
                'id' => $entity->key()->pathEnd()['id'],
                'name' => $entity['Name'] ?? '',
                'description' => $entity['Description'] ?? '',
                'location' => $entity['Location'] ?? '',
                'date' => $entity['Date'] ?? '',
                'createdAt' => $entity['createdAt'] instanceof \DateTimeInterface ? $entity['createdAt']->format('c') : null,
                'updatedAt' => $entity['updatedAt'] instanceof \DateTimeInterface ? $entity['updatedAt']->format('c') : null, // Add updatedAt
                'imageUrl' => $imageUrl,
            ];

            return response()->json([
                'success' => true,
                'data' => $eventData,
            ]);
        } catch (\Exception $e) {
            // Log::error('Error fetching event: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
