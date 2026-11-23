<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()->assertSee('build/app.js')->assertSee('build/app.css');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->has('mensagem'));
    }

    public function test_inertia_navigation_returns_the_requested_page(): void
    {
        $version = app(HandleInertiaRequests::class)->version(request());

        $this->get('/sobre', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertJsonPath('component', 'Sobre');
    }
}
