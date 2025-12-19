<?php

namespace App\Tests\Controller;

use App\Document\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ClientControllerTest extends WebTestCase
{
    /**
     * Insère un admin de test si MongoDB est disponible.
     * Marque le test comme ignoré lorsque la base n'est pas joignable.
     */
    private function createAdminUser()
    {
        $user = new Client();
        $user->setNom('Marc Zuckerberg');
        $user->setEmail('marc@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setPassword('password');

        try {
            $dm = static::getContainer()->get('doctrine_mongodb')->getManager();
            $dm->persist($user);
            $dm->flush();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MongoDB non disponible pour le test : ' . $e->getMessage());
        }

        return $user;
    }

    /**
     * Crée un utilisateur simple pour valider les restrictions d'accès sans toucher à la base.
     */
    private function createRegularUser(): Client
    {
        $user = new Client();
        $user->setNom('Alice Utilisatrice');
        $user->setEmail('alice@example.com');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('password');

        return $user;
    }

    /**
     * Vérifie qu'un administrateur voit bien la liste des clients.
     */
    public function testIndex(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createAdminUser());

        $client->request('GET', '/clients');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Liste des clients');
    }

    /**
     * Vérifie qu'un utilisateur non authentifié est redirigé vers la page de connexion.
     */
    public function testIndexRedirectsAnonymousUserToLogin(): void
    {
        $client = static::createClient();

        $client->request('GET', '/clients');

        $this->assertResponseRedirects('/login');
    }

    /**
     * Vérifie qu'un utilisateur connecté sans rôle administrateur est refusé.
     */
    public function testIndexIsForbiddenForNonAdminUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createRegularUser());

        $client->request('GET', '/clients');

        $this->assertResponseStatusCodeSame(403);
    }
}
