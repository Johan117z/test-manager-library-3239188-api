<?php

namespace App\Http\Controllers;

use App\Application\Loans\LendBooksService;
use App\Http\Presenters\LoanPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController extends Controller
{
    public function __construct(private readonly LoanPresenter $presenter)
    {
    }

    public function store(Request $request, LendBooksService $service): JsonResponse
    {
        // Only the shape here; the 1-3 and repeated-book rules live in the service to keep the error order.
        $data = $request->validate([
            'member_id' => ['required', 'string'],
            'items' => ['required', 'array'],
            'items.*.book_id' => ['required', 'string'],
        ]);

        $loan = $service->lend($data['member_id'], array_values(array_map(fn ($i) => $i['book_id'], $data['items'])), $request->attributes->get('auth')->userId);

        return $this->json($this->presenter->loan($loan), 201);
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
    }
}
