<?php

namespace App\Transformers\SinglePost;

use League\Fractal\TransformerAbstract;
use App\Models\User;

class UserTransformers extends TransformerAbstract
{
    protected $defaultIncludes = [
        'role',
    ];

    public function transform(User $user)
    {
        return [
            'id' => $user->id,
            'user_name' => $user->user_name,
            'avatar' => $user->avatar,
            'following' => $user->isFollowing(),
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at
        ];
    }

    public function includeRole(User $user)
    {
        $role = $user->role;
        return $this->item($role, new RoleTransformers);
    }
}
