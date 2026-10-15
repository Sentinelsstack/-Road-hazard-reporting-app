<?php

namespace Tests\Feature;

use App\Models\Hazard;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoadHazardApiTest extends TestCase
{
    /* ============================
       Auth Tests
       ============================ */

    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'role' => 'user']);
    }

    public function test_registration_rejects_short_password()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'short1',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_rejects_common_password()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_user_can_login()
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    public function test_login_rejects_invalid_credentials()
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrong-password-here',
        ]);

        $response->assertStatus(422);
    }

    public function test_suspended_user_cannot_login()
    {
        $user = User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => Hash::make('correct-horse-battery'),
            'suspended' => true,
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'suspended@example.com',
            'password' => 'correct-horse-battery',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['email' => ['Your account has been suspended. Contact an administrator.']]);
    }

    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/logout');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_requests_are_rejected()
    {
        $response = $this->getJson('/api/hazards');
        $response->assertStatus(401);

        $response = $this->postJson('/api/hazards', []);
        $response->assertStatus(401);
    }

    /* ============================
       Hazard CRUD Tests
       ============================ */

    public function test_user_can_create_hazard()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/hazards', [
                'type' => 'pothole',
                'description' => 'Large pothole on the main road',
                'lat' => 14.5995,
                'lng' => 120.9842,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'type' => 'pothole',
                'status' => 'pending',
                'user_id' => $user->id,
            ]);

        $this->assertDatabaseHas('hazards', ['type' => 'pothole', 'user_id' => $user->id]);
    }

    public function test_user_can_list_hazards()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        Hazard::factory()->count(5)->create(['user_id' => $user->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/hazards');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_user_can_view_own_hazard()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $hazard = Hazard::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/hazards/' . $hazard->id);

        $response->assertStatus(200)
            ->assertJson(['id' => $hazard->id]);
    }

    public function test_user_can_update_own_hazard()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $hazard = Hazard::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/hazards/' . $hazard->id, [
                'description' => 'Updated description',
            ]);

        $response->assertStatus(200)
            ->assertJson(['description' => 'Updated description']);

        $this->assertDatabaseHas('hazards', ['id' => $hazard->id, 'description' => 'Updated description']);
    }

    public function test_user_cannot_update_others_hazard()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $other->createToken('test')->plainTextToken;
        $hazard = Hazard::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/hazards/' . $hazard->id, [
                'description' => 'Should not work',
            ]);

        $response->assertStatus(403);
    }

    public function test_user_can_delete_own_hazard()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $hazard = Hazard::factory()->create(['user_id' => $user->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson('/api/hazards/' . $hazard->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('hazards', ['id' => $hazard->id]);
    }

    public function test_user_cannot_delete_others_hazard()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $other->createToken('test')->plainTextToken;
        $hazard = Hazard::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->deleteJson('/api/hazards/' . $hazard->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('hazards', ['id' => $hazard->id]);
    }

    /* ============================
       Admin Access Control Tests
       ============================ */

    public function test_non_admin_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_list_users()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_update_user_role()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/admin/users/' . $target->id, ['role' => 'admin']);

        $response->assertStatus(403);
    }

    public function test_admin_can_suspend_user()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $target = User::factory()->create(['role' => 'user']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/users/' . $target->id . '/suspend');

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $target->id, 'suspended' => true]);
    }

    public function test_admin_cannot_suspend_another_admin()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/admin/users/' . $otherAdmin->id . '/suspend');

        $response->assertStatus(422);
    }

    public function test_admin_can_update_any_hazard()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;
        $owner = User::factory()->create(['role' => 'user']);
        $hazard = Hazard::factory()->create(['user_id' => $owner->id]);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->putJson('/api/hazards/' . $hazard->id, ['status' => 'resolved']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('hazards', ['id' => $hazard->id, 'status' => 'resolved']);
    }

    /* ============================
       Image Upload Security Tests
       ============================ */

    public function test_valid_image_upload_is_accepted()
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('hazard.jpg', 100, 100);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/hazards', [
                'type' => 'pothole',
                'description' => 'Test hazard with image',
                'lat' => 14.5995,
                'lng' => 120.9842,
                'image' => $file,
            ]);

        $response->assertStatus(201);

        $hazard = Hazard::latest()->first();
        $this->assertNotNull($hazard->image_path);
        Storage::disk('private')->assertExists('hazards/' . $hazard->image_path);
    }

    public function test_php_file_renamed_as_image_is_rejected()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('malicious.php.jpg', 100, 'image/jpeg');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/hazards', [
                'type' => 'pothole',
                'description' => 'Trying to upload PHP file',
                'lat' => 14.5995,
                'lng' => 120.9842,
                'image' => $file,
            ]);

        $response->assertStatus(201);

        $hazard = Hazard::latest()->first();
        $storedPath = 'hazards/' . $hazard->image_path;
        $storedExtension = pathinfo($hazard->image_path, PATHINFO_EXTENSION);
        $this->assertEquals('jpg', $storedExtension);
    }

    public function test_non_image_file_is_rejected()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/hazards', [
                'type' => 'pothole',
                'description' => 'Trying to upload PDF',
                'lat' => 14.5995,
                'lng' => 120.9842,
                'image' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_image_too_large_is_rejected()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $file = UploadedFile::fake()->image('huge.jpg', 2000, 2000)->size(6000);

        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
            ->postJson('/api/hazards', [
                'type' => 'pothole',
                'description' => 'Too large image',
                'lat' => 14.5995,
                'lng' => 120.9842,
                'image' => $file,
            ]);

        $response->assertStatus(422);
    }
}
