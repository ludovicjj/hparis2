<?php

namespace App\Command;

use App\Enum\BrandSetting;
use App\Service\Setting\BrandSettingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:brand-settings',
    description: 'Set the site name and (optionally) the Google Search Console verification token. Idempotent — overwrites existing values.',
)]
class BrandSettingsCommand extends Command
{
    public function __construct(
        private readonly BrandSettingService $brandSettingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('site-name', InputArgument::REQUIRED, 'Nom du site (ex: "Hollywood Paris")')
            ->addArgument('gsc-token', InputArgument::OPTIONAL, 'Token Google Search Console (omettre si non configuré)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Set brand settings');

        $siteName = (string) $input->getArgument('site-name');
        $gscToken = $input->getArgument('gsc-token');

        $this->brandSettingService->save(BrandSetting::SITE_NAME, $siteName);
        $io->text(sprintf(' - site_name => %s', $siteName));

        if ($gscToken !== null) {
            $this->brandSettingService->save(BrandSetting::GSC_TOKEN, (string) $gscToken);
            $io->text(' - gsc_token => set');
        } else {
            $io->text(' - gsc_token => skipped (no value provided)');
        }

        $io->success('Brand settings saved.');

        return Command::SUCCESS;
    }
}
