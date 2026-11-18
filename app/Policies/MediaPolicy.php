<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    protected function allows(User $user, string $permission): bool
    {
        return $user->can($permission);
    }

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'media.view')
            || $this->allows($user, 'media.upload')
            || $this->allows($user, 'media.delete')
            || $this->allows($user, 'media.view_private');
    }

    public function view(User $user, Media $media): bool
    {
        if ($media->isPrivate()) {
            return $this->allows($user, 'media.view_private');
        }

        return $this->viewAny($user);
    }

    public function upload(User $user): bool
    {
        return $this->allows($user, 'media.upload');
    }

    public function delete(User $user, Media $media): bool
    {
        return $this->allows($user, 'media.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->allows($user, 'media.delete');
    }

    public function viewPrivate(User $user): bool
    {
        return $this->allows($user, 'media.view_private');
    }

    public function restore(User $user, Media $media): bool
    {
        return false;
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $this->allows($user, 'media.delete');
    }
}
