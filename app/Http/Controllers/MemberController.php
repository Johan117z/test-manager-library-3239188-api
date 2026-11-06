<?php

namespace App\Http\Controllers;

use App\Application\Members\MemberService;
use App\Domain\Member\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController extends Controller
{
    public function index(Request $request, MemberService $service): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = $service->search($data['q'] ?? null, isset($data['page']) ? (int) $data['page'] : null, isset($data['size']) ? (int) $data['size'] : null);

        return $this->json([
            'items' => array_map(fn (Member $m) => self::member($m), $page->items),
            'page' => $page->page,
            'size' => $page->size,
            'total' => $page->total,
        ]);
    }

    public function show(string $id, MemberService $service): JsonResponse
    {
        $found = $service->get($id);

        return $this->json(self::member($found['member']) + ['active_loans' => $found['active_loans']]);
    }

    public function store(Request $request, MemberService $service): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:200'],
            'document_number' => ['required', 'string', 'max:30'],
        ]);

        return $this->json(self::member($service->create($data['full_name'], $data['document_number'])), 201);
    }

    /** @return array<string, string> */
    private static function member(Member $m): array
    {
        return ['id' => $m->id, 'full_name' => $m->fullName, 'document_number' => $m->documentNumber];
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
    }
}
