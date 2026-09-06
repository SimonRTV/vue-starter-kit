<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public const VIEW = 'media.view';

    public const CREATE = 'media.create';

    public const UPDATE = 'media.update';

    public const DELETE = 'media.delete';

    public const MANAGE_ALL = 'media.manage_all';

    public const PUBLISH = 'media.publish';

    /** @var list<string> */
    public const PERMISSIONS = [self::VIEW, self::CREATE, self::UPDATE, self::DELETE, self::MANAGE_ALL, self::PUBLISH];

    /** @var array<string, string> */
    public const PERMISSION_DESCRIPTIONS = [
        self::VIEW => 'Consulter ses fichiers et les télécharger.',
        self::CREATE => 'Importer des fichiers dans la médiathèque.',
        self::UPDATE => 'Modifier les informations de ses fichiers.',
        self::DELETE => 'Supprimer ses fichiers inutilisés.',
        self::MANAGE_ALL => 'Accéder aux fichiers de tous les utilisateurs avec les opérations autorisées.',
        self::PUBLISH => 'Rendre un fichier accessible publiquement.',
    ];

    /** @var list<string> */
    public const SENSITIVE_PERMISSIONS = [self::DELETE, self::MANAGE_ALL, self::PUBLISH];

    public function viewAny(User $user): bool
    {
        return $user->can(self::VIEW);
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can(self::VIEW) && $this->ownsOrManages($user, $media);
    }

    public function create(User $user): bool
    {
        return $user->can(self::CREATE);
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can(self::UPDATE) && $this->ownsOrManages($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->can(self::DELETE) && $this->ownsOrManages($user, $media);
    }

    private function ownsOrManages(User $user, Media $media): bool
    {
        return $media->uploaded_by === $user->id || $user->can(self::MANAGE_ALL);
    }
}
