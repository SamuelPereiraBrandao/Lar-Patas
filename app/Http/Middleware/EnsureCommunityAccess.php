<?php

namespace App\Http\Middleware;

use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\FriendRequest;
use App\Models\ProfilePost;
use App\Models\User;
use App\Models\UserBlock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCommunityAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $viewer = $request->user()?->id;
        if ($viewer && ! $request->is('api/admin/*') && ! $request->is('api/safety/*')) {
            $targets = [];
            foreach ($request->route()->parameters() as $resource) {
                if ($resource instanceof User) {
                    $targets[] = $resource->id;
                }
                if ($resource instanceof ProfilePost) {
                    $targets[] = $resource->user_id;
                }
                if ($resource instanceof DirectMessage) {
                    $resource = $resource->conversation;
                }
                if ($resource instanceof DirectConversation) {
                    $targets[] = $resource->user_one_id;
                    $targets[] = $resource->user_two_id;
                }
                if ($resource instanceof FriendRequest) {
                    $targets[] = $resource->sender_id;
                    $targets[] = $resource->recipient_id;
                }
            }
            foreach (array_unique($targets) as $id) {
                abort_if($id !== $viewer && UserBlock::between($viewer, $id), 403, 'Esta interação está indisponível por um bloqueio entre as contas.');
            }
        }

        return $next($request);
    }
}
