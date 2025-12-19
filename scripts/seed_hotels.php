<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Document\Chambre;
use App\Document\Hotel;
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->bootEnv(dirname(__DIR__) . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? false));
$kernel->boot();
$dm = $kernel->getContainer()->get('doctrine_mongodb.odm.document_manager');

$fixtures = [
    [
        'nom' => 'Hôtel Paris Centre',
        'adresse' => '10 Rue des Fleurs',
        'ville' => 'Paris',
        'telephone' => '0102030405',
        'categorie' => 4,
        'chambres' => [
            ['numero' => '101', 'capacite' => 2, 'prix' => 120, 'type' => 'double'],
            ['numero' => '102', 'capacite' => 1, 'prix' => 90, 'type' => 'single'],
        ],
    ],
    [
        'nom' => 'Lyon Part-Dieu',
        'adresse' => '5 Avenue Bellecour',
        'ville' => 'Lyon',
        'telephone' => '0472000000',
        'categorie' => 3,
        'chambres' => [
            ['numero' => '201', 'capacite' => 2, 'prix' => 110, 'type' => 'double'],
            ['numero' => '202', 'capacite' => 3, 'prix' => 150, 'type' => 'triple'],
        ],
    ],
];

foreach ($fixtures as $data) {
    $hotel = (new Hotel())
        ->setNom($data['nom'])
        ->setAdresse($data['adresse'])
        ->setVille($data['ville'])
        ->setTelephone($data['telephone'])
        ->setCategorie($data['categorie']);
    $dm->persist($hotel);

    foreach ($data['chambres'] as $c) {
        $chambre = (new Chambre())
            ->setNumero($c['numero'])
            ->setCapacite($c['capacite'])
            ->setPrix($c['prix'])
            ->setType($c['type'])
            ->setHotel($hotel);
        $dm->persist($chambre);
        $hotel->addChambre($chambre);
    }
}

$dm->flush();

echo "Seed OK\n";
