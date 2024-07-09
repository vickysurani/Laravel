<?php

namespace App\Http\Requests\Api;

use Illuminate\Support\Str;

class CreateCommentRequest extends ApiRequest
{

    public function rules()
    {
        return [
            'content' => 'required',
            'slug' => 'unique:comment',
            'post_slug' => 'required'
        ];
    }

    public function messages()
    {
        return [
            'content.required' => 'Content is required',
            'slug.unique' => 'Slug already exists',
            'post_slug.required' => 'post_slug is required'
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'slug' => Str::lower(Str::random(5))
        ]);
    }
}
