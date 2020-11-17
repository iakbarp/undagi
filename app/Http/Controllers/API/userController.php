<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\User;
use App\Model\Bank;
use App\Model\Kota;
use App\Model\Bio;
use App\Model\Skill;
use App\Model\Portofolio;
use App\Model\Provinsi;
use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;





class userController extends Controller
{
    public function get()
    {
        try {
            $user = auth('api')->user();
            $user = User::find($user->id);

            // ->makeHidden(['id','user_id'])

            // $user->get_portofolio;
            $user->bio = Bio::where('user_id', $user->id)->first()->makeHidden(['id', 'user_id', 'created_at', 'updated_at', 'latar_belakang']);
            $user->skills = Skill::where('user_id', $user->id)->get()->makeHidden(['user_id', 'created_at', 'updated_at']);
            $user->portofolio = portofolio::where('user_id', $user->id)->orderBy(
                'tahun'
            )->get()->makeHidden(['user_id']);

            $user->makeHidden(['id', 'created_at', 'deleted_at', 'updated_at', 'role']);

            $user->bio = $this->imgCheck($user->bio, 'foto', 'storage/users/foto/');
            $user->portofolio = $this->imgCheck($user->portofolio->toArray(), 'foto', 'storage/users/portofolio/', 1);



            return response()->json(
                [
                    'error' => false,
                    'data' => $user
                ]
            );
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function summary(Request $request)
    {

        $summary = $request->summary;
        try {
            $bio = auth('api')->user()->get_bio;
            $summary_edit = $bio->summary;

            $bio->update([
                'summary' => $summary
            ]);

            return response()->json([
                'error' => false,
                'data' => [
                    'message' => 'summary berhasil ' . ($summary_edit ? 'diubah!' : 'ditambah!'),
                ]
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function edit(Request $request)
    {


        try {
            $user = auth('api')->user();
            $bio = $user->get_bio;


            $bio->name = $user->name;
            $bio->email = $user->email;
            $bio->bank = Bank::find($bio->bank, ['id', 'nama', 'kode']);
            $bio->kota_provinsi = Kota::find($bio->kota_id, ['id', 'nama', 'provinsi_id']);
            $bio->kota_provinsi->nama_provinsi = Provinsi::find($bio->kota_provinsi->provinsi_id)->nama;

            $bio->makeHidden(['id', 'user_id', 'latar_belakang', 'created_at', 'updated_at', 'summary', 'kota_id']);
            $bio = $this->imgCheck($bio, 'foto', 'storage/users/foto/');

            //   return response()->json($bio);

            return response()->json([
                'error' => false,
                'data' => $bio
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function update(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'jenis_kelamin' => 'required|string',
            'tgl_lahir' => 'required|date_format:Y-m-d',
            'status' => 'required|max:60',
            'kewarganegaraan' => 'required|exists:negara,id',
            'hp' => 'required|max:60',
            'alamat' => 'required|max:191',
            'kota_id' => 'nullable|exists:kota,id',
            'bank' => 'required|exists:bank,id',
            'rekening' => 'required|max:191',
            'an' => 'required|max:100',

        ]);

        if ($validator->fails()) {

            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $validator->errors()
                ]
            ], 422);
        }


        try {
            $user = auth('api')->user();
            $bio = $user->get_bio;

            User::find($user->id)->update($r->only('name', 'email'));
            $bio->update($r->except('name', 'email'));

            return response()->json([
                'error' => false,
                'data' => [
                    'message' => 'informasi telah diperbarui!'
                ]
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function photo(Request $r)
    {
        try {
            $image_64 = $r->photo; //your base64 encoded data
            $user = auth('api')->user();
            $bio = $user->get_bio;

            $extension = explode('/', explode(':', substr($image_64, 0, strpos($image_64, ';')))[1])[1];   // .jpg .png .pdf

            $replace = substr($image_64, 0, strpos($image_64, ',') + 1);

            $image = str_replace($replace, '', $image_64);

            $image = str_replace(' ', '+', $image);
            $kond = (bool) base64_decode($image, true);

            if ($kond) {

                $imageName = $user->id . '_' . $user->name . '_' . now()->format('Ymd') . '.' . $extension;

                if ($user->get_bio->foto != '') {
                    Storage::delete('public/users/foto/' . $bio->foto);
                }

                $picture = base64_decode($image);
                // optimize


                Storage::disk('public')->put('users/foto/' . $imageName, $picture);
                $new = 'storage/users/foto/' . $imageName;

                $bio->update([
                    'foto' => $imageName
                ]);
            }

            return response()->json([
                'error' => !(bool)$kond,
                'data' => [
                    'message' => $kond ? 'foto telah diperbarui!' : 'Harap pilih gambar!',
                ]
            ], ($kond ? 201 : 400));
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function skills()
    {


        try {
            $user = auth('api')->user();
            $skills = Skill::where('user_id', $user->id)->get(['id', 'nama', 'tingkatan']);

            return response()->json([
                'error' => false,
                'data' => $skills
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function skills_update(Request $r)
    {
        DB::beginTransaction();

        try {
            $user = auth('api')->user();
            $data = collect(json_decode($r->update));
            $skills = Skill::where('user_id', $user->id)
                ->whereNotIn('id', $data->pluck('id'))
                ->delete();

            foreach ($data as $row) {
                Skill::updateOrCreate($data->only('id', 'nama', 'tingkatan'));
            }

            DB::commit();


            return response()->json([
                'error' => false,
                'data' => [
                    'message' => 'berhasil diperbarui!'
                ]
            ]);
        } catch (\Exception $exception) {
            DB::rollback();
            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function portofolio(Request $r)
    {
        $validator = Validator::make($r->all(), [
            'judul' => 'required|string|max:100',
            'deskripsi' => 'required|string|max:255',
            'tahun' => 'required',
            'tautan' => 'required',
            
            'image' => 'image|mimes:jpg,jpeg,gif,png|max:2048'
        ]);

        if ($validator->fails()) {

            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $validator->errors()
                ]
            ], 422);
        }
        DB::beginTransaction();


        try {
            $user = auth('api')->user();

            $foto = $user->id . now()->format('is') . '_' . $r->file('image')->getClientOriginalName();
            $r->file('image')->storeAs('public/users/portofolio', $foto);

            $r->request->add(['user_id' => $user->id]);
            $r->request->add(['foto' => $foto]);

            $port = Portofolio::create($r->only('user_id', 'judul', 'deskripsi', 'tahun', 'tautan', 'foto'));
            DB::commit();

            return response()->json([
                'error' => false,
                'data' => [
                    'message' => 'Portofolio berhasil dibuat!'
                ]
            ]);
        } catch (\Exception $exception) {
            DB::rollback();


            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function portofolio_update(Request $r, $id)
    {
        $validator = Validator::make($r->all(), [
            'judul' => 'required|string|max:100',
            'deskripsi' => 'required|string|max:255',
            'tahun' => 'required',
            'tautan' => 'required',
            
            'image' => 'image|mimes:jpg,jpeg,gif,png|max:2048'
        ]);

        if ($validator->fails()) {

            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $validator->errors()
                ]
            ], 422);
        }
        DB::beginTransaction();


        try {
            $user = auth('api')->user();
            $port = Portofolio::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if ($port) {
                if ($port->foto != '') {
                    Storage::delete('public/users/portofolio/' . $port->foto);
                }

                $foto = $user->id . now()->format('is') . '_' . $r->file('image')->getClientOriginalName();
                $r->file('image')->storeAs('public/users/portofolio', $foto);

                
                $r->request->add(['foto' => $foto]);

                $port->update($r->only('judul', 'deskripsi', 'tahun', 'tautan', 'foto'));
                DB::commit();
            }

            return response()->json([
                'error' => !((bool) $port),
                'data' => [
                    'message' => ((bool) $port ? 'Portofolio berhasil diubah!' : 'data tidak ditemukan!')
                ]
                ],($port ? 201 : 400));
        } catch (\Exception $exception) {
            DB::rollback();

            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    public function portofolio_delete($id)
    {
       
        DB::beginTransaction();


        try {
            $user = auth('api')->user();
            $port = Portofolio::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if ($port) {
                if ($port->foto != '') {
                    Storage::delete('public/users/portofolio/' . $port->foto);
                }

              
                $port->delete();
                DB::commit();
            }

            return response()->json([
                'error' => !(bool) $port,
                'data' => [
                    'message' => $port ? 'Portofolio berhasil dihapus!' : 'data tidak ditemukan!'
                ]
            ],($port ? 201 : 400));

        } catch (\Exception $exception) {
            DB::rollback();

            return response()->json([
                'error' => true,
                'data' => [
                    'message' => $exception->getMessage()
                ]
            ], 400);
        }
    }

    private function imgCheck($data, $column, $path, $ch = 0)
    {
        $dummy_photo = [

            asset('admins/avatar/avatar-' . rand(1, 2) . '.jpg'),
            asset('images/porto.jpg'),



        ];
        $res = $data;

        if (is_array($data)) {

            $res = [];

            foreach ($data as $i => $row) {
                $res[$i] = $row;

                $res[$i][$column] = $res[$i][$column] && File::exists($path . $res[$i][$column]) ?
                    asset($path . $res[$i][$column]) :
                    $dummy_photo[$ch];
            }
        } elseif ($data) {


            $res->{$column} = $res->{$column} && File::exists($path . $res->{$column}) ?
                asset($path . $res->{$column}) :
                $dummy_photo[$ch];
        } else {


            $res->{$column} = $dummy_photo[$ch];
        }

        return $res;
    }
}
