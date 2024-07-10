<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Models\Post;
use App\Models\PostTag;
use App\Models\Follow;
use App\Models\User;
use App\Models\Tag;
use App\Models\FavoritePost;
use Illuminate\Support\Facades\File;
use App\Transformers\ListPost\PostTransformers as ListPostTransformers;
use App\Transformers\SinglePost\PostTransformers as SinglePostTransformers;
use App\Http\Requests\Api\CreatePostRequest;
use App\Http\Requests\Api\UpdatePostRequest;

class PostController extends ApiController
{
    public function listPost(Request $request)
    {
        $user = auth('api')->user();
        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);
        $sortBy = $request->get('sort_by', 'feed');
        $sortDirection = $request->get('sort_direction', 'desc');

        if ($sortDirection == 'desc') {
            $orderDirection = 'desc';
        } else if ($sortDirection == 'asc') {
            $orderDirection = 'asc';
        } else {
            return $this->respondNotFound();
        }

        if ($request->has('tag')) {
            $post = Post::whereHas('tag', function ($q) use ($request) {
                $q->where('slug', $request->tag);
            })->where('published', 1);
            if ($sortBy == 'feed') {
                if ($user) {
                    $post = $post->where(function ($subQuery) use ($user) {
                        $subQuery->whereHas('tag', function ($q) use ($user) {
                            $q->whereIn('slug',  Tag::select('slug')->whereHas('followtag', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        })->orWhereHas('user', function ($q) use ($user) {
                            $q->whereIn('user_name',  User::select('user_name')->whereHas('following', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        });
                    });
                } else {
                    $post = $post->orderBy('published_at', $orderDirection);
                }
            } else if ($sortBy == 'published_at') {
                $post = $post->orderBy('published_at', $orderDirection);
            } else {
                return $this->respondNotFound();
            }
        } else if ($request->has('category')) {
            $post = Post::whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            })->where('published', 1);
            if ($sortBy == 'feed') {
                if ($user) {
                    $post = $post->where(function ($subQuery) use ($user) {
                        $subQuery->whereHas('user', function ($q) use ($user) {
                            $q->whereIn('user_name',  User::select('user_name')->whereHas('following', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        })->orWhereHas('tag', function ($q) use ($user) {
                            $q->whereIn('slug',  Tag::select('slug')->whereHas('followtag', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        });
                    })->orderBy('published_at', $orderDirection);
                } else {
                    $post = $post->orderBy('published_at', $orderDirection);
                }
            } else if ($sortBy == 'published_at') {
                $post = $post->orderBy('published_at', $orderDirection);
            } else {
                return $this->respondNotFound();
            }
        } else if ($request->has('user')) {
            $post = Post::whereHas('user', function ($q) use ($request) {
                $q->where('user_name', $request->user);
            });
            if ($sortBy == 'published_at') {
                $post = $post->orderBy('published_at', $orderDirection);
            } else {
                return $this->respondNotFound();
            }
        } else {
            $post = Post::where('pinned', 0)->where('published', 1);
            if ($sortBy == 'feed') {
                if ($user) {
                    $post = $post->where(function ($subQuery) use ($user) {
                        $subQuery->whereHas('user', function ($q) use ($user) {
                            $q->whereIn('user_name',  User::select('user_name')->whereHas('following', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        })->orWhereHas('tag', function ($q) use ($user) {
                            $q->whereIn('slug',  Tag::select('slug')->whereHas('followtag', function ($q) use ($user) {
                                $q->where('user_id',  $user->id);
                            })->get());
                        });
                    })->orderBy('published_at', $orderDirection);
                } else {
                    $post = $post->orderBy('published_at', $orderDirection);
                }
            } else if ($sortBy == 'published_at') {
                $post = $post->orderBy('published_at', $orderDirection);
            } else {
                return $this->respondNotFound();
            }
        }

        $postsCount = $post->get()->count();
        $listPost = fractal($post->skip($offset)->take($limit)->get(), new ListPostTransformers);
        return $this->respondSuccessWithPagination($listPost, $postsCount);
    }

    public function listPostPinned(Request $request, $limit = 10, $offset = 0, $field = 'created_at', $type = 'desc', $tab = 'feed')
    {
        $limit = $request->get('limit', $limit);
        $offset = $request->get('offset', $offset);

        $post = Post::where('pinned', 1);
        $postsCount = $post->get()->count();
        $listPost = fractal($post->orderBy($field, $type)->skip($offset)->take($limit)->get(), new ListPostTransformers);
        return $this->respondSuccessWithPagination($listPost, $postsCount);
    }

    public function singlePost(Request $request, $slug)
    {
        $post = Post::where('slug', $slug)->where('user_id', User::where('user_name', $request->user_name)->firstOrFail()->id);
        $singlePost = fractal($post->firstOrFail(), new SinglePostTransformers);
        return $this->respondSuccess($singlePost);
    }

    public function createPost(CreatePostRequest $request)
    {
        /* if ($request->hasfile('image')) {
            $imageName = time() . '.' . $request->file('image')->extension();
            Storage::disk('s3')->put('images/' . $imageName, file_get_contents($request->file('image')), 'public');
        } else {
            $imageName = null;
        } */

        // Public folder
        if ($request->hasfile('image')) {
            $imageName = time() . '.' . $request->file('image')->extension();
            Storage::disk('images')->put($imageName, file_get_contents($request->file('image')));
        } else {
            $imageName = null;
        }

        $createPost = new Post;
        $createPost->category_id = $request->category_id;
        $createPost->user_id = auth()->user()->id;
        $createPost->title = $request->title;
        $createPost->slug = Str::slug($request->title, '-') . '-' . Str::lower(Str::random(4));
        $createPost->content = $request->content;
        $createPost->image = $imageName;
        $createPost->pinned = '0';
        $createPost->published = '1';
        $createPost->published_at = Carbon::now()->toDateTimeString();
        $createPost->excerpt = Str::limit(
            preg_replace(
                '/\s+/',
                ' ',
                trim(
                    strip_tags(
                        Str::markdown($request->content)
                    )
                )
            ),
            166,
            '...'
        );
        $createPost->save();
        $lastIdPost = $createPost->id;

        foreach ($request->tags as $key => $tags) {
            $convertTitleToSlug = Str::slug($tags['slug'], '-');
            $checkTag = Tag::where('slug', $convertTitleToSlug)->first();
            if (!$checkTag) {
                $newTag = new Tag;
                $newTag->title = $convertTitleToSlug;
                $newTag->slug = $convertTitleToSlug;
                $newTag->content = $convertTitleToSlug;
                $newTag->save();
                $tagId = $newTag->id;
            } else {
                $tagId = Tag::where('slug', $convertTitleToSlug)->first()->id;
            }
            $checkPostTag = PostTag::where('post_id', $lastIdPost)->where('tag_id', $tagId)->first();
            if (!$checkPostTag) {
                $postTag = new PostTag;
                $postTag->post_id = $lastIdPost;
                $postTag->tag_id = $tagId;
                $postTag->save();
            }
        }

        $post = Post::where('id', $lastIdPost);
        $singlePost = fractal($post->firstOrFail(), new SinglePostTransformers);
        return $this->respondSuccess($singlePost);
    }

    public function updatePost(UpdatePostRequest $request, $slug)
    {
        $updatePost = Post::where('slug', $slug)->where('user_id', auth()->user()->id)->firstOrFail();
        $updatePost->category_id = $request->category_id;
        $updatePost->user_id = auth()->user()->id;
        $updatePost->title = $request->title;
        //$updatePost->slug = Str::slug($request->title, '-') . '-' . Str::lower(Str::random(4));
        $updatePost->content = $request->content;
        $updatePost->published = '1';
        $updatePost->published_at = Carbon::now()->toDateTimeString();
        $updatePost->excerpt = Str::limit(
            preg_replace(
                '/\s+/',
                ' ',
                trim(
                    strip_tags(
                        Str::markdown($request->content)
                    )
                )
            ),
            166,
            '...'
        );

        if ($request->boolean('is_remove_img')) {
            $removeImage = $updatePost->image;
            if (Storage::disk('images')->exists($removeImage)) {
                Storage::disk('images')->delete($removeImage);
            }
            $updatePost->image = null;
        }

        if ($request->hasfile('image')) {
            $oldImage = $updatePost->image;
            if (Storage::disk('images')->exists($oldImage)) {
                Storage::disk('images')->delete($oldImage);
            }
            $imageName = time() . '.' . $request->file('image')->extension();
            Storage::disk('images')->put($imageName, file_get_contents($request->file('image')));
            $updatePost->image = $imageName;
        }

        $updatePost->save();

        $lastIdPost = $updatePost->id;

        $deletePostTag = PostTag::where('post_id', $lastIdPost);
        if ($deletePostTag->get()->count() > 0) {
            $deletePostTag->delete();
        }

        foreach ($request->tags as $key => $tags) {
            $convertTitleToSlug = Str::slug($tags['slug'], '-');
            $checkTag = Tag::where('slug', $convertTitleToSlug)->first();
            if (!$checkTag) {
                $newTag = new Tag;
                $newTag->title = $convertTitleToSlug;
                $newTag->slug = $convertTitleToSlug;
                $newTag->content = $convertTitleToSlug;
                $newTag->save();
                $tagId = $newTag->id;
            } else {
                $tagId = Tag::where('slug', $convertTitleToSlug)->first()->id;
            }
            $checkPostTag = PostTag::where('post_id', $lastIdPost)->where('tag_id', $tagId)->first();
            if (!$checkPostTag) {
                $postTag = new PostTag;
                $postTag->post_id = $lastIdPost;
                $postTag->tag_id = $tagId;
                $postTag->save();
            }
        }

        $post = Post::where('id', $lastIdPost);
        $singlePost = fractal($post->firstOrFail(), new SinglePostTransformers);
        return $this->respondSuccess($singlePost);
    }

    public function editPost(Request $request, $slug)
    {
        $post = Post::where('slug', $slug)->where('user_id', auth()->user()->id)
            ->where('user_id', User::where('user_name', $request->user_name)->first()->id);
        $editPost = fractal($post->firstOrFail(), new SinglePostTransformers);
        return $this->respondSuccess($editPost);
    }

    public function deletePost($slug)
    {
        $deletePost = Post::where('slug', $slug)->where('user_id', auth()->user()->id)->firstOrFail();
        $deletePost->delete();
        return $this->respondSuccess($deletePost);
    }

    public function deletePostConfirm(Request $request, $slug)
    {
        $post = Post::where('slug', $slug)
            ->where('user_id', auth()->user()->id)
            ->where('user_id', User::where('user_name', $request->user_name)->firstOrFail()->id);
        $deletePostConfirm = fractal($post->firstOrFail(), new SinglePostTransformers);
        return $this->respondSuccess($deletePostConfirm);
    }

    public function favoritePost(Request $request)
    {
        $user = auth()->user();

        $postFavorite = Post::where('slug', $request->slug)->firstOrFail();

        $favoriteCheck = FavoritePost::where('user_id', $user->id)->where('post_id', $postFavorite->id)->first();

        if (!$favoriteCheck) {
            $favorite = new FavoritePost;
            $favorite->user_id = $user->id;
            $favorite->post_id = $postFavorite->id;
            $favorite->save();
            return $this->respondSuccess([
                'id' => $favorite->post->id,
                'slug' => $favorite->post->slug
            ]);
        } else {
            return $this->respondUnprocessableEntity('Post favorited');
        }
    }

    public function unfavoritePost(Request $request)
    {
        $user = auth()->user();

        $postFavorite = Post::where('slug', $request->slug)->firstOrFail();

        $favoriteCheck = FavoritePost::where('user_id', $user->id)->where('post_id', $postFavorite->id)->first();

        if (!!$favoriteCheck) {
            $favoriteCheck->delete();
            return $this->respondSuccess([
                'id' => $favoriteCheck->post->id,
                'slug' => $favoriteCheck->post->slug
            ]);
        } else {
            return $this->respondUnprocessableEntity('Post does not exist or not in the favoritelist');
        }
    }
}
