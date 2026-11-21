<?php

namespace Tests\Feature;

use Tests\TestCase;

// Une route protégée appelée sans jeton, depuis un navigateur (sans « Accept: application/json ») : 401 en JSON,
// jamais une erreur 500 (renvoi vers une page « login » qui n'existe pas).
class SansJetonTest extends TestCase
{
    public function test_navigateur_sans_jeton(): void
    {
        $this->get('/api/moi')->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_jeu_sans_jeton(): void
    {
        $this->getJson('/api/moi')->assertStatus(401);
    }
}
