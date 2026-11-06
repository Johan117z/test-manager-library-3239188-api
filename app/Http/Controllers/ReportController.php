<?php

namespace App\Http\Controllers;

use App\Application\Reports\MostBorrowedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReportController extends Controller
{
    public function mostBorrowed(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'string'],
            'to' => ['required', 'string'],
        ]);

        return response()->json([
            'from' => $data['from'],
            'to' => $data['to'],
            'rows' => $service->run($data['from'], $data['to']),
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
