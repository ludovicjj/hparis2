<?php

namespace App\Command;

use App\Service\S3Service;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:s3:test',
    description: 'Smoke test the Scaleway Object Storage connection: uploads one public + one private object and verifies their reachability via direct URL.',
)]
class S3TestCommand extends Command
{
    public function __construct(
        private readonly S3Service $s3Service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'keep',
            null,
            InputOption::VALUE_NONE,
            'Do not delete the uploaded test objects at the end — useful to manually verify the public URLs in a browser.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Scaleway S3 smoke test');

        $keep = (bool) $input->getOption('keep');
        $content = 'Hello from H Paris at ' . (new \DateTimeImmutable())->format(\DATE_ATOM);

        $publicKey = 'tests/public-' . uniqid() . '.txt';
        $privateKey = 'tests/private-' . uniqid() . '.txt';

        // ─── 1a. Public upload ──────────────────────────────────────────
        $io->section('1a. Public upload (uploadPublicFile)');
        $io->text(sprintf('Key: <info>%s</info>', $publicKey));

        if (!$this->s3Service->uploadPublicFile($publicKey, $content, 'text/plain')) {
            $io->error('uploadPublicFile() failed.');
            return Command::FAILURE;
        }
        $io->success('Upload OK');

        $publicUrl = $this->s3Service->getPublicUrl($publicKey);
        $io->text(sprintf('Public URL: <info>%s</info>', $publicUrl));

        [$publicStatus, $publicBody] = $this->fetchUrl($publicUrl);
        $io->text(sprintf('HTTP status from direct fetch: <info>%d</info>', $publicStatus));

        if ($publicStatus !== 200) {
            $io->error('Expected HTTP 200 on the public object URL — public ACL is not effective.');
            $io->writeln('<comment>Response body:</comment>');
            $io->writeln($publicBody);
            return Command::FAILURE;
        }
        if ($publicBody !== $content) {
            $io->error('Public URL returned a 200 but the body does not match the upload content.');
            return Command::FAILURE;
        }
        $io->success('Public object is reachable via direct URL and content matches');

        // ─── 1b. Private upload ─────────────────────────────────────────
        $io->section('1b. Private upload (uploadPrivateFile)');
        $io->text(sprintf('Key: <info>%s</info>', $privateKey));

        if (!$this->s3Service->uploadPrivateFile($privateKey, $content, 'text/plain')) {
            $io->error('uploadPrivateFile() failed.');
            return Command::FAILURE;
        }
        $io->success('Upload OK');

        $privateUrl = $this->s3Service->getPublicUrl($privateKey);
        $io->text(sprintf('Direct URL: <info>%s</info>', $privateUrl));

        [$privateStatus, ] = $this->fetchUrl($privateUrl);
        $io->text(sprintf('HTTP status from direct fetch: <info>%d</info>', $privateStatus));

        if ($privateStatus === 200) {
            $io->error('Private object is publicly reachable via direct URL — this should not happen!');
            return Command::FAILURE;
        }
        $io->success(sprintf('Private object correctly forbidden from direct URL (status %d)', $privateStatus));

        // The backend should still be able to read the private object via authenticated SDK
        $io->text('Reading private object via authenticated SDK (getFileContent)...');
        $downloaded = $this->s3Service->getFileContent($privateKey);
        if ($downloaded === false) {
            $io->error('getFileContent() failed on private object.');
            return Command::FAILURE;
        }
        if ($downloaded !== $content) {
            $io->error('Authenticated read of private object returned mismatched content.');
            return Command::FAILURE;
        }
        $io->success('Authenticated read of private object OK (content matches)');

        // ─── 2. Presigned URL on the private object ─────────────────────
        $io->section('2. Presigned URL (on the private object)');
        $presigned = $this->s3Service->getPresignedUrl($privateKey);
        if ($presigned === false) {
            $io->warning('getPresignedUrl() returned false (non-blocking).');
        } else {
            $io->text(sprintf('Presigned URL: <info>%s</info>', $presigned));
            [$presignedStatus, ] = $this->fetchUrl($presigned);
            if ($presignedStatus === 200) {
                $io->success(sprintf('Presigned URL returns HTTP %d as expected', $presignedStatus));
            } else {
                $io->warning(sprintf('Presigned URL returned HTTP %d (expected 200, non-blocking).', $presignedStatus));
            }
        }

        // ─── 3. Cleanup ─────────────────────────────────────────────────
        $io->section('3. Cleanup');
        if ($keep) {
            $io->warning(sprintf(
                "Skipping delete (--keep). Leftover keys:\n  - public:  %s\n  - private: %s\nClean them up later from the Scaleway console.",
                $publicKey,
                $privateKey
            ));
        } else {
            foreach ([$publicKey, $privateKey] as $key) {
                if (!$this->s3Service->deleteFile($key)) {
                    $io->error(sprintf('deleteFile() returned false for "%s".', $key));
                    return Command::FAILURE;
                }
            }
            $io->success('Deleted both test objects');
        }

        $io->newLine();
        $io->success('All S3 operations succeeded.');

        return Command::SUCCESS;
    }

    /**
     * Fetch a URL with file_get_contents() and return [status, body].
     * Avoids pulling symfony/http-client just for this smoke test.
     */
    private function fetchUrl(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true, // do not throw on 4xx/5xx, return body anyway
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return [0, ''];
        }

        // $http_response_header is magically populated by file_get_contents
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return [$status, $body];
    }
}
