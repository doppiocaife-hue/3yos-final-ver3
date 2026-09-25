<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicCatalogFeaturesTest extends TestCase
{
    public function test_package_image_is_rendered_without_showing_a_per_guest_price(): void
    {
        $package = Package::create([
            'name' => 'Garden Package',
            'slug' => 'garden-package',
            'description' => 'A garden event menu.',
            'price' => 600,
            'min_guests' => 20,
            'max_guests' => 100,
            'image_path' => 'packages/garden.jpg',
        ]);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk();
        $catalog->assertSee('storage/packages/garden.jpg');
        $catalog->assertDontSee('/ guest');
        $catalog->assertSee('Marikina City, Metro Manila');

        $detail = $this->get(route('packages.show', $package->slug));
        $detail->assertOk();
        $detail->assertSee('storage/packages/garden.jpg');
    }

    public function test_gallery_shows_all_images_without_a_filter_or_metadata_panel(): void
    {
        GalleryItem::create([
            'title' => 'Family reunion',
            'image_path' => 'gallery/reunion.jpg',
            'event_type' => 'Other Events',
        ]);
        GalleryItem::create([
            'title' => 'Wedding reception',
            'image_path' => 'gallery/wedding.jpg',
            'event_type' => 'Wedding',
        ]);

        $response = $this->get(route('gallery'));

        $response->assertOk();
        $response->assertSee('gallery/reunion.jpg');
        $response->assertSee('gallery/wedding.jpg');
        $response->assertDontSee('Filter by event');
        $response->assertDontSee('gallery-card-body');
    }

    public function test_admin_gallery_keeps_image_management_available_in_collapsed_controls(): void
    {
        GalleryItem::create([
            'title' => 'Admin gallery photo',
            'image_path' => 'gallery/admin-photo.jpg',
            'event_type' => 'Wedding',
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.gallery.index'));

        $response->assertOk();
        $response->assertSee('gallery-admin-preview');
        $response->assertSee('Image controls');
        $response->assertDontSee('name="title"');
        $response->assertDontSee('name="event_type"');
        $response->assertDontSee('name="description"');
        $response->assertSee('Delete this gallery image?');
    }

    public function test_admin_can_add_gallery_photo_without_metadata_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('gallery-admin-password'),
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ])->from(route('admin.gallery.index'))->post(route('admin.gallery.store'), [
            'password_confirmation' => 'gallery-admin-password',
            'is_featured' => '1',
        ]);

        $response->assertRedirect(route('admin.gallery.index'));
        $response->assertSessionHasErrors(['image']);
        $response->assertSessionDoesntHaveErrors(['title', 'event_type', 'description']);
    }

    public function test_package_estimate_uses_the_package_rate_times_guest_count(): void
    {
        $package = new Package(['price' => 600]);

        $this->assertSame(48000.0, $package->estimatedTotalFor(80));
    }

    public function test_package_guest_ranges_are_absent_from_public_and_admin_screens(): void
    {
        $package = Package::create([
            'name' => 'Open guest package',
            'slug' => 'open-guest-package',
            'price' => 600,
            'min_guests' => 20,
            'max_guests' => 100,
        ]);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk();
        $catalog->assertDontSee('For 20–100 guests');

        $detail = $this->get(route('packages.show', $package->slug));
        $detail->assertOk();
        $detail->assertDontSee('Guests:');

        $adminSession = ['is_admin' => true, 'admin_role' => 'full'];
        $adminList = $this->withSession($adminSession)->get(route('admin.packages.index'));
        $adminList->assertOk();
        $adminList->assertDontSee('Guest range');
        $adminList->assertDontSee('20-100');

        $adminForm = $this->withSession($adminSession)->get(route('admin.packages.edit', $package));
        $adminForm->assertOk();
        $adminForm->assertDontSee('Minimum guests');
        $adminForm->assertDontSee('Maximum guests');
    }

    public function test_reservation_package_picker_contains_a_preview_for_each_package(): void
    {
        Package::create([
            'name' => 'Preview package',
            'slug' => 'preview-package',
            'price' => 700,
            'description' => 'A generous menu for milestone events.',
            'menu' => 'Three mains, pasta, dessert, and refreshments.',
            'freebies' => 'Buffet styling and service crew.',
            'addons' => 'Optional dessert station.',
        ]);

        $response = $this->get(route('reservation'));

        $response->assertOk();
        $response->assertSee('id="package-picker"', false);
        $response->assertSee('data-package-option', false);
        $response->assertSee('Three mains, pasta, dessert, and refreshments.');
        $response->assertSee('Buffet styling and service crew.');
        $response->assertSee('Optional dessert station.');
    }
}