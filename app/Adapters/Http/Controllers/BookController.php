<?php

namespace App\Adapters\Http\Controllers;

use App\Adapters\Http\ErrorResponder;
use App\Adapters\Http\Json;
use App\Adapters\Persistence\DbCategoryReader;
use App\Adapters\Storage\DiskCoverStorage;
use App\Application\Services\BookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class BookController
{
    public function __construct(private BookService $books, private DbCategoryReader $categories)
    {
    }

    public function categories(): JsonResponse
    {
        return Json::ok($this->categories->all());
    }

    public function index(Request $request): JsonResponse
    {
        $q = $request->validate(['q' => 'nullable|string', 'category_id' => 'nullable|uuid', 'page' => 'nullable|integer|min:1', 'size' => 'nullable|integer|min:1|max:50']);
        $page = $this->books->search($q['q'] ?? null, $q['category_id'] ?? null, (int) ($q['page'] ?? 1), (int) ($q['size'] ?? 20));

        return Json::ok(Json::page($page, fn ($b) => Json::book($b, $this->categories->nameOf($b->categoryId()))));
    }

    public function show(string $id): JsonResponse
    {
        $b = $this->books->get($id);

        return Json::ok(Json::book($b, $this->categories->nameOf($b->categoryId())));
    }

    public function store(Request $request): JsonResponse
    {
        $d = $request->validate(['title' => 'required|string|max:250', 'author' => 'required|string|max:200', 'isbn' => 'required|string',
            'category_id' => 'required|uuid', 'total_copies' => 'required|integer']);
        $b = $this->books->create($d['title'], $d['author'], $d['isbn'], $d['category_id'], (int) $d['total_copies']);

        return Json::ok(Json::book($b, $this->categories->nameOf($b->categoryId())), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $d = $request->validate(['title' => 'required|string|max:250', 'author' => 'required|string|max:200',
            'category_id' => 'required|uuid', 'total_copies' => 'required|integer']);
        $b = $this->books->update($id, $d['title'], $d['author'], $d['category_id'], (int) $d['total_copies']);

        return Json::ok(Json::book($b, $this->categories->nameOf($b->categoryId())));
    }

    public function destroy(string $id): Response
    {
        $this->books->delete($id);

        return response()->noContent();
    }

    public function putCover(Request $request, string $id): JsonResponse
    {
        $bytes = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : $this->multipartPart($request, 'file');
        if ($bytes === null) {
            return ErrorResponder::body(422, 'VALIDATION_ERROR', 'Hay campos inválidos.', [['field' => 'file', 'issue' => 'El archivo es obligatorio.']]);
        }
        $b = $this->books->setCover($id, $bytes);

        return Json::ok(Json::book($b, $this->categories->nameOf($b->categoryId())));
    }

    /** PHP does not parse multipart bodies on PUT; read the named part from the raw body (api-contract.md §3.4). */
    private function multipartPart(Request $request, string $name): ?string
    {
        if (preg_match('/boundary="?([^";]+)"?/', (string) $request->header('Content-Type'), $m) !== 1) {
            return null;
        }
        foreach (explode('--'.$m[1], $request->getContent()) as $part) {
            [$headers, $body] = array_pad(explode("\r\n\r\n", $part, 2), 2, null);
            if ($body !== null && preg_match('/name="'.preg_quote($name, '/').'"/', $headers) === 1) {
                return substr($body, 0, -2);
            }
        }

        return null;
    }

    public function deleteCover(string $id): Response
    {
        $this->books->removeCover($id);

        return response()->noContent();
    }

    public function getCover(string $id, DiskCoverStorage $storage)
    {
        $key = $this->books->get($id)->coverKey();
        $file = $key === null ? null : $storage->read($key);
        if ($file === null) {
            return ErrorResponder::body(404, 'NOT_FOUND', 'El libro no tiene portada.');
        }

        return response($file['bytes'], 200, ['Content-Type' => $file['mime']]);
    }
}
