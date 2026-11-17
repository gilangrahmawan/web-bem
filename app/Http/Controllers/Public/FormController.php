<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/** Halaman isi form /form/{public_id}. Isi & alur ada di komponen Livewire PublicForm. */
class FormController extends Controller
{
    public function show(string $publicId): View
    {
        return view('public.forms.show', ['publicId' => $publicId, 'preview' => false]);
    }
}
