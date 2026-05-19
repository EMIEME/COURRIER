<?php

namespace App\Controller;

use App\Entity\ListOption;
use App\Repository\ListOptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/parametres/listes')]
#[IsGranted('ROLE_ADMIN')]
class ListOptionController extends AbstractController
{
    #[Route('', name: 'app_list_option_index', methods: ['GET'])]
    public function index(ListOptionRepository $listOptionRepository): Response
    {
        return $this->render('list_option/index.html.twig', [
            'categories' => ListOption::CATEGORIES,
            'open_categories' => ListOption::OPEN_CATEGORIES,
            'groupedOptions' => $listOptionRepository->findGrouped(),
        ]);
    }

    #[Route('/ajouter', name: 'app_list_option_new', methods: ['POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('new-list-option', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $category = (string) $request->request->get('category');
        if (!in_array($category, ListOption::OPEN_CATEGORIES, true)) {
            $this->addFlash('error', 'Cette liste ne peut pas recevoir de nouvelles valeurs.');

            return $this->redirectToRoute('app_list_option_index');
        }

        $label = trim((string) $request->request->get('label'));
        if ('' === $label) {
            $this->addFlash('error', 'Le libelle est obligatoire.');

            return $this->redirectToRoute('app_list_option_index');
        }

        $value = trim((string) $request->request->get('value')) ?: $this->normalizeValue($label);

        $option = (new ListOption())
            ->setCategory($category)
            ->setLabel($label)
            ->setValue($value)
            ->setPosition((int) $request->request->get('position', 0))
            ->setActive(true)
            ->setLocked(false);

        $entityManager->persist($option);
        $entityManager->flush();

        $this->addFlash('success', 'Valeur ajoutee.');

        return $this->redirectToRoute('app_list_option_index');
    }

    #[Route('/{id}/modifier', name: 'app_list_option_edit', methods: ['POST'])]
    public function edit(Request $request, ListOption $option, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('edit-list-option'.$option->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $label = trim((string) $request->request->get('label'));
        if ('' === $label) {
            $this->addFlash('error', 'Le libelle est obligatoire.');

            return $this->redirectToRoute('app_list_option_index');
        }

        $option
            ->setLabel($label)
            ->setPosition((int) $request->request->get('position', 0))
            ->setActive($request->request->getBoolean('active'));

        if (!$option->isLocked()) {
            $value = trim((string) $request->request->get('value')) ?: $this->normalizeValue($label);
            $option->setValue($value);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Valeur mise a jour.');

        return $this->redirectToRoute('app_list_option_index');
    }

    #[Route('/{id}/supprimer', name: 'app_list_option_delete', methods: ['POST'])]
    public function delete(Request $request, ListOption $option, EntityManagerInterface $entityManager): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('delete-list-option'.$option->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($option->isLocked()) {
            $this->addFlash('error', 'Cette valeur est protegee et ne peut pas etre supprimee.');

            return $this->redirectToRoute('app_list_option_index');
        }

        $entityManager->remove($option);
        $entityManager->flush();

        $this->addFlash('success', 'Valeur supprimee.');

        return $this->redirectToRoute('app_list_option_index');
    }

    private function normalizeValue(string $label): string
    {
        return strtolower((new AsciiSlugger())->slug($label, '_')->toString());
    }
}
