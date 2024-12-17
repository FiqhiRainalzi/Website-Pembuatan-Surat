<?php

namespace App\Http\Controllers;

use App\Models\Ketpub;
use App\Models\Notification;
use App\Models\Penulis;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class KeteranganPublikController extends BaseController
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $title = 'Surat Publikasi';
        $ketpub = Ketpub::where('user_id', $user->id)->with('penulis')->get();
        return view('user.dosen.ketpub.index', compact('ketpub', 'title'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $title = 'Tambah Surat Publikasi';
        return view('user.dosen.ketpub.create', compact('title'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'judul'         => 'required',
            'namaPenerbit'  => 'required|min:5',
            'penerbit'      => 'required|min:5',
            'volume'        => 'required',
            'nomor'         => 'required',
            'bulan'         => 'required',
            'tahun'         => 'required',
            'akreditas'     => 'required|min:5',
            'issn'          => 'required|min:5',
        ]);

        $user = Auth::user();

        $ketpub = Ketpub::create([
            'judul'          => $request->judul,
            'namaPenerbit'   => $request->namaPenerbit,
            'penerbit'       => $request->penerbit,
            'volume'         => $request->volume,
            'nomor'          => $request->nomor,
            'bulan'          => $request->bulan,
            'tahun'          => $request->tahun,
            'akreditas'      => $request->akreditas,
            'issn'           => $request->issn,
            'tanggal'        => $request->tanggal,
            'statusSurat'        => 'pending',
            'user_id'       => $user->id // Otomatis mengisi user_id dengan ID user yang sedang login

        ]);

        // Kirim notifikasi ke admin
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'Pengajuan Baru',
                'message' => 'Dosen telah membuat pengajuan surat Keterangan Publikasi.',
                'status' => 'unread',
            ]);
        }
        // Menambahkan Penulis (Validasi untuk mengabaikan input kosong)
        foreach ($request->penulis as $penulis) {
            if (!empty($penulis['nama'])) { // Hanya proses jika nama tidak kosong
                Penulis::create([
                    'ketpub_id' => $ketpub->id,
                    'nama' => $penulis['nama'],
                ]);
            }
        }

        return redirect()->route('ketpub.index')->with(['success' => 'Data berhasil Disimpan']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $ketpub = Ketpub::findOrFail($id);
        $ketpub->load('penulis');
        $year = Carbon::parse($ketpub->tanggal)->translatedFormat('Y');
        $title = 'Tampilan Surat Publikasi';
        if ($user->role === 'admin') {
            return view('user.admin.ketpub.show', compact('ketpub', 'title', 'year'));
        } elseif ($user->role === 'dosen') {
            return view('user.dosen.ketpub.show', compact('ketpub', 'title', 'year'));
        }
        abort(403, 'Unautorized');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $ketpub = Ketpub::findOrFail($id);
        $ketpub->load('penulis');
        $title = 'Edit Surat Publikasi';
        return view('user.dosen.ketpub.edit', compact('ketpub', 'title'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Validasi request
        $request->validate([
            'judul'         => 'required',
            'namaPenerbit'  => 'required|min:5',
            'penerbit'      => 'required|min:5',
            'volume'        => 'required',
            'nomor'         => 'required',
            'bulan'         => 'required',
            'tahun'         => 'required',
            'akreditas'     => 'required|min:5',
            'issn'          => 'required|min:5',
        ]);

        $ketpub = Ketpub::findOrFail($id); // Pastikan data ada, gunakan findOrFail

        // Hapus penulis lama
        $ketpub->penulis()->delete();

        // Tambahkan penulis baru
        if ($request->has('penulis')) {
            foreach ($request->penulis as $penulis) {
                if (!empty($penulis['nama'])) { // Cek jika nama penulis tidak kosong
                    $ketpub->penulis()->create([
                        'nama' => $penulis['nama'],
                    ]);
                }
            }
        }

        // Update data ketpub
        $ketpub->update([
            'judul' => $request->judul,
            'namaPenerbit' => $request->namaPenerbit,
            'penerbit' => $request->penerbit,
            'volume' => $request->volume,
            'nomor' => $request->nomor,
            'bulan' => $request->bulan,
            'tahun' => $request->tahun,
            'akreditas' => $request->akreditas,
            'issn' => $request->issn,
        ]);

        return redirect()->route('ketpub.index')->with('success', 'Data Berhasil di Update');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $ketpub = Ketpub::findOrFail($id);
        $ketpub->delete();

        return redirect()->route('ketpub.index')->with('success', 'Data Berhasil di Hapus');
    }

    public function downloadWord(string $id)
    {
        $phpWord = new \PhpOffice\PhpWord\TemplateProcessor('suratKetPub.docx');

        $ketpub = Ketpub::with('penulis')->findOrFail($id);

        $tanggal = Carbon::parse($ketpub->tanggal)->translatedFormat('j F Y');
        $year = Carbon::parse($ketpub->tanggal)->translatedFormat('Y');

        $phpWord->setValues([
            'nomorSurat' => $ketpub->nomorSurat?: '-',
            'judul'          => $ketpub->judul,
            'namaPenerbit'   => $ketpub->namaPenerbit,
            'penerbit'       => $ketpub->penerbit,
            'volume'         => $ketpub->volume,
            'nomor'          => $ketpub->nomor,
            'bulan'          => $ketpub->bulan,
            'tahun'          => $ketpub->tahun,
            'akreditas'      => $ketpub->akreditas,
            'issn'           => $ketpub->issn,
            'tanggal'        => $tanggal,
            'tahun' => $year,

        ]);
        // Hitung jumlah penulis
        $penulis = $ketpub->penulis;

        // Clone row untuk data penulis
        $phpWord->cloneRow('no', $penulis->count());

        foreach ($penulis as $index => $penulis) {
            $row = $index + 1;

            $phpWord->setValue("no#{$row}", $row);
            $phpWord->setValue("namaPenulis#{$row}", $penulis->nama ?: '-');
        }

        $fileName = 'Surat_Keterangan_Publik_' . $ketpub->penulis1 . '.docx';
        $phpWord->saveAs($fileName);

        // Return the file for download
        return response()->download($fileName)->deleteFileAfterSend(true);
    }
}
