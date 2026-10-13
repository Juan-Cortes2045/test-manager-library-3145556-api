<?php

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Json;
use App\Application\Services\LendBooksService;
use App\Application\Services\LoanQueryService;
use App\Application\Services\MostBorrowedReportService;
use App\Application\Services\OverdueService;
use App\Application\Services\ReturnItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoanController
{
    public function store(Request $request, LendBooksService $lend, LoanQueryService $query): JsonResponse
    {
        $d = $request->validate(['member_id' => 'required|uuid', 'items' => 'required|array|min:1|max:3', 'items.*.book_id' => 'required|uuid']);
        $loan = $lend->lend($d['member_id'], (string) $request->attributes->get('user_id'), array_column($d['items'], 'book_id'));

        return Json::ok(Json::loan($loan, $query->now()), 201);
    }

    public function index(Request $request, LoanQueryService $query): JsonResponse
    {
        $q = $request->validate(['from' => 'nullable|string', 'to' => 'nullable|string', 'status' => 'nullable|string', 'member_id' => 'nullable|uuid',
            'page' => 'nullable|integer|min:1', 'size' => 'nullable|integer|min:1|max:50']);
        $now = $query->now();
        $page = $query->list($q['from'] ?? null, $q['to'] ?? null, $q['status'] ?? null, $q['member_id'] ?? null, (int) ($q['page'] ?? 1), (int) ($q['size'] ?? 20));

        return Json::ok(Json::page($page, fn ($l) => Json::loan($l, $now)));
    }

    public function show(string $id, LoanQueryService $query): JsonResponse
    {
        return Json::ok(Json::loan($query->get($id), $query->now()));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service): JsonResponse
    {
        return Json::ok(Json::item($service->return($loanId, $itemId)));
    }

    public function overdue(OverdueService $service): JsonResponse
    {
        return Json::ok(['items' => $service->list()]);
    }

    public function report(Request $request, MostBorrowedReportService $service): JsonResponse
    {
        $q = $request->validate(['from' => 'required|string', 'to' => 'required|string']);

        return Json::ok(['from' => $q['from'], 'to' => $q['to'], 'rows' => $service->report($q['from'], $q['to'])]);
    }
}
