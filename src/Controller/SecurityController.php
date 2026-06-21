<?php

namespace App\Controller;

use App\Form\ForgotPasswordRequestType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response|RedirectResponse
    {
        if ($this->getUser()) {
            if (!$this->isGranted('ROLE_COURRIER_VIEW')) {
                return $this->redirectToRoute('app_account_no_access');
            }

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, MailerInterface $mailer, UrlGeneratorInterface $urlGenerator, ParameterBagInterface $parameterBag): Response
    {
        $form = $this->createForm(ForgotPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = (string) $form->get('email')->getData();
            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                    $token = bin2hex(random_bytes(32));
                    $user->setPasswordResetToken($token);
                    $user->setPasswordResetRequestedAt(new \DateTimeImmutable());
                    $entityManager->persist($user);
                    $entityManager->flush();
                $resetUrl = $urlGenerator->generate('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $fromAddress = (string) $parameterBag->get('app.mail_from_address');
                $fromName = (string) $parameterBag->get('app.mail_from_name');
                $fromAddress = $fromAddress ?: 'no-reply@example.com';
                $fromName = $fromName ?: 'Gestion Courrier';

                $htmlBody = sprintf(
                    '<p>Bonjour %s,</p><p>Une demande de réinitialisation de mot de passe a été effectuée pour votre compte.</p><p>Pour définir un nouveau mot de passe, cliquez sur le lien suivant :</p><p><a href="%s">%s</a></p><p>Si vous n\'avez pas demandé ce changement, ignorez ce message.</p>',
                    htmlspecialchars($user->getFullName() ?: $user->getEmail(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                    $resetUrl,
                    $resetUrl,
                );

                $mailer->send((new Email())
                    ->from(new Address($fromAddress, $fromName))
                    ->to(new Address($user->getEmail(), $user->getFullName() ?: $user->getEmail()))
                    ->subject('Réinitialisation de mot de passe')
                    ->html($htmlBody)
                    ->text(strip_tags(str_replace(['<p>', '</p>', '<a href="', '">', '</a>'], ["\n\n", '', '', '', ''], $htmlBody)))
                );
            }

            $this->addFlash('success', 'Si cette adresse email est enregistrée, un lien de réinitialisation vous a été envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = $userRepository->findOneBy(['passwordResetToken' => $token]);
        $tokenTtl = 3600;

        if (!$user || $user->isPasswordResetTokenExpired($tokenTtl)) {
            if ($user) {
                $user->clearPasswordReset();
                $entityManager->flush();
            }

            $this->addFlash('error', 'Le lien de réinitialisation est invalide ou expiré.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $user->clearPasswordReset();
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/compte/sans-acces', name: 'app_account_no_access', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function noAccess(): Response
    {
        if ($this->isGranted('ROLE_COURRIER_VIEW')) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/no_access.html.twig');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
