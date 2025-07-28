<?php

namespace App\Http\Controllers;

use Google\Cloud\Storage\StorageClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    protected $storage;
    protected $bucket;

    public function __construct()
    {
        $this->storage = new StorageClient([
            'projectId' => env('GOOGLE_CLOUD_PROJECT_ID'),
        ]);

        $this->bucket = $this->storage->bucket(env('GOOGLE_CLOUD_STORAGE_BUCKET'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'images.*' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB limit per file
        ]);

        try {
            $uploadedUrls = [];

            foreach ($request->file('images') as $image) {
                $filename = 'bblGallery/' . Str::uuid() . '.' . $image->getClientOriginalExtension();
                $stream = fopen($image->getRealPath(), 'r');

                $object = $this->bucket->upload($stream, [
                    'name' => $filename,
                    'predefinedAcl' => 'publicRead', // optional: make image publicly accessible
                ]);

                $uploadedUrls[] = [
                    'name' => $filename,
                    'url' => sprintf("https://storage.googleapis.com/%s/%s", $this->bucket->name(), $filename),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Images uploaded successfully',
                'files' => $uploadedUrls,
            ]);
        } catch (\Exception $e) {
            Log::error('Image upload failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
            ], 500);
        }
    }

    public function delete(Request $request)
{
    $request->validate([
        'filename' => 'required|string',
    ]);

    try {
        $object = $this->bucket->object($request->input('filename'));

        if ($object->exists()) {
            $object->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }
    } catch (\Exception $e) {
        Log::error('Image deletion failed: ' . $e->getMessage());

        return response()->json([
            'success' => false,
            'message' => 'Failed to delete image',
        ], 500);
    }
}

}
