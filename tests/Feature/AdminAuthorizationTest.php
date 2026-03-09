<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_routes_return_401_when_not_authenticated(): void
    {
        // usa una rotta admin che esiste sicuramente
        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(401);
    }

    public function test_admin_routes_return_403_when_authenticated_but_not_admin(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/products');

        $response->assertStatus(403);
    }
}

