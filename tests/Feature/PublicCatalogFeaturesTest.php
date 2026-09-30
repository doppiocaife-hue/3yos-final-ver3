<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCatalogFeaturesTest extends TestCase
{
    public function test_homepage_replaces_unverified_metrics_with_service_and_package_sections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Beautiful food for life’s important moments.',
            'Our services',
            'Why Choose 3YOS',
            'A smoother event planning process.',
            'Find a package for your event.',
            'Let’s make your next event feel beautifully easy.',
        ]);
        $response->assertSee('Custom Catering Packages');
        $response->assertSee('Catering options designed around the needs and style of your event.');
        $response->assertSee('Flexible Event Options');
        $response->assertSee('Suitable options for different event types, guest counts, and requirements.');
        $response->assertSee('Professional Event Support');
        $response->assertSee('Support throughout the reservation and event planning process.');
        $response->assertSee('Easy Reservation Process');
        $response->assertSee('A simple way to explore packages, submit event details, and make a reservation.');
        $response->assertSee('View all packages');

        foreach (['1500+', '1,500+', 'events hosted', '12 yrs', '12 years', '24/7', 'planning support', '4.9/5'] as $claim) {
            $response->assertDontSee($claim);
        }
    }

    public function test_package_image_is_rendered_without_showing_a_per_guest_price(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('packages/garden.jpg', 'fake-image-content');

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
        $catalog->assertSee('package-images/packages/garden.jpg');
        $catalog->assertDontSee('/ guest');
        $catalog->assertSee('Marikina City, Metro Manila');
        $catalog->assertSee('Choose Garden Package');
        $catalog->assertSee(route('reservation', ['package' => $package->id]), false);

        $detail = $this->get(route('packages.show', $package->slug));
        $detail->assertOk();
        $detail->assertSee('package-images/packages/garden.jpg');
        $detail->assertSee('Choose this package');
    }

    public function test_gallery_shows_all_images_without_a_filter_or_metadata_panel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/reunion.jpg', 'reunion-image');
        Storage::disk('public')->put('gallery/wedding.jpg', 'wedding-image');

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
        $response->assertSee('Replace image');
        $response->assertDontSee('Image controls');
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

    public function test_uploaded_package_and_gallery_images_are_saved_and_served_from_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('image-admin-password'),
        ]);
        $session = [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ];
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lb8AAAAASUVORK5CYII=');

        $packageResponse = $this->withSession($session)->post(route('admin.packages.store'), [
            'password_confirmation' => 'image-admin-password',
            'name' => 'Image package',
            'price' => 500,
            'image' => UploadedFile::fake()->createWithContent('package.png', $image),
        ]);
        $packageResponse->assertRedirect(route('admin.packages.index'));

        $package = Package::where('name', 'Image package')->firstOrFail();
        $this->assertSame('packages/', substr($package->image_path, 0, 9));
        Storage::disk('public')->assertExists($package->image_path);

        $packageImage = $this->get(route('package.image', ['path' => $package->image_path]));
        $packageImage->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent($image);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk()->assertSee(route('package.image', ['path' => $package->image_path]), false);

        $galleryResponse = $this->withSession($session)->from(route('admin.gallery.index'))->post(route('admin.gallery.store'), [
            'password_confirmation' => 'image-admin-password',
            'image' => UploadedFile::fake()->createWithContent('gallery.png', $image),
        ]);
        $galleryResponse->assertRedirect(route('admin.gallery.index'));

        $gallery = GalleryItem::latest()->firstOrFail();
        Storage::disk('public')->assertExists($gallery->image_path);
        $galleryImage = $this->get(route('gallery.image', ['path' => $gallery->image_path]));
        $galleryImage->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent($image);
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

    public function test_reservation_shows_only_a_simple_selected_package_summary(): void
    {
        $package = Package::create([
            'name' => 'Preview package',
            'slug' => 'preview-package',
            'price' => 700,
            'description' => 'A generous menu for milestone events.',
            'menu' => 'Three mains, pasta, dessert, and refreshments.',
            'freebies' => 'Buffet styling and service crew.',
            'addons' => 'Optional dessert station.',
        ]);

        $response = $this->get(route('reservation', ['package' => $package->id]));

        $response->assertOk();
        $response->assertSee('id="selected-package-card"', false);
        $response->assertSee('Preview package');
        $response->assertSee('&#8369;700.00 / person', false);
        $response->assertSee('Change package');
        $response->assertSee('View package details');
        $response->assertDontSee('data-package-option', false);
        $response->assertDontSee('Three mains, pasta, dessert, and refreshments.');
        $response->assertDontSee('Buffet styling and service crew.');
        $response->assertDontSee('Optional dessert station.');
    }

    public function test_reservation_reports_a_preselected_package_that_is_no_longer_available(): void
    {
        $package = Package::create([
            'name' => 'Unavailable package',
            'slug' => 'unavailable-package',
            'price' => 700,
        ]);
        $packageId = $package->id;
        $package->delete();

        $response = $this->get(route('reservation', ['package' => $packageId]));

        $response->assertOk();
        $response->assertSee('The selected package is no longer available. Please choose another package.');
    }

    public function test_reservation_requires_a_catering_package(): void
    {
        $response = $this->post(route('reservation.store'), []);

        $response->assertSessionHasErrors([
            'package_id' => 'Please select a catering package.',
        ]);
    }

    public function test_reservation_reports_a_package_that_is_no_longer_available(): void
    {
        $response = $this->post(route('reservation.store'), [
            'package_id' => PHP_INT_MAX,
        ]);

        $response->assertSessionHasErrors([
            'package_id' => 'The selected package is no longer available. Please choose another package.',
        ]);
    }
}