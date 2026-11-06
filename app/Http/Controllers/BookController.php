<?php

namespace App\Http\Controllers;

use App\Application\Books\CreateBookService;
use App\Application\Books\DeleteBookService;
use App\Application\Books\SearchBooksService;
use App\Application\Books\UpdateBookService;
use App\Http\Presenters\BookPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController extends Controller
{
    public function __construct(private readonly BookPresenter $presenter)
    {
    }

    public function index(Request $request, SearchBooksService $service): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:250'],
            'category_id' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $service->search(
            $data['q'] ?? null,
            $data['category_id'] ?? null,
            isset($data['page']) ? (int) $data['page'] : null,
            isset($data['size']) ? (int) $data['size'] : null,
        );

        return $this->json($this->presenter->page($page));
    }

    public function show(string $id, SearchBooksService $service): JsonResponse
    {
        return $this->json($this->presenter->one($service->get($id)));
    }

    public function store(Request $request, CreateBookService $service): JsonResponse
    {
        $data = $request->validate($this->rules() + ['isbn' => ['required', 'string', 'regex:/^\d{13}$/']]);

        $book = $service->create($data['title'], $data['author'], $data['isbn'], $data['category_id'], (int) $data['total_copies']);

        return $this->json($this->presenter->one($book), 201);
    }

    public function update(Request $request, string $id, UpdateBookService $service): JsonResponse
    {
        $data = $request->validate($this->rules());

        $book = $service->update($id, $data['title'], $data['author'], $data['category_id'], (int) $data['total_copies']);

        return $this->json($this->presenter->one($book));
    }

    public function destroy(string $id, DeleteBookService $service): Response
    {
        $service->delete($id);

        return response()->noContent();
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'author' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'uuid'],
            'total_copies' => ['required', 'integer', 'min:1'],
        ];
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
    }
}
