@extends('layouts.auth')

<!-- Main Content -->
@section('content')


<div class="login-box">
  <div class="login-logo">
    <a href="/"><b>Learn</b>More</a>
  </div>
  <!-- /.login-logo -->
  <div class="login-box-body">
    <p class="login-box-msg">Reset your account password</p>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

        <form class="form-horizontal" role="form" method="POST" action="{{ url('/password/email') }}">
            {{ csrf_field() }}

      <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }} has-feedback">
              <input id="email" type="email" class="form-control" placeholder="Enter Your E-Mail" name="email" value="{{ old('email') }}">
              <span class="glyphicon glyphicon-envelope form-control-feedback"></span>

              @if ($errors->has('email'))
                  <span class="help-block">
                      <strong>{{ $errors->first('email') }}</strong>
                  </span>
              @endif
      </div>


      <div class="row">
        <div class="col-xs-4">
        </div>
        <!-- /.col -->
        <div class="col-xs-8">
          <button type="submit" class="btn btn-primary btn-block btn-flat">
              <i class="fa fa-btn fa-envelope"></i> Send Password Reset Link
          </button>
        </div>
        <!-- /.col -->
      </div>
    </form>

    <div class="social-auth-links text-center">
      <p>- OR -</p>
      <a href="#" class="btn btn-block btn-social btn-facebook btn-flat"><i class="fa fa-facebook"></i> Sign in using
        Facebook</a><br>
      <a href="#" class="btn btn-block btn-social btn-google btn-flat"><i class="fa fa-google-plus"></i> Sign in using
        Google+</a>
    </div>
    <!-- /.social-auth-links -->

    <a class="text-center" href="{{ url('/login') }}">Login to your account</a><br>
    <a href="{{ url('/register') }}" class="text-center">Register a new membership</a>

  </div>
  <!-- /.login-box-body -->
</div>
<!-- /.login-box -->



<!--
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Reset Password</div>
                <div class="panel-body">
                    @if (session('status'))
                        <div class="alert alert-success">
                            {{ session('status') }}
                        </div>
                    @endif

                    <form class="form-horizontal" role="form" method="POST" action="{{ url('/password/email') }}">
                        {{ csrf_field() }}

                        <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                            <label for="email" class="col-md-4 control-label">E-Mail Address</label>

                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}">

                                @if ($errors->has('email'))
                                    <span class="help-block">
                                        <strong>{{ $errors->first('email') }}</strong>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-btn fa-envelope"></i> Send Password Reset Link
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
-->
@endsection
