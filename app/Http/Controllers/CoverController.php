<?php

namespace App\Http\Controllers;

use App\Application\Books\GetCoverService;
use App\Application\Books\RemoveCoverService;
use App\Application\Books\SetCoverService;
use App\Http\Presenters\BookPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class CoverController extends Controller
{
    /** multipart/form-data with field "file". */
    public function update(Request $request, string $id, SetCoverService $service, BookPresenter $presenter): JsonResponse
    {
        $request->validate(['file' => ['required', 'file']]);

        $book = $service->set($id, (string) file_get_contents($request->file('file')->getRealPath()));

        return response()->json($presenter->one($book), 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function destroy(string $id, RemoveCoverService $service): Response
    {
        $service->remove($id);

        return response()->noContent();
    }

    public function show(string $id, GetCoverService $service): Response
    {
        $cover = $service->get($id);

        return response($cover['bytes'], 200, [
            'Content-Type' => $cover['mime'],
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
