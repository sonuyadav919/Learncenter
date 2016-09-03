$(document).ready(function() {

  /* initialize the calendar
   ----------------------------------------------------------------- */
  var date = new Date();
  var d = date.getDate();
  var m = date.getMonth();
  var y = date.getFullYear();
  var h = date.getHours();
  var m = date.getMinutes();
  var s = date.getSeconds();

  $('#calendar').fullCalendar({
      header: {
          left: 'prev,next today',
          center: 'title',
          right: 'month,agendaWeek,agendaDay'
      },
      theme: true,
      editable: true,
      droppable: true, // this allows things to be dropped onto the calendar
      drop: function() {
          // is the "remove after drop" checkbox checked?
          if ($('#drop-remove').is(':checked')) {
              // if so, remove the element from the "Draggable Events" list
              $(this).remove();
          }
      },
      eventDrop: function(event, delta, revertFunc) {
          var id  = event.id;
          var date = new Date(event.start);
              date = date.getFullYear() + "-"+(date.getMonth()+1) +"-"+date.getDate();

          var end = new Date(event.end);
              end = end.getFullYear() + "-"+(end.getMonth()+1) +"-"+end.getDate();

          var data = {'start_date':date, 'id':id, 'end_date':end};

          if (AuthID != event.userID){
              revertFunc();
          }
          else if (!confirm("Are you sure about this change?")) {
              revertFunc();
          }else{

            jQuery.ajax({
                url: "dashboard/savecalenderevent",
                type:'POST',
                data: data,
                success: function(data){
                  console.log(data);
                }
            });
          }

      },
      events: [

      ],

      eventClick:  function(event, jsEvent, view) {
          $('#modalTitle').html(event.title);

          if(event.start)
            $("#start").html(moment(event.start).format('dddd, MMMM Do YYYY, h:mm A'));
          else
            $("#eStart").hide();

          if(event.end)
            $("#end").html(moment(event.end).subtract(1, 'days').format('dddd, MMMM Do YYYY, h:mm A'));
          else
            $("#eEnd").hide();

          $("#modalBody").html(event.description);
          $("#createdBy").html(event.createdBy);

          var invitedUser = event.invitedUser;
          if(invitedUser){
              invitedUser = invitedUser.replace(/\!/g, '<span class="sample-tag">');
              invitedUser = invitedUser.replace(/\,/g, '</span>');
          }


          $("#invitedUser").html(invitedUser);

          $("#eventEdit").attr('data-id',event.id);

          if(event.url){
            $('#eventUrl').show();
            $('#eventUrl').attr('href',event.url);
          }
          else
            $('#eventUrl').hide();

            if (AuthID != event.userID){
                $('#eventDelete').hide();
                $('#eventEdit').hide();
            }else{
                $('#eventDelete').show();
                $('#eventDelete').attr('href',"dashboard/deleteevent/"+event.id);
                $('#eventEdit').show();
            }

          $('#fullCalModal').modal();
          return false;
      },

      dayClick: function(date) {
        var date = new Date(date);
            date = date.getFullYear() + "-"+('0'+(date.getMonth()+1)).slice(-2) +"-"+('0'+date.getDate()).slice(-2)+" "+('0'+date.getHours()).slice(-2)+":"+('0'+date.getMinutes()).slice(-2)+":"+('0'+date.getSeconds()).slice(-2);

        if($('#TeamCalender').hasClass('btn-primary')){
          $('#eventType').attr('value','team');
        }


        $('#eventStartDate').attr("value",""+date);
        $('#addEventModal').modal();
      },

      eventResize: function(event, delta, revertFunc) {
          eventResizeUpdate(event.id,event.end.format());
      }
  });

});


function eventResizeUpdate(id,end_date){
  jQuery.ajax({
      url: "dashboard/updateenddate/"+id+"/"+end_date,
      type:'POST',
      success: function(data){
        console.log(data);
      }
  });
}


function saveCalenderEvent()
{
    var date = $("#eventStartDate").val();
    var end_date = $("#eventEndDate").val();
    var title = $("#eventTitle").val();
    var description = $("#eventDesc").val();
    var event_url = $("#eventURL").val();
    var event_type = $("#eventType").val();
    var invite_user = $("#inviteMember").val();
    var event_id = $("#eventId").val();

    var data = {'event_id':event_id,'start_date':date, 'end_date':end_date, 'title':title, 'description':description,'event_url':event_url,'event_type':event_type,'invite_user':invite_user};

    jQuery.ajax({
        url: "dashboard/savecalenderevent",
        type:'POST',
        data: data,
        success: function(data){
          console.log(data);
          if(event_type == 'team')
            window.location = '/dashboard?param=team';
          else
            window.location = '/dashboard';
        }
    });
}
