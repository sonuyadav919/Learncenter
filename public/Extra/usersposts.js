
$('body').on('click', '#imageContent', function(){
  $('#imageUpload').show();
  $('#text-box').hide();
  $('#imageContent').removeClass('btn-white');
  $('#imageContent').addClass('btn-primary');
  $('#textContent').removeClass('btn-primary');
  $('#textContent').addClass('btn-white');
  $('#savePost').attr('id', 'saveImage');
});

$('body').on('click', '#textContent', function(){
  $('#imageUpload').hide();
  $('#text-box').show();
  $('#textContent').removeClass('btn-white');
  $('#textContent').addClass('btn-primary');
  $('#imageContent').removeClass('btn-primary');
  $('#imageContent').addClass('btn-white');
  $('#saveImage').attr('id', 'savePost');
});


$('body').on('click', '#saveImage', function(){
   var formData = new FormData();
   formData.append('file', $('input[type=file]')[0].files[0]);
   formData.append('_token', '');

   var  html = '<form id="imageForm" enctype="multipart/form-data" action="/profile/saveimage">'
           +'<input type="file" accept="image/*" onchange="loadFile(event)" name="image">'
         +'</form>'
         +'<img id="output" name="image"/>';

      $.ajax({
         type: "POST",
         url: "/profile/saveimage",
         mimeType:"multipart/form-data",
         contentType: false,
         cache: false,
         processData:false,
         data: formData,
         success: function( data )
         {
            if(data){
              $('#imageUpload').html(html);
              $('#imageUpload').hide();
              $('#text-box').show();
              $('#textContent').removeClass('btn-white');
              $('#textContent').addClass('btn-primary');
              $('#imageContent').removeClass('btn-primary');
              $('#imageContent').addClass('btn-white');
              $('#saveImage').attr('id', 'savePost');
              getLastposts(data);
            }else{
              alert('Please select a valid image!');
            }
         }
    });

});


  $('body').on('click', '#savePost', function(){
    var posts = $('#addPost').val();
    var data = {'posts':posts};

    $.ajax({
        url: '/profile/addpost',
        type:'POST',
        data: data,
        success:function(data){
          $('textarea[name=post]').val('');
          console.log(data);
          if(data != 0){
            getLastposts(data);
          }
        }
    });
  });


  $('body').on('click', '#updatePost', function(){
    var posts = $('#addPost').val();
    var data = {'posts':posts};
    var id = $(this).attr('data-id');

    $.ajax({
        url: '/profile/addpost/'+id,
        type:'POST',
        data: data,
        success:function(data){
          $('textarea[name=post]').val('');
          $('#updatePost').hide();
          $('#savePost').show();
          $('#post'+id).html(posts);
        }
    });
  });



$('body').on('click', '#editPost', function(){
    var post = $(this).attr('data-post');
    var id = $(this).attr('data-id');
    $('textarea[name=post]').val(post);
    $('#updatePost').attr('data-id', id);

    $("html, body").animate({
        scrollTop: 0
    }, 600);

    $('#savePost').hide();
    $('#updatePost').show();


});


$('body').on('click', '#likeThis', function(){
  var postId = $(this).attr('data-id');

  $.ajax({
      url:'profile/likedislikepost/'+postId,
      type:'GET',
      success:function(data){
          var html = '';
          var totalLike = (data.totalLike)?data.totalLike:'';

          if(data.action == 'like'){
            html = '<span class="text-success"><i class="fa fa-thumbs-up"></i></span> '+totalLike+' Like this!';
          }
          else{
            html = '<i class="fa fa-thumbs-up"></i> '+totalLike+' Like this!';
          }

          $('.likeThis'+postId).html(html);
      }
  });

});


$('body').on('click', '#commentLike', function(){
  var commentId = $(this).attr('data-id');

  $.ajax({
      url:'profile/likedislikecomment/'+commentId,
      type:'GET',
      success:function(data){
          var html = '';
          var totalLike = (data.totalLike)?data.totalLike:'';

          if(data.action == 'like'){
            html = '<i class="fa fa-thumbs-up text-success"></i> '+totalLike+' Like this!';
          }
          else{
            html = '<i class="fa fa-thumbs-up"></i> '+totalLike+' Like this!';
          }

          $('.commentLike'+commentId).html(html);
      }
  });

});


$('body').on('click', '#shareThis', function(){
  var postId = $(this).attr('data-post-id');

  $.ajax({
      url:'profile/sharepost/'+postId,
      type:'POST',
      success:function(data){
        getLastposts(data.postId, data.shared);
        html = data.totalShare+' <i class="fa fa-share"></i> Share';
        $('.shareThis'+postId).html(html);
      }
  });

});


$('body').on('click', '#sendFriendRequest', function(){
  var request_sender_id = $(this).attr('data-sender-id');
  var request_recever_id = $(this).attr('data-recever-id');
  var suggession = $(this).attr('data-suggession');
  var data = {'request_sender_id':request_sender_id, 'request_recever_id':request_recever_id}
  $.ajax({
      url: '/profile/sendfriendrequest',
      type:'POST',
      data: data,
      success:function(data){

        if(suggession){
            $('#suggession'+request_recever_id).hide();
        }else{
          html = '<i class="fa fa-check-circle-o"></i>&nbsp;Request Sent';
          $('#sendFriendRequest').html(html);
          $('#sendFriendRequest').removeClass('btn-primary');
          $('#sendFriendRequest').addClass('btn-danger');
          $('#sendFriendRequest').attr('id', 'cancelFriendRequest');
        }

        flashNotification(data);

      }
  });
});


$('body').on('click', '#cancelFriendRequest', function(){
  var request_sender_id = $(this).attr('data-sender-id');
  var request_recever_id = $(this).attr('data-recever-id');
  var data = {'request_sender_id':request_sender_id, 'request_recever_id':request_recever_id}
  $.ajax({
      url: '/profile/cancelfriendrequest',
      type:'POST',
      data: data,
      success:function(data){
        html = '<i class="fa fa-plus-circle"></i>&nbsp;Add Friend';
        $('#cancelFriendRequest').html(html);

        $('#cancelFriendRequest').removeClass('btn-danger');
        $('#cancelFriendRequest').addClass('btn-primary');

        $('#cancelFriendRequest').attr('id', 'sendFriendRequest');

        flashNotification(data);

      }
  });
});

$('body').on('click', '#acceptRequest', function(){
  var rowId = $(this).attr('data-id');
  var _this = this;
  $.ajax({
      url: '/profile/acceptfriendrequest',
      type:'POST',
      data: {'id':rowId},
      success:function(data){
        $('#requestRow'+rowId).hide();

        html = '<i class="fa fa-times-circle-o"></i>&nbsp;Unfriend';
        $(_this).html(html);
        $(_this).removeClass('btn-success');
        $(_this).addClass('btn-danger');
        $(_this).attr('id', 'cancelFriendRequest');

        flashNotification(data);

      }
  });
});


$('body').on('click', '#startFollow', function(){
  var followingId = $(this).attr('data-following-id');
  var followerId = $(this).attr('data-follower-id');

  $.ajax({
      url: '/profile/startfollowing',
      type:'POST',
      data: {'user_id':followerId, 'following':followingId},
      success:function(data){
        var html = '<span style="color:yellow;"><i class="fa fa-star"></i>&nbsp;</span> Following'

        $('#startFollow').html(html);
        $('#startFollow').attr('id', 'unFollow');

        flashNotification(data);

      }
  });
});



$('body').on('click', '#unFollow', function(){
  var followingId = $(this).attr('data-following-id');
  var followerId = $(this).attr('data-follower-id');

  $.ajax({
      url: '/profile/unfollow',
      type:'POST',
      data: {'user_id':followerId, 'following':followingId},
      success:function(data){
        var html = '<i class="fa fa-star"></i>&nbsp; Follow'

        $('#unFollow').html(html);
        $('#unFollow').attr('id', 'startFollow');

        flashNotification(data);

      }
  });
});


$('body').on('click', '#sendMessage', function(){
  var recever = $(this).attr('data-msg-recever');
  var sender = $(this).attr('data-mess-sender');
  var message = $('#messBox').val();

  postSendMessage(sender, recever, message);

});


$('body').on('click', '#messageSend', function(){
  var recever = $(this).attr('data-recever');
  var sender = $(this).attr('data-sender');
  readAppendMessage(recever, sender);
});


$("#searchFriend").keyup(function() {
    var value = $(this).val();
    var sender = $(this).attr('data-sender');

    $.ajax({
      url:'/profile/searchfriend',
      type:'GET',
      data: {'search': value},
      success:function(data){
        $('#FriendList').html('');
        $('.absolute').show();

        $.each(data, function(key, result){
          var imgPath='/uploads/users/'+result.avatar;

          var html = '<div class="list-group-item">'
                        +'<div class="col-sm-1" style="padding:0px;">'
                              +'<img src="'+imgPath+'" onerror="imgError(this);" border="0" width="25" class="img-circle" alt="image"/>'
                         +'</div>'
                         +'<div class="col-sm-7" style="padding:0px;">'
                              +'<span class="name"><a href="/profile?user='+result.id+'" title="Click to view profile"> '+result.first_name+' '+result.last_name+' </a></span><br/>'
                          +'</div>'
                          +'<div class="col-sm-4" style="padding:0px;">';

                          if(result.sentRequest){
                            html += '<a href="" onclick="return false;" class="btn btn-primary btn-xs" data-recever-id="'+result.id+'" data-sender-id="'+sender+'" ><i class="fa fa-check-circle-o"></i>&nbsp; Request Sent</a>';
                          }
                          else if(result.unfriend){
                            html += '<a href="" onclick="return false;" class="btn btn-primary btn-xs" data-recever-id="'+result.id+'" data-sender-id="'+sender+'" ><i class="fa fa-users"></i>&nbsp; Friends</a>';
                          }
                          else{
                            html += '<a href="" onclick="return false;" class="btn btn-primary btn-xs requestsent" id="sendFriendRequest" data-recever-id="'+result.id+'" data-sender-id="'+sender+'" data-suggession="1" ><i title="Send Request" class="fa fa-user-plus"></i>&nbsp; Send Request</a>';
                          }

                           html +='</div>'
                        +'<div class="clearfix"></div>'
                    +'</div>';


                if(key == 4){

                  html += '<div class="list-group-item">'
                            +'<button type="submit" class="btn btn-white btn-sm btn-block" >View More</button>'
                          +'<div>';

                }


          $('#FriendList').append(html);
        });

        if(!value){
          $('.absolute').hide();
        }
      }
    });

});


$('body').on('click', '.requestsent', function(){
    $(this).html('<i class="fa fa-check-circle-o"></i>&nbsp; Request Sent');
    $(this).attr('id', '');
});


$('input[type="checkbox"][name="email_notification"]').on('ifChanged', function(event){
     if(!$(this).parent('div').hasClass('checked')){
         $.ajax({
            url:'/profile/updateprofile',
            type:'POST',
            data:{'email_notification':1},
            success:function(data){}
         });
     }else{
         $.ajax({
            url:'/profile/updateprofile',
            type:'POST',
            data:{'email_notification':0},
            success:function(data){}
         });
     }
});


function getLastposts(id,share=null)
{
    $.ajax({
        url:'/profile/lastpost/'+id,
        type:'GET',
        success:function(post){
            var imgPath='/uploads/users/'+post.avatar;

            var posthtml = '<div class="social-feed-box">';

                if(share){
                  posthtml+= '<div style="padding:5px 15px; border-bottom:1px solid #e6e6e6;"><b><i class="fa fa-share"></i> <a href="profile?user='+post.user_id+'"> '+share.sharedUser+' </a> shared <a href="profile?user='+post.post_user_id+'"> '+share.originalUser+'</a> post!</b></div>';
                }

                if(post.auth_user.id == post.user_id){
                 posthtml +='<div class="pull-right social-action dropdown">'
                                  +'<button data-toggle="dropdown" class="dropdown-toggle btn-white"><i class="fa fa-angle-down"></i></button>'
                                  +'<ul class="dropdown-menu m-t-xs">';

                  if(!share || post.type != 'img'){
                    posthtml += '<li><a id="editPost" data-id="'+post.id+'" data-post="'+post.posts+'"><i class="fa fa-edit"></i>&nbsp;Edit</a></li>';
                  }

                  posthtml +=      '<li><a href="/profile/deletepost/'+post.id+'" class="postDelete"><i class="fa fa-trash-o"></i>&nbsp;Delete</a></li>'
                                  +'</ul>'
                              +'</div>';
                }
                  posthtml += '<div class="social-avatar">'
                                  +'<a href="" class="pull-left">'
                                    +'<img src="'+imgPath+'" onerror="imgError(this);" border="0" width="40" alt="image"/>'
                                  +'</a>'
                                  +'<div class="media-body">'
                                      +'<a href="/profile?user='+post.user_id+'">'+post.first_name+' '+post.last_name+'</a>'
                                      +'<small class="text-muted postDate">'+moment(post.created_at).format('h:mm a, DD-MMM-YYYY')+'</small>'
                                  +'</div>'
                              +'</div>'
                              +'<div class="social-body">';

                              if(post.type == 'img')
                                posthtml += '<img src="/'+post.posts+'" alt="image" style="max-width:100%;">';
                              else
                                posthtml += '<p id="post'+post.id+'" class="word-wrap">'+post.posts+'</p>';

                            posthtml += '<div class="btn-group">'
                                      +'<button class="btn btn-white btn-xs likeThis'+post.id+'" id="likeThis" data-id="'+post.id+'"><i class="fa fa-thumbs-up"></i> Like this!</button>'
                                      +'<button class="btn btn-white btn-xs totalComment'+post.id+'"><i class="fa fa-comments"></i> Comment</button>'
                                      +'<button class="btn btn-white btn-xs shareThis'+post.id+'" id="shareThis" data-post-id="'+post.id+'"><i class="fa fa-share"></i> Share</button>'
                                  +'</div>'
                              +'</div>'
                              +'<div class="social-footer">'
                                  +'<div id="commentBox'+post.id+'"></div>'
                                  +'<div class="social-comment">'
                                      +'<a href="" class="pull-left">'
                          							+'<img src="uploads/users/'+post.auth_user.avatar+'" onerror="imgError(this);" border="0" width="40" class="" alt="image" />'
                                      +'</a>'
                                      +'<div class="media-body">'
                                          +'<textarea class="form-control" style="resize:none; height:30px;" onkeypress="comments(event, '+post.id+')" id="postsComments'+post.id+'" name="comment" placeholder="Write comment..."></textarea>'
                                      +'</div>'
                                  +'</div>'
                              +'</div>'
                          +'</div>';

                        $( "#postsContainer" ).prepend(posthtml);
        }
    });
}



function comments(e,id) {
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code == 13) {
        postCommentsSave(id,$('#postsComments'+id).val());
        $('#postsComments'+id).val('');
    }
}


function postCommentsSave(id,comment)
{
    $.ajax({
        url: '/profile/addpostcomment',
        type:'POST',
        data: {'post_id':id, 'comments':comment},
        success:function(data){
          $('input[name=comment]').val('');
          //console.log(data);
          getLastcomment(data);
        }
    });
}


function getLastcomment(data)
{
    $.ajax({
        url: '/profile/lastcomment/'+data,
        type:'GET',
        success:function(data){
            var imgPath='/uploads/users/'+data.lastComment.avatar;
            var commentHTML = '<div class="social-comment">'
                              +'<a href="" class="pull-left">'
                                +'<img src="'+imgPath+'" onerror="imgError(this);" border="0" width="40" alt="image" />'
                              +'</a>'
                              +'<div class="media-body word-wrap">'
                                  +'<a href="/profile?user='+data.lastComment.user_id+'">'+data.lastComment.first_name+' '+data.lastComment.last_name+'</a>'
                                  +' '+data.lastComment.comments
                                  +'<br/>'
                                  +'<a class="small" id="commentLike" data-id="'+data.lastComment.id+'"><span class="commentLike'+data.lastComment.id+'"><i class="fa fa-thumbs-up"></i> Like this!</span></a> -'
                                  +'<small class="text-muted">'+moment(data.lastComment.created_at).format('h:mm a, DD-MMM-YYYY')+'</small>'
                              +'</div>'
                          +'</div>';


            var totalComment = (data.totalComment)?data.totalComment:'';

            $('#commentBox'+data.lastComment.post_id).append(commentHTML);
            $('.totalComment'+data.lastComment.post_id).html('<i class="fa fa-comments"></i> '+totalComment+' Comment');

        }
    });

}

function sendMessage(e,sender,userAvatar,username) {
    var recever = $('#messageSend').attr('data-recever');
    var code = (e.keyCode ? e.keyCode : e.which);
    if (code == 13) {
            var imgPath='/uploads/users/'+userAvatar;
            var message = $('#messageSend').val();

            if(!message){
              $('textarea[name=message]').val('');
              return false;
            }

            html = '<div class="chat-message right" >'
                    +'<img src="'+imgPath+'" onerror="imgError(this);" border="0" class="message-avatar"  />'
                    +'<div class="message">'
                        +'<a class="message-author" onclick="return false;" href=""> '+username+' </a>'
                          +'<span class="message-date">' +moment( new Date() ).format('h:mm A, DD-MMM-YYYY')+ '</span>'
                        +'<span class="message-content">'+message+'</span>'
                    +'</div>'
                +'</div>';

          $('#chatDiscussion').append(html);

        postSendMessage(sender, recever,message);
        $('#chatDiscussion').animate({ scrollTop: $('#chatDiscussion').prop("scrollHeight") }, 'slow');
    }
}


function postSendMessage(sender_id, recever_id, message)
{
    $.ajax({
        url: '/profile/messagesave',
        type:'POST',
        data: {'sender_id':sender_id, 'recever_id':recever_id, 'message':message},
        success:function(data){
            $('textarea[name=message]').val('');

            getAllPrivateMessageList();

        }
    });
}


function appendPrivateMessage(sender, recever)
{
  $.ajax({
      url: '/profile/unreadmessages/'+sender+'/'+recever,
      type:'GET',
      success:function(data){
        $.each(data, function (k, message) {

          if(message.sender_id != recever){
          var imgPath='/uploads/users/'+message.avatar;
          var html = '';
                    if(message.sender_id == recever)
                        html += '<div class="chat-message right" >';
                    else
                        html += '<div class="chat-message left" >';

                    html += '<img src="'+imgPath+'" onerror="imgError(this);" border="0" class="message-avatar"  />'
                          +'<div class="message">'
                              +'<a class="message-author" onclick="return false;" href=""> '+message.first_name+' '+message.last_name+' </a>'
                                +'<span class="message-date">' +moment(message.created_at).format('h:mm A, DD-MMM-YYYY')+ '</span>'
                              +'<span class="message-content">'+message.message+'</span>'
                          +'</div>'
                      +'</div>';

                  $('#chatDiscussion').append(html);
                  $('#chatDiscussion').animate({ scrollTop: $('#chatDiscussion').prop("scrollHeight") }, 'slow');

                }
                  readAppendMessage(sender, recever);
        });
      }
  });
}


function getAllPrivateMessageList()
{
  $.ajax({
      url: '/profile/private-message-list',
      type:'GET',
      success:function(data){

        if(data.length === 0){
          $('.noMessage').show();
          $('#appendMessage').html('');
        }
        else{
            $('#appendMessage').html('');
            $('.noMessage').hide();

            $.each(data, function (k, message) {
              var imgPath='/uploads/users/'+message.avatar;
              var html = '<tr>'
                              +'<td width="20">'
                                +'<a href="/profile?message='+message.id+'" title="View message">'
                                  +'<img src="'+imgPath+'" onerror="imgError(this);" border="0" width="25" class="img-circle"/>'
                                +'</a>'
                              +'</td>'
                              +'<td><b> <a href="/profile?message='+message.id+'" title="View message">'+message.first_name+' '+message.last_name+'</a><b></td>'
                              +'<td width="20"><span class="label label-warning">'+message.totalMessage+'</span></td>'
                          +'</tr>';

                  $('#appendMessage').append(html);
            });
        }
      }
  });
}



function readAppendMessage(sender, recever)
{
    $.ajax({
        url: '/profile/readmessage',
        type:'POST',
        data: {'sender_id':sender, 'recever_id':recever,},
        success:function(data){
        }
    });
  }


function flashNotification(message)
{
  toastr.success("success", message);
  toastr.options = {
      "closeButton": true,
      "debug": false,
      "positionClass": "toast-top-right",
      "onclick": null,
      "showDuration": "300",
      "hideDuration": "1000",
      "timeOut": "5000",
      "extendedTimeOut": "1000",
      "showEasing": "swing",
      "hideEasing": "linear",
      "showMethod": "fadeIn",
      "hideMethod": "fadeOut"
    }
}


function imgError(image) {
    image.onerror = "";
    image.src = "//www.gravatar.com/avatar/43c3ec07dd8d45dssse88we87878ewewew34bd90d8ab063b6b8b90";
    return true;
}
