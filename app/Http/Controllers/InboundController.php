<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InboundController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function index(Request $request)
    {
        try {
            if ($request->filter_id) {
                $data = InboundStuff::where('stuff_id', $request->filter_id)
                    ->with('stuffStock')
                    ->get();
            } else {
                $data = InboundStuff::with('stuffStock')->get();
            }

            return ApiFormatter::sendResponse(200, 'success', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $this->validate($request, [
                'stuff_id' => 'required',
                'total' => 'required',
                'date' => 'required',
                'proff_file' => 'required|image',
            ]);

            $nameImage = Str::random(5) .  "_" . $request->file('proff_file')->getClientOriginalName();
            $request->file('proff_file')->move('upload-images', $nameImage);
            $pathImage = url('upload-images/' . $nameImage);

            $inboundData = InboundStuff::create([
                'stuff_id' => $request->stuff_id,
                'total' => $request->total,
                'date' => $request->date,
                'proff_file' => $pathImage,
            ]);

            if ($inboundData) {
                $stockData = StuffStock::where('stuff_id', $request->stuff_id)->first();
                if ($stockData) {
                    $total_available = (int)$stockData['total_available'] + (int)$request->total;
                    $stockData->update(['total_available' => $total_available]);
                } else {
                    StuffStock::create([
                        'stuff_id' => $request->stuff_id,
                        'total_available' => $request->total,
                        'total_defec' => 0,
                    ]);
                }

                return ApiFormatter::sendResponse(200, 'success', $inboundData);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $inboundData = InboundStuff::where('id', $id)->first();
            $dataStock = StuffStock::where('stuff_id', $inboundData['stuff_id'])->first();

            if ((int)$dataStock['total_available'] < (int)$inboundData['total']) {
                return ApiFormatter::sendResponse(400, 'bad request', 'Jumlah total inbound yang akan dihapus lebih besar dari total available stuff saat ini!');
            }

            $totalInbound = $inboundData['total'];
            $inboundData->delete();

            $total_available = (int)$dataStock['total_available'] - (int)$totalInbound;
            $dataStock->update(['total_available' => $total_available]);

            return ApiFormatter::sendResponse(200, 'success', 'Inbound data deleted successfully');
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function trash()
    {
        try {
            $data = InboundStuff::onlyTrashed()->get();

            return ApiFormatter::sendResponse(200, 'success', $data);
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function restore($id)
    {
        try {
            $restore = InboundStuff::onlyTrashed()->where('id', $id)->restore();

            if ($restore) {
                $data = InboundStuff::find($id);
                $stock = StuffStock::where('stuff_id', $data['stuff_id'])->first();
                $total_available = (int)$stock['total_available'] + (int)$data['total'];
                $stock->update(['total_available' => $total_available]);

                return ApiFormatter::sendResponse(200, 'success', $data);
            }
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }

    public function permanentDelete($id)
    {
        try {
            $data = InboundStuff::onlyTrashed()->where('id', $id)->first();

            $images = explode("/", $data['proff_file']);
            if (file_exists(public_path('upload-images/' . $images[4]))) {
                unlink(public_path('upload-images/' . $images[4]));
            }

            $data->forceDelete();
            return ApiFormatter::sendResponse(200, 'success', 'Berhasil hapus permanen inbound beserta file nya!');
        } catch (\Exception $err) {
            return ApiFormatter::sendResponse(400, 'bad request', $err->getMessage());
        }
    }
}

}
