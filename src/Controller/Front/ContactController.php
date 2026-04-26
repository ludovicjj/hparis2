<?php

namespace App\Controller\Front;

use App\Form\ContactType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_front_contact', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        MailerInterface $mailer,
        LoggerInterface $logger,
        #[Autowire('%env(CONTACT_FROM)%')] string $contactFrom,
        #[Autowire('%env(CONTACT_TO)%')] string $contactTo,
    ): Response {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = new TemplatedEmail()
                ->from($contactFrom)
                ->to($contactTo)
                ->subject('[Contact] ' . $data['subject'] . ' — ' . $data['email'])
                ->htmlTemplate('emails/contact.html.twig')
                ->context([
                    'senderEmail' => $data['email'],
                    'subject' => $data['subject'],
                    'message' => $data['message'],
                ]);

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons rapidement.');

                return $this->redirectToRoute('app_front_contact');
            } catch (TransportExceptionInterface $e) {
                $logger->error('Contact form mail failed', ['exception' => $e]);
                $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de votre message. Veuillez réessayer plus tard.');
            }
        }

        return $this->render('front/contact/index.html.twig', [
            'form' => $form,
        ]);
    }
}
