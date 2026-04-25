<?php

namespace App\Command;

use App\Entity\Picture;
use App\Repository\PictureRepository;
use App\Service\PictureService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-orphan-pictures',
    description: 'Mark stuck "processing" pictures as failed and clean their temp S3 file.',
)]
class CleanupOrphanPicturesCommand extends Command
{
    private const string THRESHOLD = '-1 hour';

    public function __construct(
        private readonly PictureRepository      $pictureRepository,
        private readonly PictureService         $pictureService,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = new DateTimeImmutable(self::THRESHOLD);
        $stuckPicture = $this->pictureRepository->findStuckProcessing($threshold);

        $io->title('Cleanup orphan pictures');
        $io->definitionList(
            ['Threshold (older than)' => $threshold->format('Y-m-d H:i:s')],
            ['Found'                  => count($stuckPicture) . ' stuck picture(s)'],
        );

        if ($stuckPicture === []) {
            $io->success('Nothing to do.');
            return Command::SUCCESS;
        }

        foreach ($stuckPicture as $picture) {
            $io->text(sprintf(
                ' • Picture #%d  created_at=%s  filename=%s',
                $picture->getId(),
                $picture->getCreatedAt()?->format('Y-m-d H:i:s') ?? 'n/a',
                $picture->getFilename() ?? 'n/a',
            ));

            $this->pictureService->deleteFile($picture);
            $picture->setStatus(Picture::STATUS_FAILED);
        }

        $this->entityManager->flush();
        $io->success(sprintf('%d picture(s) marked as failed.', count($stuckPicture)));

        return Command::SUCCESS;
    }
}
