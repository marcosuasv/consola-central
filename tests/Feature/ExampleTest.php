<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }
}
