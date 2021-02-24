<?php

namespace App\Http\Controllers;

use App\Jumlah;
use App\Barang;
use App\DetailPenjualan;
use App\Kategori;
use App\Supplier;
use App\Keuangan;
use App\Pembelian;
use App\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class StafController extends Controller
{
    public function index()
    {
        $supplier = Supplier::select('id', 'supplier')->get();

        return $this->sendResponse('berhasil', 'data supplier berhasil ditampilkan', $supplier, 200);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier' => 'required',
            'alamat' => 'required',
            'nomor_telepon' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendResponse('gagal', 'data gagal divalidasi', $validator->errors(), 501);
        }


        try {
            $supplier = Supplier::create([
                'supplier' => $request->get('supplier'),
                'alamat' => $request->get('alamat'),
                'nomor_telepon' => $request->get('nomor_telepon'),
            ]);

            return $this->sendResponse('berhasil', 'data supplier berhasil diinputkan', $supplier, 200);
        } catch (\Throwable $th) {
            return $this->sendResponse('gagal', 'data gagal diinputkan', $th->getMessage(), 500);
        }
    }

    public function createBarang(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori_id' => 'required',
            'nama_barang' => 'required',
            'kode_barang' => 'required',
            'harga' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendResponse('gagal', 'data gagal divalidasi', $validator->errors(), 500);
        };

        try {
            $barang = Barang::create([
                'kategori_id' => $request->kategori_id,
                'nama_barang' => $request->nama_barang,
                'kode_barang' => $request->kode_barang,
                'harga' => $request->harga,
            ]);

            $jumlah = Jumlah::create([
                'barang_id' => $barang->id,
            ]);

            return $this->sendResponse('berhasil', 'barang berhasil diinputkan', $barang, 200);
        } catch (\Throwable $th) {
            return $this->sendResponse('gagal', 'data gagal diinputkan', $th->getMessage(), 400);
        }
    }

    public function buyStuff(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jumlah' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendResponse('gagal', 'data gagal divalidasi', $validator->errors(), 200);
        }

        $pj = Auth::user()->id;

        $satuan = Barang::where('id', $request->barang_id)->first('harga');

        $harga = $satuan->harga * $request->jumlah;

        try {
            $input = Pembelian::create([
                'pj' => $pj,
                'barang_id' => $request->barang_id,
                'jumlah' => $request->jumlah,
                'harga' => $harga,
            ]);

            return $this->sendResponse('berhasil', 'pesanan berhasil ditambahkan', $input, 200);
        } catch (\Throwable $th) {
            return $this->sendResponse('gagal', 'pesanan gagal ditambahkan', $th->getMessage(), 500);
        }

    }

    public function postCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kategori' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendResponse('gagal', 'data gagal divalidasi', $validator->errors(), 501);
        }

        try {
            $Kategori = Kategori::create([
                'kategori' => $request->get('kategori'),
            ]);

            return $this->sendResponse('berhasil', 'kategori berhasil ditambahkan', $Kategori, 200);
        } catch (\Throwable $th) {
            return $this->sendResponse('gagal', 'kategori gagal ditambahkan', $th->getMessage(), 501);
        }
    }

    public function getCategory()
    {
        $Kategori = Kategori::all();

        return $this->sendResponse('berhasil', 'kategori data berhasil ditampilkan', $Kategori, 200);
    }

    public function getBarang()
    {
        $barang = Barang::select('id', 'nama_barang', 'kode_barang')->get();

        return $this->sendResponse('berhasil', 'data barang berhasil ditampilkan', $barang, 200);
    }

    public function getTotal()
    {
        $pj = Auth::user()->id;

        $pembelian = Pembelian::where('pj', $pj)->where('status', 0)->with('Barang')->get();

        $total = $pembelian->sum('harga');

        $Total = [
            'item' => $pembelian,
            'total harga' => $total,
        ];

        return $this->sendResponse('berhasil', 'harga barang berhasil diambil', $Total, 200);
    }

    public function payTotal()
    {
        $pj = Auth::user()->id;

        $barang = Pembelian::where('pj', $pj)->where('status', 0)->get();

        $test = $barang->sum('harga');

        $akhir = Keuangan::latest()->first('saldo');

        $saldo = $akhir->saldo - $test;

        $keuangan = Keuangan::create([
            'pj' => $pj,
            'debit' => $test,
            'saldo' => $saldo,
        ]);

        foreach ($barang as $key) {
            $kotor = Jumlah::where('barang_id', $key['barang_id'])->first('total');

            $total = $kotor->total + $key['jumlah'];

            try {
                $jumlah = Jumlah::create([
                    'barang_id' => $key['barang_id'],
                    'input' => $key['jumlah'],
                    'total' => $total,
                ]);

                $update = Pembelian::where('id', $key['id'])->first();

                $update->update(['status' =>1]);
            
            } catch (\Throwable $th) {
                return $this->sendResponse('gagal', 'jumlah barang gagal di inputkan', $th->getMessage(), 500);
            }
        }

        $ids = $barang->map(function ($data) {
            return $data->id;
        });

        $result = Pembelian::whereIn('id',$ids)->get();

        return $this->sendResponse('berhasil', 'pembelian sukses dibayar', $result, 200);
    }

    public function getRiwayat()
    {
        $pj = Auth::user()->id;

        $riwayat = Pembelian::where('pj', $pj)->get();

        return $this->sendResponse('berhasil', 'riwayat pembelian berhasil ditampilkan', $riwayat, 200);
    }

  
}
