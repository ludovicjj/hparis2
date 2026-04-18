<?php

namespace App\Command;

use Aws\S3\S3Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

#[AsCommand(
    name: 'app:s3:test',
    description: 'Test S3.',
)]
class S3TestCommand extends Command
{
    public function __construct(
        #[Autowire('%env(AWS_S3_ENDPOINT)%')]
        private readonly string $endpoint,
        #[Autowire('%env(AWS_S3_REGION)%')]
        private readonly string $region,
        #[Autowire('%env(AWS_S3_ACCESS_KEY)%')]
        private readonly string $accessKey,
        #[Autowire('%env(AWS_S3_SECRET_KEY)%')]
        private readonly string $secretKey,
        #[Autowire('%env(AWS_S3_BUCKET)%')]
        private readonly string $bucket,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'key',
            InputArgument::REQUIRED,
            'The S3 object key to HEAD (e.g. "temp/926.jpg")'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getArgument('key');

        $io->title('S3 HEAD diagnostic');
        $io->definitionList(
            ['Endpoint'   => $this->endpoint],
            ['Region'     => $this->region],
            ['Bucket'     => $this->bucket],
            ['Access key' => substr($this->accessKey, 0, 4) . '…'],
            ['Target key' => $key],
        );

        $client = new S3Client([
            'version' => 'latest',
            'endpoint' => $this->endpoint,
            'region' => $this->region,
            'credentials' => [
                'key' => $this->accessKey,
                'secret' => $this->secretKey,
            ],
        ]);

        try {
            $result = $client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            $io->success(sprintf(
                'HEAD OK — ContentLength=%s, ContentType=%s, ETag=%s',
                $result['ContentLength'] ?? 'n/a',
                $result['ContentType'] ?? 'n/a',
                $result['ETag'] ?? 'n/a',
            ));

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error('HEAD failed');
            $io->definitionList(
                ['Exception class' => $e::class],
                ['Message'         => $e->getMessage()],
            );
            return Command::FAILURE;
        }
    }
}
