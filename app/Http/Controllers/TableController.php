<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use App\Support\ApiResponse;

class TableController extends Controller
{
    public function index()
    {
        return ApiResponse::success(Table::all(), 'Tables retrieved');
    }

    public function store(StoreTableRequest $request)
    {
        $table = Table::create($request->validated());

        return ApiResponse::success($table, 'Table created', 201);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $table->update($request->validated());

        return ApiResponse::success($table, 'Table updated');
    }

    public function destroy(Table $table)
    {
        $table->delete();

        return ApiResponse::success(null, 'Table deleted');
    }
}
