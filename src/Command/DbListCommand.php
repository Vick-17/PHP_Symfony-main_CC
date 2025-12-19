<?php

namespace App\Command;

use App\Document\Client;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'db:list',
    description: 'Liste tous les clients de la base MongoDB'
)]
class DbListCommand extends Command
{
    private $dm;

    public function __construct(DocumentManager $dm)
    {
        parent::__construct();
        $this->dm = $dm;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clients = $this->dm->getRepository(Client::class)->findAll();

        if (!$clients) {
            $output->writeln('Aucun client trouvé.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Nom', 'Email']);

        foreach ($clients as $client) {
            $table->addRow([$client->getNom(), $client->getEmail()]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
