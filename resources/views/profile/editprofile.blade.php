<div class="tab-pane" id="editprofile">
  <form class="form-horizontal" method="POST" action="{{url('profile/updateprofile')}}" enctype="multipart/form-data">
    {!! csrf_field() !!}
    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">Name</label>

      <div class="col-sm-10">
        <input type="text" name="name" value="{{$user->name}}" class="form-control" id="inputName" placeholder="Name">
      </div>
    </div>
    <div class="form-group">
      <label for="inputEmail" class="col-sm-2 control-label">Email</label>

      <div class="col-sm-10">
        <input type="email" name="email" value="{{$user->email}}" class="form-control" id="inputEmail" placeholder="Email" disabled>
      </div>
    </div>
    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">Country</label>

      <div class="col-sm-10">
        <select class="form-control" name="address[country]" id="selectCountry">
            <option value=""> -- Please Select --</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">State</label>

      <div class="col-sm-10">
        <select class="form-control" name="address[state]" id="selectState">
            <option value=""> -- Please Select --</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">City</label>

      <div class="col-sm-10">
        <select class="form-control" name="address[city]" id="selectCity">
            <option value=""> -- Please Select --</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">Education</label>

      <div class="col-sm-10">
        <input type="text" name="education" value="{{$user->education}}" class="form-control" id="inputName" placeholder="Education">
      </div>
    </div>

    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">Profession</label>

      <div class="col-sm-10">
        <input type="text" name="profile" value="{{$user->profile}}" class="form-control" id="inputName" placeholder="Occupation/Work Profile">
      </div>
    </div>

    <div class="form-group">
      <label for="inputName" class="col-sm-2 control-label">Skills</label>

      <div class="col-sm-10">
        <input type="text" name="skills" value="{{$user->skills}}" class="form-control" id="userSkills" placeholder="Skills">
      </div>
    </div>
    <div class="form-group">
      <label for="inputExperience" class="col-sm-2 control-label">About</label>

      <div class="col-sm-10">
        <textarea class="form-control" rows="5" name="about" id="inputExperience" placeholder="About Me">{{$user->about}}</textarea>
      </div>
    </div>
    <div class="form-group">
      <label for="inputSkills" class="col-sm-2 control-label">Avatar</label>

      <div class="col-sm-10">
        <div class="col-sm-5" style="padding:0px;">
          <div class="image-crop">
              <img class="crop-img" src="{{url('/uploads/avatar/'.$user->id.'/'.$user->avatar)}}">
          </div>
        </div>
        <div class="col-sm-7">
          <div class="btn-group">
              <label title="Upload Image" for="inputImage" id="cropperBtn" class="btn btn-primary">
                  <input type="file" accept="image/*" name="avatar" id="inputImage" class="hide">
                  Select Avatar
              </label>
              <input type="hidden" name="crop_height" value="" id="crop_height">
              <input type="hidden" name="crop_width" value="" id="crop_width">
              <input type="hidden" name="crop_x" value="" id="crop_x">
              <input type="hidden" name="crop_y" value="" id="crop_y">
          </div>
        </div>
      </div>
    </div>

    <div class="form-group">
      <div class="col-sm-offset-2 col-sm-10">
        <button type="submit" class="btn btn-danger">Update profile</button>
      </div>
    </div>
  </form>
</div>
<!-- /.tab-pane -->
