<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //sku	nombre	cliente_id	propiedades	caracteristicas	usuario_id	usuarioModificacion	created_at	updated_at	

            'sku' => $this->faker->uuid,
            'nombre' => $this->faker->word,
            'cliente_id' => \App\Models\Cliente::inRandomOrder()->first()->id,
            'propiedades' => json_encode(['color' => $this->faker->colorName, 'talla' => $this->faker->randomElement(['XS', 'S', 'M', 'L', 'XL'])]),
            'caracteristicas' => json_encode(['peso' => $this->faker->randomFloat(2, 1, 100), 'stock' => $this->faker->numberBetween(1, 100)]),
            'usuario_id' => \App\Models\Usuario::inRandomOrder()->first()->id,
            'usuarioModificacion' => $this->faker->optional()->numberBetween(1)
        ];
    }
}
