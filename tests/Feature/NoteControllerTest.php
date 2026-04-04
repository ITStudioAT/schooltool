<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    }

    public function test_it_can_list_notes_for_authenticated_user()
    {
        Note::factory()->count(3)->create(['user_id' => $this->user->id]);
        Note::factory()->count(2)->create(); // Other user's notes

        $response = $this->getJson('/api/homepage/notes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'title', 'content', 'is_pinned', 'user_id', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_it_can_create_a_note()
    {
        $noteData = [
            'title' => 'Test Note',
            'content' => 'This is a test note content.',
            'is_pinned' => true,
        ];

        $response = $this->postJson('/api/homepage/notes', $noteData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'content', 'is_pinned', 'user_id'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Test Note',
                    'content' => 'This is a test note content.',
                    'is_pinned' => true,
                    'user_id' => $this->user->id,
                ],
            ]);

        $this->assertDatabaseHas('notes', [
            'title' => 'Test Note',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_it_validates_note_creation()
    {
        $response = $this->postJson('/api/homepage/notes', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'errors' => ['title', 'content'],
            ]);
    }

    public function test_it_can_show_a_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/homepage/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'title', 'content', 'is_pinned', 'user_id'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $note->id,
                    'title' => $note->title,
                ],
            ]);
    }

    public function test_it_cannot_show_another_users_note()
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->getJson("/api/homepage/notes/{$note->id}");

        $response->assertStatus(403);
    }

    public function test_it_can_update_a_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $updateData = [
            'title' => 'Updated Title',
            'content' => 'Updated content.',
            'is_pinned' => true,
        ];

        $response = $this->putJson("/api/homepage/notes/{$note->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'title', 'content', 'is_pinned', 'user_id'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Title',
                    'content' => 'Updated content.',
                    'is_pinned' => true,
                ],
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Updated Title',
        ]);
    }

    public function test_it_cannot_update_another_users_note()
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->putJson("/api/homepage/notes/{$note->id}", [
            'title' => 'Hacked Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_it_can_delete_a_note()
    {
        $note = Note::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/homepage/notes/{$note->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Note deleted successfully',
            ]);

        // Check that the note is soft deleted
        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_it_cannot_delete_another_users_note()
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/homepage/notes/{$note->id}");

        $response->assertStatus(403);
    }

    public function test_it_can_toggle_pin_status()
    {
        $note = Note::factory()->create([
            'user_id' => $this->user->id,
            'is_pinned' => false,
        ]);

        $response = $this->postJson("/api/homepage/notes/{$note->id}/toggle-pin");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'is_pinned'],
            ])
            ->assertJson([
                'success' => true,
                'data' => ['is_pinned' => true],
            ]);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'is_pinned' => true,
        ]);
    }

    public function test_it_cannot_toggle_pin_for_another_users_note()
    {
        $otherUser = User::factory()->create();
        $note = Note::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->postJson("/api/homepage/notes/{$note->id}/toggle-pin");

        $response->assertStatus(403);
    }
}
