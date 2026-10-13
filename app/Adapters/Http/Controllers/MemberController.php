<?php

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\Json;
use App\Application\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController
{
    public function __construct(private MemberService $members)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => 'nullable|string', 'page' => 'nullable|integer|min:1', 'size' => 'nullable|integer|min:1|max:50']);

        return Json::ok(Json::page($this->members->search($q['q'] ?? null, (int) ($q['page'] ?? 1), (int) ($q['size'] ?? 20)), [Json::class, 'member']));
    }

    public function show(string $id): JsonResponse
    {
        $r = $this->members->get($id);

        return Json::ok(Json::member($r['member']) + ['active_loans' => $r['active_loans']]);
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['full_name' => 'required|string|max:200', 'document_number' => 'required|string|max:30']);

        return Json::ok(Json::member($this->members->create($d['full_name'], $d['document_number'])), 201);
    }
}
