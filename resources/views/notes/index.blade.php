@extends('layouts.app')
@section('content')

@section('extrastyle')
<link rel="stylesheet" type="text/css" href="{{asset('plugins/quill/quill.snow.css')}}">
<link rel="stylesheet" type="text/css" href="{{asset('plugins/quill/advanced.css')}}">
@endsection

<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/metisMenu/2.5.2/metisMenu.min.css">
<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/metisMenu/2.5.2/metisMenu.min.js"></script>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
<section class="content-header">
  <h1>
    Notes
    <small>Create & Share Notes</small>
  </h1>
  <ol class="breadcrumb">
    <li><a href="{{url('/')}}"><i class="fa fa-dashboard"></i> Home</a></li>
    <li class="active">Notes</li>
  </ol>
</section>

<!-- Main content -->
<section class="content">
  <div class="row">
    <div class="col-md-3">
      <a href="#" data-toggle="modal" data-target="#createFolder" class="btn btn-primary btn-block margin-bottom">Create Folder</a>

      <div class="box box-solid">
        <!--
        <div class="box-header with-border">
          <h3 class="box-title"><i class="fa fa-folder"></i> Folders</h3>
        </div>
      -->
        <div class="box-body no-padding">

          <ul class="nav nav-pills nav-stacked" id="menu">

            @foreach($folderFiles as $folder)

            <li class="active">
              <a href="#" aria-expanded="true"><i class="fa fa-folder-o"></i> {{$folder['name']}}</a>

              <ul aria-expanded="true" class="nav nav-pills nav-stacked">

                <li class=""> &nbsp;
                  <a href="#" style="padding:0px 15px; margin-top:-49px;"><span class="pull-right label label-warning" data-folder-id="{{$folder['id']}}" id="AddFileToFolder"><i class="fa fa-plus"></i> Create File</span></a>
                </li>

                <li class=""><a href="#"><i class="fa fa-file-text-o"></i> Drafts <span class="pull-right" onclick="alert('here');"><i class="fa fa-trash-o"></i></span> </a></li>

              </ul>
            </li>

            @endforeach

            <li class="active">
              <a href="#" aria-expanded="true"><i class="fa fa-folder-o"></i> Test Folder</a>

              <ul aria-expanded="true" class="nav nav-pills nav-stacked">

                <li class=""> &nbsp;
                  <a href="#" style="padding:0px 15px; margin-top:-49px;"><span class="pull-right label label-warning"><i class="fa fa-plus"></i> Create File</span></a>
                </li>

                <li class=""><a href="#"><i class="fa fa-file-text-o"></i> Drafts <span class="pull-right" onclick="alert('here');"><i class="fa fa-trash-o"></i></span> </a></li>

              </ul>
            </li>


            </ul>

        </div>
        <!-- /.box-body -->
      </div>



    </div>
    <!-- /.col -->
    <div class="col-md-9">
      <input type="hidden" value="{{csrf_token()}}" id="token">
      <input type="hidden" value="{{($activeNote)?$activeNote->id:''}}" id="fileId">

      <div class="box box-primary">
        <div class="box-header with-border">



          @if($new)
          <div class="col-sm-5" style="padding:0px;">
            <input type="text" name="name" class="form-control" value="{{$activeNote->name}}" id="fileName">
          </div>
          @else
            <h3 class="box-title">{{($activeNote)?$activeNote->name:'Untitled File'}}</h3>
          @endif

          <div class="box-tools pull-right">
            <div class="has-feedback">
              <input type="text" class="form-control input-sm" placeholder="Search Notes">
              <span class="glyphicon glyphicon-search form-control-feedback"></span>
            </div>
          </div>
          <!-- /.box-tools -->
        </div>
        <!-- /.box-header -->
        <div class="box-body no-padding" id="content-container">
          <div class="toolbar-container"><span class="ql-format-group">
              <select title="Font" class="ql-font">
                <option value="sans-serif" selected>Sans Serif</option>
                <option value="Georgia, serif">Serif</option>
                <option value="Monaco, 'Courier New', monospace">Monospace</option>
              </select>
              <select title="Size" class="ql-size">
                <option value="10px">Small</option>
                <option value="13px" selected>Normal</option>
                <option value="18px">Large</option>
                <option value="32px">Huge</option>
              </select></span><span class="ql-format-group"><span title="Bold" class="ql-format-button ql-bold"></span><span class="ql-format-separator"></span><span title="Italic" class="ql-format-button ql-italic"></span><span class="ql-format-separator"></span><span title="Underline" class="ql-format-button ql-underline"></span></span><span class="ql-format-group">
              <select title="Text Color" class="ql-color">
                <option value="rgb(0, 0, 0)" selected></option>
                <option value="rgb(230, 0, 0)"></option>
                <option value="rgb(255, 153, 0)"></option>
                <option value="rgb(255, 255, 0)"></option>
                <option value="rgb(0, 138, 0)"></option>
                <option value="rgb(0, 102, 204)"></option>
                <option value="rgb(153, 51, 255)"></option>
                <option value="rgb(255, 255, 255)"></option>
                <option value="rgb(250, 204, 204)"></option>
                <option value="rgb(255, 235, 204)"></option>
                <option value="rgb(255, 255, 204)"></option>
                <option value="rgb(204, 232, 204)"></option>
                <option value="rgb(204, 224, 245)"></option>
                <option value="rgb(235, 214, 255)"></option>
                <option value="rgb(187, 187, 187)"></option>
                <option value="rgb(240, 102, 102)"></option>
                <option value="rgb(255, 194, 102)"></option>
                <option value="rgb(255, 255, 102)"></option>
                <option value="rgb(102, 185, 102)"></option>
                <option value="rgb(102, 163, 224)"></option>
                <option value="rgb(194, 133, 255)"></option>
                <option value="rgb(136, 136, 136)"></option>
                <option value="rgb(161, 0, 0)"></option>
                <option value="rgb(178, 107, 0)"></option>
                <option value="rgb(178, 178, 0)"></option>
                <option value="rgb(0, 97, 0)"></option>
                <option value="rgb(0, 71, 178)"></option>
                <option value="rgb(107, 36, 178)"></option>
                <option value="rgb(68, 68, 68)"></option>
                <option value="rgb(92, 0, 0)"></option>
                <option value="rgb(102, 61, 0)"></option>
                <option value="rgb(102, 102, 0)"></option>
                <option value="rgb(0, 55, 0)"></option>
                <option value="rgb(0, 41, 102)"></option>
                <option value="rgb(61, 20, 102)"></option>
              </select><span class="ql-format-separator"></span>
              <select title="Background Color" class="ql-background">
                <option value="rgb(0, 0, 0)"></option>
                <option value="rgb(230, 0, 0)"></option>
                <option value="rgb(255, 153, 0)"></option>
                <option value="rgb(255, 255, 0)"></option>
                <option value="rgb(0, 138, 0)"></option>
                <option value="rgb(0, 102, 204)"></option>
                <option value="rgb(153, 51, 255)"></option>
                <option value="rgb(255, 255, 255)" selected></option>
                <option value="rgb(250, 204, 204)"></option>
                <option value="rgb(255, 235, 204)"></option>
                <option value="rgb(255, 255, 204)"></option>
                <option value="rgb(204, 232, 204)"></option>
                <option value="rgb(204, 224, 245)"></option>
                <option value="rgb(235, 214, 255)"></option>
                <option value="rgb(187, 187, 187)"></option>
                <option value="rgb(240, 102, 102)"></option>
                <option value="rgb(255, 194, 102)"></option>
                <option value="rgb(255, 255, 102)"></option>
                <option value="rgb(102, 185, 102)"></option>
                <option value="rgb(102, 163, 224)"></option>
                <option value="rgb(194, 133, 255)"></option>
                <option value="rgb(136, 136, 136)"></option>
                <option value="rgb(161, 0, 0)"></option>
                <option value="rgb(178, 107, 0)"></option>
                <option value="rgb(178, 178, 0)"></option>
                <option value="rgb(0, 97, 0)"></option>
                <option value="rgb(0, 71, 178)"></option>
                <option value="rgb(107, 36, 178)"></option>
                <option value="rgb(68, 68, 68)"></option>
                <option value="rgb(92, 0, 0)"></option>
                <option value="rgb(102, 61, 0)"></option>
                <option value="rgb(102, 102, 0)"></option>
                <option value="rgb(0, 55, 0)"></option>
                <option value="rgb(0, 41, 102)"></option>
                <option value="rgb(61, 20, 102)"></option>
              </select><span class="ql-format-separator"></span>
              <select title="Text Alignment" class="ql-align">
                <option value="left" selected></option>
                <option value="center"></option>
                <option value="right"></option>
                <option value="justify"></option>
              </select></span><span class="ql-format-group"><span title="Link" class="ql-format-button ql-link"></span><span class="ql-format-separator"></span><span title="Image" class="ql-format-button ql-image"></span><span class="ql-format-separator"></span><span title="List" class="ql-format-button ql-list"></span>

              <span class="ql-format-separator">
              <span class="font-size:18px;" id="saveButton"><span title="Save" class="fa fa-save fa-1x" style="margin-left:10px; margin-top: 0 !important; position: absolute;"></span></span>

            </span></div>

              <div class="editor-container" id="myNotes" style="min-height:500px;">

                @if($activeNote)
                {!!$activeNote->notes!!}
                @endif

              </div>

        </div>
      </div>
      <!-- /. box -->
    </div>
    <!-- /.col -->
  </div>
  <!-- /.row -->
</section>
<!-- /.content -->

</div>
<!-- /.content-wrapper -->


<!-- Modal -->
<div id="createFolder" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Create Folder</h4>
      </div>
      <form class="form-horizontal" method="POST" action="{{url('notes/savefolder')}}">
        {!! csrf_field() !!}

      <div class="modal-body">
          <div class="form-group">
            <label for="inputName" class="col-sm-12">Folder Name</label>

            <div class="col-sm-12">
              <input type="text" name="name" value="" class="form-control" id="inputName" placeholder="Folder Name" required>
            </div>
          </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
      </div>
    </form>
    </div>

  </div>
</div>


@section('extrascript')
<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/lodash.js/2.4.1/lodash.js"></script>
<script type="text/javascript" src="{{asset('plugins/quill/quill.js')}}"></script>
<script type="text/javascript" src="{{asset('plugins/quill/advanced.js')}}"></script>

<script>
  $(document).ready(function(){

    $("#menu").metisMenu();

    $("#myNotes").keypress(function(){
        var html = advancedEditor.getHTML();
        var fileName = $('#fileName').val();
        var fileId = $('#fileId').val();
        var token = $('#token').val();

        $.ajax({
            url:'/notes/savenotes',
            type:'post',
            data:{'name':fileName, 'notes':html, '_token':token,'file_id':fileId},
            success:function(data){}
        });
    });

    $('body').on('click', '#saveButton', function(){
      var html = advancedEditor.getHTML();
      var fileName = $('#fileName').val();
      var fileId = $('#fileId').val();
      var token = $('#token').val();

      $.ajax({
          url:'/notes/savenotes',
          type:'post',
          data:{'name':fileName, 'notes':html, '_token':token,'file_id':fileId},
          success:function(data){}
      });
    });


  });

  $('body').on('click', '#AddFileToFolder', function(){
      var folder = $(this).attr('data-folder-id');
      window.location = 'notes/savefile/'+folder;
  });


</script>

@endsection

@endsection
