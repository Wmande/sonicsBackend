<?php

namespace App\Http\Controllers;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function store(Request $request)
    {
        try {
            // Log incoming request data for debugging
            Log::info('Incoming request data', $request->all());

            // Validation rules matching the frontend form
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'position' => 'required|string|in:Center,Power Forward,Small Forward,Point Guard,Shooting Guard',
                'height' => 'required|string|max:255',
                'age' => 'required|integer|min:0',
                'status' => 'required|string|in:Active,Inactive,Injury,Pending', // Match dropdown options
                'image' => 'nullable|image|max:2048', // Optional image, max 2MB
            ]);

            $datastore = new DatastoreClient();
            $key = $datastore->key('players'); // Using 'players' kind as per your controller
            $entityData = [
                'Name' => $validated['name'],
                'Position' => $validated['position'],
                'Height' => $validated['height'],
                'Age' => (int) $validated['age'], // Cast to integer
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

            $query = $datastore->query()
                ->kind('players')
                ->order('createdAt', 'DESCENDING');
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
                    'Name' => $entity['Name'] ?? '',
                    'Position' => $entity['Position'] ?? '',
                    'Height' => $entity['Height'] ?? '',
                    'Age' => $entity['Age'] ?? 0,
                    'Status' => $entity['Status'] ?? '',
                    'createdAt' => $entity['createdAt'] instanceof \DateTimeInterface ? $entity['createdAt']->format('c') : null,
                    'imageUrl' => $imageUrl,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $submissions,
            ]);
        } catch (\Exception $e) {
            Log::error('Something went wrong: ' . $e->getMessage());
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

            // Lookup the entity by ID
            $key = $datastore->key('players', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                return response()->json([
                    'success' => false,
                    'error' => 'User not found',
                ], 404);
            }

            // Delete the image from GCS if it exists
            if (isset($entity['imagePath'])) {
                $object = $bucket->object($entity['imagePath']);
                if ($object->exists()) {
                    $object->delete();
                }
            }

            // Delete the entity from Datastore
            $datastore->delete($key);

            return response()->json([
                'success' => true,
                'message' => 'User data and image deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting user: ' . $e->getMessage());
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
            Log::error('Something went wrong: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Log the incoming request data
            Log::info('Update request received', ['id' => $id, 'data' => $request->all()]);

            $validated = $request->validate([
                'name' => 'string|max:255',
                'position' => 'string|max:255',
                'height' => 'string|max:255',
                'age' => 'integer|min:0',
                'status' => 'string|max:255',
                'image' => 'nullable|image|max:2048',
            ]);

            $datastore = new DatastoreClient();
            $storage = new StorageClient();
            $bucket = $storage->bucket('app-one-da1ad.appspot.com');

            // Lookup the existing entity by ID
            $key = $datastore->key('players', (int) $id);
            $entity = $datastore->lookup($key);

            if (!$entity) {
                Log::warning('Something went wrong', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'error' => 'Submission not found',
                ], 404);
            }

            // Log the current entity data
            Log::info('Current entity data', $entity->get());

            // Prepare updated entity data, merging with existing values
            $entityData = [
                'Name' => $validated['name'] ?? $entity['Name'],
                'Position' => $validated['position'] ?? $entity['Position'],
                'Height' => $validated['height'] ?? $entity['Height'],
                'Age' => isset($validated['age']) ? (int) $validated['age'] : $entity['Age'],
                'Status' => $validated['status'] ?? $entity['Status'],
                'createdAt' => $entity['createdAt'], // Preserve original creation date
            ];

            $imageUrl = $entity['imagePath'] ? $bucket->object($entity['imagePath'])->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']) : null;

            // Handle image upload if provided
            if ($request->hasFile('image')) {
                // Delete the old image from GCS if it exists
                if (isset($entity['imagePath'])) {
                    $oldObject = $bucket->object($entity['imagePath']);
                    if ($oldObject->exists()) {
                        $oldObject->delete();
                        Log::info('Old image deleted', ['path' => $entity['imagePath']]);
                    }
                }

                // Upload the new image
                $file = $request->file('image');
                $fileName = 'sonicsplayers/' . uniqid() . '.' . $file->getClientOriginalExtension();
                $filePath = $file->getRealPath();

                $object = $bucket->upload(fopen($filePath, 'r'), [
                    'name' => $fileName,
                    'metadata' => ['contentType' => $file->getMimeType()],
                ]);

                $entityData['imagePath'] = $fileName;
                $imageUrl = $object->signedUrl(new \DateTime('+1 hour'), ['version' => 'v4']);
                Log::info('New image uploaded', ['path' => $fileName]);
            } else {
                $entityData['imagePath'] = $entity['imagePath'] ?? null;
            }

            // Log the data to be updated
            Log::info('Updated entity data', $entityData);

            // Update the entity in Datastore
            $updatedEntity = $datastore->entity($key, $entityData);
            $datastore->upsert($updatedEntity);

            // Confirm the update was applied by fetching the entity again
            $postUpdateEntity = $datastore->lookup($key);
            Log::info('Entity after update', $postUpdateEntity->get());

            return response()->json([
                'success' => true,
                'message' => 'Submission updated successfully',
                'data' => [
                    'id' => $id,
                    'Name' => $entityData['Name'],
                    'Position' => $entityData['Position'],
                    'Height' => $entityData['Height'],
                    'Age' => $entityData['Age'],
                    'Status' => $entityData['Status'],
                    'createdAt' => $entityData['createdAt'] instanceof \DateTimeInterface ? $entityData['createdAt']->format('c') : null,
                    'imageUrl' => $imageUrl,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Something went wrong: ' . $e->getMessage(), ['id' => $id]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
