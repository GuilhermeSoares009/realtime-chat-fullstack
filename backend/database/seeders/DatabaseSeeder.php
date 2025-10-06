<?php

namespace Database\Seeders;

use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        
        $users = User::factory(10)->create();

        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password123'),
        ]);

        $users->take(5)->each(function ($otherUser) use ($testUser) {
            $chat = Chat::create(['type' => 'direct']);
            
            $chat->users()->attach([$testUser->id, $otherUser->id], [
                'joined_at' => now(),
            ]);

            Message::factory(20)->create([
                'chat_id' => $chat->id,
                'user_id' => fake()->randomElement([$testUser->id, $otherUser->id]),
            ]);

            $chat->update([
                'last_message_id' => $chat->messages()->latest()->first()->id,
            ]);
        });
    }
}