<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Auth, Redirect;
use App\Models\NotesFolder;
use App\Models\Note;

class NotesController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function getIndex(Request $request,$fileId=null)
    {
        $user = Auth::user();
        $this->data['user'] = $user;
        $this->data['folderFiles'] = $this->getFolderAndFiles($user->id);
        $this->data['activeNote'] = Note::find(base64_decode($fileId));
        $this->data['new'] = isset($_GET['new'])?1:0;
        return view('notes.index', $this->data);
    }



    public function getSavefile($folderId)
    {
        $note = Note::create(['folder_id' => $folderId, 'name' => 'Untitled File', 'user_id' => Auth::id()]);

        return Redirect::to('notes/'.base64_encode($note['id']).'?new');
    }

    private function getFolderAndFiles($userId)
    {
        $folders = NotesFolder::where('user_id', $userId)->get()->toArray();

        return $this->getFiles($folders, $userId);
    }


    private function getFiles($folders, $userId)
    {
        $return = [];

        foreach ($folders as $k => $folder) {
            $files = Note::where(['folder_id' => $folder['id'], 'user_id' => $userId])->get()->toArray();

            $return[$k] = $folder;
            $return[$k]['files'] = $files;
        }

        return $return;
    }

}
