<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunityFeedCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_feed_includes_the_comments_and_authors_counted_on_each_post(): void
    {
        $author = User::factory()->create();
        $commenter = User::factory()->create(['avatar_path' => 'profiles/avatars/example.jpg']);
        $post = $author->profilePosts()->create(['body' => 'A day with my pet', 'image_path' => 'pets/example.jpg']);
        foreach (['First comment', 'Second comment', 'Third comment'] as $body) {
            $post->comments()->create(['user_id' => $commenter->id, 'body' => $body]);
        }

        $this->getJson('/api/community/feed')
            ->assertOk()
            ->assertJsonPath('data.0.id', $post->id)
            ->assertJsonPath('data.0.comments_count', 3)
            ->assertJsonCount(3, 'data.0.comments')
            ->assertJsonPath('data.0.comments.0.body', 'First comment')
            ->assertJsonPath('data.0.comments.0.user.name', $commenter->name)
            ->assertJsonPath('data.0.comments.0.user.avatar_url', $commenter->avatar_url)
            ->assertJsonMissingPath('data.0.comments.0.user.email');
    }

    public function test_a_comment_submitted_from_the_feed_appears_with_its_updated_count(): void
    {
        Queue::fake();
        $author = User::factory()->create();
        $commenter = User::factory()->create();
        $post = $author->profilePosts()->create(['body' => 'A day with my pet']);

        $this->actingAs($commenter, 'sanctum')
            ->postJson("/api/profile/posts/{$post->id}/comments", ['body' => 'Lovely pet!'])
            ->assertCreated()
            ->assertJsonPath('comment.body', 'Lovely pet!')
            ->assertJsonPath('comment.user.name', $commenter->name);

        $this->getJson('/api/community/feed')
            ->assertOk()
            ->assertJsonPath('data.0.comments_count', 1)
            ->assertJsonCount(1, 'data.0.comments')
            ->assertJsonPath('data.0.comments.0.body', 'Lovely pet!');
    }
}
