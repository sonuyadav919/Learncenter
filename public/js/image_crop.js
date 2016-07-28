$(document).ready(function(){
  var url = $(location).attr('href');
  var lastSegment = url.split('/').pop();

  $('#inputImage').click(function(){
		  var $image = $(".image-crop > img")

      if(lastSegment == 'profile'){
            $($image).cropper({
                aspectRatio: 1.1,
                preview: ".img-preview",
                done: function(data) {
					$('#crop_height').val(data.height);
					$('#crop_width').val(data.width);
					$('#crop_x').val(data.x);
					$('#crop_y').val(data.y);
                }
            });
        }
        else{
            $($image).cropper({
            //    aspectRatio: 1.1,
                preview: ".img-preview",
                done: function(data) {
          $('#crop_height').val(data.height);
          $('#crop_width').val(data.width);
          $('#crop_x').val(data.x);
          $('#crop_y').val(data.y);
                }
            });
        }

            var $inputImage = $(this);
            if (window.FileReader) {
                $inputImage.change(function() {
                    var fileReader = new FileReader(),
                            files = this.files,
                            file;
                    if (!files.length) {
                        return;
                    }

                    file = files[0];

                    if (/^image\/\w+$/.test(file.type)) {
                        fileReader.readAsDataURL(file);
                        fileReader.onload = function () {
                            $image.cropper("reset", true).cropper("replace", this.result);

                        };
                    } else {
                        showMessage("Please choose an image file.");
                    }
                });
            } else {
                $inputImage.addClass("hide");
            }
		});
});
