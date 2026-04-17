<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_a_successful_response(): void
    {
        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('Order Management System')
            ->assertSee('Run product stock and order flow')
            ->assertSee('Product control')
            ->assertSee('Order handling')
            ->assertSee('Activity history')
            ->assertSee('Basic reporting');
    }
}
