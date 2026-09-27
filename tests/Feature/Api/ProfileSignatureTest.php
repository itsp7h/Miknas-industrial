<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Profile → Signature: drawn or uploaded once, reused on every LPO issued. */
class ProfileSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_saves_a_signature_and_the_profile_returns_it(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/profile')->assertJsonPath('data.signature', null);

        $this->actingAs($user)
            ->putJson('/api/v1/profile/signature', ['signature_image' => UserFactory::SIGNATURE])
            ->assertOk()
            ->assertJsonPath('data.signature', UserFactory::SIGNATURE)
            ->assertJsonPath('message', 'Signature saved. It will appear on every LPO you issue.');

        $this->assertSame(UserFactory::SIGNATURE, $user->fresh()->signature_image);
    }

    public function test_a_jpeg_upload_is_accepted(): void
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagejpeg($image);
        $jpeg = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());

        $this->actingAs(User::factory()->create())
            ->putJson('/api/v1/profile/signature', ['signature_image' => $jpeg])
            ->assertOk();
    }

    public function test_anything_that_is_not_a_small_png_or_jpeg_is_refused(): void
    {
        $user = User::factory()->create();
        $gif = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $lie = 'data:image/png;base64,'.base64_encode('this is not a png');
        $huge = 'data:image/png;base64,'.base64_encode(str_repeat('x', 600 * 1024));

        foreach (['', 'hello', $gif, $lie, $huge, 'data:image/png;base64,@@@'] as $bad) {
            $this->actingAs($user)
                ->putJson('/api/v1/profile/signature', ['signature_image' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('signature_image');
        }

        $this->assertNull($user->fresh()->signature_image);
    }

    public function test_a_user_removes_their_signature(): void
    {
        $user = User::factory()->withSignature()->create();

        $this->actingAs($user)
            ->deleteJson('/api/v1/profile/signature')
            ->assertOk()
            ->assertJsonPath('data.signature', null);

        $this->assertNull($user->fresh()->signature_image);
    }

    public function test_it_needs_a_session(): void
    {
        $this->putJson('/api/v1/profile/signature', ['signature_image' => UserFactory::SIGNATURE])->assertUnauthorized();
    }
}
