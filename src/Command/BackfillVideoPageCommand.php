<?php

namespace App\Command;

use App\Repository\PageRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:backfill-video-page',
    description: 'Assign every Video row with NULL page_id to the "video_index" Page (run after migration or after re-seeding pages).',
)]
class BackfillVideoPageCommand extends Command
{
    private const string TARGET_PAGE_SLUG = 'video_index';

    public function __construct(
        private readonly VideoRepository $videoRepository,
        private readonly PageRepository $pageRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Backfill video pages');

        $targetPage = $this->pageRepository->findOneBySlug(self::TARGET_PAGE_SLUG);

        if ($targetPage === null) {
            throw new RuntimeException(sprintf('Target page "%s" not found. Run "php bin/console app:seed-pages" first.', self::TARGET_PAGE_SLUG));
        }

        $videos = $this->videoRepository->findBy(['page' => null]);
        $io->definitionList(['Videos with NULL page' => count($videos)]);

        if ($videos === []) {
            $io->success('Nothing to do.');
            return Command::SUCCESS;
        }

        foreach ($videos as $video) {
            $video->setPage($targetPage);
            $io->text(sprintf(' • #%d  →  %s', $video->getId(), self::TARGET_PAGE_SLUG));
        }

        $this->entityManager->flush();

        $io->success(sprintf('%d video(s) attached to "%s".', count($videos), self::TARGET_PAGE_SLUG));

        return Command::SUCCESS;
    }
}
