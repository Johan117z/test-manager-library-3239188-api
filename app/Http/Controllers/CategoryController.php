<?php

namespace App\Http\Controllers;

use App\Application\Ports\CategoryRepository;
use App\Domain\Book\Category;
use Illuminate\Http\JsonResponse;

final class CategoryController extends Controller
{
    /** The five categories ordered by name, as a plain array. */
    public function index(CategoryRepository $categories): JsonResponse
    {
        return response()->json(array_map(
            fn (Category $c) => ['id' => $c->id, 'name' => $c->name],
            $categories->all(),
        ), 200, [], JSON_UNESCAPED_UNICODE);
    }
}
