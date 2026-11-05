<?php

use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\CarImage;
use App\Models\Color;
use App\Models\Equipment;
use App\Models\User;
use App\Policies\CarPolicy;
use App\Repositories\CarRepository;
use App\Repositories\Contracts\CarRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('public'));

function managedCarData(array $overrides = []): array
{
    return [
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'vehicle_type' => 'passenger',
        'registration_number' => 'RKR 12345',
        'vin' => 'JTDBR32E720123456',
        'production_year' => 2022,
        'color' => null,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'required_license' => 'b',
        'daily_rate' => '189.99',
        'deposit_amount' => '1000.00',
        'prices' => [['min_days' => 1, 'max_days' => null, 'daily_rate' => '189.99']],
        'operational_status' => 'active',
        'description' => null,
        ...$overrides,
    ];
}

function managedCar(array $overrides = []): Car
{
    return Car::query()->create(managedCarData($overrides));
}

function carPhoto(string $name = 'zdjecie.jpg', int $kilobytes = 100, string $mime = 'image/jpeg'): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, $mime);
}

function carWithImages(int $count): Car
{
    $car = managedCar();

    for ($position = 0; $position < $count; $position++) {
        $path = "cars/istniejace-{$position}.jpg";
        Storage::disk('public')->put($path, 'obraz');
        $car->images()->create(['path' => $path, 'position' => $position]);
    }

    return $car;
}

// Lista i formularz edycji

test('car list shows cars from database with images', function () {
    $car = carWithImages(2);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cars.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cars/index')
            ->has('cars', 1)
            ->where('cars.0.id', $car->id)
            ->has('cars.0.images', 2));
});

test('edit page has car, equipment and images', function () {
    $car = carWithImages(1);
    $car->equipment()->attach(Equipment::query()->create(['name' => 'Ukryte', 'is_active' => false]));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cars.edit', $car->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/cars/edit')
            ->where('car.vin', $car->vin)
            ->where('car.equipment.0.name', 'Ukryte')
            ->has('car.images', 1));
});

test('editing unknown car returns 404', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('admin.cars.edit', 999))->assertNotFound();
    $this->actingAs($admin)->post(route('admin.cars.update', 999), managedCarData())->assertNotFound();
});

// Edycja

test('administrator can edit a car', function () {
    $car = managedCar();
    $old = Equipment::query()->create(['name' => 'ABS']);
    $new = Equipment::query()->create(['name' => 'GPS']);
    $car->equipment()->attach($old);
    Color::query()->create(['name' => 'Czarny']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData([
            'model' => 'Yaris',
            'registration_number' => 'rkr 54321',
            'color' => 'Czarny',
            'equipment' => [$new->id],
        ]))
        ->assertRedirect(route('admin.cars.index'));

    $car->refresh();

    expect($car->model)->toBe('Yaris')
        ->and($car->registration_number)->toBe('RKR 54321')
        ->and($car->color)->toBe('Czarny')
        ->and($car->equipment->pluck('name')->all())->toBe(['GPS']);
});

test('car keeps its own registration and vin when edited', function () {
    $car = managedCar();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData(['model' => 'Yaris']))
        ->assertSessionHasNoErrors();
});

test('edit rejects registration and vin of another car', function () {
    managedCar();
    $other = managedCar(['registration_number' => 'RKR 99999', 'vin' => 'JTDBR32E720999999']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $other->id), managedCarData())
        ->assertSessionHasErrors(['registration_number', 'vin']);

    expect($other->refresh()->vin)->toBe('JTDBR32E720999999');
});

test('edit validates fields', function () {
    $car = managedCar();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData(['brand' => '', 'seats' => 40]))
        ->assertSessionHasErrors(['brand', 'seats']);

    expect($car->refresh()->brand)->toBe('Toyota');
});

test('edit keeps color and equipment hidden in dictionary', function () {
    Color::query()->create(['name' => 'Beżowy', 'is_active' => false]);
    $hidden = Equipment::query()->create(['name' => 'GPS', 'is_active' => false]);
    $car = managedCar(['color' => 'Beżowy']);
    $car->equipment()->attach($hidden);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData(['color' => 'Beżowy', 'equipment' => [$hidden->id]]))
        ->assertSessionHasNoErrors();

    expect($car->refresh()->equipment)->toHaveCount(1);
});

test('edit cannot attach hidden equipment or color to another car', function () {
    Color::query()->create(['name' => 'Beżowy', 'is_active' => false]);
    $hidden = Equipment::query()->create(['name' => 'GPS', 'is_active' => false]);
    $car = managedCar();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData(['color' => 'Beżowy', 'equipment' => [$hidden->id]]))
        ->assertSessionHasErrors(['color', 'equipment.0']);
});

test('edit without equipment clears it', function () {
    $car = managedCar();
    $car->equipment()->attach(Equipment::query()->create(['name' => 'ABS']));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData());

    expect($car->refresh()->equipment)->toBeEmpty();
});

// Zdjęcia

test('car can be created with several images', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), managedCarData([
            'images' => [carPhoto('a.jpg'), carPhoto('b.png', 100, 'image/png'), carPhoto('c.webp', 100, 'image/webp')],
        ]))
        ->assertRedirect(route('admin.cars.index'));

    $images = Car::query()->sole()->images;

    expect($images)->toHaveCount(3)
        ->and($images->pluck('position')->all())->toBe([1, 2, 3]);

    $images->each(fn (CarImage $image) => Storage::disk('public')->assertExists($image->path));
});

test('edit adds images at the end and removes selected ones', function () {
    $car = carWithImages(2);
    $removed = $car->images[0];

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData([
            'images' => [carPhoto()],
            'removed_images' => [$removed->id],
        ]))
        ->assertSessionHasNoErrors();

    $images = $car->refresh()->images;

    expect($images)->toHaveCount(2)
        ->and($images[0]->path)->toBe('cars/istniejace-1.jpg')
        ->and($images[1]->position)->toBe(2);

    Storage::disk('public')->assertMissing('cars/istniejace-0.jpg');
    Storage::disk('public')->assertExists($images[1]->path);
});

test('edit cannot remove image of another car', function () {
    $car = managedCar(['registration_number' => 'RKR 99999', 'vin' => 'JTDBR32E720999999']);
    $other = carWithImages(1);
    $foreignImage = $other->images[0];

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData([
            'registration_number' => 'RKR 99999',
            'vin' => 'JTDBR32E720999999',
            'removed_images' => [$foreignImage->id],
        ]))
        ->assertSessionHasErrors('removed_images.0');

    expect(CarImage::query()->find($foreignImage->id))->not->toBeNull();
    Storage::disk('public')->assertExists($foreignImage->path);
});

test('car cannot have more than ten images', function () {
    $car = carWithImages(9);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData(['images' => [carPhoto('a.jpg'), carPhoto('b.jpg')]]))
        ->assertSessionHasErrors('images');

    expect($car->images()->count())->toBe(9);
});

test('removing images frees slots for new ones', function () {
    $car = carWithImages(10);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), managedCarData([
            'images' => [carPhoto()],
            'removed_images' => [$car->images[0]->id],
        ]))
        ->assertSessionHasNoErrors();

    expect($car->images()->count())->toBe(10);
});

test('invalid images are rejected', function (Closure $file) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), managedCarData(['images' => [$file()]]))
        ->assertSessionHasErrors('images.0');

    expect(Car::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'skrypt SVG' => [fn () => carPhoto('a.svg', 10, 'image/svg+xml')],
    'plik PHP' => [fn () => carPhoto('a.php', 10, 'application/x-php')],
    'PDF' => [fn () => carPhoto('a.pdf', 10, 'application/pdf')],
    'za duże zdjęcie' => [fn () => carPhoto('a.jpg', 5121)],
]);

test('uploaded files are removed when saving fails', function () {
    $this->partialMock(CarRepository::class)
        ->shouldReceive('createCar')
        ->andThrow(new RuntimeException('Baza niedostępna'));
    $this->app->bind(CarRepositoryInterface::class, fn () => app(CarRepository::class));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), managedCarData(['images' => [carPhoto()]]))
        ->assertSessionHasErrors('save');

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

// Tylko administrator

test('regular user cannot add or edit cars', function () {
    $car = managedCar();
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.cars.edit', $car->id))->assertForbidden();
    $this->post(route('admin.cars.store'), managedCarData(['vin' => 'JTDBR32E720000000']))->assertForbidden();
    $this->post(route('admin.cars.update', $car->id), managedCarData(['model' => 'Yaris']))->assertForbidden();

    expect($car->refresh()->model)->toBe('Corolla')
        ->and(Car::query()->count())->toBe(1);
});

test('guest cannot edit cars', function () {
    $car = managedCar();

    $this->post(route('admin.cars.update', $car->id), managedCarData(['model' => 'Yaris']))
        ->assertRedirect(route('login'));

    expect($car->refresh()->model)->toBe('Corolla');
});

test('policy blocks regular user even without role middleware', function () {
    $car = managedCar();

    $this->withoutMiddleware(CheckRole::class)
        ->actingAs(User::factory()->create());

    $this->post(route('admin.cars.store'), managedCarData(['vin' => 'JTDBR32E720000000']))->assertForbidden();
    $this->post(route('admin.cars.update', $car->id), managedCarData(['model' => 'Yaris']))->assertForbidden();

    $option = Equipment::query()->create(['name' => 'ABS']);

    $this->post(route('admin.dictionary.equipment.store'), ['name' => 'GPS'])->assertForbidden();
    $this->put(route('admin.dictionary.equipment.update', $option->id), ['name' => 'GPS'])->assertForbidden();
    $this->patch(route('admin.dictionary.equipment.toggle', $option->id))->assertForbidden();
    $this->post(route('admin.dictionary.colors.store'), ['name' => 'Biały'])->assertForbidden();

    expect($car->refresh()->model)->toBe('Corolla')
        ->and($option->refresh()->name)->toBe('ABS');
});

test('car policy allows only administrator', function () {
    $policy = new CarPolicy;
    $admin = User::factory()->admin()->make();
    $user = User::factory()->make();

    expect($policy->update($admin))->toBeTrue()
        ->and($policy->update($user))->toBeFalse()
        ->and($policy->manageDictionary($admin))->toBeTrue()
        ->and($policy->manageDictionary($user))->toBeFalse();
});
