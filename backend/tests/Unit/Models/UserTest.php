<?php

use App\Models\User;
use App\Models\Chat;

test('user has many chats', function () {
    $user = User::factory()->create();
    $chat = Chat::factory()->create();
    $chat->users()->attach($user->id);

    expect($user->chats)->toHaveCount(1);
    expect($user->chats->first()->id)->toBe($chat->id);
});

test('user can update online status', function () {
    $user = User::factory()->create(['is_online' => false]);

    $user->updateOnlineStatus(true);

    expect($user->fresh()->is_online)->toBeTrue();
    expect($user->fresh()->last_seen_at)->not->toBeNull();
});