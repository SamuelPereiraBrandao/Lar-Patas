<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Jobs\PublishAblyMessage;
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
        $messages = $pet->messages()->with('user:id,name,avatar_path,city,state')->latest()->paginate(5);

        return response()->json(['data' => $messages->getCollection()->reverse()->values(), 'pagination' => ['current_page' => $messages->currentPage(), 'has_more' => $messages->hasMorePages()]]);
    }

    public function storeMessage(Request $request, Pet $pet): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:1000']);
        $message = $pet->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $messageData = $message->load('user:id,name,avatar_path,city,state');

        PublishAblyMessage::dispatch("pet:{$pet->id}:messages", $messageData->toArray());

        return response()->json(['data' => $messageData], 201);
    }
}
