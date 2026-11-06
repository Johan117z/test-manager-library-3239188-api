<?php

namespace App\Http\Controllers;

use App\Application\Loans\LendBooksService;
use App\Application\Loans\LoanQueryService;
use App\Application\Loans\ReturnItemService;
use App\Domain\Loan\Loan;
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

    public function index(Request $request, LoanQueryService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'string'],
            'to' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'member_id' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
            'size' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = $service->list(
            $data['from'] ?? null, $data['to'] ?? null, $data['status'] ?? null, $data['member_id'] ?? null,
            isset($data['page']) ? (int) $data['page'] : null, isset($data['size']) ? (int) $data['size'] : null,
        );

        return $this->json([
            'items' => array_map(fn (Loan $l) => $this->presenter->loan($l), $page->items),
            'page' => $page->page, 'size' => $page->size, 'total' => $page->total,
        ]);
    }

    public function show(string $id, LoanQueryService $service): JsonResponse
    {
        return $this->json($this->presenter->loan($service->get($id)));
    }

    public function overdue(Request $request, LoanQueryService $service): JsonResponse
    {
        $data = $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'size' => ['nullable', 'integer', 'min:1']]);
        $page = $service->overdue(isset($data['page']) ? (int) $data['page'] : null, isset($data['size']) ? (int) $data['size'] : null);

        return $this->json([
            'items' => array_map(fn (array $r) => array_merge($r, ['due_at' => LoanPresenter::instant($r['due_at'])]), $page->items),
            'page' => $page->page, 'size' => $page->size, 'total' => $page->total,
        ]);
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return $this->json(LoanPresenter::item($service->return($loanId, $itemId)));
    }

    /** @param array<string, mixed> $body */
    private function json(array $body, int $status = 200): JsonResponse
    {
        return response()->json($body, $status, [], JSON_UNESCAPED_UNICODE);
    }
}
