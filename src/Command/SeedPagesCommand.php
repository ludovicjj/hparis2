<?php

namespace App\Command;

use App\Entity\Page;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:seed-pages',
    description: 'Truncate the Page table and re-seed it with the static front pages.',
)]
class SeedPagesCommand extends Command
{

    private const array PAGES = [
        [
            'slug' => 'home',
            'parentSlug' => null,
            'label' => 'Acceuil',
            'position' => 1,
            'metaTitleFr' => 'Hollywood Paris — Studio photo professionnel à Paris',
            'metaTitleEn' => 'Hollywood Paris — Professional photo studio in Paris',
            'metaDescriptionFr' => 'Hollywood Paris est un studio photo professionnel situé à Paris. Mariages, événements, mode, portraits corporate — découvrez nos galeries de photographies.',
            'metaDescriptionEn' => 'Hollywood Paris is a professional photo studio based in Paris. Weddings, events, fashion, corporate portraits — browse our photography galleries.',
        ],
        [
            'slug' => 'gallery_index',
            'parentSlug' => null,
            'label' => 'Liste des galeries',
            'position' => 2,
            'metaTitleFr' => 'Galeries photo | Hollywood Paris',
            'metaTitleEn' => 'Photo galleries | Hollywood Paris',
            'metaDescriptionFr' => "Toutes les galeries photo d'Hollywood Paris : mariages, événements, mode et portraits corporate réalisés à Paris.",
            'metaDescriptionEn' => 'All Hollywood Paris photo galleries: weddings, events, fashion and corporate portraits shot in Paris.',
        ],
        [
            'slug' => 'gallery_show',
            'parentSlug' => 'gallery_index',
            'label' => 'Détail galerie',
            'position' => 3,
            'metaTitleFr' => 'Galerie | Hollywood Paris',
            'metaTitleEn' => 'Gallery | Hollywood Paris',
            'metaDescriptionFr' => 'Galerie photo réalisée par Hollywood Paris, studio photo professionnel à Paris.',
            'metaDescriptionEn' => 'Photo gallery shot by Hollywood Paris, professional photo studio in Paris.',
        ],
        [
            'slug' => 'video_index',
            'parentSlug' => null,
            'label' => 'Liste des vidéos',
            'position' => 4,
            'metaTitleFr' => 'Vidéos | Hollywood Paris',
            'metaTitleEn' => 'Videos | Hollywood Paris',
            'metaDescriptionFr' => "Découvrez les vidéos d'Hollywood Paris — backstage, événements et créations vidéo réalisées à Paris.",
            'metaDescriptionEn' => 'Discover Hollywood Paris videos — behind the scenes, events and video creations made in Paris.',
        ],
        [
            'slug' => 'video_show',
            'parentSlug' => 'video_index',
            'label' => 'Détail vidéo',
            'position' => 5,
            'metaTitleFr' => 'Vidéo | Hollywood Paris',
            'metaTitleEn' => 'Video | Hollywood Paris',
            'metaDescriptionFr' => 'Vidéo par Hollywood Paris',
            'metaDescriptionEn' => 'Video by Hollywood Paris',
        ],
        [
            'slug' => 'contact',
            'parentSlug' => null,
            'label' => 'Contact',
            'position' => 6,
            'metaTitleFr' => 'Contact | Hollywood Paris — Studio photo à Paris',
            'metaTitleEn' => 'Contact | Hollywood Paris — Photo studio in Paris',
            'metaDescriptionFr' => 'Contactez Hollywood Paris pour vos projets photo : mariages, événements, séances mode et portraits corporate. Studio photo professionnel à Paris.',
            'metaDescriptionEn' => 'Get in touch with Hollywood Paris for your photo projects: weddings, events, fashion shoots and corporate portraits. Professional photo studio in Paris.',
        ],
        [
            'slug' => 'team',
            'parentSlug' => null,
            'label' => 'Team',
            'position' => 7,
            'metaTitleFr' => 'Team | Hollywood Paris',
            'metaTitleEn' => 'Team | Hollywood Paris',
            'metaDescriptionFr' => "Découvrez l'équipe d'Hollywood Paris en vidéo.",
            'metaDescriptionEn' => 'Meet the Hollywood Paris team in video.',
        ],
        [
            'slug' => 'options',
            'parentSlug' => null,
            'label' => 'Options',
            'position' => 8,
            'metaTitleFr' => 'Options | Hollywood Paris',
            'metaTitleEn' => 'Options | Hollywood Paris',
            'metaDescriptionFr' => 'Découvrez les options proposées par Hollywood Paris en vidéo.',
            'metaDescriptionEn' => 'Explore the options offered by Hollywood Paris in video.',
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Seed pages');

        try {
            $connection = $this->entityManager->getConnection();
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
            $connection->executeStatement('TRUNCATE TABLE page');
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
            $io->text('table "page" truncated');

            /** @var array<string, Page> $bySlug */
            $bySlug = [];

            foreach (self::PAGES as $data) {
                $page = new Page()
                    ->setSlug($data['slug'])
                    ->setLabel($data['label'])
                    ->setPosition($data['position'])
                    ->setMetaTitleFr($data['metaTitleFr'])
                    ->setMetaTitleEn($data['metaTitleEn'])
                    ->setMetaDescriptionFr($data['metaDescriptionFr'])
                    ->setMetaDescriptionEn($data['metaDescriptionEn']);

                $this->entityManager->persist($page);
                $bySlug[$data['slug']] = $page;
                $io->text(sprintf(' - %s  →  created', $data['slug']));
            }

            foreach (self::PAGES as $data) {
                if ($data['parentSlug'] === null) {
                    continue;
                }

                if (!isset($bySlug[$data['parentSlug']])) {
                    throw new RuntimeException(sprintf('Page "%s" references unknown parent "%s".', $data['slug'], $data['parentSlug']));
                }

                $bySlug[$data['slug']]->setParent($bySlug[$data['parentSlug']]);
            }

            $this->entityManager->flush();

            $io->success(sprintf('%d page(s) seeded.', count(self::PAGES)));

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
