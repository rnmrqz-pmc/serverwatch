<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Server>
 */
class ServerFactory extends Factory
{
    protected $model = Server::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->domainWord() . '-node',
            'ip'   => $this->faker->unique()->ipv4(),
            'role' => $this->faker->sentence(3),
            'env'  => $this->faker->randomElement(['production', 'staging', 'development']),
        ];
    }
}
