<?php

namespace App\Tests\Controller;

use App\Document\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityControllerTest extends WebTestCase
{
    /**
     * Vérifie qu'un utilisateur peut afficher la page de connexion.
     */
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    /**
     * Vérifie qu'un utilisateur déjà connecté est redirigé vers l'accueil et ne revoit pas le formulaire.
     */
    public function testLoginPageRedirectsWhenUserIsAlreadyAuthenticated(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createRegularUser());

        $client->request('GET', '/login');

        $this->assertResponseRedirects('/hotel/accueil');
    }

    /**
     * Crée un utilisateur standard pour les tests sans dépendre de la base MongoDB.
     */
    private function createRegularUser(): Client
    {
        $user = new Client();
        $user->setNom('Test User');
        $user->setEmail('user@example.com');
        $user->setPassword('password');
        $user->setRoles(['ROLE_USER']);

        return $user;
    }
}
