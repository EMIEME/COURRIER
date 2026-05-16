<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findBy([], ['fullName' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = new User();
        $user->setRoles(['ROLE_USER']);
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur cree.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel utilisateur',
            'button_label' => 'Creer',
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(UserType::class, $user, ['password_required' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('plainPassword')->getData()) {
                $user->setPassword($passwordHasher->hashPassword($user, (string) $form->get('plainPassword')->getData()));
            }

            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur mis a jour.');

            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('user/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier un utilisateur',
            'button_label' => 'Mettre a jour',
        ]);
    }

    #[Route('/{id}/supprimer', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): RedirectResponse
    {
        if ($this->getUser() === $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('app_user_index');
        }

        if ($this->isCsrfTokenValid('delete-user'.$user->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprime.');
        }

        return $this->redirectToRoute('app_user_index');
    }
}
