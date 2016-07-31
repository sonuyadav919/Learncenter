<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Auth;

class NotesController extends Controller
{

    public function getIndex(Request $request)
    {
        $this->data['user'] = Auth::user();
        return view('notes.index', $this->data);
    }

}
