<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AbogadoAccessToken;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AbogadoAccessTokenController extends Controller
{
    public function index()
    {
        $tokens = AbogadoAccessToken::query()->orderByDesc('id')->get();

        return view('admin.abogado_tokens.index', compact('tokens'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:characterization,update',
            'label' => 'nullable|string|max:255',
            'hours' => 'required|integer|min:1|max:720',
        ]);

        $token = AbogadoAccessToken::create([
            'type' => $data['type'],
            'token' => Str::random(64),
            'label' => $data['label'] ?? null,
            'active' => true,
            'expires_at' => now()->addHours((int) $data['hours']),
            'created_by' => optional($request->user())->id,
        ]);

        return redirect()
            ->route('admin.abogado_tokens.index')
            ->with('success', 'Enlace creado correctamente.')
            ->with('generated_url', $token->publicUrl())
            ->with('generated_projection_url', route('admin.abogado_tokens.projection', $token));
    }

    public function projection(AbogadoAccessToken $token)
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(760, 3),
                new SvgImageBackEnd()
            )
        ))->writeString($token->publicUrl());

        $qrSvg = trim(substr($svg, strpos($svg, "\n") + 1));

        return view('admin.abogado_tokens.projection', compact('token', 'qrSvg'));
    }

    public function toggle(AbogadoAccessToken $token)
    {
        $token->update(['active' => !$token->active]);

        return back()->with('success', 'Estado del enlace actualizado.');
    }

    public function destroy(AbogadoAccessToken $token)
    {
        $token->delete();

        return back()->with('success', 'Enlace eliminado.');
    }
}
