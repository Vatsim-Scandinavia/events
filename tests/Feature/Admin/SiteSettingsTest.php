<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing locked by the environment unless a test says so.
        config(['vacc.name' => null, 'vacc.division' => null, 'vacc.website' => null]);
        Storage::fake('public');
    }

    public function test_only_administrators_can_open_or_save_the_settings(): void
    {
        $this->get('/admin/settings')->assertRedirect('/auth/vatsim');
        $this->post('/admin/settings', $this->payload())->assertRedirect('/auth/vatsim');

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff)->get('/admin/settings')->assertForbidden();
        $this->actingAs($staff)->post('/admin/settings', $this->payload(['name' => 'Hijacked']))->assertForbidden();
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('vacc.name', 'Example vACC'));

        $this->actingAs(User::factory()->admin()->create())->get('/admin/settings')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Settings/Edit')
            ->where('settings.name', 'Example vACC')
            ->where('locked', [])
        );
    }

    public function test_an_admin_can_set_the_vacc_details_shown_across_the_site(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings', $this->payload())
            ->assertRedirect('/admin/settings');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('vacc.name', 'VATSIM Scandinavia')
            ->where('vacc.division', 'VATEUD')
            ->where('vacc.website', 'https://vatsim-scandinavia.org')
            ->where('vacc.logo', null)
        );
    }

    public function test_clearing_a_detail_brings_back_its_default(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/settings', $this->payload());

        $this->actingAs($admin)->post('/admin/settings', $this->payload(['division' => '', 'website' => '']));

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('vacc.division', null)
            ->where('vacc.website', null)
        );
    }

    public function test_the_default_theme_is_used_until_an_admin_picks_another(): void
    {
        $this->get('/')->assertOk()->assertSee('data-theme="default"', false);
    }

    public function test_an_admin_can_pick_the_theme_the_whole_site_is_shown_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
            ->where('settings.theme', 'default')
            ->where('themes', [['value' => 'default', 'label' => 'Default'], ['value' => 'vatsca', 'label' => 'VATSIM Scandinavia']])
        );

        $this->actingAs($admin)
            ->post('/admin/settings', $this->payload(['theme' => 'vatsca']))
            ->assertRedirect('/admin/settings');

        $this->get('/')->assertSee('data-theme="vatsca"', false);
        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page->where('settings.theme', 'vatsca'));

        // Saving without a theme, as older forms do, leaves it as it is.
        $this->actingAs($admin)->post('/admin/settings', collect($this->payload())->except('theme')->all());
        $this->get('/')->assertSee('data-theme="vatsca"', false);

        $this->actingAs($admin)->post('/admin/settings', $this->payload(['theme' => 'default']));
        $this->get('/')->assertSee('data-theme="default"', false);
    }

    public function test_details_set_in_the_environment_are_locked(): void
    {
        config(['vacc.name' => 'VATSIM Scandinavia']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/settings')->assertInertia(fn (Assert $page) => $page
            ->where('locked', ['name' => 'VACC_NAME'])
        );

        $this->actingAs($admin)
            ->post('/admin/settings', $this->payload(['name' => 'Something else', 'division' => 'VATEUD']))
            ->assertRedirect('/admin/settings');

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('vacc.name', 'VATSIM Scandinavia')
            ->where('vacc.division', 'VATEUD')
        );

        // Lifting the lock shows that nothing was stored for the locked details.
        config(['vacc.name' => null]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('vacc.name', 'Example vACC'));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    #[DataProvider('invalidDetails')]
    public function test_invalid_details_are_refused(array $changes, string $field, string $message): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/settings')
            ->post('/admin/settings', $this->payload($changes))
            ->assertRedirect('/admin/settings')
            ->assertSessionHasErrors([$field => $message]);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function invalidDetails(): array
    {
        return [
            'no name' => [['name' => ''], 'name', 'Enter the vACC’s name.'],
            'a subdivision that is not a code' => [['subdivision' => 'Scandinavia 1'], 'subdivision', 'Use the subdivision’s code from VATSIM, like SCA.'],
            'automatic Controller without a subdivision' => [['auto_controller' => true, 'subdivision' => ''], 'auto_controller', 'Set the subdivision first.'],
            'a website without https' => [['website' => 'http://example.org'], 'website', 'Enter a full link starting with https://.'],
            'a theme that does not exist' => [['theme' => 'neon'], 'theme', 'The selected theme is invalid.'],
            'a logo that is not an image' => [['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], 'logo', 'Upload a PNG, JPG, WebP or SVG file.'],
        ];
    }

    public function test_an_uploaded_image_becomes_the_logo_and_favicons(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 1024, 512)]))
            ->assertRedirect('/admin/settings');

        $files = Storage::disk('public')->allFiles('branding');
        $this->assertCount(3, $files);

        $sizes = collect($files)->mapWithKeys(fn (string $path) => [$path => getimagesizefromstring(Storage::disk('public')->get($path))]);
        $logo = $sizes->search(fn (array $size) => $size['mime'] === 'image/webp');
        $this->assertSame([512, 256], [$sizes[$logo][0], $sizes[$logo][1]]);
        $this->assertEqualsCanonicalizing([[32, 32], [180, 180]], $sizes->except($logo)->map(fn (array $size) => [$size[0], $size[1]])->values()->all());

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('vacc.logo', Storage::disk('public')->url($logo)))
            ->assertSee('rel="apple-touch-icon"', false);
    }

    public function test_replacing_or_removing_the_logo_deletes_the_old_files(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('first.png', 300, 300)]));
        $first = Storage::disk('public')->allFiles('branding');

        $this->actingAs($admin)->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('second.jpg', 300, 300)]));
        $second = Storage::disk('public')->allFiles('branding');
        $this->assertCount(3, $second);
        $this->assertEmpty(array_intersect($first, $second));

        $this->actingAs($admin)->post('/admin/settings', $this->payload(['remove_logo' => true]));
        $this->assertEmpty(Storage::disk('public')->allFiles('branding'));
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('vacc.logo', null))
            ->assertSee('favicon.ico', false);
    }

    public function test_saving_details_alone_keeps_the_logo(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->image('logo.png', 300, 300)]));

        $this->actingAs($admin)->post('/admin/settings', $this->payload(['name' => 'Renamed']));

        $this->assertCount(3, Storage::disk('public')->allFiles('branding'));
        $this->get('/')->assertInertia(fn (Assert $page) => $page->whereNot('vacc.logo', null));
    }

    public function test_an_svg_logo_is_cleaned_of_scripts_and_used_as_the_favicon(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)">'
            .'<script>alert(document.cookie)</script>'
            .'<a href="javascript:alert(1)"><circle cx="5" cy="5" r="4" fill="#fff"/></a>'
            .'</svg>';

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->createWithContent('logo.svg', $svg)]))
            ->assertRedirect('/admin/settings');

        [$path] = Storage::disk('public')->allFiles('branding');
        $stored = Storage::disk('public')->get($path);
        $this->assertStringContainsString('<circle', $stored);
        $this->assertStringNotContainsStringIgnoringCase('script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onload', $stored);

        $url = Storage::disk('public')->url($path);
        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page->where('vacc.logo', $url))
            ->assertSee('<link rel="icon" href="'.$url.'" type="image/svg+xml">', false)
            ->assertDontSee('rel="apple-touch-icon"', false);
    }

    public function test_an_image_named_like_an_svg_is_still_redrawn_as_an_image(): void
    {
        // A real upload, as fake files take their type from the name; the browser claims it's an SVG.
        $png = UploadedFile::fake()->image('logo.png', 512, 256);
        $upload = new UploadedFile($png->getRealPath(), 'logo.svg', 'image/svg+xml', null, true);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/admin/settings', $this->payload(['logo' => $upload]))
            ->assertRedirect('/admin/settings');

        $files = Storage::disk('public')->allFiles('branding');
        $this->assertCount(3, $files);
        $this->assertEmpty(array_filter($files, fn (string $path) => str_ends_with($path, '.svg')));
    }

    public function test_an_svg_that_cannot_be_read_is_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->from('/admin/settings')
            ->post('/admin/settings', $this->payload(['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><unclosed></svg>')]))
            ->assertSessionHasErrors(['logo' => 'That SVG couldn’t be read. Try exporting it again, or upload a PNG.']);

        $this->assertEmpty(Storage::disk('public')->allFiles('branding'));
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function payload(array $changes = []): array
    {
        return [
            'name' => 'VATSIM Scandinavia',
            'division' => 'VATEUD',
            'website' => 'https://vatsim-scandinavia.org',
            'theme' => 'default',
            ...$changes,
        ];
    }
}
