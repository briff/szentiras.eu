<?php

namespace SzentirasHu\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SzentirasHu\Http\Controllers\Controller;
use SzentirasHu\Models\Media;
use SzentirasHu\Service\Editor\EditorService;
use Illuminate\Support\Facades\Validator;

class MediaApiController extends Controller
{
    public function __construct(
        protected EditorService $editorService
    ) {}

    /**
     * Move a media item to a new verse location.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function move(Request $request): JsonResponse
    {
        // Check if user is an editor
        if (!$this->editorService->currentIsEditor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        // Validate input
        $validator = Validator::make($request->all(), [
            'media_id' => 'required|integer|exists:media,id',
            'usx_code' => 'required|string|max:10',
            'chapter' => 'required|integer|min:1',
            'verse' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $media = Media::findOrFail($request->input('media_id'));
            
            // Update media location
            $media->usx_code = $request->input('usx_code');
            $media->chapter = $request->input('chapter');
            $media->verse = $request->input('verse');
            $media->save();

            return response()->json([
                'success' => true,
                'message' => 'Media moved successfully',
                'media' => [
                    'id' => $media->id,
                    'usx_code' => $media->usx_code,
                    'chapter' => $media->chapter,
                    'verse' => $media->verse,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to move media: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get media item details.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        // Check if user is an editor
        if (!$this->editorService->currentIsEditor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        try {
            $media = Media::with('mediaType')->findOrFail($id);

            return response()->json([
                'success' => true,
                'media' => [
                    'id' => $media->id,
                    'uuid' => $media->uuid,
                    'filename' => $media->filename,
                    'usx_code' => $media->usx_code,
                    'chapter' => $media->chapter,
                    'verse' => $media->verse,
                    'media_type' => $media->mediaType->name ?? null,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Media not found'
            ], 404);
        }
    }

    /**
     * Get next verse in same chapter.
     *
     * @param string $usxCode
     * @param int $chapter
     * @param int $verse
     * @return JsonResponse
     */
    public function getNextVerse(string $usxCode, int $chapter, int $verse): JsonResponse
    {
        // Check if user is an editor
        if (!$this->editorService->currentIsEditor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        // In a real implementation, we would query the database to get the next verse
        // For now, we'll just return the next verse number
        $nextVerse = $verse + 1;

        return response()->json([
            'success' => true,
            'next_verse' => [
                'usx_code' => $usxCode,
                'chapter' => $chapter,
                'verse' => $nextVerse,
            ]
        ]);
    }

    /**
     * Get previous verse in same chapter.
     *
     * @param string $usxCode
     * @param int $chapter
     * @param int $verse
     * @return JsonResponse
     */
    public function getPreviousVerse(string $usxCode, int $chapter, int $verse): JsonResponse
    {
        // Check if user is an editor
        if (!$this->editorService->currentIsEditor()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Editor privileges required.'
            ], 403);
        }

        // In a real implementation, we would query the database to get the previous verse
        // For now, we'll just return the previous verse number (minimum 1)
        $previousVerse = max(1, $verse - 1);

        return response()->json([
            'success' => true,
            'previous_verse' => [
                'usx_code' => $usxCode,
                'chapter' => $chapter,
                'verse' => $previousVerse,
            ]
        ]);
    }
}