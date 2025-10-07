<?php

use App\Models\User;
use App\Models\Chat;
use App\Models\Message;

test('authenticated user can send message to chat', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $chat = Chat::factory()->create(['type' => 'direct']);
    $chat->users()->attach([$user1->id, $user2->id]);

    $response = $this->actingAs($user1, 'sanctum')
        ->postJson("/api/chats/{$chat->id}/messages", [
            'content' => 'Hello, this is a test message!',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message' => ['id', 'content', 'user_id', 'chat_id', 'created_at'],
        ]);

    $this->assertDatabaseHas('messages', [
        'chat_id' => $chat->id,
        'user_id' => $user1->id,
        'content' => 'Hello, this is a test message!',
    ]);
});

test('cannot send empty message', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->create();
    $chat->users()->attach($user->id);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson("/api/chats/{$chat->id}/messages", [
            'content' => '',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['content']);
});

test('cannot send message to chat user is not part of', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    
    $chat = Chat::factory()->create();
    $chat->users()->attach($user2->id);

    $response = $this->actingAs($user1, 'sanctum')
        ->postJson("/api/chats/{$chat->id}/messages", [
            'content' => 'Hello!',
        ]);

    $response->assertStatus(404);
});