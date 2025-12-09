<?php

namespace App\Command;

use App\Repository\GalleryRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:gallery',
    description: 'Add a short description for your command',
)]
class GalleryCommand extends Command
{
    public function __construct(
        private readonly GalleryRepository $galleryRepository,
        private readonly EntityManagerInterface $entityManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'action to do')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $action = $input->getOption('action');

        try {
            match ($action) {
                'token' => $this->updateToken(),
                default => throw new RuntimeException('Invalid action')
            };

            $io->success('Success!');
            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    private function updateToken(): void
    {
        $galleries = $this->galleryRepository->findAll();

        foreach ($galleries as $gallery) {
            $gallery->setToken(bin2hex(random_bytes(32)));
        }

        $this->entityManager->flush();
    }
}
