<?php

namespace App\Builder\Logs;

use App\Entity\Notification;
use App\Entity\User;
use App\Service\Logs\NotificationService;
use Doctrine\ORM\EntityManagerInterface;

class NotificationBuilder
{
    private Notification $notification;

    public function __construct(
        ?User $author = null,
        ?string $expireAfter = null,
        array $audiences = [NotificationService::AUDIENCE_ADMIN],
        string $title = '',
        ?string $description = null
    ) {
        $this->notification = new Notification();
        $this->notification->setAuthor($author);
        $this->notification->setAudience($audiences);
        $this->notification->setTitle($title);
        $this->notification->setDescription($description);

        if ($expireAfter) {
            $this->notification->setExpiresAt(new \DateTimeImmutable($expireAfter));
        }
    }

    public function type(string $type): self
    {
        $this->notification->setType($type);
        return $this;
    }

    public function priority(string $priority): self
    {
        $this->notification->setPriority($priority);
        return $this;
    }

    public function target(User $user): self
    {
        $this->notification->setTargetUser($user);
        return $this;
    }

    public function data(array $data): self
    {
        $this->notification->setData($data);
        return $this;
    }

    public function action(string $url, string $label): self
    {
        $this->notification->setActionUrl($url);
        $this->notification->setActionLabel($label);
        return $this;
    }

    public function style(string $icon, string $color): self
    {
        $this->notification->setIcon($icon);
        $this->notification->setColor($color);
        return $this;
    }

    public function save(EntityManagerInterface $em): Notification
    {
        $em->persist($this->notification);
        $em->flush();
        return $this->notification;
    }
}
