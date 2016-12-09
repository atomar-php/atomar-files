// Requirements: jquery.filedupload.js, jquery.iframe-transport.js, jquery.ui.widget.js, spark-md5.min.js

// Implements https://github.com/blueimp/jQuery-File-Upload
(function($) {
  // Adds vertical scroll bars to the object if the inner content exeeds the container height.
  $.fn.make_scrollable = function() {
    var totalHeight = 0;
    this.children().filter(':visible').each(function(){
      totalHeight += $(this).outerHeight(true);
    });
    return this.css('overflow-y', totalHeight > this.outerHeight(true) ? 'scroll' : 'visible');
  };

  function make_progress_bar(progressAmt) {
    progressAmt = (typeof progressAmt === "undefined") ? 0 : progressAmt;
    var progress = $('<div>').addClass('file-drop-progress file-drop-progress-striped active').append(function() {
      return $('<div>').addClass('bar').append('<span>');
    });
    progress.find('.bar').width(progressAmt+'%');
    return progress;
  }

  function letterTrim(text, length) {
    if (text.length > length) {
      return text.substring(0,length-3)+'...';
    } else {
      return text;
    }
  }

  // http://stackoverflow.com/questions/1043957/clearing-input-type-file-using-jquery
  function resetFormElement(e) {
    e.wrap('<form>').closest('form').get(0).reset();
    e.unwrap();
  }

  // calculates the upload speed in kbs
  function check_upload_speed(iterations, done) {
    var average = 0
      , index = 0 // used to track completed posts.
      , numPosts = 0 // used to keep track of how many posts have been sent.
      , timer = window.setInterval( check, 1000 ); // check every second

    // Run just once if iterations is 1 or less. The setInterval will fall through.
    if (iterations > 1) return;
    check();

    function check() {
      var xhr = new XMLHttpRequest()
        , url = '?cache=' + Math.floor( Math.random() * 10000 ) //random number prevents url caching
        , data = getRandomString( 1 ) //1 meg POST size handled by all servers
        , startTime
        , speed = 0;
      xhr.onreadystatechange = function ( event ) {
        if( xhr.readyState == 4 ) {
          speed = Math.round( 1024 / ( ( new Date() - startTime ) / 1000 ) );
          average == 0 
            ? average = speed 
            : average = Math.round( ( average + speed ) / 2 );
          index++;
          if( index == iterations ) {
            // Stop sending posts and return the average
            window.clearInterval( timer );
            done(average);
          }
        }
      };
      xhr.open( 'POST', url, true );
      startTime = new Date();
      xhr.send( data );

      numPosts++;
      
      // Stop sending posts
      if (numPosts == iterations) {
        window.clearInterval(timer);
      }
    }

    function getRandomString( sizeInMb ) {
      var chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789~!@#$%^&*()_+`-=[]\{}|;':,./<>?" //random data prevents gzip effect
        , iterations = sizeInMb * 1024 * 1024 //get byte count
        , result = '';
      for( var index = 0; index < iterations; index++ ) {
        result += chars.charAt( Math.floor( Math.random() * chars.length ) );
      };    
      return result;
    }
  }

  $.fn.filedrop = function(settings) {
    var filedropInstance = this
      , dropZoneVisible = false
      , dropZoneTimer
      , uploadSpeed = 0
      , checkingSpeed = false // speed check lock
      , speedQueue = []
      , options = $.extend({
        container: undefined, // see below
        dropZone: this.is('input') ? null : this, //NOTE: BlueImp FileUpload requires null (instead of undefined)
        fileInput: this.is('input') ? this : null, //NOTE: BlueImp FileUpload requires null (instead of undefined)
        uploadButton: undefined,
        maxFiles: Number.MAX_VALUE,
        maxFileSize: 20*1048576,
        maxChunkSize: 0,
        readyNotification: 'Ready',
        successNotification: 'Complete',
        errorNotification: 'Error',
        targetPath: '/',
        paramName: 'file',
        multipart: false,
        initUploadUrl: '/~/?!=filedrop&op=init',
        confirmUploadUrl: '/~/?!=filedrop&op=confirm',
        callbackDone: function() {},
        callbackDoneAll: function() {},
        callbackDelete: function() {},
        callbackStart: function() {},
        shouldScrapeFiles: false,
        showFilesOnComplete: true
      }, settings);
    options.autoUpload = !options.uploadButton;

    if(options.uploadButton) {
      // Have the upload button trigger the submission and upload process.
      options.uploadButton.on("click", function() {
        load_progress_bars();
        files.init_upload();
        $(this).attr("disabled", "disabled");
      });

      // There are no files yet, so disable the upload button.
      options.uploadButton.attr("disabled", "disable");
    }

    $.support.cors = true;

    var container
      , progressBars = $('<ul>').addClass('file-drop-progress-bars').hide()
      , totalProgressBar = make_progress_bar().hide()
      , files = []
      , fileDropInfo;

    container = !this.is('input') ? this :
        options.container ||
        this.closest('.container') ||
        this.closest('form') ||
        this.parent();
    container.addClass('file-drop');
    fileDropInfo = $('<div/>', {
      class:'file-drop-info'
    });
    container.append(fileDropInfo);

    files.isUploadComplete = false;

    if (!this.is('input')) {
      var multiple = options.maxFiles > 1 ? 'multiple' : ''
        , fileSelect = $('<input type="file" name="file_input" id="file-select" '+multiple+'>');
      container.append(fileSelect);
      fileSelect.bind('change', function(e) {
        console.log('some files were selected');
        execute_callback(options.callbackStart, {});
        $(filedropInstance).fileupload('add', {
          files: $(this).prop('files')
        });
        resetFormElement($(this));
      });

      fileDropInfo.click(function() {
        container.find('#file-select').click();
      });
    }

    // Set up drag-n-drop handling for child elements
    // http://stackoverflow.com/questions/7110353/html5-dragleave-fired-when-hovering-a-child-element/17052434#17052434
    $(document).on('dragstart dragenter dragover', function(event) {    
      // Only file drag-n-drops allowed, http://jsfiddle.net/guYWx/16/
      if ($.inArray('Files', event.originalEvent.dataTransfer.types) > -1) {
        // Needed to allow effectAllowed, dropEffect to take effect
        event.stopPropagation();
        // Needed to allow effectAllowed, dropEffect to take effect
        event.preventDefault();

        // .file-drop
        if($(event.target).hasClass('file-drop')) {
          container.addClass('over');     // Hilight the drop zone
          dropZoneVisible= true;
          event.originalEvent.dataTransfer.effectAllowed= 'copyMove';
          // event.originalEvent.dataTransfer.dropEffect= 'move';
        } else if($(event.target).parents('.file-drop').length) {
          container.addClass('over');     // Hilight the drop zone
          dropZoneVisible= true;
          event.originalEvent.dataTransfer.effectAllowed= 'copyMove';
        } else {
          // http://www.html5rocks.com/en/tutorials/dnd/basics/
          // http://api.jquery.com/category/events/event-object/
          event.originalEvent.dataTransfer.effectAllowed= 'none';
          event.originalEvent.dataTransfer.dropEffect= 'none';
        }
      }
    }).on('drop dragleave dragend', function (event) {  
      dropZoneVisible= false;

      clearTimeout(dropZoneTimer);
      dropZoneTimer= setTimeout( function(){
        if( !dropZoneVisible ) {
          container.removeClass('over'); 
        }
      }, 70); // dropZoneHideDelay= 70, but anything above 50 is better
    });

    var getUploadSpeed = function(doneCallback) {
      var history = variableGet('upload_speed');
      // use cached upload speed within the last 5 minutes.
      var d = new Date();
      if (history && (d.getTime() - history.created_at) < 1000*60*5 ) {
        uploadSpeed = history.speed;
      }

      // fetch upload speed`
      if (uploadSpeed) {
        doneCallback(uploadSpeed);
      } else {
        checkingSpeed = true;
        check_upload_speed(1, function(avg) {
          var history = {};
          var d = new Date();
          history.speed = avg;
          history.created_at = d.getTime();
          variableSet('upload_speed', history);

          uploadSpeed = avg;
          doneCallback(uploadSpeed);
        });
      }
    }

    files.reset = function() {
      this.length = 0;
      this.isUploadComplete = false;
    };

    files.notify_on_complete = (function(on_done) {
      return function() {
        var ids = $.map(this, function(file) {
          return (file.status === 'success' ? {'id':file.fid, 'name':file.name} : null);
        });
        execute_callback(on_done, ids);
      };
    })(options.callbackDoneAll);

    files.group = function() {
      var groups = {};

      $.each(this, function(index, file) {
        groups[file.status] = groups[file.status] || [];
        groups[file.status].push(file);
      });

      return groups;
    };

    files.init_upload = function() {
      this.status = 'uploading';
      $.each(this, function(index, file) {
        if(file.status === 'new') {
          file.context.find('.file-meta .file-status').html('Estimating upload speed...').removeClass('error');
          if (!checkingSpeed && uploadSpeed == 0) {
            getUploadSpeed(function(speed) {
              file.init_upload();
              $(speedQueue).each(function(index, f) {
                f.init_upload();
              });
              speedQueue = [];
            });
          } else if(uploadSpeed > 0) {
            file.init_upload();
          } else {
            speedQueue.push(file);
          }
        }
      });
      this.check_if_complete(); // see if anything happened
    };

    files.on_complete = (function(showFilesOnComplete, cb) {
      var on_complete
        , before = cb.before
        , after = cb.after;

      function on_status(files) {
        return (function(theFiles) {
          var complete
            , error
            , canceled
            , always
            , files = theFiles;

          return {
            complete: function(on_complete) {
              complete = on_complete;
              return this;
            },
            error: function(on_error) {
              error = on_error;
              return this;
            },
            canceled: function(on_canceled) {
              canceled = on_canceled;
              return this;
            },
            always: function(on_always) {
              always = on_always;
              return this;
            },
            run: function() {
              var status = files.status;
              if(status === 'complete' && complete) {
                complete();
              } else if(status === 'error' && error) {
                error();
              } else if(status === 'canceled' && canceled) {
                canceled();
              }
              
              if(always) {
                always();
              }

              return this;
            }
          };
        })(files);
      }

      if(!showFilesOnComplete) {
        on_complete = function() {
          return on_status(files).complete(function() {
            container.addClass('complete');
            update_total_progress(100, 'complete');
            progressBars.slideDown(500, function() {
              $(this).empty();
            });
          }).error(function() {
            container.addClass('complete');
            update_total_progress(100, 'complete');
            progressBars.find('.success')
                        .fadeOut(500);
          }).canceled(function() {
            update_total_progress(0, 'canceled');
            progressBars.hide();
            container.removeClass('dropped complete');
          }).always(function() {
            container.removeClass('in-progress');
          });
        };
      } else {
        on_complete = function() {
          return on_status(files).complete(function() {
            update_total_progress(100, 'complete');
          }).error(function() {
            update_total_progress(100, 'complete');
          }).canceled(function() {
            update_total_progress(0, 'canceled');
          }).always(function() {
            totalProgressBar.fadeOut(250);

            if(progressBars.find('li').length === 0) {
              container.removeClass('dropped');

              if(options.uploadButton) {
                options.uploadButton.attr("disabled", "disable");
              }
            }

            container.removeClass('in-progress');
          });
        };
      }

      return function() {
        if(before) {
          before.call(this);
        }
        on_complete().run();
        if(after) {
          after.call(this);
        }
      };
    })(!!options.showFilesOnComplete, {
      before: function() {
        // console.log('uploading completed. about to finish up. status: ' + this.status);
      },
      after: function() {
        // console.log('all finished.');
        this.notify_on_complete();
      }
    });

    files.check_if_complete = function() {
      var len = this.length
        , groups = this.group();

      this.isUploadComplete = !groups['uploading'] && !groups['new'];

      if(this.isUploadComplete) {
        if(groups['canceled'] && groups['canceled'].length === len) {
          this.status = 'canceled';
        } else if(groups['error']) {
          this.status = 'error';
        } else {
          this.status = 'complete';
        }

        this.on_complete();
      } else {
        this.status = 'uploading';
        container.removeClass('complete');
      }
    };

    files.is_under_limit = (function(limit) {
      return function(num) {
        var groups = this.group();
        // don't count errored-out or canceled files against file limit
        var errors = groups['error'] ? groups['error'].length : 0;
        var cancels = groups['canceled'] ? groups['canceled'].length : 0;
        var len = this.length - errors - cancels;
        return (num + len  <= limit);
      };
    })(options.maxFiles);

    files.is_in_progress = function() {
      return this.status === 'uploading';
    };

    container.append(totalProgressBar);
    container.append(progressBars);

    container.on('dragenter', function() {
      $(this).addClass('over');
    });

    container.on('dragleave', function() {
      $(this).removeClass('over');
    });

    function execute_callback(callback, args) {
      if (callback && typeof(callback) === 'function') {
        callback(args);
      }
    }

    function update_total_progress(progress, state) {
      state = state || 'uploading';

      var working = 'active file-drop-progress-striped';
      var msg = '';

      switch(state) {
      case 'hidden':
        totalProgressBar.fadeOut(250);
        break;
      case 'loading':
        msg = 'Loading...';
        totalProgressBar.addClass(working)
                        .fadeIn(500);
        break;
      case 'complete':
        msg = 'Complete';
        totalProgressBar.removeClass(working)
                        .fadeIn(500);
        break;
      case 'canceled':
        msg = 'Canceled';
        totalProgressBar.removeClass(working)
                        .fadeOut(250);
        break;
      case 'uploading':
      //fall-through
      default:
        msg = progress+'%';
        totalProgressBar.addClass(working)
                        .fadeIn(500);
        break;
      }

      totalProgressBar.find('.bar').width(progress+"%")
                      .find('span').text(msg);
    }

    function load_progress_bars() {
      if(!files.is_in_progress()) {
        update_total_progress(100, 'loading');
      }
      progressBars.fadeIn(500);
      progressBars.find('.file-drop-progress').show();
    }

    //NOTE: This encapsulates logic for a drag-n-drop as well as a file input selection.
    function validate(data) {
      /* 
       * Make sure there are actually files for this drop event,
       *  since this can be called for any drop event on the dropzone,
       *  such as an HTML element drag-n-drop.
       */

      var isFileDrop = data.files.length > 0;

      // Make sure that we haven't gone over the file limit.
      var isUnderFileLimit = files.is_under_limit(data.files.length);

      if(!isUnderFileLimit) {
        alert('Too many files.\nMaximum number of files allowed is ' + options.maxFiles + '.');
      }

      // If already uploading (with autoUpload disabled), ignore this drop.
      //var shouldIgnoreDrop = files.is_in_progress() && !options.autoUpload;
      var shouldIgnoreDrop = false;

      if(shouldIgnoreDrop) {
        alert('There is already an upload in progress.');
      }

      var isValid = isFileDrop && isUnderFileLimit && !shouldIgnoreDrop;
      data.isValid = isValid;

      if(isValid) {
        if(options.autoUpload || files.is_in_progress()) {
          load_progress_bars();
        } else {
          progressBars.show();
          if(options.uploadButton) {
            options.uploadButton.removeAttr("disabled");
          }
        }
        container.addClass('dropped');
      } else {
        if(files.is_in_progress()) {
          // in case it was hidden due to the dragover call (see note there)
          totalProgressBar.show(); 
        }
      }
      container.removeClass('over');
    }

    var dropOptions = $.extend({
      crossDomain: true,
      type: 'POST',
      dragover: function() {
        // If we can't handle at least one more file, just do nothing.
        if(!files.is_under_limit(1)) return;

        /* NOTE:
         * This stuff should probably be in CSS, not JS
         * Also, not sure how much of this block is needed now.
         */
        if(files.isUploadComplete) {
          totalProgressBar.fadeOut(250);
          progressBars.fadeIn(500);
        }
      },
      drop: function(e, data) {
        console.log('some files where dropped.');
        execute_callback(options.callbackStart, {});
        validate(data);
      },
      add: function(e, data) {
        // NOTE: Checking if undefined since the file input can also trigger this.
        if(typeof data.isValid === "undefined") {
          validate(data);
        }

        if(!data.isValid) return;

        // we are doing singleFileUploads; add is called once per file.
        var file = data.files[0];

        file.status = 'new';
        
        file.abort = function() { file.aborted = true; };

        file.on_retry = function() {
          var self = this;
          var el = self.context;
          var retryBtn = el.find('.retry-btn');
          var cancelBtn = retryBtn.clone();
          cancelBtn.removeClass('cancel-btn')
                   .addClass('retry-btn')
                   .text('remove')
                   .attr('title','Remove from the upload queue');

          cancelBtn.insertAfter(retryBtn);
          retryBtn.remove();

          cancelBtn.fadeIn(250).on('click', function() {
            self.on_cancel();
          });

          el.addClass('uploading')
            .find('.file-drop-progress')
            .addClass('file-drop-progress-striped active');
        };

        file.on_uploaded = function() {
          var el = this.context;
          el.removeClass('uploading').find('.cancel-btn').fadeOut(250).off();
          el.find('.file-drop-progress')
            .addClass('file-drop-progress-striped active');
        };

        file.on_error = function() {
          this.abort();
          this.status = 'error';

          this.context.removeClass('file-drop-progress-striped active')
                      .addClass('error');

          var cancelBtn = this.context.find('.cancel-btn');
          this.context.find('.file-meta .file-status').html(options.errorNotification).addClass('error');
          var retryBtn = cancelBtn.clone()
                                  .removeClass('cancel-btn')
                                  .addClass('retry-btn')
                                  .text('retry');

          var self = this;
          retryBtn.on("click", function() {
            self.on_retry();
            self.init_upload();
            files.check_if_complete(); // make sure this knows something is uploading
          });

          retryBtn.insertAfter(cancelBtn);
          cancelBtn.remove();
         
          files.check_if_complete();
        };

        file.on_cancel = function() {
          this.abort();
          this.status = 'canceled';
          this.context.fadeOut(500, function() {
            $(this).remove();
            files.check_if_complete();
          });
        };

        file.on_success = function() {
          this.status = 'success';

          this.context.removeClass('error');

          execute_callback(options.callbackDone, {
            'id': this.fid,
            'name': this.name
          });

          var el = this.context;
          el.addClass('upload-complete')
            .find('.file-drop-progress')
            .fadeOut(500, function() {
              $(this).remove();
            });

          var meta = el.find('.file-meta');
          meta.find('.file-status').html(options.successNotification);
          var cancelBtn = el.find('.cancel-btn');
          var deleteBtn = cancelBtn.clone()
                                   .removeClass('cancel-btn')
                                   .addClass('delete-btn')
                                   .text('delete')
                                   .attr('title','Delete the uploaded file');

          var self = this;
          deleteBtn.on("click", function() {
            el.remove();
            // TRICKY: JL 02-12-2014 We need to be careful here because we don't want to lose custom methods on "files"
            remainingFiles = jQuery.grep(files, function(value) {
              return value != self;
            });
            while(files.length) {
              files.pop();
            }
            $(remainingFiles).each(function(index, element) {
              files.push(element);
            });
            execute_callback(options.callbackDelete, {
              'id': self.fid,
              'name': self.name
            });
          });

          deleteBtn.insertAfter(cancelBtn);
          cancelBtn.remove();

          files.check_if_complete();
        };

        file.hash = function() {
          var self = this
            , blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice
            , chunkSize = 1024 * 1024 * 2
            , startTime = +new Date(), elapsed
            , chunks = Math.ceil(file.size / chunkSize)
            , size = file.size
            , currentChunk = 0
            , spark = new SparkMD5.ArrayBuffer()
            , fileReader = new FileReader()
            , readNextChunk = function() {
              var start = currentChunk * chunkSize;
              var end = Math.min(start + chunkSize, file.size);
              onProgress({
                loaded:start,
                total: size
              })
              fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
            }
            , onProgress = function(data) {
              var progress = parseFloat(data.loaded / data.total * 100, 10).toFixed(2);
              self.context.find('.file-meta .file-status').html('Generating thumb print: '+progress+'%').removeClass('error');
            }

          fileReader.onload = function (e) {
            spark.append(e.target.result); // append array buffer
            ++currentChunk;

            if(file.aborted) {
              return;
            } else if (currentChunk < chunks) {
              readNextChunk();
            } else {
              elapsed = +new Date() - startTime;
              var hash = spark.end();
              console.log("computed hash", hash, 'for file', file.name, 'in', elapsed, 'ms'); // compute hash
              $(file).trigger('hash-generated', [hash]);
            }
          };

          fileReader.onerror = function () {
            console.warn("oops, something went wrong.");
          };

          readNextChunk();
        };

        if(!options.showFilesOnComplete && files.isUploadComplete) {
          files.reset();
        }

        files.push(file);

        var file_upload = $('<li>').addClass('file clearfix uploading');
        var meta = $('<div>').addClass('file-meta');
        meta.appendTo(file_upload);
        $('<span>').addClass('file-name')
                   .text(letterTrim(file.name, 40))
                   .attr('title', file.name)
                   .appendTo(meta);

        var filesize = (function bytesToSize(bytes) {
           var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
           if (bytes == 0) return '0 Bytes';
           var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
           return '(' + Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i] + ')';
        })(file.size);

        $('<span>').addClass('file-size')
                   .text(filesize)
                   .appendTo(meta);
        $('<span>').addClass('file-status')
                    .text(options.readyNotification)
                    .appendTo(meta);
        $('<span>').addClass('cancel-btn text-danger')
                   .text('remove')
                   .attr('title','Remove from the upload queue')
                   .appendTo(file_upload);
        make_progress_bar(100).appendTo(file_upload)
                              .toggle(options.autoUpload || files.is_in_progress());
        progressBars.append(file_upload);
        
        //set the context to the file list element so we can reference it later (for UI)
        file.context = file_upload;

        //adjust the container (visually) since we just added an element
        container.make_scrollable();

        var cancelBtn = file.context.find('.cancel-btn');
        cancelBtn.on('click', function(event){
          file.on_cancel();
        });

        file.init_upload = (function(initUploadUrl, targetPath) {
          return function() {
            var self = this;
            if (self.aborted) return;

            self.status = 'uploading';
            self.context.removeClass('error');

            container.removeClass('complete').addClass('in-progress');
            totalProgressBar.show();
            
            /*
             * Tell the main server that the upload is starting
             *  so that we can get the appropriate upload url and custom file id
             *  before we communicate with the upload server itself
             *  (which may or may not be the same server)
             */

            $(self).on('hash-generated', function(evt, hash) {
              self.context.find('.file-meta .file-status').html('Validating data...').removeClass('error');
              $.get(initUploadUrl, {
                path: targetPath,
                filename: self.name,
                hash: hash,
                speed: uploadSpeed,
                size: self.size
              }, function(response) {
                if(self.status !== 'canceled') {
                  if (response.status === 'success') {
                    data.url = response.upload_url;
                    self.fid = response.fid;
                    console.log('SERVER: '+response.message);
                    self.abort = function() {
                      jqXHR.abort();
                    };
                    self.context.find('.file-meta .file-status').html('Establishing secure connection...').removeClass('error');
                    var jqXHR = data.submit();
                  } else if(response.status === 'duplicate') {
                    self.fid = response.fid;
                    console.log('SERVER: '+response.message);
                    self.on_success();
                  } else {
                    console.log('Error on "' + self.name + '": ' + response.message);
                    self.on_error();
                  }
                }
              });
            });
            self.context.find('.file-meta .file-status').html('Generating thumb print...').removeClass('error');
            self.hash();
          };
        })(options.initUploadUrl, options.targetPath);

        if(options.autoUpload || files.is_in_progress()) {
          file.context.find('.file-meta .file-status').html('Estimating upload speed...').removeClass('error');
          if (!checkingSpeed && uploadSpeed == 0) {
            getUploadSpeed(function(speed) {
              file.init_upload();
              $(speedQueue).each(function(index, f) {
                f.init_upload();
              });
              speedQueue = [];
            });
          } else if(uploadSpeed > 0) {
            file.init_upload();
          } else {
            speedQueue.push(file);
          }
        }
      },
      progress: function(e, data) {
        var progress = parseFloat(data.loaded / data.total * 100, 10).toFixed(2);
        var fileItem = data.files[0].context;
        fileItem.find('.file-meta .file-status').html('Uploading: '+progress+'%').removeClass('error');
        fileItem.find('.bar').width(progress+'%');
        fileItem.find('.file-drop-progress').removeClass('file-drop-progress-striped');
      },
      progressall: function(e, data) {
        var progress = parseInt(data.loaded / data.total * 100, 10);
        update_total_progress(progress);
      },
      done: function(e, data) {
        // This callback will only be called once per file.
        var file = data.files[0];

        file.on_uploaded();

        /* 
         * Even though the file is "done", we need to confirm it if we can.
         * If there is no callback url for the confirm, we will assume that
         *  the file uploaded successfully.
         */

        if(options.confirmUploadUrl) {
          file.context.find('.file-meta .file-status').html('Finishing upload...').removeClass('error');
          file.context.find('.file-drop-progress').addClass('confirming');
          $.get(options.confirmUploadUrl, {
            fid: file.fid,
            scrape: (+options.shouldScrapeFiles)
          }, function(response) {
            if (response.status === 'success') {
              // console.log('Upload confirmed.');
              file.on_success();
            } else {
              // console.log('Upload confirmation error!');
              file.on_error();
            }
          });
        } else {
          file.on_success();
        }
      },
      fail: function(e, data) {
        // This callback will only be called once per file.
        var file = data.files[0];

        // check if canceled by the user (already handled)
        if(file.status !== 'canceled') {
          file.on_error();
        }
      },
      always: function(e, data) {

      }
    }, options);

    this.fileupload(dropOptions);
    return this;
  };

}(jQuery));

