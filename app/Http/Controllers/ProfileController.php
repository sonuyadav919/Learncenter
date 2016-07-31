<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use Intervention\Image\Facades\Image as Image;
use Auth,Input;
use App\User;

class ProfileController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }


    public function getIndex(Request $request)
    {
        $this->data['user'] = Auth::user();

        return view('profile.index', $this->data);
    }



    public function postUpdateprofile(Request $request)
    {

      $user = Auth::user();

      $data = $request->all();
      unset($data['avatar']);
      unset($data['crop_x']);
      unset($data['crop_y']);
      unset($data['crop_width']);
      unset($data['crop_height']);
      unset($data['_token']);

      $data['address'] = json_encode($data['address']);

        if(!is_null($request->file('avatar')))
        {
                $img = Image::make($_FILES['avatar']['tmp_name']);
                $img->crop(round($request->crop_height),round($request->crop_height), round($request->crop_x),round($request->crop_y));
                $file = $request->file('avatar');
                $destinationPath = base_path('/public/uploads/avatar/'.$user->id.'/');
                $filename = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension(); //if you need extension of the file
                $newfilename = str_random(10).'.'.$extension;
                $uploadSuccess = $request->file('avatar')->move($destinationPath, $newfilename);
                $img->save(base_path('/public/uploads/avatar/'.$user->id.'/'.$newfilename));

                $data['avatar'] = $newfilename;
          }



            $update = User::find($user->id)->update($data);

      return back();

    }

    public function getCountries()
    {
        $countries = \DB::table('countries')->get();

        return $countries;
    }


    public function getStates($countryId)
    {
        $states = \DB::table('states')->where('country_id',$countryId)->get();

        return $states;
    }


    public function getCities($stateId)
    {
        $states = \DB::table('cities')->where('state_id',$stateId)->get();

        return $states;
    }

}
