<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
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
