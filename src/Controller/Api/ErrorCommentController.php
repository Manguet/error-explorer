<?php

namespace App\Controller\Api;

use App\Entity\ErrorComment;
use App\Entity\ErrorGroup;
use App\Repository\ErrorCommentRepository;
use App\Repository\ErrorGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/comments', name: 'api_comments_')]
#[IsGranted('ROLE_USER')]
class ErrorCommentController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ErrorCommentRepository $commentRepository,
        private readonly ErrorGroupRepository $errorGroupRepository,
        private readonly ValidatorInterface $validator,
        private readonly SluggerInterface $slugger,
        private readonly string $projectDir
    ) {}

    #[Route('/error/{errorId}', name: 'list', methods: ['GET'])]
    public function list(int $errorId): JsonResponse
    {
        $user = $this->getUser();
        $errorGroup = $this->errorGroupRepository->find($errorId);

        if (!$errorGroup) {
            return $this->json(['error' => 'Erreur non trouvée'], 404);
        }

        // Vérifier que l'erreur appartient à l'utilisateur
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $comments = $this->commentRepository->findMainCommentsByErrorGroup($errorGroup);
        $commentsData = [];

        foreach ($comments as $comment) {
            $replies = $this->commentRepository->findRepliesByParent($comment);
            $repliesData = [];

            foreach ($replies as $reply) {
                $repliesData[] = $this->formatComment($reply);
            }

            $commentData = $this->formatComment($comment);
            $commentData['replies'] = $repliesData;
            $commentsData[] = $commentData;
        }

        return $this->json([
            'success' => true,
            'comments' => $commentsData,
            'total' => count($commentsData)
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $errorId = $data['error_id'] ?? null;
        $content = $data['content'] ?? null;
        $parentId = $data['parent_id'] ?? null;
        $isInternal = $data['is_internal'] ?? false;

        if (!$errorId || !$content) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        $errorGroup = $this->errorGroupRepository->find($errorId);
        if (!$errorGroup) {
            return $this->json(['error' => 'Erreur non trouvée'], 404);
        }

        // Vérifier l'accès
        if (!$this->errorGroupRepository->belongsToUser($errorGroup, $user)) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $parent = null;
        if ($parentId) {
            $parent = $this->commentRepository->find($parentId);
            if (!$parent || $parent->getErrorGroup() !== $errorGroup) {
                return $this->json(['error' => 'Commentaire parent invalide'], 400);
            }
        }

        $comment = new ErrorComment();
        $comment->setErrorGroup($errorGroup)
                ->setAuthor($user)
                ->setContent($content)
                ->setParent($parent)
                ->setIsInternal($isInternal);

        $errors = $this->validator->validate($comment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'comment' => $this->formatComment($comment),
            'message' => 'Commentaire ajouté avec succès'
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['PUT'])]
    public function edit(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->json(['error' => 'Commentaire non trouvé'], 404);
        }

        if (!$comment->canEdit($user)) {
            return $this->json(['error' => 'Vous ne pouvez pas modifier ce commentaire'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!$content) {
            return $this->json(['error' => 'Contenu manquant'], 400);
        }

        $comment->setContent($content);

        $errors = $this->validator->validate($comment);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => implode(', ', $errorMessages)], 400);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'comment' => $this->formatComment($comment),
            'message' => 'Commentaire modifié avec succès'
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $user = $this->getUser();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->json(['error' => 'Commentaire non trouvé'], 404);
        }

        if (!$comment->canDelete($user)) {
            return $this->json(['error' => 'Vous ne pouvez pas supprimer ce commentaire'], 403);
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Commentaire supprimé avec succès'
        ]);
    }

    #[Route('/{id}/upload', name: 'upload', methods: ['POST'])]
    public function uploadAttachment(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $comment = $this->commentRepository->find($id);

        if (!$comment) {
            return $this->json(['error' => 'Commentaire non trouvé'], 404);
        }

        if (!$comment->canEdit($user)) {
            return $this->json(['error' => 'Vous ne pouvez pas modifier ce commentaire'], 403);
        }

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->files->get('file');

        if (!$uploadedFile) {
            return $this->json(['error' => 'Aucun fichier fourni'], 400);
        }

        // Récupérer les informations du fichier avant qu'il ne soit déplacé
        $mimeType = $uploadedFile->getMimeType();
        $fileSize = $uploadedFile->getSize();
        $originalName = $uploadedFile->getClientOriginalName();

        // Vérifier le type et la taille
        $allowedMimeTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'application/json'
        ];

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return $this->json(['error' => 'Type de fichier non autorisé'], 400);
        }

        if ($fileSize > 5 * 1024 * 1024) { // 5MB max
            return $this->json(['error' => 'Fichier trop volumineux (max 5MB)'], 400);
        }

        try {
            $originalFilename = pathinfo($originalName, PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$uploadedFile->guessExtension();

            // Créer le dossier s'il n'existe pas
            $uploadDir = $this->projectDir . '/public/uploads/comments';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $uploadedFile->move($uploadDir, $newFilename);

            $attachment = [
                'filename' => $newFilename,
                'original_name' => $originalName,
                'mime_type' => $mimeType,
                'size' => $fileSize,
                'type' => str_starts_with($mimeType, 'image/') ? 'image' : 'file',
                'uploaded_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'uploaded_by' => $user->getId()
            ];

            $comment->addAttachment($attachment);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'attachment' => $attachment,
                'message' => 'Fichier uploadé avec succès'
            ]);

        } catch (FileException $e) {
            return $this->json(['error' => 'Erreur lors de l\'upload: ' . $e->getMessage()], 500);
        }
    }

    private function formatComment(ErrorComment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'author' => [
                'id' => $comment->getAuthor()->getId(),
                'name' => $comment->getAuthor()->getFullName(),
                'initials' => $comment->getAuthor()->getInitials(),
                'email' => $comment->getAuthor()->getEmail()
            ],
            'created_at' => $comment->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $comment->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'time_ago' => $comment->getTimeAgo(),
            'is_edited' => $comment->isEdited(),
            'is_internal' => $comment->isInternal(),
            'attachments' => $comment->getAttachments() ?? [],
            'image_attachments' => $comment->getImageAttachments(),
            'file_attachments' => $comment->getFileAttachments(),
            'can_edit' => $comment->canEdit($this->getUser()),
            'can_delete' => $comment->canDelete($this->getUser()),
            'parent_id' => $comment->getParent()?->getId()
        ];
    }
}
