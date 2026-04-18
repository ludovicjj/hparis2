<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:totp',
    description: 'Manage TOTP 2FA by user',
)]
class UserTotpCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email of the target user')
            // Action
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Generate a fresh TOTP secret and set totp_enabled to true')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Clear the TOTP secret and set totp_enabled to false (bypass 2FA, e.g. lost phone)')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Clear the TOTP secret but keep totp_enabled true (force re-setup via web UI on next login)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $doEnable = (bool) $input->getOption('enable');
        $doDisable = (bool) $input->getOption('disable');
        $doReset = (bool) $input->getOption('reset');

        if ((int) $doEnable + (int) $doDisable + (int) $doReset > 1) {
            $io->error('You can choose only one option, allowed option are --enable, --disable and --reset.');
            return Command::FAILURE;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            $io->error(sprintf('No user found with email "%s".', $email));
            return Command::FAILURE;
        }

        if ($doEnable) {
            $secret = TOTP::generate()->getSecret();
            $user->setTotpSecret($secret);
            $user->setTotpDraftSecret(null);
            $user->setTotpEnabled(true);
            $this->entityManager->flush();

            $io->success(sprintf('2FA enabled for %s with a fresh secret.', $email));
            $io->definitionList(
                ['Secret (base32)' => $secret],
            );
            return Command::SUCCESS;
        }

        if ($doDisable) {
            $user->setTotpSecret(null);
            $user->setTotpDraftSecret(null);
            $user->setTotpEnabled(false);
            $this->entityManager->flush();

            $io->success(sprintf('2FA disabled for %s (secret cleared, totp_enabled set to false).', $email));
            return Command::SUCCESS;
        }

        if ($doReset) {
            $user->setTotpSecret(null);
            $user->setTotpDraftSecret(null);
            $user->setTotpEnabled(true);
            $this->entityManager->flush();

            $io->success(sprintf('2FA reset for %s (secret cleared, totp_enabled kept true).', $email));
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
