<?php

namespace App\Controller\Api;

use App\Entity\Tag;
use App\Entity\ErrorGroup;
use App\Repository\TagRepository;
use App\Repository\ErrorGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tags', name: 'api_tags_')]
#[IsGranted('ROLE_USER')]
class TagController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TagRepository $tagRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ValidatorInterface $validator
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();

        // Paramètres de recherche
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 50);
        $sortBy = $request->query->get('sort', 'usage');

        if ($query) {
            // Recherche avec autocomplete
            $tags = $this->tagRepository->findByNameForUser($query, $user, $limit);
        } else {
            // Liste complète avec filtres
            $filters = [
                'sort_by' => $sortBy,
                'sort_order' => $request->query->get('order', 'DESC'),
                'limit' => $limit
            ];

            if ($request->query->has('min_usage')) {
                $filters['min_usage'] = $request->query->getInt('min_usage');
            }

            $tags = $this->tagRepository->findWithFilters($user, $filters);
        }

        $data = array_map(fn(Tag $tag) => $tag->toArray(), $tags);

        return $this->json([
            'success' => true,
            'tags' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/autocomplete', name: 'autocomplete', methods: ['GET'])]
    public function autocomplete(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $query = $request->query->get('q', '');
        $limit = $request->query->getInt('limit', 10);

        if (strlen($query) < 1) {
            return $this->json([
                'success' => true,
                'tags' => []
            ]);
        }

        $tags = $this->tagRepository->findByNameForUser($query, $user, $limit);

        $data = array_map(function(Tag $tag) {
            return [
                'id' => $tag->getId(),
                'name' => $tag->getName(),
                'color' => $tag->getColor(),
                'usage_count' => $tag->getUsageCount()
            ];
        }, $tags);

        return $this->json([
            'success' => true,
            'tags' => $data
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $name = trim($data['name'] ?? '');
        $color = $data['color'] ?? null;
        $description = $data['description'] ?? null;

        if (!$name) {
            return $this->json(['error' => 'Le nom du tag est requis'], 400);
        }

        // Vérifier que le tag n'existe pas déjà
        $existingTag = $this->tagRepository->findOneByNameAndUser($name, $user);
        if ($existingTag) {
            return $this->json([
                'success' => true,
                'tag' => $existingTag->toArray(),
                'message' => 'Tag déjà existant'
            ]);
        }

        $tag = new Tag();
        $tag->setName($name)
            ->setOwner($user)
            ->setDescription($description)
            ->setCreatedAt(new \DateTime())
            ->setUpdatedAt(new \DateTime())
        ;

        if ($color) {
            $tag->setColor($color);
        }

        $tag->initialize(); // Génère la couleur et le slug si nécessaires

        $errors = $this->validator->validate($tag);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'tag' => $tag->toArray(),
            'message' => 'Tag créé avec succès'
        ], 201);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        $user = $this->getUser();
        $tag = $this->tagRepository->find($id);

        if (!$tag || $tag->getOwner() !== $user) {
            return $this->json(['error' => 'Tag non trouvé'], 404);
        }

        return $this->json([
            'success' => true,
            'tag' => $tag->toArray()
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $tag = $this->tagRepository->find($id);

        if (!$tag || !$tag->canEdit($user)) {
            return $this->json(['error' => 'Tag non trouvé ou accès refusé'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            $newName = trim($data['name']);
            if ($newName && $newName !== $tag->getName()) {
                // Vérifier que le nouveau nom n'existe pas déjà
                $existingTag = $this->tagRepository->findOneByNameAndUser($newName, $user);
                if ($existingTag && $existingTag->getId() !== $tag->getId()) {
                    return $this->json(['error' => 'Un tag avec ce nom existe déjà'], 400);
                }
                $tag->setName($newName);
            }
        }

        if (isset($data['color'])) {
            $tag->setColor($data['color']);
        }

        if (isset($data['description'])) {
            $tag->setDescription($data['description']);
        }

        $errors = $this->validator->validate($tag);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }

        $tag->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'tag' => $tag->toArray(),
            'message' => 'Tag modifié avec succès'
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        $tag = $this->tagRepository->find($id);

        if (!$tag || !$tag->canDelete($user)) {
            return $this->json(['error' => 'Tag non trouvé ou accès refusé'], 404);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Tag supprimé avec succès'
        ]);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();
        $stats = $this->tagRepository->getStatsForUser($user);

        return $this->json([
            'success' => true,
            'stats' => $stats
        ]);
    }

    #[Route('/cleanup', name: 'cleanup', methods: ['POST'])]
    public function cleanup(): JsonResponse
    {
        $user = $this->getUser();
        $deletedCount = $this->tagRepository->cleanupUnusedTags($user);

        return $this->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'message' => sprintf('%d tag(s) non utilisé(s) supprimé(s)', $deletedCount)
        ]);
    }

    #[Route('/error/{errorId}/add', name: 'add_to_error', methods: ['POST'], requirements: ['errorId' => '\d+'])]
    public function addToError(int $errorId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($errorId);

        if (!$errorGroup || !$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Erreur non trouvée ou accès refusé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $tagNames = $data['tags'] ?? [];

        if (!is_array($tagNames)) {
            return $this->json(['error' => 'Format de données invalide'], 400);
        }

        $addedTags = [];
        $existingTags = [];

        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if (!$tagName) continue;

            // Trouver ou créer le tag
            $tag = $this->tagRepository->findOrCreateByNameAndUser($tagName, $user);

            // Ajouter le tag à l'erreur s'il n'y est pas déjà
            if (!$errorGroup->hasTag($tag)) {
                $errorGroup->addTag($tag);
                $addedTags[] = $tag->toArray();
            } else {
                $existingTags[] = $tag->getName();
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'added_tags' => $addedTags,
            'existing_tags' => $existingTags,
            'message' => sprintf('%d tag(s) ajouté(s)', count($addedTags))
        ]);
    }

    #[Route('/error/{errorId}/remove', name: 'remove_from_error', methods: ['POST'], requirements: ['errorId' => '\d+'])]
    public function removeFromError(int $errorId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($errorId);

        if (!$errorGroup || !$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Erreur non trouvée ou accès refusé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $tagIds = $data['tag_ids'] ?? [];

        if (!is_array($tagIds)) {
            return $this->json(['error' => 'Format de données invalide'], 400);
        }

        $removedCount = 0;

        foreach ($tagIds as $tagId) {
            $tag = $this->tagRepository->find($tagId);
            if ($tag && $tag->getOwner() === $user && $errorGroup->hasTag($tag)) {
                $errorGroup->removeTag($tag);
                $removedCount++;
            }
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'removed_count' => $removedCount,
            'message' => sprintf('%d tag(s) supprimé(s)', $removedCount)
        ]);
    }

    #[Route('/error/{errorId}/sync', name: 'sync_error_tags', methods: ['POST'], requirements: ['errorId' => '\d+'])]
    public function syncErrorTags(int $errorId, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($errorId);

        if (!$errorGroup || !$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Erreur non trouvée ou accès refusé'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $tagNames = $data['tags'] ?? [];

        if (!is_array($tagNames)) {
            return $this->json(['error' => 'Format de données invalide'], 400);
        }

        // Supprimer tous les tags actuels
        $errorGroup->clearTags();

        // Ajouter les nouveaux tags
        $tags = [];
        foreach ($tagNames as $tagName) {
            $tagName = trim($tagName);
            if (!$tagName) continue;

            $tag = $this->tagRepository->findOrCreateByNameAndUser($tagName, $user);
            $errorGroup->addTag($tag);
            $tags[] = $tag->toArray();
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'tags' => $tags,
            'message' => sprintf('%d tag(s) synchronisé(s)', count($tags))
        ]);
    }

    #[Route('/refresh-counts', name: 'refresh_counts', methods: ['POST'])]
    public function refreshCounts(): JsonResponse
    {
        $user = $this->getUser();
        $this->tagRepository->refreshUsageCounts($user);

        return $this->json([
            'success' => true,
            'message' => 'Compteurs d\'usage mis à jour'
        ]);
    }
}
