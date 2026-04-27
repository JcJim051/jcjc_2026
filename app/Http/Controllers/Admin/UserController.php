<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\Puestos;
use App\Models\Seller;
use App\Models\Candidato;
use App\Models\Eleccion;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::all();
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::all();
        $municipios = Seller::select('codmun', 'municipio')->whereNotNull('codmun')
        ->whereNotNull('municipio')
        ->groupBy('codmun', 'municipio') // evita duplicados
        ->orderBy('municipio')
        ->get();
      
        $puestos = Puestos::orderBy('codpuesto')->get();

        $eleccionId = Eleccion::where('estado', 'activa')->max('id');
        $candidatos = $eleccionId
            ? Candidato::where('eleccion_id', $eleccionId)->orderBy('codigo')->get()
            : collect();

        return view('admin.users.create', compact('roles', 'municipios', 'candidatos'));
    }

    public function store(Request $request)
    {
        $eleccionId = Eleccion::where('estado', 'activa')->max('id');
        $allowedCodes = $eleccionId
            ? Candidato::where('eleccion_id', $eleccionId)->pluck('codigo')->all()
            : [];

        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role'     => 'required',
            'status'     => 'required',
            'candidatos' => 'required|array',
            'candidatos.*' => [
                'integer',
                Rule::in(array_merge([0], $allowedCodes)),
            ],
        ]);

      
        User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role,
            



            // 👇 CONVERTIMOS ARRAYS A TEXTO
            'codzon'    => is_array($request->codzon) ? implode(',', $request->codzon) : $request->codzon,
            'codpuesto' => is_array($request->codpuesto) ? implode(',', $request->codpuesto) : $request->codpuesto,
            'mun'       => is_array($request->mun) ? implode(',', $request->mun) : $request->mun,
            'candidatos'       => is_array($request->candidatos) ? implode(',', $request->candidatos) : $request->candidatos,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    // public function edit(User $user)
    // {
    //     $roles = Role::all();
    //     $municipios = Puestos::select('mun')->distinct()->orderBy('mun')->get();
    //     $puestos = Puestos::orderBy('codpuesto')->get();

    //     // 👇 Convertimos texto "1,2,3" a array [1,2,3]
    //     $user->mun = $user->mun ? explode(',', $user->mun) : [];
    //     $user->codpuesto = $user->codpuesto ? explode(',', $user->codpuesto) : [];
    //     $user->codzon = $user->codzon ? explode(',', $user->codzon) : [];

    //     return view('admin.users.edit', compact('user', 'roles', 'municipios', ));
    // }

//     public function edit(User $user)
// {
//     $roles = Role::all();
//         $municipios = Seller::select('codmun', 'municipio')->whereNotNull('codmun')
//         ->whereNotNull('municipio')
//         ->groupBy('codmun', 'municipio') // evita duplicados
//         ->orderBy('municipio')
//         ->get();
      
//         $puestos = Puestos::orderBy('codpuesto')->get();

      

//     $user->mun = $user->mun ? explode(',', $user->mun) : [];
//     $user->codpuesto = $user->codpuesto ? explode(',', $user->codpuesto) : [];
//     $user->codzon = $user->codzon ? explode(',', $user->codzon) : [];

//     return view('admin.users.edit', compact('user', 'roles', 'municipios'));
// }

        public function edit(User $user)
        {
            $roles = Role::all();

            $municipios = Seller::select('codmun', 'municipio')
                ->whereNotNull('codmun')
                ->whereNotNull('municipio')
                ->groupBy('codmun', 'municipio')
                ->orderBy('municipio')
                ->get();

            // 🔥 Convertimos texto guardado a arrays para los selects múltiples
            $user->mun = $user->mun ? explode(',', $user->mun) : [];
            $user->codpuesto = $user->codpuesto ? explode(',', $user->codpuesto) : [];
            $user->codzon = $user->codzon ? explode(',', $user->codzon) : [];

            $eleccionId = Eleccion::where('estado', 'activa')->max('id');
            $candidatos = $eleccionId
                ? Candidato::where('eleccion_id', $eleccionId)->orderBy('codigo')->get()
                : collect();

            return view('admin.users.edit', compact('user', 'roles', 'municipios', 'candidatos'));
        }

    public function update(Request $request, User $user)
    {
        $eleccionId = Eleccion::where('estado', 'activa')->max('id');
        $allowedCodes = $eleccionId
            ? Candidato::where('eleccion_id', $eleccionId)->pluck('codigo')->all()
            : [];

        $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|unique:users,email,' . $user->id,
            'role'   => 'required',
            'status' => 'required|in:0,1',
            'candidatos' => 'required|array',
            'candidatos.*' => [
                'integer',
                Rule::in(array_merge([0], $allowedCodes)),
            ],
        ]);

        $user->update([
            'name'      => $request->name,
            'email'     => $request->email,
            'role'      => $request->role,
            'status'    => $request->status,
            'candidatos'    => $request->candidatos,

            // 👇 CONVERTIR ARRAYS
            'codzon'    => is_array($request->codzon) ? implode(',', $request->codzon) : $request->codzon,
            'codpuesto' => is_array($request->codpuesto) ? implode(',', $request->codpuesto) : $request->codpuesto,
            'mun'       => is_array($request->mun) ? implode(',', $request->mun) : $request->mun,
            'candidatos'       => is_array($request->candidatos) ? implode(',', $request->candidatos) : $request->candidatos,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        $user->delete();
        return redirect()->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }

    /* ================== IMPORTACIÓN MASIVA ================== */

    public function showImportForm()
    {
        return view('admin.users.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv');

        $data = array_map(function($line) {
            return str_getcsv($line, ';');
        }, file($file->getRealPath()));

        foreach ($data as $index => $row) {
            if ($index === 0) continue;
            if (count($row) < 9) continue;

            $id        = (int) trim($row[0]);
            $name      = trim($row[1]);
            $email     = trim($row[2]);
            $password  = trim($row[3]);
            $role      = intval($row[4]);
            $status    = intval($row[5]);
            $codzon    = trim($row[6]);
            $codpuesto = trim($row[7]);
            $mun       = trim($row[8]);

            $user = User::find($id);

            if ($user) {
                $user->name = $name;
                $user->email = $email;
                if (!empty($password)) {
                    $user->password = Hash::make($password);
                }
                $user->role = $role;
                $user->status = $status;
                $user->codzon = $codzon;
                $user->codpuesto = $codpuesto;
                $user->mun = $mun;
                $user->save();
            } else {
                if (User::where('email', $email)->exists()) continue;

                $newUser = new User();
                $newUser->incrementing = false;
                $newUser->id = $id;
                $newUser->name = $name;
                $newUser->email = $email;
                $newUser->password = Hash::make($password ?: '123456');
                $newUser->role = $role;
                $newUser->status = $status;
                $newUser->codzon = $codzon;
                $newUser->codpuesto = $codpuesto;
                $newUser->mun = $mun;
                $newUser->save();
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'Usuarios importados correctamente.');
    }
}
