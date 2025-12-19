<?php
namespace App\Controller;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MongoTestController extends AbstractController
{
    #[Route('/test-mongo', name: 'test_mongo')]
    public function index(DocumentManager $dm): Response
    {
        try {
            // Vérifiez si la base de données est accessible en listant les collections
            $database = $dm->getDocumentDatabase('App\Document\YourDocumentClass'); // Remplacez par votre classe de document
            $collections = $database->listCollections();

            return new Response('MongoDB connection successful! Collections: ' . implode(', ', array_map(fn($c) => $c->getName(), iterator_to_array($collections))));
        } catch (\Exception $e) {
            return new Response('MongoDB connection failed: ' . $e->getMessage());
        }
    }
}