<?php

namespace App\Command;

use App\Repository\GalleryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(
    name: 'app:backfill-gallery-slugs',
    description: 'Generate a slug for every Gallery row that has slug = NULL.',
)]
class BackfillGallerySlugsCommand extends Command
{
    public function __construct(
        private readonly GalleryRepository      $galleryRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $galleries = $this->galleryRepository->findBy(['slug' => null]);

        $io->title('Backfill gallery slugs');
        $io->definitionList(['Galleries with NULL slug' => count($galleries)]);

        if ($galleries === []) {
            $io->success('Nothing to do.');
            return Command::SUCCESS;
        }

        $slugger = new AsciiSlugger();

        foreach ($galleries as $gallery) {
            $title = $gallery->getTitle();
            if ($title === null || $title === '') {
                $io->warning(sprintf('Gallery #%d has no title — skipped.', $gallery->getId()));
                continue;
            }

            $slug = $slugger->slug($title)->lower()->toString();
            $gallery->setSlug($slug);

            $io->text(sprintf(' • #%d  %s  →  %s', $gallery->getId(), $title, $slug));
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d gallery slug(s) backfilled.', count($galleries)));

        return Command::SUCCESS;
    }
}
