<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => fake()->randomElement(['administrateur', 'technicien', 'client']),
            'telephone' => fake()->optional()->phoneNumber(),
            'actif' => fake()->boolean(90),
        ];
    }

    /**
     * Indicate the user is an administrateur.
     */
    public function administrateur(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'administrateur',
        ]);
    }

    /**
     * Indicate the user is a technicien.
     */
    public function technicien(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'technicien',
        ]);
    }

    /**
     * Indicate the user is a client.
     */
    public function client(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'client',
        ]);
    }
}
