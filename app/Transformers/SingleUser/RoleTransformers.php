<?php

namespace App\Transformers\SingleUser;

use League\Fractal\TransformerAbstract;
use App\Models\Role;

class RoleTransformers extends TransformerAbstract
{
    public function transform(Role $role)
    {
        return [
            'id' => $role->id,
            'title' => $role->title,
            'slug' => $role->slug,
        ];
    }
}
