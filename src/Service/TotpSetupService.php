<?php

namespace App\Service;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use OTPHP\TOTP;

class TotpSetupService
{
    private const string ISSUER = 'Hollywood Paris';

    public function generateSecret(): string
    {
        return TOTP::generate()->getSecret();
    }

    public function buildOtpAuthUri(string $secret, string $accountLabel): string
    {
        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($accountLabel);
        $totp->setIssuer(self::ISSUER);

        return $totp->getProvisioningUri();
    }

    public function buildQrCodeSvg(string $uri, int $size = 280): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $uri,
            size: $size,
            margin: 0,
        );

        return $builder->build()->getString();
    }

    public function verifyCode(string $secret, string $code): bool
    {
        return TOTP::createFromSecret($secret)->verify($code);
    }
}
