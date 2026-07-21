<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(NotificationService::class);
    }

    private function makeUser(): User
    {
        return User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    public function test_list_for_user_returns_own_notifications(): void
    {
        $user = $this->makeUser();
        $user->notifications()->create(['type' => NotificationType::NEW_APPLICATION, 'message' => 'Test']);

        $notifications = $this->service->listForUser($user->fresh());

        $this->assertCount(1, $notifications);
    }

    public function test_mark_as_read_updates_flag(): void
    {
        $user = $this->makeUser();
        $notification = $user->notifications()->create(['type' => NotificationType::NEW_APPLICATION, 'message' => 'Test']);

        $updated = $this->service->markAsRead($user->fresh(), $notification);

        $this->assertTrue($updated->read);
    }

    public function test_mark_as_read_rejects_foreign_notification(): void
    {
        $owner = $this->makeUser();
        $notification = $owner->notifications()->create(['type' => NotificationType::NEW_APPLICATION, 'message' => 'Test']);
        $intruder = User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->expectException(ApiException::class);
        $this->service->markAsRead($intruder, $notification);
    }

    public function test_mark_all_as_read_updates_all_unread(): void
    {
        $user = $this->makeUser();
        $user->notifications()->create(['type' => NotificationType::NEW_APPLICATION, 'message' => 'A']);
        $user->notifications()->create(['type' => NotificationType::PAYMENT_SUCCEEDED, 'message' => 'B']);

        $this->service->markAllAsRead($user->fresh());

        $this->assertSame(0, $user->notifications()->where('read', false)->count());
    }
}
