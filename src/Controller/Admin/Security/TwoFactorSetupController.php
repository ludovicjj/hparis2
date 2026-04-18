<?php

namespace App\Controller\Admin\Security;

use App\Entity\User;
use App\Service\TotpSetupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class TwoFactorSetupController extends AbstractController
{
    private const string CSRF_TOKEN_ID = '2fa_setup';

    public function __construct(
        private readonly TotpSetupService $totpSetup,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Route(path: '/2fa/setup', name: 'app_2fa_setup')]
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user
    ): Response {
        if ($user->getTotpSecret() !== null) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $secret = $user->getTotpDraftSecret();

        if ($secret === null) {
            $secret = $this->totpSetup->generateSecret();
            $user->setTotpDraftSecret($secret);
            $this->entityManager->flush();
        }

        if ($request->isMethod('POST')) {
            $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token'));
            $code = (string) $request->request->get('_auth_code');

            $error = match (true) {
                !$this->csrfTokenManager->isTokenValid($token) => 'Jeton CSRF invalide. Rechargez la page et réessayez.',
                !$this->totpSetup->verifyCode($secret, $code) => "Code invalide. Vérifiez l'heure de votre téléphone et réessayez.",
                default => null,
            };

            if ($error === null) {
                $user->setTotpSecret($secret);
                $user->setTotpDraftSecret(null);
                $user->setTotpEnabled(true);
                $this->entityManager->flush();

                return $this->redirectToRoute('app_admin_dashboard');
            }
        }

        $uri = $this->totpSetup->buildOtpAuthUri($secret, $user->getUserIdentifier());
        $qrSvg = $this->totpSetup->buildQrCodeSvg($uri);

        return $this->render('admin/security/2fa_setup.html.twig', [
            'qr_svg'        => $qrSvg,
            'secret'        => $secret,
            'error'         => $error ?? null,
            'csrf_token_id' => self::CSRF_TOKEN_ID,
        ]);
    }
}
