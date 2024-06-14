<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StuffStock;
use App\Models\Lending;
use App\Models\Restoration;
use App\Helpers\ApiFormatter;
use Validator;

class LendingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index()
    {
        try {
            $data = Lending::with('stuff', 'user', 'restoration')->get();
            return ApiFormatter::sendResponse(200, 'success', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function show($id)
    {
        try {
            $data = Lending::where('id', $id)
                ->with('user', 'restoration', 'restoration.user', 'stuff', 'stuff.stuffStock')
                ->first();

            if ($data) {
                return ApiFormatter::sendResponse(200, 'success', $data);
            } else {
                return ApiFormatter::sendResponse(404, 'not found', 'Data not found');
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'stuff_id' => 'required|integer',
            'date_time' => 'required|date',
            'name' => 'required|string',
            'total_stuff' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiFormatter::sendResponse(400, 'bad request', $validator->errors());
        }

        try {
            // Cek total_available stuff terkait
            $totalAvailable = StuffStock::where('stuff_id', $request->stuff_id)->value('total_available');

            if (is_null($totalAvailable)) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Belum ada data inbound!');
            } elseif ($request->total_stuff > $totalAvailable) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Stok tidak tersedia!');
            } else {
                $lending = Lending::create([
                    'stuff_id' => $request->stuff_id,
                    'date_time' => $request->date_time,
                    'name' => $request->name,
                    'notes' => $request->notes ?? '-',
                    'total_stuff' => $request->total_stuff,
                    'user_id' => auth()->user()->id,
                ]);

                $totalAvailableNow = $totalAvailable - $request->total_stuff;
                StuffStock::where('stuff_id', $request->stuff_id)->update(['total_available' => $totalAvailableNow]);

                $dataLending = Lending::where('id', $lending->id)->with('user', 'stuff', 'stuff.stuffStock')->first();

                return ApiFormatter::sendResponse(200, 'success', $dataLending);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $restoration = Restoration::where('lending_id', $id)->first();
            if ($restoration) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Data peminjaman sudah memiliki data pengembalian!');
            }

            $lending = Lending::where('id', $id)->first();
            if (!$lending) {
                return ApiFormatter::sendResponse(404, 'not found', 'Data peminjaman tidak ditemukan!');
            }

            $stuffStock = StuffStock::where('stuff_id', $lending->stuff_id)->first();
            $totalAvailable = $stuffStock->total_available + $lending->total_stuff;
            $stuffStock->update(['total_available' => $totalAvailable]);
            $lending->delete();

            return ApiFormatter::sendResponse(200, 'success', 'Berhasil menghapus data peminjaman!');
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }
}
