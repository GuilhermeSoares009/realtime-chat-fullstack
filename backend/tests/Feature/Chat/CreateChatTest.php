<?php

use App\Models\User;
use App\Models\Chat;

test('authenticated user can create direct chat', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $response = $this->actingAs($user1, 'sanctum')
        ->postJson('/api/chats/direct', [
            'user_id' => $user2->id,
        ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'chat' => ['id', 'type', 'users'],
        ]);

    $this->assertDatabaseHas('chats', [
        'type' => 'direct',
    ]);

    $this->assertDatabaseHas('chat_user', [
        'user_id' => $user1->id,
    ]);

    $this->assertDatabaseHas('chat_user', [
        'user_id' => $user2->id,
    ]);
});

test('cannot create chat with yourself', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/chats/direct', [
            'user_id' => $user->id,
        ]);

    $response->assertStatus(422);
});

test('unauthenticated user cannot create chat', function () {
    $user = User::factory()->create();

    $response = $this->postJson('/api/chats/direct', [
        'user_id' => $user->id,
    ]);

    $response->assertStatus(401);
});