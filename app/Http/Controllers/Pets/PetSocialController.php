<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetSocialController extends Controller
{
    public function toggleLike(Request $request, Pet $pet): JsonResponse
    {
        $like = $pet->likes()->where('user_id', $request->user()->id)->first();
        if ($like) {
            $like->delete();
        } else {
            $pet->likes()->create(['user_id' => $request->user()->id]);
        }

        return response()->json(['liked' => ! $like, 'likes_count' => $pet->likes()->count()]);
    }

    public function messages(Pet $pet): JsonResponse
    {
        return response()->json(['data' => $pet->messages()->with('user:id,name,avatar_path')->latest()->take(30)->get()->reverse()->values()]);
    }

    public function storeMessage(Request $request, Pet $pet): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $message = $pet->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return response()->json(['data' => $message->load('user:id,name,avatar_path')], 201);
    }
}
