var CallforwardC = UCPMC.extend({
	init: function(){
		this.stopPropagation = {
			'CFU': {},
			'CFB': {},
			'CF': {},
			'ringtimer': {}
		};
	},
	prepoll: function() {
		var exts = [];
		$(".grid-stack-item[data-rawname=callforward]").each(function() {
			exts.push($(this).data("widget_type_id"));
		});
		return exts;
	},
	poll: function(data) {
		var self = this;
		$.each(data.states, function(extension,data) {
			$.each(data, function(type,number) {
				var state = (number !== false);
				if(typeof self.stopPropagation[type][extension] !== "undefined" && self.stopPropagation[type][extension]) {
					return true;
				}
				var widget = $(".grid-stack-item[data-rawname=callforward][data-widget_type_id='"+extension+"']:visible input[data-type='"+type+"']"),
					sidebar = $(".widget-extra-menu[data-module=callforward][data-widget_type_id='"+extension+"']:visible input[data-type='"+type+"']"),
					sstate = state ? "on" : "off";
				if(widget.length && (widget.is(":checked") !== state)) {
					self.stopPropagation[type][extension] = true;
					widget.bootstrapToggle(sstate);
					if(state) {
						widget.parents(".parent").find(".display").removeClass("hidden").find(".text").text(number);
					} else {
						widget.parents(".parent").find(".display").addClass("hidden").find(".text").text("");
					}
					self.stopPropagation[type][extension] = false;
				}
				if(sidebar.length && (sidebar.is(":checked") !== state)) {
					self.stopPropagation[type][extension] = true;
					sidebar.bootstrapToggle(sstate);
					if(state) {
						sidebar.parents(".parent").find(".display").removeClass("hidden").find(".text").text(number);
					} else {
						sidebar.parents(".parent").find(".display").addClass("hidden").find(".text").text("");
					}
					self.stopPropagation[type][extension] = false;
				}
			});
		});
	},
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;
		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=callforward] .widget-content input[type='checkbox']").change(function(e) {
			var name = $(this).prop("name"),
				nice = $(this).data("nice"),
				parent = $(this).parents("."+name),
				type = $(this).data("type"),
				checked = $(this).is(':checked'),
				extension = $(".grid-stack-item[data-id='"+widget_id+"']").data("widget_type_id"),
				widget = $(this),
				sidebar = $(".widget-extra-menu[data-module='callforward'][data-widget_type_id='"+extension+"']:visible input[data-type='"+type+"']");

			if(typeof self.stopPropagation[type][extension] !== "undefined" && self.stopPropagation[type][extension]) {
				return true;
			}

			if(!$(this).is(":checked")) {
				if(sidebar.length && sidebar.is(":checked")) {
					sidebar.bootstrapToggle('off');
					sidebar.parents("."+name).find(".display").addClass("hidden").find(".text").text("");
				}
				self.saveSettings(extension,type,"",function() {
					parent.find(".display").addClass("hidden").find(".text").text("");
				});
				return;
			}

			if(sidebar.length && !sidebar.is(":checked")) {
				sidebar.bootstrapToggle('on');
			}
			self.showDialog(this, extension, function(state, number) {
				if(state == 'off') {
					widget.bootstrapToggle('off');
					parent.find(".display").addClass("hidden").find(".text").text("");
					if(sidebar.length && sidebar.is(":checked")) {
						sidebar.bootstrapToggle('off');
						sidebar.parents("."+name).find(".display").addClass("hidden").find(".text").text("");
					}
				} else {
					if(sidebar.length) {
						sidebar.parents("."+name).find(".display").removeClass("hidden").find(".text").text(number);
					}
					parent.find(".display").removeClass("hidden").find(".text").text(number);
				}
			});
		});
	},
	showDialog: function(el, extension, callback) {
		var nice = $(el).data("nice"),
				type = $(el).data("type"),
				self = this;

		self.stopPropagation[type][extension] = true;
		UCP.showDialog(
			sprintf(_("Set Forwarding for %s"),nice),
			'<label for="cfnumber">'+_("Enter a number")+'</label><input id="cfnumber" name="cfnumber" class="form-control">',
			'<button class="btn btn-primary" id="cfsave">'+_("Save")+'</button>',
			function() {
				var value = '';
				$("#globalModal").one("hide.bs.modal", function() {
					self.stopPropagation[type][extension] = false;
					if(value === '') {
						callback('off','');
					}
				});
				$("#cfsave").click(function(e) {
					e.preventDefault();
					value = $("#cfnumber").val();
					if(value === "") {
						UCP.showAlert(_("A valid number needs to be entered"),"warning");
						return;
					}

					callback('on',value);
					self.stopPropagation[type][extension] = false;
					self.saveSettings(extension, type, value, function(data) {
						if(data.status) {
							UCP.closeDialog();
						} else {
							callback('off','');
							UCP.showAlert(data.message, 'danger');
						}
					});
				});
			}
		);
	},
	saveSettings: function(extension, type, value, callback) {
		var self = this;
		data = {
			ext: extension,
			type: type,
			module: "callforward",
			command: "settings"
		};
		if(value !== "") {
			data.value = value;
		}
		self.stopPropagation[type][extension] = true;
		$.post( UCP.ajaxUrl, data, callback).always(function() {
			self.stopPropagation[type][extension] = false;
		}).fail(function() {
			UCP.showAlert(_('An Unknown error occured'),'danger');
		});
	},
	displayWidgetSettings: function(widget_id,dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$("#cfringtimer").change(function() {
			self.saveSettings(extension, 'ringtimer', $(this).val(), function() {
				console.log("saved!");
			});
		});
	},
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] input[type='checkbox']").change(function(e) {
			var type = $(this).data("type"),
					checked = $(this).is(':checked'),
					extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
					name = $(this).prop("name"),
					parent = $(this).parents("."+name),
					el = $(".grid-stack-item[data-widget_type_id='"+extension+"'][data-rawname=callforward] .widget-content input[data-type='"+type+"']");

			if(typeof self.stopPropagation[type][extension] !== "undefined" && self.stopPropagation[type][extension]) {
				return true;
			}

			if(!checked) {
				parent.find(".display").addClass("hidden").find(".text").text("");
			}

			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				if(checked) {
					self.showDialog(this, extension, function(state, number) {
						if(state == 'on') {
							parent.find(".display").removeClass("hidden").find(".text").text(number);
						} else {
							el.bootstrapToggle('off');
							parent.find(".display").addClass("hidden").find(".text").text("");
						}
					});
				} else {
					self.saveSettings(extension, type, '', function(data) {
						if(!data.status) {
							UCP.showAlert(data.message, 'danger');
						}
					});
				}
			}
		});
	},
	displaySimpleWidgetSettings: function(widget_id) {
		this.displayWidgetSettings(widget_id);
	}
});

var CallwaitingC = UCPMC.extend({
	init: function(){
		this.stopPropagation = {};
	},
	prepoll: function() {
		var exts = [];
		$(".grid-stack-item[data-rawname=callwaiting]").each(function() {
			exts.push($(this).data("widget_type_id"));
		});
		return exts;
	},
	poll: function(data) {
		var self = this;
		$.each(data.states, function(ext,state) {
			if(typeof self.stopPropagation[ext] !== "undefined" && self.stopPropagation[ext]) {
				return true;
			}
			var widget = $(".grid-stack-item[data-rawname=callwaiting][data-widget_type_id='"+ext+"']:visible input[name='cwenable']"),
				sidebar = $(".widget-extra-menu[data-module='callwaiting'][data-widget_type_id='"+ext+"']:visible input[name='cwenable']"),
				sstate = state ? "on" : "off";
			if(widget.length && (widget.is(":checked") !== state)) {
				self.stopPropagation[extension] = true;
				widget.bootstrapToggle(sstate);
				self.stopPropagation[extension] = false;
			} else if(sidebar.length && (sidebar.is(":checked") !== state)) {
				self.stopPropagation[extension] = true;
				sidebar.bootstrapToggle(sstate);
				self.stopPropagation[extension] = false;
			}
		});
	},
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;
		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=callwaiting] .widget-content input[name='cwenable']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=callwaiting]").data("widget_type_id"),
				el = $(".widget-extra-menu[data-module='callwaiting'][data-widget_type_id='"+extension+"']:visible input[name='cwenable']"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name');
			if(el.length && el.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				el.bootstrapToggle(state);
			}
			self.saveSettings(extension, {enable: checked});
		});
	},
	saveSettings: function(extension, data, callback) {
		var self = this;
		data.ext = extension;
		data.module = "Callwaiting";
		data.command = "enable";
		this.stopPropagation[extension] = true;
		$.post( UCP.ajaxUrl, data, callback).always(function() {
			self.stopPropagation[extension] = false;
		});
	},
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='cwenable']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name'),
				el = $(".grid-stack-item[data-rawname=callwaiting][data-widget_type_id='"+extension+"']:visible input[name='cwenable']");

			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				self.saveSettings(extension, {enable: checked});
			}
		});
	}
});

var CdrC = UCPMC.extend({
	init: function() {
		this.playing = null;
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .cdr-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()-1});
	},
	poll: function(data, url) {

	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		$(".grid-stack-item[data-id='"+widget_id+"'] .cdr-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$('.grid-stack-item[data-id='+widget_id+'] .cdr-grid').on("post-body.bs.table", function () {
			self.bindPlayers(widget_id);
			$(".cdr-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
		});
	},
	formatDescription: function (value, row, index) {
		var icons = '';
		if(typeof row.icons !== "undefined") {
			$.each(row.icons, function(i, v) {
				icons += '<i class="fa '+v+'"></i> ';
			});
		}
		return icons + " " + value;
	},
	formatActions: function (value, row, index) {
		var settings = UCP.Modules.Cdr.staticsettings;
		if(row.recordingfile === '' || settings.showDownload === "0") {
			return '';
		}
		var link = '<a class="download" alt="'+_("Download")+'" href="'+UCP.ajaxUrl+'?module=cdr&amp;command=download&amp;msgid='+row.uniqueid+'&amp;type=download&amp;ext='+row.requestingExtension+'"><i class="fa fa-cloud-download"></i></a>';
		if((row.converttotext !== undefined) && row.converttotext.transcriptionURL !== undefined && row.converttotext.transcriptionURL !== null && row.converttotext.transcriptionURL != '' && settings.isScribeEnabled) {
			link += '<a href="#" class="transcript tool-tip" data-toggle="tooltip" title="Read the voice transcription" onclick="openmodal(\'' + UCP.ajaxUrl+row.converttotext.transcriptionURL + '\')"> <img src="'+row.converttotext.scribeIconURL+'" width="15px" height="15px" alt="PBX Scribe" /></a>';
		}
		return link;
	},
	formatPlayback: function (value, row, index) {
		var settings = UCP.Modules.Cdr.staticsettings,
				rand = Math.floor(Math.random() * 10000);
		if(row.recordingfile.length === 0 || settings.showPlayback === "0") {
			return '';
		}
		return '<div id="jquery_jplayer_'+row.niceUniqueid+'-'+rand+'" class="jp-jplayer" data-container="#jp_container_'+row.niceUniqueid+'-'+rand+'" data-id="'+row.uniqueid+'"></div><div id="jp_container_'+row.niceUniqueid+'-'+rand+'" data-player="jquery_jplayer_'+row.niceUniqueid+'-'+rand+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
			'<div class="jp-type-single">'+
				'<div class="jp-gui jp-interface">'+
					'<div class="jp-controls">'+
						'<i class="fa fa-play jp-play"></i>'+
						'<i class="fa fa-undo jp-restart"></i>'+
					'</div>'+
					'<div class="jp-progress">'+
						'<div class="jp-seek-bar progress">'+
							'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
							'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
							'<div class="jp-play-bar progress-bar"></div>'+
							'<div class="jp-play-bar">'+
								'<div class="jp-ball"></div>'+
							'</div>'+
							'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
						'</div>'+
					'</div>'+
					'<div class="jp-volume-controls">'+
						'<i class="fa fa-volume-up jp-mute"></i>'+
						'<i class="fa fa-volume-off jp-unmute"></i>'+
					'</div>'+
				'</div>'+
				'<div class="jp-no-solution">'+
					'<span>Update Required</span>'+
					sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"https://sangomakb.atlassian.net/wiki/spaces/FP/pages/10682566/Installing+Media+Conversion+Libraries")+
				'</div>'+
			'</div>'+
		'</div>';
	},
	formatDuration: function (value, row, index) {
		return row.niceDuration;
	},
	formatDate: function(value, row, index) {
		return UCP.dateTimeFormatter(value);
	},
	bindPlayers: function(widget_id) {
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id="+widget_id+"] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							$this.recordGreeting(type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: "index.php?quietmode=1",
								data: {module: "cdr", command: "gethtml5", id: id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.log(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										alert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var $this = this;
					$(container).find(".jp-restart").click(function() {
						if($($this).data("jPlayer").status.paused) {
							$($this).jPlayer("pause",0);
						} else {
							$($this).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: UCP.Modules.Cdr.staticsettings.supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('.jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	},
});

function openmodal(turl) {
    var result = $.ajax({
        url: turl,
        type: 'POST',
        async: false
    });
    result = JSON.parse(result.responseText);
    $("#addtionalcontent").html(result.html);
    $("#addtionalcontent").appendTo("body");
    $("#datamodal").show();
}

function closemodal() {
	$('div#addtionalcontent:not(:first)').remove();
	$("#addtionalcontent").html("");
	$("#datamodal").hide();
}

var CelC = UCPMC.extend({
	init: function() {
	},
	poll: function(data, url) {
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .cel-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()-1});
	},
	displayWidget: function(widget_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		$(".grid-stack-item[data-id='"+widget_id+"'] .cel-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'] .cel-grid").on("post-body.bs.table", function() {
			self.bindPlayers(widget_id);
		});
		$(".grid-stack-item[data-id='"+widget_id+"'] .cel-grid").on("click-cell.bs.table", function(event, field, value, row) {
			if(field == "file" || field == "controls") {
				return;
			}

			$.getJSON(UCP.ajaxUrl+'?module=cel&command=eventmodal', function(data){
				if (data.status === true){
					UCP.showDialog(_("Call Events"),
						data.message,
						'<button type="button" class="btn btn-primary" data-dismiss="modal">'+_("Close")+'</button>',
						function() {
							$("#globalModal .cel-detail-grid").bootstrapTable();
							$("#globalModal .cel-detail-grid").bootstrapTable('load', row.moreinfo);
						}
					);
				} else {
					UCP.showAlert(_("Error getting form"),'danger');
				}
			}).always(function() {
			}).fail(function() {
				UCP.showAlert(_("Error getting form"),'danger');
			});
		});
	},
	formatDuration: function (value, row, index) {
		return sprintf(_("%s seconds"),value);
	},
	formatDate: function(value, row, index) {
		return UCP.dateTimeFormatter(value);
	},
	formatControls: function (value, row, index) {
		var settings = UCP.Modules.Cel.staticsettings;
		if(typeof row.file === "undefined" || settings.showDownload === "0") {
			return '';
		}
		var links = '';
		links = '<a class="download" alt="'+_("Download")+'" href="'+UCP.ajaxUrl+'?module=cel&amp;command=download&amp;id='+encodeURIComponent(row.uniqueid)+'&amp;type=download"><i class="fa fa-cloud-download"></i></a>';
		return links;
	},
	formatPlayback: function (value, row, index) {
		var settings = UCP.Modules.Cel.staticsettings,
				rand = Math.floor(Math.random() * 10000);

		if(typeof row.file === "undefined" || settings.showPlayback === "0") {
			return '';
		}

		var recordings = [row.file];

		var html = '',
			count = 0;
		$.each(recordings, function(k, v){
			if(v === false) {
				return true;
			}
			html += '<div id="jquery_jplayer_'+index+'_'+count+'-'+rand+'" class="jp-jplayer" data-container="#jp_container_'+index+'_'+count+'-'+rand+'" data-playbackuniqueid="'+row.uniqueid+'" data-id="'+k+'"></div>'+
			'<div id="jp_container_'+index+'_'+count+'-'+rand+'" data-player="jquery_jplayer_'+index+'_'+count+'-'+rand+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
				'<div class="jp-type-single">'+
				'<div class="jp-gui jp-interface">'+
					'<div class="jp-controls">'+
						'<i class="fa fa-play jp-play"></i>'+
						'<i class="fa fa-undo jp-restart"></i>'+
					'</div>'+
					'<div class="jp-progress">'+
						'<div class="jp-seek-bar progress">'+
							'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
							'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
							'<div class="jp-play-bar progress-bar"></div>'+
							'<div class="jp-play-bar">'+
								'<div class="jp-ball"></div>'+
							'</div>'+
							'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
						'</div>'+
					'</div>'+
					'<div class="jp-volume-controls">'+
						'<i class="fa fa-volume-up jp-mute"></i>'+
						'<i class="fa fa-volume-off jp-unmute"></i>'+
					'</div>'+
				'</div>'+
				'<div class="jp-no-solution">'+
					'<span>Update Required</span>'+
					sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"http://wiki.freepbx.org/display/FOP/Installing+Media+Conversion+Libraries")+
				'</div>'+
			'</div>';
});
		return html;
	},
	bindPlayers: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					playback = $(this).data("playbackuniqueid");

			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							$this.recordGreeting(type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: "ajax.php",
								data: {module: "cel", command: "gethtml5", uniqueid: playback, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.log(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										alert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var $this = this;
					$(container).find(".jp-restart").click(function() {
						if($($this).data("jPlayer").status.paused) {
							$($this).jPlayer("pause",0);
						} else {
							$($this).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: UCP.Modules.Cel.staticsettings.supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$(".grid-stack-item[data-id='"+widget_id+"'] .jp-play-bar").mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	}
});

var ContactmanagerC = UCPMC.extend({
	init: function(UCP) {
		var cm = this;
		this.contacts = {};
		$(document).bind("staticSettingsFinished", function( event ) {
			if (cm.staticsettings.enabled) {
				cm.contacts = cm.staticsettings.contacts;
			}
		});
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .contacts-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()});
		if ($(".favorite-div .fav-tab").length) {
			setTimeout(function() {
				var elem = $(".favorite-div");
				elem.parents(".widget-content .row:first").height(elem.parents(".widget-content").prop("scrollHeight"));
				
				var padding1 = parseInt(elem.outerHeight(true) - elem.height());
				var padding2 = parseInt(elem.find(".fav-tab").outerHeight(true) - elem.find(".fav-tab").height());
				var padding3 = parseInt(elem.find(".fav-tab #users").outerHeight(true) - elem.find(".fav-tab #users").height());
				var buttonHeight = parseInt(elem.find(".fav-save-bar").outerHeight(true));
				var rowHeight = parseInt(elem.parents(".widget-content .row:first").height());
				var h = rowHeight - (padding1+padding2+padding3+buttonHeight);
	
				var h1 = 0;
				elem.find("#included_contacts > span").each(function(){
					h1 += $(this).outerHeight(true);
				});
				var h2 = 0;
				elem.find("#excluded_contacts > span").each(function(){
					h2 += $(this).outerHeight(true);
				});
				var legendHeigh = parseInt(elem.find(".fav-tab legend").outerHeight(true));
				var contactListHeight = (parseInt(h1) > parseInt(h2) ? parseInt(h1) : parseInt(h2)) + legendHeigh;
	
				h = h > contactListHeight ? contactListHeight : h;
				elem.find(".contact_list").height(parseInt(h));
			},250);
		}
	},
	groupClick: function(el, widget_id) {
		$(".contacts-div").show();
		$(".favorite-div").hide();
		$(".show-favorites").removeClass("active");
		$(".grid-stack-item[data-id="+widget_id+"] .group").removeClass("active");
		$(el).addClass("active");
		var group = $(el).data("group");

		if ($(el).data('readonly') || group.length === 0) {
			$(".grid-stack-item[data-id="+widget_id+"] .deletegroup").prop("disabled",true);
			$(".grid-stack-item[data-id="+widget_id+"] .addcontact").prop("disabled",true);
		} else {
			$(".grid-stack-item[data-id="+widget_id+"] .deletegroup").prop("disabled",false);
			$(".grid-stack-item[data-id="+widget_id+"] .addcontact").prop("disabled",false);
		}
      
      	$.ajax({
			url: UCP.ajaxUrl+'?module=contactmanager&command=grid&group=' + group,
			type: "POST",
			async: false,
			success: function(data){
				$('.grid-stack-item[data-id='+widget_id+'] .contacts-grid').bootstrapTable("refreshOptions", {url: UCP.ajaxUrl+'?module=contactmanager&command=grid&group=' + group});
			}
		});
	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this;

		$(".grid-stack-item[data-id='"+widget_id+"'] .contacts-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'] .group").click(function() {
			self.groupClick(this, widget_id);
		});

		$('.grid-stack-item[data-id='+widget_id+'] .contacts-grid').on('click-row.bs.table', function (e, row, $element, field) {
			$.post(UCP.ajaxUrl, {
				module: "contactmanager",
				command: "showcontact",
				group: row.groupid,
				id: row.uid
			}, function(data) {
				if(data.status) {
					UCP.showDialog(data.title,
						data.body,
						data.footer,
						function() {
							$("#globalModal .clickable").click(function(e) {
								var type = $(this).data("type"),
										text = $(this).text(),
										primary = $(this).data("primary");
								self.showActionDialog(type, text, primary);
							});
							$("#deletecontact").click(function() {
								$("#deletecontact").prop("disabled",true);
								UCP.showConfirm(_("Are you sure you wish to delete this contact?"), 'info', function() {
									$.post( UCP.ajaxUrl, {
										module: "contactmanager",
										command: "deletecontact",
										id: row.uid
									}, function( data ) {
										if (data.status) {
											$('.grid-stack-item[data-id='+widget_id+'] .contacts-grid').bootstrapTable("refreshOptions", {url: UCP.ajaxUrl+'?module=contactmanager&command=grid&group=' + group});
											UCP.closeDialog();
										} else {
											UCP.showAlert(_("Error deleting user"),'danger');
										}
									});
								});
								$("#deletecontact").prop("disabled",false);
							});
							$("#editcontact").click(function() {
								$.getJSON(UCP.ajaxUrl, {
									module: "contactmanager",
									command: "editcontactmodal",
									group: row.groupid,
									id: row.uid
								}, function(data){
									if (data.status === true){
										UCP.showDialog(_("Edit Contact"),
											data.message,
											'<button type="button" class="btn btn-secondary" data-dismiss="modal">'+_("Close")+'</button><button id="save" type="button" class="btn btn-primary">'+ _("Save changes")+'</button>',
											function() {
												self.displayEditContact(widget_id);
											}
										);
									} else {
										UCP.showAlert(_("Error getting form"),'danger');
									}
								}).always(function() {
								}).fail(function() {
									UCP.showAlert(_("Error getting form"),'danger');
								});
							});
						}
					);
				}
			});
		});

		$(".grid-stack-item[data-id='"+widget_id+"'] .addgroup").click(function() {
			$.getJSON(UCP.ajaxUrl+'?module=contactmanager&command=addgroupmodal', function(data){
				if (data.status === true){
					UCP.showDialog(_("Add Group"),
						data.message,
						'<button type="button" class="btn btn-secondary" data-dismiss="modal">'+_("Close")+'</button><button type="button" class="btn btn-primary" id="save">'+ _("Save changes")+'</button>',
						function() {
							$("#groupname").focus();
							$('#contactmanager-addgroup').submit(function() {
								$('#save').click();
								return false;
							});
							$('#save').one('click',function() {
								$.ajax({
									type: 'POST',
									url: UCP.ajaxUrl+'?module=contactmanager&command=addgroup',
									data: $('#contactmanager-addgroup').serialize(),
									success: function (data) {
										if (data.status === true) {
											$(".grid-stack-item[data-id='"+widget_id+"'] .group-list").append('<div class="group" data-name="' + $("#groupname").val() + '" data-group="' + data.id + '" data-readonly="false"><a href="#" class="group-inner">' + $("#groupname").val() + '<span class="badge">0</span></a></div>');
											$(".grid-stack-item[data-id='"+widget_id+"'] .group[data-group=" + data.id + "]").click(function() {
												self.groupClick(this, widget_id);
											});
											UCP.closeDialog();
										} else {
											UCP.showAlert(data.message,'danger');
										}
									}
								});
							});
						});
				} else {
					UCP.showDialog(_("Add Group"),_("Error getting form"),'<button type="button" class="btn btn-secondary" data-dismiss="modal">'+_("Close"));
				}
			});
		});

		$(".show-favorites").click(function() {
			$.getJSON(UCP.ajaxUrl+'?module=contactmanager&command=favorite_contacts', function(data) {
				if (data.status === true) {
					var elem = $(".favorite-div");
					elem.parents(".widget-content .row:first").height(elem.parents(".widget-content").prop("scrollHeight"));
					var h = parseInt(elem.parents(".widget-content .row:first").height());
					$(".favorite-div").html(data.body);
					$("#widget_content_height").val(h);
					$("#fav_contact_count").text(data.favoriteContactsCount);
					$(".grid-stack-item .group").removeClass("active");
					$(".show-favorites").addClass("active");
					$(".contacts-div").hide();
					$(".favorite-div").show();
				} else {
					UCP.showAlert(_("There was an error loading favorite contacts"),"danger");
				}
			});
		});

		$(".grid-stack-item[data-id="+widget_id+"] .deletegroup").click(function(e) {
			e.preventDefault();
			UCP.showConfirm(_("Are you sure you want to delete this group and all of its contacts?"), 'info', function() {
				var group = $(".grid-stack-item[data-id='"+widget_id+"'] .group-list .group.active").data("group");

				$.post( UCP.ajaxUrl+"?module=contactmanager&command=deletegroup", { id: group }, function( data ) {
					if (data.status) {
						$(".group[data-group='']").trigger("click");
						$(".grid-stack-item[data-id='"+widget_id+"'] .group-list .group[data-group='" + group + "']").remove();
					}
				}).fail(function() {
					UCP.showAlert(_("There was an error removing this group"),"danger");
				});
			});
		});

		$(".grid-stack-item[data-id="+widget_id+"] .addcontact").click(function(e) {
			e.preventDefault();

			var $this = this;

			$($this).prop("disabled",true);

			$.getJSON(UCP.ajaxUrl+'?module=contactmanager&command=addcontactmodal', function(data){
				if (data.status === true){
					UCP.showDialog(_("Add Contact"),
						data.message,
						'<button type="button" class="btn btn-secondary" data-dismiss="modal">'+_("Close")+'</button><button id="save" type="button" class="btn btn-primary">'+ _("Save changes")+'</button>',
						function() {
							self.displayEditContact(widget_id);
						}
					);
				} else {
					UCP.showAlert(_("Error getting form"),'danger');
				}
			}).always(function() {
				$($this).prop("disabled",false);
			}).fail(function() {
				UCP.showAlert(_("Error getting form"),'danger');
			});
		});
	},
	poll: function(data) {
		var cm = this;
		if (data.enabled) {
			cm.contacts = data.contacts;
		}
	},
	contactClickInitiateCallTo: function(did) {
		window.location.replace("tel:" + did);
	},
	contactClickInitiateFacetime: function(did) {
		window.location.replace("facetime:" + did);
	},
	contactClickOptions: function(type) {
		if (type != "number" || false) {
			return false;
		}
		var options = [ { text: _("Call To"), function: "contactClickInitiateCallTo", type: "phone" }];
		if (navigator.appVersion.indexOf("Mac")!=-1) {
			options.push({ text: _("Facetime"), function: "contactClickInitiateFacetime", type: "phone" });
		}
		return options;
	},
	showActionDialog: function(type, text, p) {
		var options = "", count = 0, operation = [], primary = "";
		if (typeof type === "undefined" || typeof text === "undefined" ) {
			return;
		}

		primary = (typeof p !== "undefined") ? p : "";
		if(primary.indexOf(",") !=-1) {
			var primaries = primary.split(",");
		}
		if (type == "number") {
			text = text.replace(/\D/g, "");
		}
		$.each(modules, function( index, module ) {
			if (UCP.validMethod(module, "contactClickOptions")) {
				var o = UCP.Modules[module].contactClickOptions(type), selected = "";
				if (o !== false && Array.isArray(o)) {
					$.each(o, function(k, v) {
						if(typeof primaries !== "undefined") {
							if (primaries.indexOf(v.type) !=-1) {
								if(primaries.indexOf(v.type) === 0) {
									options = "<option data-function='" + v.function + "' data-module='" + module + "' " + selected + ">" + v.text + "</option>" + options;
								} else {
									options = options + "<option data-function='" + v.function + "' data-module='" + module + "' " + selected + ">" + v.text + "</option>";
								}
								v.module = module;
								operation = v;
								count++;
							}
						} else {
							if ((typeof v.type !== "undefined") && (v.type == primary)) {
								options = "<option data-function='" + v.function + "' data-module='" + module + "' " + selected + ">" + v.text + "</option>" + options;
								v.module = module;
								operation = v;
								count++;
							}
						}
					});
				}
			}
		});

		if (count === 0) {
			alert(_("There are no actions for this type"));
		} else if (count === 1) {
			if (UCP.validMethod(operation.module, operation.function)) {
				UCP.Modules[operation.module][operation.function](text);
			}
		} else if (count > 1) {
			UCP.showDialog(_("Select an Action"),
				"<select id=\"contactmanageraction\" class=\"form-control\">" + options + "</select>",
				"<button class=\"btn btn-default\" id=\"initiateaction\" style=\"margin-left: 72px;\">"+_("Initiate")+"</button>",
				function() {
					$("#initiateaction").click(function() {
						var func = $("#contactmanageraction option:selected").data("function"),
						mod = $("#contactmanageraction option:selected").data("module");
						if (UCP.validMethod(mod, func)) {
							UCP.closeDialog(function() {
								UCP.Modules[mod][func](text);
							});
						} else {
							alert(_("Function call does not exist!"));
						}
					});
				}
			);
		}
	},
	displayEditContact: function(widget_id) {
		$('#globalModal input[type=checkbox][data-toggle="toggle"]:visible').bootstrapToggle();
		$("#globalModal").on("blur", "input.number-sd", function(e) {
			var orig = $(this).data("orig"),
				val = $(this).val(),
				$this = $(this),
				entry = null;

			orig = (typeof orig !== "undefined") ? orig : "";

			if(val !== "") {
				var indexes = [];
				var stop = false;
				$(".number-sd").each(function() {
					if($(this).val() === "") {
						return true;
					}
					if($.inArray(val, indexes) > -1) {
						UCP.showAlert(_("This speed dial id conflicts with another speed dial on this page"),'warning');
						$this.val(orig);
						stop = true;
						return false;
					}
					indexes.push($(this).val());
				});
				if(stop) {
					return false;
				}
				$.post( UCP.ajaxUrl + "?module=contactmanager&command=checksd", {id: val, entryid: entry}, function( data ) {
					if(!data.status) {
						UCP.showAlert(_("This speed dial id conflicts with another contact"),'warning');
						$this.val(orig);
					} else {
						$this.data("value",val);
					}
				});
			} else {
				$this.data("value",val);
			}
		});
		$('#save').on('click',function() {
			var data = {
				id: $("#id").val(),
				displayname: $("#displayname").val(),
				fname: $("#fname").val(),
				lname: $("#lname").val(),
				title: $("#title").val(),
				company: $("#company").val(),
				numbers: [],
				xmpps: [],
				emails: [],
				websites: [],
				image:$("#contactmanager_image").val()
			};
			$("input[data-name=number]").each(function() {
				var val = $(this).val(),
						parent = $(this).parents(".form-inline"),
						type = parent.find("select[data-name=type]").val(),
						sms = parent.find("input[data-name=smsflag]").is(":checked"),
						fax = parent.find("input[data-name=faxflag]").is(":checked"),
						locale = parent.find("select[data-name=locale]").val(),
						flags = [],
						speeddial = '';
				if(val === "") {
					return true;
				}
				if(parent.find("input[data-name=numbersd]:enabled").length) {
					speeddial = parent.find("input[data-name=numbersd]:enabled").val();
				}

				if(sms) {
					flags.push('sms')
				}

				if(fax) {
					flags.push('fax')
				}

				data.numbers.push({
					number: val,
					type: type,
					flags: flags,
					speeddial: speeddial,
					locale: locale
				});
			});
			$("input[data-name=websites], input[data-name=emails], input[data-name=xmpps]").each(function() {
				var val = $(this).val(),
						name = $(this).data("name"),
						type = $(this).data("type");
				if(val === "") {
					return true;
				}
				var obj = {};
				obj[type] = val;
				data[name].push(obj);
			});

			var group = $(".grid-stack-item[data-id='"+widget_id+"'] .group-list .group.active").data("group");

			var params = {
				module: "contactmanager",
				command: (data.id === "" ? "addcontact" : "updatecontact"),
				group: group,
				contact: data
			};

			$.post({
				url: UCP.ajaxUrl,
				data: params,
				success: function (data) {
					if(data.status) {
						$(".grid-stack-item[data-id='"+widget_id+"'] .contacts-grid").bootstrapTable("refreshOptions", {url: UCP.ajaxUrl+'?module=contactmanager&command=grid&group=' + group});
						UCP.closeDialog();
					} else {
						UCP.showAlert(data.message, 'danger');
					}
				}
			}).fail(function() {
				UCP.showAlert(_("There was an error"), 'danger');
			});
		});
		var changeSpeedDial = function() {
			var el = $(this).parents(".input-group").find(".number-sd");
			el.prop("disabled",!$(this).is(":checked"));
			if(!$(this).is(":checked")) {
				el.val("");
			} else {
				if(typeof el.data("value") !== "undefined") {
					el.val(el.data("value"));
				}
			}
		};
		$(".enable-sd").change(changeSpeedDial);
		$(".add-additional").click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			var name = $(this).data("type"),
					container = $("input[data-name="+name+"]").one().parents(".item-container").first();

			if(name === "number") {
				$('#globalModal input[data-name=smsflag], #globalModal input[data-name=faxflag]').bootstrapToggle('destroy');
			}
			var html = container.clone();
			html.find("input").val("");
			var cmlocale = navigator.language.split('-')[1];
			cmlocale = cmlocale ? cmlocale : navigator.language.split('-')[0]
			html.find("select[data-name=locale]").val(cmlocale)
			container.after(html);
			if(name === "number") {
				$('#globalModal input[data-name=smsflag], #globalModal input[data-name=faxflag]').bootstrapToggle();
			}
			$(".enable-sd").off("change");
			$(".enable-sd").change(changeSpeedDial);

		});
		$(document).on("click",".item-container .delete",function() {
			var name = $(this).data("type");
			if($("input[data-name="+name+"]").length === 1) {
				$("input[data-name="+name+"]").val("");
				if(name == "number") {
					$("input[data-name=smsflag]").bootstrapToggle("off");
					$("input[data-name=faxflag]").bootstrapToggle("off");
				}
			} else {
				$(this).parents(".item-container").remove();
			}

		});
		$('#contactmanager_dropzone').on('drop dragover', function (e) {
			e.preventDefault();
		});
		$('#contactmanager_dropzone').on('dragleave drop', function (e) {
			$(this).removeClass("activate");
		});
		$('#contactmanager_dropzone').on('dragover', function (e) {
			$(this).addClass("activate");
		});
		var supportedRegExp = "png|jpg|jpeg";
		$( document ).ready(function() {
			$('#contactmanager_imageupload').fileupload({
				dataType: 'json',
				dropZone: $("#contactmanager_dropzone"),
				add: function (e, data) {
					//TODO: Need to check all supported formats
					var sup = "\.("+supportedRegExp+")$",
							patt = new RegExp(sup),
							submit = true;
					$.each(data.files, function(k, v) {
						if(!patt.test(v.name.toLowerCase())) {
							submit = false;
							alert(_("Unsupported file type"));
							return false;
						}
					});
					if(submit) {
						$("#contactmanager_upload-progress .progress-bar").addClass("progress-bar-striped active");
						data.submit();
					}
				},
				drop: function () {
					$("#contactmanager_upload-progress .progress-bar").css("width", "0%");
				},
				dragover: function (e, data) {
				},
				change: function (e, data) {
				},
				done: function (e, data) {
					$("#contactmanager_upload-progress .progress-bar").removeClass("progress-bar-striped active");
					$("#contactmanager_upload-progress .progress-bar").css("width", "0%");

					if(data.result.status) {
						$("#contactmanager_dropzone img").attr("src",data.result.url);
						$("#contactmanager_image").val(data.result.filename);
						$("#contactmanager_dropzone img").removeClass("hidden");
						$("#contactmanager_del-image").removeClass("hidden");
						$("#contactmanager_gravatar").prop('checked', false);
					} else {
						alert(data.result.message);
					}
				},
				progressall: function (e, data) {
					var progress = parseInt(data.loaded / data.total * 100, 10);
					$("#contactmanager_upload-progress .progress-bar").css("width", progress+"%");
				},
				fail: function (e, data) {
				},
				always: function (e, data) {
				}
			});

			$("#contactmanager_del-image").click(function(e) {
				e.preventDefault();
				e.stopPropagation();
				var grouptype = 'external';
				$.post( "?quietmode=1&module=Contactmanager&type=contact&command=delimage", {id: $("#id").val(), grouptype: grouptype, img: $("#contactmanager_image").val()}, function( data ) {
					if(data.status) {
						$("#contactmanager_image").val("");
						$("#contactmanager_dropzone img").addClass("hidden");
						$("#contactmanager_dropzone img").attr("src","");
						$("#contactmanager_del-image").addClass("hidden");
						$("#contactmanager_gravatar").prop('checked', false);
					}
				});
			});

			$("#contactmanager_gravatar").change(function() {
				if($(this).is(":checked")) {
					var grouptype = 'external';
					if($("#email").val() === "") {
						alert(_("No email defined"));
						$("#contactmanager_gravatar").prop('checked', false);
						return;
					}
					var t = $("label[for=contactmanager_gravatar]").text();
					$("label[for=contactmanager_gravatar]").text(_("Loading..."));
					$.post( "?quietmode=1&module=Contactmanager&type=contact&command=getgravatar", {id: $("#id").val(), grouptype: grouptype, email: $("input[data-name=emails]:visible").one().val()}, function( data ) {
						$("label[for=contactmanager_gravatar]").text(t);
						if(data.status) {
							$("#contactmanager_dropzone img").data("oldsrc",$("#dropzone img").attr("src"));
							$("#contactmanager_dropzone img").attr("src",data.url);
							$("#contactmanager_image").data("old",$("#image").val());
							$("#contactmanager_image").val(data.filename);
							$("#contactmanager_dropzone img").removeClass("hidden");
							$("#contactmanager_del-image").removeClass("hidden");
						} else {
							alert(data.message);
							$("#contactmanager_gravatar").prop('checked', false);
						}
					});
				} else {
					var oldsrc = $("#contactmanager_dropzone img").data("oldsrc");
					if(typeof oldsrc !== "undefined" && oldsrc !== "") {
						$("#contactmanager_dropzone img").attr("src",oldsrc);
						$("#contactmanager_image").val($("#image").data("old"));
					} else {
						$("#contactmanager_image").val("");
						$("#contactmanager_dropzone img").addClass("hidden");
						$("#contactmanager_dropzone img").attr("src","");
						$("#contactmanager_del-image").addClass("hidden");
					}
				}
			});
		});
	},
	/**
	 * Lookup a contact from the directory
	 * @param  {string} search The string to look for
	 * @param  {object} regExp The regular expression object (make sure /g is on the end)
	 * @return {string} replaced value
	 */
	lookup: function(search, regExp) {
		var o = this.recursiveObjectSearch(search, this.contacts), contact;
		if (o !== false) {
			contact = this.contacts[o[0]];
			if (contact !== false) {
				contact.ignore = o[0];
				contact.key = o[o.length - 1];
			}
			return contact;
		}
		return false;
	},
	recursiveObjectSearch: function(search, haystack, key, strict, stack) {
		var k, o, pattern = new RegExp(search);
		for (k in haystack) {
			if (haystack.hasOwnProperty(k) && haystack[k] !== null) {
				if (typeof stack === "undefined") {
					stack = [];
				}
				if (typeof haystack[k] === "object") {
					stack.push(k);
					o = this.recursiveObjectSearch(search, haystack[k], key, strict, stack);
					if (o !== false) {
						return stack;
					} else {
						stack = [];
					}
				} else if (pattern.test(haystack[k])) {
					stack.push(k);
					return stack;
				}
			}
		}
		return false;
	}
});

var obj = new UCPC();
$(document).on("click", "#save_favorites", function () {
	var included_contacts = [];
	$('#included_contacts>span').each(function() {
		included_contacts.push($(this).attr('data-contactId'));
	});
	$.ajax({
		url: 'ajax.php?module=contactmanager&command=update_favorite_contacts',
		type: "POST",
		data: {'included_contacts': included_contacts},
		success: function(data){
			$("#fav_contact_count").text(data.favoriteContactsCount);
			obj.showAlert(data.message, 'success');
		}
	});
})
var DonotdisturbC = UCPMC.extend({
	init: function(){
		this.stopPropagation = {};
	},
	prepoll: function() {
		var exts = [];
		$(".grid-stack-item[data-rawname=donotdisturb]").each(function() {
			exts.push($(this).data("widget_type_id"));
		});
		return exts;
	},
	poll: function(data) {
		var self = this;
		$.each(data.states, function(ext,state) {
			if(typeof self.stopPropagation[ext] !== "undefined" && self.stopPropagation[ext]) {
				return true;
			}
			var widget = $(".grid-stack-item[data-rawname=donotdisturb][data-widget_type_id='"+ext+"']:visible input[name='dndenable']"),
				sidebar = $(".widget-extra-menu[data-module='donotdisturb'][data-widget_type_id='"+ext+"']:visible input[name='dndenable']"),
				sstate = state ? "on" : "off";
			if(widget.length && (widget.is(":checked") !== state)) {
				self.stopPropagation[ext] = true;
				widget.bootstrapToggle(sstate);
				self.stopPropagation[ext] = false;
			} else if(sidebar.length && (sidebar.is(":checked") !== state)) {
				self.stopPropagation[ext] = true;
				sidebar.bootstrapToggle(sstate);
				self.stopPropagation[ext] = false;
			}
		});
	},
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;
		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=donotdisturb] .widget-content input[name='dndenable']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=donotdisturb]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='donotdisturb'][data-widget_type_id='"+extension+"']:visible input[name='dndenable']"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name');
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			self.saveSettings(extension, {enable: checked});
		});
	},
	saveSettings: function(extension, data, callback) {
		var self = this;
		data.ext = extension;
		data.module = "donotdisturb";
		data.command = "enable";
		this.stopPropagation[extension] = true;
		$.post( UCP.ajaxUrl, data, callback).always(function() {
			self.stopPropagation[extension] = false;
		});
	},
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='dndenable']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name'),
				el = $(".grid-stack-item[data-rawname=donotdisturb][data-widget_type_id='"+extension+"']:visible input[name='dndenable']");

			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				self.saveSettings(extension, {enable: checked});
			}
		});
	}
});

var FindmefollowC = UCPMC.extend({
	init: function(){
		this.stopPropagation = {};
	},
	prepoll: function() {
		var exts = [];
		$(".grid-stack-item[data-rawname=findmefollow]").each(function() {
			exts.push($(this).data("widget_type_id"));
		});
		return exts;
	},
	poll: function(data) {
		var self = this;
		$.each(data.states, function(ext,state) {
			if(typeof self.stopPropagation[ext] !== "undefined" && self.stopPropagation[ext]) {
				return true;
			}
			var widget = $(".grid-stack-item[data-rawname=findmefollow][data-widget_type_id='"+ext+"']:visible input[name='ddial']"),
				sidebar = $(".widget-extra-menu[data-module='findmefollow'][data-widget_type_id='"+ext+"']:visible input[name='ddial']"),
				sstate = state ? "on" : "off";
			if(widget.length && (widget.is(":checked") !== state)) {
				widget.bootstrapToggle(sstate);
			} else if(sidebar.length && (sidebar.is(":checked") !== state)) {
				sidebar.bootstrapToggle(sstate);
			}
		});
	},
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;
		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=findmefollow] .widget-content input[name='ddial']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=findmefollow]").data("widget_type_id"),
				el = $(".widget-extra-menu[data-module='findmefollow'][data-widget_type_id='"+extension+"']:visible input[name='ddial']"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name');
			if(el.length && el.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				el.bootstrapToggle(state);
			}
			self.saveSettings(extension, {key: name, value: checked});
		});
	},
	saveSettings: function(extension, data, callback) {
		var self = this;
		data.ext = extension;
		data.module = "findmefollow";
		data.command = "settings";
		this.stopPropagation[extension] = true;
		$.post(UCP.ajaxUrl, data, callback).always(function() {
			self.stopPropagation[extension] = false;
		});
	},
	displayWidgetSettings: function(widget_id,dashboard_id) {
		var self = this;
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		$("#widget_settings .widget-settings-content textarea").blur(function() {
			self.saveSettings(extension, {key: $(this).prop('name'), value: $(this).val()});
		});
		$("#widget_settings .widget-settings-content select").change(function() {
			self.saveSettings(extension, {key: $(this).prop('name'), value: $(this).val()});
		});
		$("#widget_settings .widget-settings-content input[type='checkbox']").change(function() {
			self.saveSettings(extension, {key: $(this).prop('name'), value: $(this).is(':checked')});
		});
	},
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='ddial']").change(function() {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				name = $(this).prop('name'),
				el = $(".grid-stack-item[data-rawname=findmefollow][data-widget_type_id='"+extension+"']:visible input[name='ddial']");

			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				self.saveSettings(extension, {key: name, value: checked});
			}
		});
	},
	displaySimpleWidgetSettings: function(widget_id) {
		this.displayWidgetSettings(widget_id);
	}
});

var HomeC = UCPMC.extend({
	init: function() {
		this.packery = false;
		this.doit = null;
	},
	poll: function(data) {
		//console.log(data)
	},
	display: function(event) {
		$(window).on("resize.Home", this.resize);
		this.resize();
	},
	hide: function(event) {
		$(window).off("resize.Home");
		//$(".masonry-container").packery("destroy");
		this.packery = false;
	},
	contactClickOptions: function(type) {
		if (type != "number" || !UCP.Modules.Home.staticsettings.enableOriginate) {
			return false;
		}
		return [ { text: _("Originate Call"), function: "contactClickInitiate", type: "phone" } ];
	},
	contactClickInitiate: function(did) {
		var Webrtc = this,
				sfrom = "",
				temp = "",
				name = did,
				selected = "";
		if (UCP.validMethod("Contactmanager", "lookup")) {
			if (typeof UCP.Modules.Contactmanager.lookup(did).displayname !== "undefined") {
				name = UCP.Modules.Contactmanager.lookup(did).displayname;
			} else {
				temp = String(did).length == 11 ? String(did).substring(1) : did;
				if (typeof UCP.Modules.Contactmanager.lookup(temp).displayname !== "undefined") {
					name = UCP.Modules.Contactmanager.lookup(temp).displayname;
				}
			}
		}
		$.each(UCP.Modules.Home.staticsettings.extensions, function(i, v) {
			sfrom = sfrom + "<option>" + v + "</option>";
		});

		selected = "<option value=\"" + did + "\" selected>" + name + "</option>";
			UCP.showDialog(_("Originate Call"),
			"<label for=\"originateFrom\">From:</label><select id=\"originateFrom\" class=\"form-control\">" + sfrom + "</select><label for=\"originateTo\">To:</label><select class=\"form-control\" id=\"originateTo\" data-toggle=\"select\" data-size=\"auto\">" + selected + "</select>",
			"<button class=\"btn btn-primary text-center\" id=\"originateCall\" style=\"margin-left: 72px;\">" + _("Originate") + "</button>",
			function() {
				$("#originateCall").click(function() {
					setTimeout(function() {
						UCP.Modules.Home.originate();
					}, 50);
				});
				$("#originateTo").keypress(function(event) {
					if (event.keyCode == 13) {
						setTimeout(function() {
							UCP.Modules.Home.originate();
						}, 50);
					}
				});
			}
		);
	},
	refresh: function(module, id) {
		$("#"  +  module  +  "-title-"  +  id + " i.fa-refresh").addClass("fa-spin");
		$.post( "?quietmode=1&module=" + module + "&command=homeRefresh&id=" + id, {}, function( data ) {
			$("#" + module + "-title-" + id + " i.fa-refresh").removeClass("fa-spin");
			$("#" + module + "-content-" + id).html(data.content);
		});
	},
	originate: function() {
		if ($("#originateTo").val() !== null && $("#originateTo").val()[0] === "") {
			alert(_("Nothing Entered"));
			return;
		}
		$.post( "index.php?quietmode=1&module=home&command=originate",
						{ from: $("#originateFrom").val(),
						to: $("#originateTo").val() },
						function( data ) {
							if (data.status) {
								UCP.closeDialog();
							}
						}
		)
		.fail(function(xhr, status, error) {
			alert(status +" "+ error);
		});
	},
	resize: function() {
		return;
		var wasPackeryEnabled = this.packery;
		this.packery = $(window).width() >= 768;
		if (this.packery !== wasPackeryEnabled) {
			if (this.packery) {
				clearTimeout(this.doit);
				this.doit = setTimeout(function() {
					$(".widget").css("width", "33.33%");
					$(".widget").css("margin-bottom", "");
					$(".masonry-container").packery({
						columnWidth: 40,
						gutter: 10,
						itemSelector: ".widget"
					});
				}, 100);
			} else {
				this.packery = false;
				$(".masonry-container").packery("destroy");
				$(".widget").css("width", "100%");
				$(".widget").css("margin-bottom", "10px");
			}
		} else if (!this.packery) {
			$(".widget").css("width", "100%");
			$(".widget").css("margin-bottom", "10px");
		}
	}
});

$(document).bind("logIn", function( event ) {
	$("#settings-menu a.originate").on("click", function() {
		var sfrom = "";
		$.each(UCP.Modules.Home.staticsettings.extensions, function(i, v) {
			sfrom = sfrom + "<option>" + v + "</option>";
		});

		UCP.showDialog(_("Originate Call"),
			"<label for=\"originateFrom\">From:</label> <select id=\"originateFrom\" class=\"form-control\">" + sfrom + "</select><label for=\"originateTo\">To:</label><select class=\"form-control Tokenize Fill\" id=\"originateTo\" multiple></select><button class=\"btn btn-default\" id=\"originateCall\" style=\"margin-left: 72px;\">" + _("Originate") + "</button>",
			200,
			250,
			function() {
				$("#originateTo").tokenize({ maxElements: 1, datas: "index.php?quietmode=1&module=home&command=contacts" });
				$("#originateCall").click(function() {
					setTimeout(function() {
						UCP.Modules.Home.originate();
					}, 50);
				});
				$("#originateTo").keypress(function(event) {
					if (event.keyCode == 13) {
						setTimeout(function() {
							UCP.Modules.Home.originate();
						}, 50);
					}
				});
			}
		);
	});
});

var MissedcallC = UCPMC.extend({
	/**
	 * This function is similar to PHP's __construct
	 * class variables are declared in this method using 'this.variable'
	 */
	init: function(){
		this.socket = null;
		this.stopPropagation = {};
	},
	/**
	 * Display Widget
	 * This method is executed when the side bar widget has finished loading.
	 * @method displayWidget
	 * @link https://wiki.freepbx.org/pages/viewpage.action?pageId=71271742#DevelopingforUCP14+-displayWidget
	 * @param  {string}      widget_id    The widget ID on the dashboard
	 * @param  {string}      dashboard_id The dashboard ID the widget has been placed on
	 */
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;
		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall] .widget-content input[name='notification']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='"+extension+"']:visible input[name='notification']"),
				checked = $(this).is(':checked'),
				data = {};
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			data["notification"] = checked ? 1 : 0;
			self.saveSettings(data);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall] .widget-content input[name='internal']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='"+extension+"']:visible input[name='internal']"),
				checked = $(this).is(':checked'),
				data = {};
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			data["internal"] = checked ? 1 : 0;
			self.saveSettings(data);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall] .widget-content input[name='external']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='"+extension+"']:visible input[name='external']"),
				checked = $(this).is(':checked'),
				data = {};
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			data["external"] = checked ? 1 : 0;
			self.saveSettings(data);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall] .widget-content input[name='ringgroup']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='"+extension+"']:visible input[name='ringgroup']"),
				checked = $(this).is(':checked'),
				data = {};
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			data["ringgroup"] = checked ? 1 : 0;
			self.saveSettings(data);
		});

		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall] .widget-content input[name='queue']").change(function() {
			var extension = $(".grid-stack-item[data-id='"+widget_id+"'][data-rawname=missedcall]").data("widget_type_id"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='"+extension+"']:visible input[name='queue']"),
				checked = $(this).is(':checked'),
				data = {};
			if(sidebar.length && sidebar.is(":checked") !== checked) {
				var state = checked ? "on" : "off";
				sidebar.bootstrapToggle(state);
			}
			data["queue"] = checked ? 1 : 0;
			self.saveSettings(data);
		});
	},
	/**
	 * Display Side Bar Widget
	 * This method is executed after the side bar widget has been clicked and the window has fully extended has finished loading.
	 * @method displaySimpleWidget
	 * @link https://wiki.freepbx.org/pages/viewpage.action?pageId=71271742#DevelopingforUCP14+-displaySimpleWidget
	 * @param  {string}            widget_id The widget id in the sidebar
	 */
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='enable']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				el = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='"+extension+"']:visible input[name='enable']"),
				data = {};
			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				data["enable"] = checked ? 1 : 0;
				self.saveSettings(data);
			}
		});

		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='internal']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				el = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='"+extension+"']:visible input[name='internal']"),
				data = {};
			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				data["internal"] = checked ? 1 : 0;
				self.saveSettings(data);
			}
		});

		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='external']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				el = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='"+extension+"']:visible input[name='external']"),
				data = {};
			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				data["external"] = checked ? 1 : 0;
				self.saveSettings(data);
			}
		});

		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='ringgroup']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				el = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='"+extension+"']:visible input[name='ringgroup']"),
				data = {};
			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				data["ringgroup"] = checked ? 1 : 0;
				self.saveSettings(data);
			}
		});

		$(".widget-extra-menu[data-id='"+widget_id+"'] input[name='queue']").change(function(e) {
			var extension = $(".widget-extra-menu[data-id='"+widget_id+"']").data("widget_type_id"),
				checked = $(this).is(':checked'),
				el = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='"+extension+"']:visible input[name='queue']"),
				data = {};
			if(el.length) {
				if(el.is(":checked") !== checked) {
					var state = checked ? "on" : "off";
					el.bootstrapToggle(state);
				}
			} else {
				data["queue"] = checked ? 1 : 0;
				self.saveSettings(data);
			}
		});
	},
	/**
	 * Pre Poll (Before the poll)
	 * This method is used to populate data to send to the PHP poll function for this module
	 * @method prepoll
	 * @link https://wiki.freepbx.org/pages/viewpage.action?pageId=71271742#DevelopingforUCP14+-prepoll
	 * @return  {mixed}      Data to send back to the PHP poll function for this module
	 */
	prepoll: function() {
		var exts = [];
		$(".grid-stack-item[data-rawname=missedcall]").each(function() {
			exts.push($(this).data("widget_type_id"));
		});
		return exts;
	},
	/**
	 * Poll
	 * This method is used to process data returned from the PHP poll function for this module
	 * @method poll
	 * @link https://wiki.freepbx.org/pages/viewpage.action?pageId=71271742#DevelopingforUCP14+-poll(Javascript)
	 * @param  {mixed}      data    Data returned from the PHP poll function for this module
	 */
	poll: function(data){
		var self = this;
		$.each(data.mc, function(id, value){
			if(typeof self.stopPropagation["missedcall"] !== "undefined" && self.stopPropagation["missedcall"]) {
				return true;
			}

			checked = (value == "1") ? true : false;
			var widget = $(".grid-stack-item[data-rawname=missedcall][data-widget_type_id='missedcall']:visible input[name='"+id+"']"),
				sidebar = $(".widget-extra-menu[data-module='missedcall'][data-widget_type_id='missedcall']:visible input[name='"+id+"']"),
				sstate = value == "1" ? "on" : "off";
			if(widget.length && (widget.is(":checked") !== (value == "1") ? true : false)) {
				self.stopPropagation["missedcall"] = true;
				widget.bootstrapToggle(sstate);
				self.stopPropagation["missedcall"] = false;
			} else if(sidebar.length && (sidebar.is(":checked") !== (value == "1") ? true : false)) {
				self.stopPropagation["missedcall"] = true;
				sidebar.bootstrapToggle(sstate);
				self.stopPropagation["missedcall"] = false;
			}
		});
	},
	/**
	 * saveSettings
	 * @param {array} data 
	 * @param {array} callback 
	 */
	saveSettings: function(data) {
		var self = this;
		data.module = "missedcall";
		data.command = "mcsave";
		this.stopPropagation["missedcall"] = true;
		$.post( UCP.ajaxUrl, data).always(function() {
			self.stopPropagation["missedcall"] = false;
		});
	},
});

var PresencestateC = UCPMC.extend({
	init: function() {
		this.presenceStates = {};
		this.presenceSpecials = { startSessionStatus: null, endSessionStatus: null };
		this.menu = null;
	},
	poll: function(data) {
		if (data.status) {
			this.menu = data.menu;
			this.statusUpdate(data.presence.State, data.presence.Message);
		}
	},
	displayWidget: function(widget_id,dashboard_id) {
		var self = this;

		$(".grid-stack-item[data-id='"+widget_id+"'][data-rawname='presencestate'] select[name='status']").change(function() {
			var selected = $(this).find("option:selected");
			if (selected !== null) {
				id = $(selected).data('id');

				self.saveState(id);
			}
		});
	},
	displayWidgetSettings: function(widget_id, dashboard_id) {
		var self = this;

		/* Settings changes binds */
		$("div[data-rawname='presencestate'] .widget-settings-content .pssettings select").change(function() {
			self.savePSSettings();
		});
	},
	displaySimpleWidget: function(widget_id) {
		var self = this;
		$(".widget-extra-menu[data-id='"+widget_id+"'] select[name='status']").change(function() {
			var selected = $(this).find("option:selected");
			if (selected !== null) {
				id = $(selected).data('id');

				self.saveState(id);
			}
		});
	},
	displaySimpleWidgetSettings: function(widget_id) {
		this.displayWidgetSettings(widget_id);
	},
	statusUpdate: function(type, message) {
		if(type == 'not_set') {
			type = 'Offline';
		}
		$(".grid-stack-item[data-rawname='presencestate'] select[name='status']").selectpicker('val', type + (message !== '' ? ' (' + message + ')' : ''));
		$(".widget-extra-menu[data-module='presencestate'] select[name='status']").selectpicker('val', type + (message !== '' ? ' (' + message + ')' : ''));
	},
	saveState: function(id) {
		var self = this;

		data = { state: id };
		data.module = "presencestate";
		data.command = "set";

		$.post(UCP.ajaxUrl, data, null).always(function(data) {
			self.menu = data.poller.menu;
			self.statusUpdate(data.State, data.Message);
		});
	},
	savePSSettings: function() {
		var self = this;

		var data = {};
		data.events = {};

		$("div[data-rawname='presencestate'] .widget-settings-content .pssettings select").each(function( index ) {
			if ($(this).hasClass("event")) {
				data.events[$( this ).attr("name")] = $(this).val();
			} else {
				data[$( this ).attr("name")] = $(this).val();
			}
		});

		data.module = "presencestate";
		data.command = "savesettings";

		$.post(UCP.ajaxUrl, data, null).always(function(data) {
			if (data.status) {
				self.presenceSpecials.startSessionStatus = (data.startsessionstatus !== null) ? data.startsessionstatus.id : null;
				self.presenceSpecials.endSessionStatus = (data.endsessionstatus !== null) ? data.endsessionstatus.id : null;
			} else {
				return false;
			}
		});
	}
});

$(document).ready(function() {
	$(window).bind("beforeunload", function() {
		if ((typeof UCP.Modules.Presencestate !== 'undefined') && UCP.Modules.Presencestate.presenceSpecials.endSessionStatus !== null && navigator.onLine) {
			$.ajax({
				url: UCP.ajaxUrl + "?module=presencestate&command=set",
				type: "POST",
				data: { state: UCP.Modules.Presencestate.presenceSpecials.endSessionStatus },
				async: false, //block the browser from closing to send our request, hacky I know
				timeout: 2000
			});
		}
	});
});

$(document).on("logIn", function() {
	if (typeof UCP.Modules.Presencestate !== 'undefined'){
		UCP.Modules.Presencestate.presenceSpecials.startSessionStatus = UCP.Modules.Presencestate.staticsettings.startSessionStatus;
		UCP.Modules.Presencestate.presenceSpecials.endSessionStatus = UCP.Modules.Presencestate.staticsettings.endSessionStatus;
		if (UCP.Modules.Presencestate.presenceSpecials.startSessionStatus !== null && navigator.onLine) {
			$.ajax({
				url: UCP.ajaxUrl + "?module=presencestate&command=set",
				type: "POST",
				data: { state: UCP.Modules.Presencestate.presenceSpecials.startSessionStatus }
			});
		}		
	}
});

var SettingsC = UCPMC.extend({
	init: function() {
		this.language = language;
		this.timezone = timezone;
		this.datetimeformat = datetimeformat;
		this.timeformat = timeformat;
		this.dateformat = dateformat;
	},
	poll: function(data) {
		//console.log(data)
	},
	showMessage: function(message, type, timeout, html = false) {
		type = typeof type !== "undefined" ? type : "info";
		timeout = typeof timeout !== "undefined" ? timeout : 2000;
		if(html){
			$("#settings-message").removeClass().addClass("alert alert-"+type+" text-left").html(message);
		}
		else{
			$("#settings-message").removeClass().addClass("alert alert-"+type+" text-center").text(message);
		}
		
		setTimeout(function() {
			$("#settings-message").addClass("hidden");
		}, timeout);
	},
	updateTimeDisplay: function() {
		if(language === "") {
			language = this.language;
			Cookies.set("lang", language, { path: window.location.pathname.replace(/\/?$/,'') });
		}
		if(timezone === "") {
			timezone = this.timezone;
		}
		moment.locale(language);

		var userdtf = $("#datetimeformat").val();
		userdtf = (userdtf !== "") ? userdtf : datetimeformat;
		$("#datetimeformat-now").text(moment().tz(timezone).format(userdtf));

		var usertf = $("#timeformat").val();
		usertf = (usertf !== "") ? usertf : timeformat;
		$("#timeformat-now").text(moment().tz(timezone).format(usertf));

		var userdf = $("#dateformat").val();
		userdf = (userdf !== "") ? userdf : dateformat;
		$("#dateformat-now").text(moment().tz(timezone).format(userdf));
	},
	displaySimpleWidgetSettings: function(widget_id) {
		var $this = this;
		setInterval(function() {
			$this.updateTimeDisplay();
		},1000);
		$("#datetimeformat, #timeformat, #dateformat").keydown(function() {
			$this.updateTimeDisplay();
		});
		$("#browserlang").on("click", function(e){
			e.preventDefault();
			var bl =  browserLocale();
			bl = bl.replace("-","_");
			if(typeof bl === 'undefined'){
				UCP.showAlert(_("The Browser Language could not be determined"),"warning");
			}else{
				$("#lang").multiselect('select', bl);
				$("#lang").multiselect('refresh');
				$("#lang").trigger("onchange",[$("#lang option:selected"), $("#lang option:selected").is(":checked")]);
			}
		});
		$("#systemlang").on("click", function(e){
			e.preventDefault();
			var sl = UIDEFAULTLANG;
			if(typeof sl === 'undefined'){
				UCP.showAlert(_("The PBX Language is not set"),"warning");
			}else{
				$("#lang").multiselect('select', sl);
				$("#lang").multiselect('refresh');
				$("#lang").trigger("onchange",[$("#lang option:selected"), $("#lang option:selected").is(":checked")]);
			}
		});
		$("#browsertz").on("click", function(e){
			e.preventDefault();
			var btz =  moment.tz.guess();
			if(typeof btz === 'undefined'){
				UCP.showAlert(_("The Browser Timezone could not be determined"),"warning");
			}else{
				$("#timezone").multiselect('select', btz);
				$("#timezone").multiselect('refresh');
				$("#timezone").trigger("onchange",[$("#timezone option:selected"), $("#timezone option:selected").is(":checked")]);
			}
		});
		$("#systemtz").on("click", function(e){
			e.preventDefault();
			var stz = PHPTIMEZONE;
			if(typeof stz === 'undefined'){
				UCP.showAlert(_("The PBX Timezone is not set"),"warning");
			}else{
				$("#timezone").multiselect('select', stz);
				$("#timezone").multiselect('refresh');
				$("#timezone").trigger("onchange",[$("#timezone option:selected"), $("#timezone option:selected").is(":checked")]);
			}
		});
		$("#timezone").on("onchange", function(el, option, checked) {
			$.post( "ajax.php?module=Settings&command=settings", { key: "timezone", value: option.val() }, function( data ) {
				if(data.status) {
					timezone = option.val();
					$this.updateTimeDisplay();
					$this.showMessage(_("Success!"),"success");
					UCP.showConfirm(_("UCP needs to reload, ok?"), 'warning', function() {
						window.location.reload();
					});
				} else {
					$this.showMessage(data.message,"danger");
				}
			});
		});
		$("#lang").on("onchange", function(el, option, checked) {
			$.post( "ajax.php?module=Settings&command=settings", { key: "language", value: option.val() }, function( data ) {
				if(data.status) {
					language = option.val();
					$this.showMessage(_("Success!"),"success");
					$this.updateTimeDisplay();
					Cookies.set("lang", option.val(), { path: window.location.pathname.replace(/\/?$/,'') });
					UCP.showConfirm(_("UCP needs to reload, ok?"), 'warning', function() {
						window.location.reload();
					});
				} else {
					$this.showMessage(data.message,"danger");
				}
			});

		});
		if (Notify.isSupported()) {
			$("#ucp-settings .desktopnotifications-group").removeClass("hidden");
			$("#ucp-settings input[name=\"desktopnotifications\"]").prop("checked", UCP.notify);
			$("#ucp-settings input[name=\"desktopnotifications\"]").change(function() {
				if (!UCP.notify && $(this).is(":checked")) {
					Notify.requestPermission(function() {
						UCP.notificationsAllowed();
						$("#ucp-settings input[name=\"desktopnotifications\"]").prop("checked", true);
					}, function() {
						UCP.showAlert(_("Enabling notifications was denied"),"danger");
						UCP.notificationsDenied();
						$("#ucp-settings input[name=\"desktopnotifications\"]").prop("checked", false);
					});
				} else {
					UCP.notify = false;
				}
			});
		}

		var restartTour = false;
		$("#ucp-settings input[name=\"tour\"]").prop("checked", false);
		$("#ucp-settings input[name=\"tour\"]").change(function() {
			if($(this).is(":checked")) {
				restartTour = true;
			} else {
				restartTour = false;
			}
			$.post( UCP.ajaxUrl + "?module=ucptour&command=tour", { state: (restartTour ? 1 : 0) }, function( data ) {

			});
		});

		$("#widget_settings").one('hidden.bs.modal', function() {
			if(restartTour) {
				UCP.Modules.Ucptour.tour.restart();
			}
		});

		// Add click handler for launch-app button
		$("#launch-app").on("click", function(e) {
			console.log("Launching app...");
			e.preventDefault();
			$.ajax({
				type: 'POST',
				url: UCP.ajaxUrl,
				data: { module: "sangomaconnect", command: "getLoginUrl" },
				dataType: 'json',
				timeout: 30000,
				success: function (data) {
					if (data.status) {
						window.location.href = data.loginUrl;
					}
				}
			});
		});

		$("#update-pwd").click(function(e) {
			e.preventDefault();
			e.stopPropagation();
			var password = $("#pwd").val(), confirm = $("#pwd-confirm").val();
			if (password !== "" && password != "******" && confirm !== "") {
				if (confirm != password) {
					$this.showMessage(_("Password Confirmation Didn't Match!"),"danger");
				} else {
					$.post( "ajax.php?module=Settings&command=settings", { key: "password", value: confirm }, function( data ) {
						if (data.status) {
							$this.showMessage(_("Saved!"),"success");
							UCP.showConfirm(_("UCP needs to reload, ok?"), 'warning', function() {
								window.location.reload();
							});
						} else {
							$this.showMessage(data.message,"warning", 3000,  true);

						}
					});
				}
			} else {
				$this.showMessage(_("Password has not changed!"));
			}
		});

		$("#username").blur(function() {
			new_user = $(this).val();
			if($(this).val() != $(this).data("prevusername")) {				
				UCP.showConfirm(_("Are you sure you wish to change your username? UCP will reload after"), 'warning', function() {
					$.post( "ajax.php?module=Settings&command=settings", { key: "username", value: new_user}, function( data ) {
						if(data.status) {
							$this.showMessage(_("Username has been changed, reloading"),"success");
							window.location.reload();
						} else {
							$this.showMessage(data.message,"danger");
						}
					});
				});
			}
		});
		$("#userinfo input[type!=checkbox][type!=radio][name!=dateformat][name!=timeformat][name!=datetimeformat]").blur(function() {
			var getValueOtherInput = {};
			var filterInput = ["displayname", "fname", "lname","title","company"];
			$("#userinfo input").each(function() {
				var name = $(this).prop("name");
				if (filterInput.includes(name)) {
					var value = $(this).val();
					getValueOtherInput[name] = value;
				}
			});
			$.post( "ajax.php?module=Settings&command=settings", { key: $(this).prop("name"), value: $(this).val(), OtherInputValues:getValueOtherInput }, function( data ) {
				if (data.status) {
					$this.showMessage(_("Saved!"),"success");
				} else {
					$this.showMessage(data.message,"danger");
				}
				$(this).off("blur");
			});
		});
		$("#dateformat, #timeformat, #datetimeformat").blur(function() {
			var name = $(this).prop("name"),
					value = $(this).val();
			$.post( "ajax.php?module=Settings&command=settings", { key: name, value: value }, function( data ) {
				if (data.status) {
					if(value === "" && typeof $this[name] === "string") {
						window[name] = $this[name];
					} else {
						window[name] = value;
					}
					$this.showMessage(_("Saved!"),"success");
				} else {
					$this.showMessage(data.message,"danger");
				}
				$(this).off("blur");
			});
		});
		if($("#Contactmanager-image").length) {
			/**
			 * Drag/Drop/Upload Files
			 */
			$('#contactmanager_dropzone').on('drop dragover', function (e) {
				e.preventDefault();
			});
			$('#contactmanager_dropzone').on('dragleave drop', function (e) {
				$(this).removeClass("activate");
			});
			$('#contactmanager_dropzone').on('dragover', function (e) {
				$(this).addClass("activate");
			});
			var supportedRegExp = "png|jpg|jpeg";
			$( document ).ready(function() {
				$('#contactmanager_imageupload').fileupload({
					dataType: 'json',
					dropZone: $("#contactmanager_dropzone"),
					add: function (e, data) {
						//TODO: Need to check all supported formats
						var sup = "\.("+supportedRegExp+")$",
								patt = new RegExp(sup),
								submit = true;
						$.each(data.files, function(k, v) {
							if(!patt.test(v.name.toLowerCase())) {
								submit = false;
								alert(_("Unsupported file type"));
								return false;
							}
						});
						if(submit) {
							$("#contactmanager_upload-progress .progress-bar").addClass("progress-bar-striped active");
							data.submit();
						}
					},
					drop: function () {
						$("#contactmanager_upload-progress .progress-bar").css("width", "0%");
					},
					dragover: function (e, data) {
					},
					change: function (e, data) {
					},
					done: function (e, data) {
						$("#contactmanager_upload-progress .progress-bar").removeClass("progress-bar-striped active");
						$("#contactmanager_upload-progress .progress-bar").css("width", "0%");

						if(data.result.status) {
							$("#contactmanager_dropzone img").attr("src",data.result.url);
							$("#contactmanager_image").val(data.result.filename);
							$("#contactmanager_dropzone img").removeClass("hidden");
							$("#contactmanager_del-image").removeClass("hidden");
							$("#contactmanager_gravatar").prop('checked', false);
						} else {
							alert(data.result.message);
						}
					},
					progressall: function (e, data) {
						var progress = parseInt(data.loaded / data.total * 100, 10);
						$("#contactmanager_upload-progress .progress-bar").css("width", progress+"%");
					},
					fail: function (e, data) {
					},
					always: function (e, data) {
					}
				});

				$("#contactmanager_del-image").click(function(e) {
					e.preventDefault();
					e.stopPropagation();
					var id = $("input[name=user]").val(),
							grouptype = 'userman';
					$.post( "ajax.php?&module=Contactmanager&command=delimage", {id: id, img: $("#contactmanager_image").val()}, function( data ) {
						if(data.status) {
							$("#contactmanager_image").val("");
							$("#contactmanager_dropzone img").addClass("hidden");
							$("#contactmanager_dropzone img").attr("src","");
							$("#contactmanager_del-image").addClass("hidden");
							$("#contactmanager_gravatar").prop('checked', false);
						}
					});
				});

				$("#contactmanager_gravatar").change(function() {
					if($(this).is(":checked")) {
						var id = $("input[name=user]").val(),
								grouptype = 'userman';
						if($("#email").val() === "") {
							alert(_("No email defined"));
							$("#contactmanager_gravatar").prop('checked', false);
							return;
						}
						var t = $("label[for=contactmanager_gravatar]").text();
						$("label[for=contactmanager_gravatar]").text(_("Loading..."));
						$.post( "ajax.php?module=Contactmanager&command=getgravatar", {id: id, grouptype: grouptype, email: $("#email").val()}, function( data ) {
							$("label[for=contactmanager_gravatar]").text(t);
							if(data.status) {
								$("#contactmanager_dropzone img").data("oldsrc",$("#dropzone img").attr("src"));
								$("#contactmanager_dropzone img").attr("src",data.url);
								$("#contactmanager_image").data("old",$("#image").val());
								$("#contactmanager_image").val(data.filename);
								$("#contactmanager_dropzone img").removeClass("hidden");
								$("#contactmanager_del-image").removeClass("hidden");
							} else {
								alert(data.message);
								$("#contactmanager_gravatar").prop('checked', false);
							}
						});
					} else {
						var oldsrc = $("#contactmanager_dropzone img").data("oldsrc");
						if(typeof oldsrc !== "undefined" && oldsrc !== "") {
							$("#contactmanager_dropzone img").attr("src",oldsrc);
							$("#contactmanager_image").val($("#image").data("old"));
						} else {
							$("#contactmanager_image").val("");
							$("#contactmanager_dropzone img").addClass("hidden");
							$("#contactmanager_dropzone img").attr("src","");
							$("#contactmanager_del-image").addClass("hidden");
						}
					}
				});
			});
		}
	}
});

//15
var SmsC = UCPMC.extend({
	init: function(UCP) {
		this.lastchecked = Math.round(new Date().getTime() / 1000);
		this.dids = [];
		this.icon = "fa fa-comments-o";
		this.supportedFiles = "png|jpg|jpeg|gif|tiff|pdf|vcf|mp3|wav|ogg|mov|avi|mp4|m4a|ical|ics";
		//Logged In
		var Sms = this;
		$(document).on("chatWindowAdded", function(event, windowId, module, object) {
			if (module == "Sms") {
				object.on("click", function() {
					object.find(".title-bar").css("background-color", "");
				});
				var from = object.data("from"),
				to = object.data("to"),
				cwindow = $(".message-box[data-id=\"" + windowId + "\"] .window");
				var ea = object.find("textarea").emojioneArea()[0].emojioneArea;
				ea.on("keyup", function(editor, event) {
					if (event.keyCode == 13) {
						Sms.sendMessage(windowId, from, to, ea.getText());
						ea.setText(" ");
					}
				});
				object.find(".chat").scroll(function() {
					if ($(this)[0].scrollTop === 0) {
						var id = $(".chat .message:lt(1)").data("id");
						$(".message-box[data-id=\"" + windowId + "\"] .chat .history").prepend('<div class="message status">'+_('Loading')+'...</div>');
						$.post( UCP.ajaxUrl + "?module=sms&command=history", { id: id, from: from, to: to }, function( data ) {
							$(".message-box[data-id=\"" + windowId + "\"] .chat .history .status").remove();
							var html = "";
							$.each(data.messages, function(i, v) {
								if(v.emid ==null){
									v.emid = v.id;
								}
								html = html + '<div class="message '+v.direction+'" data-id="' + v.emid + '" title="'+UCP.dateTimeFormatter(v.date)+'">'+ v.message +'</div>';
							});
							$(".message-box[data-id=\"" + windowId + "\"] .chat .history").prepend(html);
						});
					}
				});
				object.find(".window").prepend("<input id='file-" + windowId + "' type='file' class='hidden'><label for='file-" + windowId + "'><i class='fa fa-upload'></i></label>");
				$("#file-" + windowId).fileupload({
					url: UCP.ajaxUrl + "?module=sms&command=upload&from="+from+"&to="+to,
					dropZone: cwindow,
					dataType: "json",
					add: function(e, data) {
						var sup = "\.("+Sms.supportedFiles+")$",
								patt = new RegExp(sup,'i'),
								submit = true;
						$.each(data.files, function(k, v) {
							if(!patt.test(v.name)) {
								submit = false;
								alert(_("Unsupported file type"));
								return false;
							}
							if(v.size > 1500000) {
								submit = false;
								alert(_("File size is too large. Max: 1.5mb"));
								return false;
							}
						});
						if(submit) {
							object.find(".response-status").html("");
							object.find(".response-status").prepend('<div class="progress"><div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;"><span class="sr-only">0% Complete</span></div></div>');
							data.submit();
						}
					},
					done: function(e, data) {
						if (data.result.status) {
							UCP.addChatMessage(windowId, data.result.emid, data.result.html, false, true, 'out');
							if($('#sms-grid-'+from).length) {
								$('#sms-grid-'+from).bootstrapTable('refresh', {silent: true});
							}
						} else {
							object.find(".response-status").html(data.result.message);
						}
						object.find(".progress").remove();
					},
					progressall: function(e, data) {
						var progress = parseInt(data.loaded / data.total * 100, 10);
						object.find(".progress-bar").css("width", progress + "%");
					},
					drop: function(e, data) {
						cwindow.removeClass("hover");
					}
				});
				cwindow.on("dragover", function(event) {
					if (event.preventDefault) {
						event.preventDefault(); // Necessary. Allows us to drop.
					}
					$(this).addClass("hover");
				});
				cwindow.on("dragleave", function(event) {
					$(this).removeClass("hover");
				});
			}
		});

		$(document).bind("staticSettingsFinished", function( event ) {
			if ((typeof Sms.staticsettings !== "undefined") && Sms.staticsettings.enabled) {
				Sms.dids = Sms.staticsettings.dids;
			}
		});
	},
	displayWidget: function(widget_id,dashboard_id) {
		var $this = this;
		var did = $(".grid-stack-item[data-rawname=sms][data-id='"+widget_id+"']").data("widget_type_id");

		$(".grid-stack-item[data-rawname=sms][data-id='"+widget_id+"'] .delete-selection").click(function() {
			var sel = $("#sms-grid-" + did).bootstrapTable('getSelections');
			UCP.showConfirm(_("Are you sure you wish to delete this conversation?"), 'warning', function() {
				var threads = [];
				$.each(sel, function(i, v) {
					threads.push(v.threadid);
				});
				$.post( UCP.ajaxUrl + "?module=sms&command=deletemany", { threads: threads }, function( data ) {
					if(data.status) {
						$('#sms-grid-'+did).bootstrapTable('refresh');
					}
				});
			});
		});

		$(".grid-stack-item[data-rawname=sms][data-id='"+widget_id+"'] .start-conversation").click(function() {
			UCP.showDialog(_("Send Message"),
				'<label for="SMSto">'+_("To")+':</label><input class="form-control" id="SMSto"></input>',
				'<button class="btn btn-default" id="initiateSMS">'+_("Initiate")+'</button>',
				function() {
					$("#initiateSMS").click(function() {
						$this.initiateChat(did,$("#SMSto").val(),function() {
							UCP.closeDialog();
						});

					});
					$("#SMSto").keypress(function(event) {
						if (event.keyCode == 13) {
							$this.initiateChat(did,$("#SMSto").val(),function() {
								UCP.closeDialog();
							});
						}
					});
				}
			);
		});
		$("#sms-grid-"+did).on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
			var sel = $(this).bootstrapTable('getSelections'),
					dis = true;
			if(sel.length) {
				dis = false;
			}
			$(".grid-stack-item[data-rawname=sms][data-id='"+widget_id+"'] .delete-selection").prop("disabled",dis);
		});
		$("#sms-grid-"+did).on("post-body.bs.table", function() {
			$("#sms-grid-"+did+" td .view").click(function() {
				var from = $(this).data("from"), to = $(this).data("to");
				UCP.showDialog(
					sprintf(_("Conversation Detail with %s"),from),
					$(".sms-detail-table-container").html(),
					'<button type="button" class="btn btn-default" data-dismiss="modal">'+_("Close")+'</button>',
					function() {
						$("#globalModal .sms-detail-table").bootstrapTable('showLoading');
						$.post( UCP.ajaxUrl + "?module=sms&command=messages", { from: from, to: to }, function( data ) {
							$("#globalModal .sms-detail-table").bootstrapTable('load', data);
							$("#globalModal .sms-detail-table").bootstrapTable('hideLoading');
						});
					}
				);
			});
			$("#sms-grid-"+did+" td .delete").click(function() {
				var from = $(this).data("from"), to = $(this).data("to"), id = $(this).data("id");
				UCP.showConfirm(_("Are you sure you wish to delete this conversation?"), 'warning', function() {
					$.post( UCP.ajaxUrl + "?module=sms&command=delete", { from: from, to: to,threadid:id }, function( data ) {
						if(data.status) {
							$('#sms-grid-'+did).bootstrapTable('remove', {field: "id", values: [String(id)]});
						}
					});
				});
			});
		});
	},
	contactClickInitiate: function(did) {
		var tdid = did, Sms = this,
		name = tdid,
		selected = "",
		temp = "";
		if (UCP.validMethod("Contactmanager", "lookup")) {
			if (typeof UCP.Modules.Contactmanager.lookup(tdid).displayname !== "undefined") {
				name = UCP.Modules.Contactmanager.lookup(tdid).displayname;
			} else {
				temp = String(tdid).length == 11 ? String(tdid).substring(1) : tdid;
				if (typeof UCP.Modules.Contactmanager.lookup(temp).displayname !== "undefined") {
					name = UCP.Modules.Contactmanager.lookup(temp).displayname;
				}
			}
		}

		selected = "<option value=\"" + tdid + "\" selected>" + name + "</option>";
		UCP.showDialog(_("Send Message"),
			'<label for="SMSfrom">From:</label> <select id="SMSfrom" class="form-control">'+ sfrom +'</select><label for="SMSto">To:</label><select class="form-control Tokenize Fill" id="SMSto" multiple>' + selected + '</select>',
			'<button class="btn btn-default" id="initiateSMS" style="margin-left: 72px;">'+_("Initiate")+'</button>',
			function() {
				$("#SMSto").tokenize({
					maxElements: 1,
					datas: UCP.ajaxUrl + "?module=sms&command=contacts"
				});
				$("#initiateSMS").click(function() {
					setTimeout(function() {Sms.initiateChat($("#SMSfrom").val(),$("#SMSto").val(),function(){UCP.closeDialog();});}, 50);
				});
				$("#SMSto").keypress(function(event) {
					if (event.keyCode == 13) {
						setTimeout(function() {Sms.initiateChat();}, 50);
					}
				});
			}
		);
	},
	contactClickOptions: function(type) {
		if (type != "number") {
			return false;
		}
		$.get( UCP.ajaxUrl + "?module=sms&command=dids",function( data ) {
			sfrom = "";
			$.each(data.dids, function(i, v) {
				sfrom = sfrom + "<option>" + v + "</option>"
			});
		});
		return [ { text: _("Send SMS"), function: "contactClickInitiate", type: "sms" } ];
	},
	replaceContact: function(contact) {
		var entry = null;
		if (UCP.validMethod("Contactmanager", "lookup")) {
			scontact = contact.length == 11 ? contact.substring(1) : contact;
			entry = UCP.Modules.Contactmanager.lookup(scontact);
			if (entry !== null && entry !== false) {
				return entry.displayname;
			}
			entry = UCP.Modules.Contactmanager.lookup(contact);
			if (entry !== null && entry !== false) {
				return entry.displayname;
			}
		}
		return contact;
	},
	prepoll: function(data) {
		var Sms = this,
				messageBoxes = { messageWindows: {}, lastchecked: this.lastchecked };
		$(".message-box[data-module=\"Sms\"]").each(function(i, v) {
			var windowid = $(this).data("id"),
					from = $(this).data("from"),
					to = $(this).data("to"),
					last = $(this).data("last-msg-id");
					messageBoxes.messageWindows[i] = { from: from, to: to, last: last, windowid: windowid };
		});
		return messageBoxes;
	},
	poll: function(data) {
		var Sms = this,
				delivered = [];
		if (data.status) {
			$.each(data.messages, function(windowid, messages) {
				$.each(messages, function(index, v) {
					//message already exists
					if(v.emid ==null){
						v.emid = v.id;
					}
					if($( "#messages-container .message-box[data-id=\"" + windowid + "\"] .message[data-id='"+v.id+"']").length) {
						return true;
					}
					var Notification = new Notify(sprintf(_("New Message from %s"), Sms.replaceContact(v.from)), {
						body: v.html ? _("New Message") : emojione.unifyUnicode(v.body),
						icon: "modules/Sms/assets/images/comment.png",
						timeout: 3
					});
					if(v.direction === 'in'){
						UCP.addChat("Sms", windowid, Sms.icon, v.did, v.recp, Sms.replaceContact(v.cnam), v.id, emojione.shortnameToImage(v.body), null, true, v.direction);
					}
					delivered.push(v.id);
					if (UCP.notify) {
						Notification.show();
					}
					if($('#sms-grid-'+v.did).length) {
						$('#sms-grid-'+v.did).bootstrapTable('refresh', {silent: true});
					}
				});
			});
			if (delivered.length) {
				$.post( UCP.ajaxUrl + "?module=sms&command=delivered", { ids: delivered }, function( data ) {});
			}
		}
		this.lastchecked = data.lastchecked;
	},
	initiateChat: function(did, to, callback) {
		var Sms = this,
				pattern = new RegExp(/^\d*$/);
		if (to !== "" && pattern.test(to)) {
			to = (to.length === 10) ? "1" + to : to;
			this.startChat(did, to);
			if(typeof callback === "function") {
				callback();
			}
		} else {
			UCP.showAlert(_("Invalid Number"));
		}
	},
	startChat: function(from, to) {
		var Sms = this;
		UCP.addChat("Sms", from + to, Sms.icon, from, to);
	},
	sendMessage: function(windowId, from, to, message, callback) {
		var Sms = this;
		$(".message-box[data-id='" + windowId + "'] .response-status").html(_("Sending..."));
		$(".message-box[data-id=\"" + windowId + "\"] .response .emojionearea-editor").addClass("hidden");
		$.post( UCP.ajaxUrl + "?module=sms&command=send", { from: from, to: to, message: message }, function( data ) {
			if (data.status) {
				$(".message-box[data-id='" + windowId + "'] .response-status").html("");
				UCP.addChatMessage(windowId, data.emid, message, false, false, 'out');
				$(".message-box[data-id='" + windowId + "'] textarea").val("");
				if($('#sms-grid-'+from).length) {
					$('#sms-grid-'+from).bootstrapTable('refresh', {silent: true});
				}
				if(typeof callback === "function") {
					callback();
				}
			} else {
				$(".message-box[data-id='" + windowId + "'] .response-status").html(data.message);
			}
			$(".message-box[data-id=\"" + windowId + "\"] .response .emojionearea-editor").removeClass("hidden");
			$(".message-box[data-id=\"" + windowId + "\"] .response .emojionearea-editor").focus();
		});
	},
	dateFormatter: function(value, row) {
		return UCP.dateTimeFormatter(row.timestamp);
	},
	actionFormatter: function(value, row) {
		return '<a><i class="fa fa-eye view" data-from="'+row.localdid+'" data-to="'+row.remotedid+'"></i></a><a><i class="fa fa-trash-o delete" data-from="'+row.localdid+'" data-to="'+row.remotedid+'" data-id="'+row.threadid+'"></i></a></td>';
	},
	toFormatter: function(value, row) {
		return '<a onclick="UCP.Modules.Sms.startChat(\''+row.localdid+'\',\''+row.remotedid+'\')">'+row.prettyto+'</a>';
	},
	directionFormatter: function(value) {
		switch(value) {
			case "out":
				return _("Sent");
			break;
			case "in":
				return _("Received");
			break;
		}
	},
	bodyFormatter: function(value, row) {
		return emojione.toImage(value);
	}
});

/* ========================================================================
 * bootstrap-tour - v0.12.0
 * http://bootstraptour.com
 * ========================================================================
 * Copyright 2012-2017 Ulrich Sossou
 *
 * ========================================================================
 * Licensed under the MIT License (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     https://opensource.org/licenses/MIT
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * ========================================================================
 */

/**!
 * @fileOverview Kickass library to create and place poppers near their reference elements.
 * @version 1.12.5
 * @license
 * Copyright (c) 2016 Federico Zivolo and contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */
(function (global, factory) {
  typeof exports === 'object' && typeof module !== 'undefined' ? module.exports = factory() :
    typeof define === 'function' && define.amd ? define(factory) :
      (global.Popper = factory());
}(this, (function () {
  'use strict';

  var nativeHints = ['native code', '[object MutationObserverConstructor]'];

  /**
   * Determine if a function is implemented natively (as opposed to a polyfill).
   * @method
   * @memberof Popper.Utils
   * @argument {Function | undefined} fn the function to check
   * @returns {Boolean}
   */
  var isNative = (function (fn) {
    return nativeHints.some(function (hint) {
      return (fn || '').toString().indexOf(hint) > -1;
    });
  });

  var isBrowser = typeof window !== 'undefined';
  var longerTimeoutBrowsers = ['Edge', 'Trident', 'Firefox'];
  var timeoutDuration = 0;
  for (var i = 0; i < longerTimeoutBrowsers.length; i += 1) {
    if (isBrowser && navigator.userAgent.indexOf(longerTimeoutBrowsers[i]) >= 0) {
      timeoutDuration = 1;
      break;
    }
  }

  function microtaskDebounce(fn) {
    var scheduled = false;
    var i = 0;
    var elem = document.createElement('span');

    // MutationObserver provides a mechanism for scheduling microtasks, which
    // are scheduled *before* the next task. This gives us a way to debounce
    // a function but ensure it's called *before* the next paint.
    var observer = new MutationObserver(function () {
      fn();
      scheduled = false;
    });

    observer.observe(elem, { attributes: true });

    return function () {
      if (!scheduled) {
        scheduled = true;
        elem.setAttribute('x-index', i);
        i = i + 1; // don't use compund (+=) because it doesn't get optimized in V8
      }
    };
  }

  function taskDebounce(fn) {
    var scheduled = false;
    return function () {
      if (!scheduled) {
        scheduled = true;
        setTimeout(function () {
          scheduled = false;
          fn();
        }, timeoutDuration);
      }
    };
  }

  // It's common for MutationObserver polyfills to be seen in the wild, however
  // these rely on Mutation Events which only occur when an element is connected
  // to the DOM. The algorithm used in this module does not use a connected element,
  // and so we must ensure that a *native* MutationObserver is available.
  var supportsNativeMutationObserver = isBrowser && isNative(window.MutationObserver);

  /**
  * Create a debounced version of a method, that's asynchronously deferred
  * but called in the minimum time possible.
  *
  * @method
  * @memberof Popper.Utils
  * @argument {Function} fn
  * @returns {Function}
  */
  var debounce = supportsNativeMutationObserver ? microtaskDebounce : taskDebounce;

  /**
   * Check if the given variable is a function
   * @method
   * @memberof Popper.Utils
   * @argument {Any} functionToCheck - variable to check
   * @returns {Boolean} answer to: is a function?
   */
  function isFunction(functionToCheck) {
    var getType = {};
    return functionToCheck && getType.toString.call(functionToCheck) === '[object Function]';
  }

  /**
   * Get CSS computed property of the given element
   * @method
   * @memberof Popper.Utils
   * @argument {Eement} element
   * @argument {String} property
   */
  function getStyleComputedProperty(element, property) {
    if (element.nodeType !== 1) {
      return [];
    }
    // NOTE: 1 DOM access here
    var css = window.getComputedStyle(element, null);
    return property ? css[property] : css;
  }

  /**
   * Returns the parentNode or the host of the element
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @returns {Element} parent
   */
  function getParentNode(element) {
    if (element.nodeName === 'HTML') {
      return element;
    }
    return element.parentNode || element.host;
  }

  /**
   * Returns the scrolling parent of the given element
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @returns {Element} scroll parent
   */
  function getScrollParent(element) {
    // Return body, `getScroll` will take care to get the correct `scrollTop` from it
    if (!element || ['HTML', 'BODY', '#document'].indexOf(element.nodeName) !== -1) {
      return window.document.body;
    }

    // Firefox want us to check `-x` and `-y` variations as well

    var _getStyleComputedProp = getStyleComputedProperty(element),
      overflow = _getStyleComputedProp.overflow,
      overflowX = _getStyleComputedProp.overflowX,
      overflowY = _getStyleComputedProp.overflowY;

    if (/(auto|scroll)/.test(overflow + overflowY + overflowX)) {
      return element;
    }

    return getScrollParent(getParentNode(element));
  }

  /**
   * Returns the offset parent of the given element
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @returns {Element} offset parent
   */
  function getOffsetParent(element) {
    // NOTE: 1 DOM access here
    var offsetParent = element && element.offsetParent;
    var nodeName = offsetParent && offsetParent.nodeName;

    if (!nodeName || nodeName === 'BODY' || nodeName === 'HTML') {
      return window.document.documentElement;
    }

    // .offsetParent will return the closest TD or TABLE in case
    // no offsetParent is present, I hate this job...
    if (['TD', 'TABLE'].indexOf(offsetParent.nodeName) !== -1 && getStyleComputedProperty(offsetParent, 'position') === 'static') {
      return getOffsetParent(offsetParent);
    }

    return offsetParent;
  }

  function isOffsetContainer(element) {
    var nodeName = element.nodeName;

    if (nodeName === 'BODY') {
      return false;
    }
    return nodeName === 'HTML' || getOffsetParent(element.firstElementChild) === element;
  }

  /**
   * Finds the root node (document, shadowDOM root) of the given element
   * @method
   * @memberof Popper.Utils
   * @argument {Element} node
   * @returns {Element} root node
   */
  function getRoot(node) {
    if (node.parentNode !== null) {
      return getRoot(node.parentNode);
    }

    return node;
  }

  /**
   * Finds the offset parent common to the two provided nodes
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element1
   * @argument {Element} element2
   * @returns {Element} common offset parent
   */
  function findCommonOffsetParent(element1, element2) {
    // This check is needed to avoid errors in case one of the elements isn't defined for any reason
    if (!element1 || !element1.nodeType || !element2 || !element2.nodeType) {
      return window.document.documentElement;
    }

    // Here we make sure to give as "start" the element that comes first in the DOM
    var order = element1.compareDocumentPosition(element2) & Node.DOCUMENT_POSITION_FOLLOWING;
    var start = order ? element1 : element2;
    var end = order ? element2 : element1;

    // Get common ancestor container
    var range = document.createRange();
    range.setStart(start, 0);
    range.setEnd(end, 0);
    var commonAncestorContainer = range.commonAncestorContainer;

    // Both nodes are inside #document

    if (element1 !== commonAncestorContainer && element2 !== commonAncestorContainer || start.contains(end)) {
      if (isOffsetContainer(commonAncestorContainer)) {
        return commonAncestorContainer;
      }

      return getOffsetParent(commonAncestorContainer);
    }

    // one of the nodes is inside shadowDOM, find which one
    var element1root = getRoot(element1);
    if (element1root.host) {
      return findCommonOffsetParent(element1root.host, element2);
    } else {
      return findCommonOffsetParent(element1, getRoot(element2).host);
    }
  }

  /**
   * Gets the scroll value of the given element in the given side (top and left)
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @argument {String} side `top` or `left`
   * @returns {number} amount of scrolled pixels
   */
  function getScroll(element) {
    var side = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 'top';

    var upperSide = side === 'top' ? 'scrollTop' : 'scrollLeft';
    var nodeName = element.nodeName;

    if (nodeName === 'BODY' || nodeName === 'HTML') {
      var html = window.document.documentElement;
      var scrollingElement = window.document.scrollingElement || html;
      return scrollingElement[upperSide];
    }

    return element[upperSide];
  }

  /*
   * Sum or subtract the element scroll values (left and top) from a given rect object
   * @method
   * @memberof Popper.Utils
   * @param {Object} rect - Rect object you want to change
   * @param {HTMLElement} element - The element from the function reads the scroll values
   * @param {Boolean} subtract - set to true if you want to subtract the scroll values
   * @return {Object} rect - The modifier rect object
   */
  function includeScroll(rect, element) {
    var subtract = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : false;

    var scrollTop = getScroll(element, 'top');
    var scrollLeft = getScroll(element, 'left');
    var modifier = subtract ? -1 : 1;
    rect.top += scrollTop * modifier;
    rect.bottom += scrollTop * modifier;
    rect.left += scrollLeft * modifier;
    rect.right += scrollLeft * modifier;
    return rect;
  }

  /*
   * Helper to detect borders of a given element
   * @method
   * @memberof Popper.Utils
   * @param {CSSStyleDeclaration} styles
   * Result of `getStyleComputedProperty` on the given element
   * @param {String} axis - `x` or `y`
   * @return {number} borders - The borders size of the given axis
   */

  function getBordersSize(styles, axis) {
    var sideA = axis === 'x' ? 'Left' : 'Top';
    var sideB = sideA === 'Left' ? 'Right' : 'Bottom';

    return +styles['border' + sideA + 'Width'].split('px')[0] + +styles['border' + sideB + 'Width'].split('px')[0];
  }

  /**
   * Tells if you are running Internet Explorer 10
   * @method
   * @memberof Popper.Utils
   * @returns {Boolean} isIE10
   */
  var isIE10 = undefined;

  var isIE10$1 = function () {
    if (isIE10 === undefined) {
      isIE10 = navigator.appVersion.indexOf('MSIE 10') !== -1;
    }
    return isIE10;
  };

  function getSize(axis, body, html, computedStyle) {
    return Math.max(body['offset' + axis], body['scroll' + axis], html['client' + axis], html['offset' + axis], html['scroll' + axis], isIE10$1() ? html['offset' + axis] + computedStyle['margin' + (axis === 'Height' ? 'Top' : 'Left')] + computedStyle['margin' + (axis === 'Height' ? 'Bottom' : 'Right')] : 0);
  }

  function getWindowSizes() {
    var body = window.document.body;
    var html = window.document.documentElement;
    var computedStyle = isIE10$1() && window.getComputedStyle(html);

    return {
      height: getSize('Height', body, html, computedStyle),
      width: getSize('Width', body, html, computedStyle)
    };
  }

  var classCallCheck = function (instance, Constructor) {
    if (!(instance instanceof Constructor)) {
      throw new TypeError("Cannot call a class as a function");
    }
  };

  var createClass = function () {
    function defineProperties(target, props) {
      for (var i = 0; i < props.length; i++) {
        var descriptor = props[i];
        descriptor.enumerable = descriptor.enumerable || false;
        descriptor.configurable = true;
        if ("value" in descriptor) descriptor.writable = true;
        Object.defineProperty(target, descriptor.key, descriptor);
      }
    }

    return function (Constructor, protoProps, staticProps) {
      if (protoProps) defineProperties(Constructor.prototype, protoProps);
      if (staticProps) defineProperties(Constructor, staticProps);
      return Constructor;
    };
  }();





  var defineProperty = function (obj, key, value) {
    if (key in obj) {
      Object.defineProperty(obj, key, {
        value: value,
        enumerable: true,
        configurable: true,
        writable: true
      });
    } else {
      obj[key] = value;
    }

    return obj;
  };

  var _extends = Object.assign || function (target) {
    for (var i = 1; i < arguments.length; i++) {
      var source = arguments[i];

      for (var key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
          target[key] = source[key];
        }
      }
    }

    return target;
  };

  /**
   * Given element offsets, generate an output similar to getBoundingClientRect
   * @method
   * @memberof Popper.Utils
   * @argument {Object} offsets
   * @returns {Object} ClientRect like output
   */
  function getClientRect(offsets) {
    return _extends({}, offsets, {
      right: offsets.left + offsets.width,
      bottom: offsets.top + offsets.height
    });
  }

  /**
   * Get bounding client rect of given element
   * @method
   * @memberof Popper.Utils
   * @param {HTMLElement} element
   * @return {Object} client rect
   */
  function getBoundingClientRect(element) {
    var rect = {};

    // IE10 10 FIX: Please, don't ask, the element isn't
    // considered in DOM in some circumstances...
    // This isn't reproducible in IE10 compatibility mode of IE11
    if (isIE10$1()) {
      try {
        rect = element.getBoundingClientRect();
        var scrollTop = getScroll(element, 'top');
        var scrollLeft = getScroll(element, 'left');
        rect.top += scrollTop;
        rect.left += scrollLeft;
        rect.bottom += scrollTop;
        rect.right += scrollLeft;
      } catch (err) { }
    } else {
      rect = element.getBoundingClientRect();
    }

    var result = {
      left: rect.left,
      top: rect.top,
      width: rect.right - rect.left,
      height: rect.bottom - rect.top
    };

    // subtract scrollbar size from sizes
    var sizes = element.nodeName === 'HTML' ? getWindowSizes() : {};
    var width = sizes.width || element.clientWidth || result.right - result.left;
    var height = sizes.height || element.clientHeight || result.bottom - result.top;

    var horizScrollbar = element.offsetWidth - width;
    var vertScrollbar = element.offsetHeight - height;

    // if an hypothetical scrollbar is detected, we must be sure it's not a `border`
    // we make this check conditional for performance reasons
    if (horizScrollbar || vertScrollbar) {
      var styles = getStyleComputedProperty(element);
      horizScrollbar -= getBordersSize(styles, 'x');
      vertScrollbar -= getBordersSize(styles, 'y');

      result.width -= horizScrollbar;
      result.height -= vertScrollbar;
    }

    return getClientRect(result);
  }

  function getOffsetRectRelativeToArbitraryNode(children, parent) {
    var isIE10 = isIE10$1();
    var isHTML = parent.nodeName === 'HTML';
    var childrenRect = getBoundingClientRect(children);
    var parentRect = getBoundingClientRect(parent);
    var scrollParent = getScrollParent(children);

    var styles = getStyleComputedProperty(parent);
    var borderTopWidth = +styles.borderTopWidth.split('px')[0];
    var borderLeftWidth = +styles.borderLeftWidth.split('px')[0];

    var offsets = getClientRect({
      top: childrenRect.top - parentRect.top - borderTopWidth,
      left: childrenRect.left - parentRect.left - borderLeftWidth,
      width: childrenRect.width,
      height: childrenRect.height
    });
    offsets.marginTop = 0;
    offsets.marginLeft = 0;

    // Subtract margins of documentElement in case it's being used as parent
    // we do this only on HTML because it's the only element that behaves
    // differently when margins are applied to it. The margins are included in
    // the box of the documentElement, in the other cases not.
    if (!isIE10 && isHTML) {
      var marginTop = +styles.marginTop.split('px')[0];
      var marginLeft = +styles.marginLeft.split('px')[0];

      offsets.top -= borderTopWidth - marginTop;
      offsets.bottom -= borderTopWidth - marginTop;
      offsets.left -= borderLeftWidth - marginLeft;
      offsets.right -= borderLeftWidth - marginLeft;

      // Attach marginTop and marginLeft because in some circumstances we may need them
      offsets.marginTop = marginTop;
      offsets.marginLeft = marginLeft;
    }

    if (isIE10 ? parent.contains(scrollParent) : parent === scrollParent && scrollParent.nodeName !== 'BODY') {
      offsets = includeScroll(offsets, parent);
    }

    return offsets;
  }

  function getViewportOffsetRectRelativeToArtbitraryNode(element) {
    var html = window.document.documentElement;
    var relativeOffset = getOffsetRectRelativeToArbitraryNode(element, html);
    var width = Math.max(html.clientWidth, window.innerWidth || 0);
    var height = Math.max(html.clientHeight, window.innerHeight || 0);

    var scrollTop = getScroll(html);
    var scrollLeft = getScroll(html, 'left');

    var offset = {
      top: scrollTop - relativeOffset.top + relativeOffset.marginTop,
      left: scrollLeft - relativeOffset.left + relativeOffset.marginLeft,
      width: width,
      height: height
    };

    return getClientRect(offset);
  }

  /**
   * Check if the given element is fixed or is inside a fixed parent
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @argument {Element} customContainer
   * @returns {Boolean} answer to "isFixed?"
   */
  function isFixed(element) {
    var nodeName = element.nodeName;
    if (nodeName === 'BODY' || nodeName === 'HTML') {
      return false;
    }
    if (getStyleComputedProperty(element, 'position') === 'fixed') {
      return true;
    }
    return isFixed(getParentNode(element));
  }

  /**
   * Computed the boundaries limits and return them
   * @method
   * @memberof Popper.Utils
   * @param {HTMLElement} popper
   * @param {HTMLElement} reference
   * @param {number} padding
   * @param {HTMLElement} boundariesElement - Element used to define the boundaries
   * @returns {Object} Coordinates of the boundaries
   */
  function getBoundaries(popper, reference, padding, boundariesElement) {
    // NOTE: 1 DOM access here
    var boundaries = { top: 0, left: 0 };
    var offsetParent = findCommonOffsetParent(popper, reference);

    // Handle viewport case
    if (boundariesElement === 'viewport') {
      boundaries = getViewportOffsetRectRelativeToArtbitraryNode(offsetParent);
    } else {
      // Handle other cases based on DOM element used as boundaries
      var boundariesNode = void 0;
      if (boundariesElement === 'scrollParent') {
        boundariesNode = getScrollParent(getParentNode(popper));
        if (boundariesNode.nodeName === 'BODY') {
          boundariesNode = window.document.documentElement;
        }
      } else if (boundariesElement === 'window') {
        boundariesNode = window.document.documentElement;
      } else {
        boundariesNode = boundariesElement;
      }

      var offsets = getOffsetRectRelativeToArbitraryNode(boundariesNode, offsetParent);

      // In case of HTML, we need a different computation
      if (boundariesNode.nodeName === 'HTML' && !isFixed(offsetParent)) {
        var _getWindowSizes = getWindowSizes(),
          height = _getWindowSizes.height,
          width = _getWindowSizes.width;

        boundaries.top += offsets.top - offsets.marginTop;
        boundaries.bottom = height + offsets.top;
        boundaries.left += offsets.left - offsets.marginLeft;
        boundaries.right = width + offsets.left;
      } else {
        // for all the other DOM elements, this one is good
        boundaries = offsets;
      }
    }

    // Add paddings
    boundaries.left += padding;
    boundaries.top += padding;
    boundaries.right -= padding;
    boundaries.bottom -= padding;

    return boundaries;
  }

  function getArea(_ref) {
    var width = _ref.width,
      height = _ref.height;

    return width * height;
  }

  /**
   * Utility used to transform the `auto` placement to the placement with more
   * available space.
   * @method
   * @memberof Popper.Utils
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function computeAutoPlacement(placement, refRect, popper, reference, boundariesElement) {
    var padding = arguments.length > 5 && arguments[5] !== undefined ? arguments[5] : 0;

    if (placement.indexOf('auto') === -1) {
      return placement;
    }

    var boundaries = getBoundaries(popper, reference, padding, boundariesElement);

    var rects = {
      top: {
        width: boundaries.width,
        height: refRect.top - boundaries.top
      },
      right: {
        width: boundaries.right - refRect.right,
        height: boundaries.height
      },
      bottom: {
        width: boundaries.width,
        height: boundaries.bottom - refRect.bottom
      },
      left: {
        width: refRect.left - boundaries.left,
        height: boundaries.height
      }
    };

    var sortedAreas = Object.keys(rects).map(function (key) {
      return _extends({
        key: key
      }, rects[key], {
        area: getArea(rects[key])
      });
    }).sort(function (a, b) {
      return b.area - a.area;
    });

    var filteredAreas = sortedAreas.filter(function (_ref2) {
      var width = _ref2.width,
        height = _ref2.height;
      return width >= popper.clientWidth && height >= popper.clientHeight;
    });

    var computedPlacement = filteredAreas.length > 0 ? filteredAreas[0].key : sortedAreas[0].key;

    var variation = placement.split('-')[1];

    return computedPlacement + (variation ? '-' + variation : '');
  }

  /**
   * Get offsets to the reference element
   * @method
   * @memberof Popper.Utils
   * @param {Object} state
   * @param {Element} popper - the popper element
   * @param {Element} reference - the reference element (the popper will be relative to this)
   * @returns {Object} An object containing the offsets which will be applied to the popper
   */
  function getReferenceOffsets(state, popper, reference) {
    var commonOffsetParent = findCommonOffsetParent(popper, reference);
    return getOffsetRectRelativeToArbitraryNode(reference, commonOffsetParent);
  }

  /**
   * Get the outer sizes of the given element (offset size + margins)
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element
   * @returns {Object} object containing width and height properties
   */
  function getOuterSizes(element) {
    var styles = window.getComputedStyle(element);
    var x = parseFloat(styles.marginTop) + parseFloat(styles.marginBottom);
    var y = parseFloat(styles.marginLeft) + parseFloat(styles.marginRight);
    var result = {
      width: element.offsetWidth + y,
      height: element.offsetHeight + x
    };
    return result;
  }

  /**
   * Get the opposite placement of the given one
   * @method
   * @memberof Popper.Utils
   * @argument {String} placement
   * @returns {String} flipped placement
   */
  function getOppositePlacement(placement) {
    var hash = { left: 'right', right: 'left', bottom: 'top', top: 'bottom' };
    return placement.replace(/left|right|bottom|top/g, function (matched) {
      return hash[matched];
    });
  }

  /**
   * Get offsets to the popper
   * @method
   * @memberof Popper.Utils
   * @param {Object} position - CSS position the Popper will get applied
   * @param {HTMLElement} popper - the popper element
   * @param {Object} referenceOffsets - the reference offsets (the popper will be relative to this)
   * @param {String} placement - one of the valid placement options
   * @returns {Object} popperOffsets - An object containing the offsets which will be applied to the popper
   */
  function getPopperOffsets(popper, referenceOffsets, placement) {
    placement = placement.split('-')[0];

    // Get popper node sizes
    var popperRect = getOuterSizes(popper);

    // Add position, width and height to our offsets object
    var popperOffsets = {
      width: popperRect.width,
      height: popperRect.height
    };

    // depending by the popper placement we have to compute its offsets slightly differently
    var isHoriz = ['right', 'left'].indexOf(placement) !== -1;
    var mainSide = isHoriz ? 'top' : 'left';
    var secondarySide = isHoriz ? 'left' : 'top';
    var measurement = isHoriz ? 'height' : 'width';
    var secondaryMeasurement = !isHoriz ? 'height' : 'width';

    popperOffsets[mainSide] = referenceOffsets[mainSide] + referenceOffsets[measurement] / 2 - popperRect[measurement] / 2;
    if (placement === secondarySide) {
      popperOffsets[secondarySide] = referenceOffsets[secondarySide] - popperRect[secondaryMeasurement];
    } else {
      popperOffsets[secondarySide] = referenceOffsets[getOppositePlacement(secondarySide)];
    }

    return popperOffsets;
  }

  /**
   * Mimics the `find` method of Array
   * @method
   * @memberof Popper.Utils
   * @argument {Array} arr
   * @argument prop
   * @argument value
   * @returns index or -1
   */
  function find(arr, check) {
    // use native find if supported
    if (Array.prototype.find) {
      return arr.find(check);
    }

    // use `filter` to obtain the same behavior of `find`
    return arr.filter(check)[0];
  }

  /**
   * Return the index of the matching object
   * @method
   * @memberof Popper.Utils
   * @argument {Array} arr
   * @argument prop
   * @argument value
   * @returns index or -1
   */
  function findIndex(arr, prop, value) {
    // use native findIndex if supported
    if (Array.prototype.findIndex) {
      return arr.findIndex(function (cur) {
        return cur[prop] === value;
      });
    }

    // use `find` + `indexOf` if `findIndex` isn't supported
    var match = find(arr, function (obj) {
      return obj[prop] === value;
    });
    return arr.indexOf(match);
  }

  /**
   * Loop trough the list of modifiers and run them in order,
   * each of them will then edit the data object.
   * @method
   * @memberof Popper.Utils
   * @param {dataObject} data
   * @param {Array} modifiers
   * @param {String} ends - Optional modifier name used as stopper
   * @returns {dataObject}
   */
  function runModifiers(modifiers, data, ends) {
    var modifiersToRun = ends === undefined ? modifiers : modifiers.slice(0, findIndex(modifiers, 'name', ends));

    modifiersToRun.forEach(function (modifier) {
      if (modifier.function) {
        console.warn('`modifier.function` is deprecated, use `modifier.fn`!');
      }
      var fn = modifier.function || modifier.fn;
      if (modifier.enabled && isFunction(fn)) {
        // Add properties to offsets to make them a complete clientRect object
        // we do this before each modifier to make sure the previous one doesn't
        // mess with these values
        data.offsets.popper = getClientRect(data.offsets.popper);
        data.offsets.reference = getClientRect(data.offsets.reference);

        data = fn(data, modifier);
      }
    });

    return data;
  }

  /**
   * Updates the position of the popper, computing the new offsets and applying
   * the new style.<br />
   * Prefer `scheduleUpdate` over `update` because of performance reasons.
   * @method
   * @memberof Popper
   */
  function update() {
    // if popper is destroyed, don't perform any further update
    if (this.state.isDestroyed) {
      return;
    }

    var data = {
      instance: this,
      styles: {},
      arrowStyles: {},
      attributes: {},
      flipped: false,
      offsets: {}
    };

    // compute reference element offsets
    data.offsets.reference = getReferenceOffsets(this.state, this.popper, this.reference);

    // compute auto placement, store placement inside the data object,
    // modifiers will be able to edit `placement` if needed
    // and refer to originalPlacement to know the original value
    data.placement = computeAutoPlacement(this.options.placement, data.offsets.reference, this.popper, this.reference, this.options.modifiers.flip.boundariesElement, this.options.modifiers.flip.padding);

    // store the computed placement inside `originalPlacement`
    data.originalPlacement = data.placement;

    // compute the popper offsets
    data.offsets.popper = getPopperOffsets(this.popper, data.offsets.reference, data.placement);
    data.offsets.popper.position = 'absolute';

    // run the modifiers
    data = runModifiers(this.modifiers, data);

    // the first `update` will call `onCreate` callback
    // the other ones will call `onUpdate` callback
    if (!this.state.isCreated) {
      this.state.isCreated = true;
      this.options.onCreate(data);
    } else {
      this.options.onUpdate(data);
    }
  }

  /**
   * Helper used to know if the given modifier is enabled.
   * @method
   * @memberof Popper.Utils
   * @returns {Boolean}
   */
  function isModifierEnabled(modifiers, modifierName) {
    return modifiers.some(function (_ref) {
      var name = _ref.name,
        enabled = _ref.enabled;
      return enabled && name === modifierName;
    });
  }

  /**
   * Get the prefixed supported property name
   * @method
   * @memberof Popper.Utils
   * @argument {String} property (camelCase)
   * @returns {String} prefixed property (camelCase or PascalCase, depending on the vendor prefix)
   */
  function getSupportedPropertyName(property) {
    var prefixes = [false, 'ms', 'Webkit', 'Moz', 'O'];
    var upperProp = property.charAt(0).toUpperCase() + property.slice(1);

    for (var i = 0; i < prefixes.length - 1; i++) {
      var prefix = prefixes[i];
      var toCheck = prefix ? '' + prefix + upperProp : property;
      if (typeof window.document.body.style[toCheck] !== 'undefined') {
        return toCheck;
      }
    }
    return null;
  }

  /**
   * Destroy the popper
   * @method
   * @memberof Popper
   */
  function destroy() {
    this.state.isDestroyed = true;

    // touch DOM only if `applyStyle` modifier is enabled
    if (isModifierEnabled(this.modifiers, 'applyStyle')) {
      this.popper.removeAttribute('x-placement');
      this.popper.style.left = '';
      this.popper.style.position = '';
      this.popper.style.top = '';
      this.popper.style[getSupportedPropertyName('transform')] = '';
    }

    this.disableEventListeners();

    // remove the popper if user explicity asked for the deletion on destroy
    // do not use `remove` because IE11 doesn't support it
    if (this.options.removeOnDestroy) {
      this.popper.parentNode.removeChild(this.popper);
    }
    return this;
  }

  function attachToScrollParents(scrollParent, event, callback, scrollParents) {
    var isBody = scrollParent.nodeName === 'BODY';
    var target = isBody ? window : scrollParent;
    target.addEventListener(event, callback, { passive: true });

    if (!isBody) {
      attachToScrollParents(getScrollParent(target.parentNode), event, callback, scrollParents);
    }
    scrollParents.push(target);
  }

  /**
   * Setup needed event listeners used to update the popper position
   * @method
   * @memberof Popper.Utils
   * @private
   */
  function setupEventListeners(reference, options, state, updateBound) {
    // Resize event listener on window
    state.updateBound = updateBound;
    window.addEventListener('resize', state.updateBound, { passive: true });

    // Scroll event listener on scroll parents
    var scrollElement = getScrollParent(reference);
    attachToScrollParents(scrollElement, 'scroll', state.updateBound, state.scrollParents);
    state.scrollElement = scrollElement;
    state.eventsEnabled = true;

    return state;
  }

  /**
   * It will add resize/scroll events and start recalculating
   * position of the popper element when they are triggered.
   * @method
   * @memberof Popper
   */
  function enableEventListeners() {
    if (!this.state.eventsEnabled) {
      this.state = setupEventListeners(this.reference, this.options, this.state, this.scheduleUpdate);
    }
  }

  /**
   * Remove event listeners used to update the popper position
   * @method
   * @memberof Popper.Utils
   * @private
   */
  function removeEventListeners(reference, state) {
    // Remove resize event listener on window
    window.removeEventListener('resize', state.updateBound);

    // Remove scroll event listener on scroll parents
    state.scrollParents.forEach(function (target) {
      target.removeEventListener('scroll', state.updateBound);
    });

    // Reset state
    state.updateBound = null;
    state.scrollParents = [];
    state.scrollElement = null;
    state.eventsEnabled = false;
    return state;
  }

  /**
   * It will remove resize/scroll events and won't recalculate popper position
   * when they are triggered. It also won't trigger onUpdate callback anymore,
   * unless you call `update` method manually.
   * @method
   * @memberof Popper
   */
  function disableEventListeners() {
    if (this.state.eventsEnabled) {
      window.cancelAnimationFrame(this.scheduleUpdate);
      this.state = removeEventListeners(this.reference, this.state);
    }
  }

  /**
   * Tells if a given input is a number
   * @method
   * @memberof Popper.Utils
   * @param {*} input to check
   * @return {Boolean}
   */
  function isNumeric(n) {
    return n !== '' && !isNaN(parseFloat(n)) && isFinite(n);
  }

  /**
   * Set the style to the given popper
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element - Element to apply the style to
   * @argument {Object} styles
   * Object with a list of properties and values which will be applied to the element
   */
  function setStyles(element, styles) {
    Object.keys(styles).forEach(function (prop) {
      var unit = '';
      // add unit if the value is numeric and is one of the following
      if (['width', 'height', 'top', 'right', 'bottom', 'left'].indexOf(prop) !== -1 && isNumeric(styles[prop])) {
        unit = 'px';
      }
      element.style[prop] = styles[prop] + unit;
    });
  }

  /**
   * Set the attributes to the given popper
   * @method
   * @memberof Popper.Utils
   * @argument {Element} element - Element to apply the attributes to
   * @argument {Object} styles
   * Object with a list of properties and values which will be applied to the element
   */
  function setAttributes(element, attributes) {
    Object.keys(attributes).forEach(function (prop) {
      var value = attributes[prop];
      if (value !== false) {
        element.setAttribute(prop, attributes[prop]);
      } else {
        element.removeAttribute(prop);
      }
    });
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by `update` method
   * @argument {Object} data.styles - List of style properties - values to apply to popper element
   * @argument {Object} data.attributes - List of attribute properties - values to apply to popper element
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The same data object
   */
  function applyStyle(data) {
    // any property present in `data.styles` will be applied to the popper,
    // in this way we can make the 3rd party modifiers add custom styles to it
    // Be aware, modifiers could override the properties defined in the previous
    // lines of this modifier!
    setStyles(data.instance.popper, data.styles);

    // any property present in `data.attributes` will be applied to the popper,
    // they will be set as HTML attributes of the element
    setAttributes(data.instance.popper, data.attributes);

    // if arrowElement is defined and arrowStyles has some properties
    if (data.arrowElement && Object.keys(data.arrowStyles).length) {
      setStyles(data.arrowElement, data.arrowStyles);
    }

    return data;
  }

  /**
   * Set the x-placement attribute before everything else because it could be used
   * to add margins to the popper margins needs to be calculated to get the
   * correct popper offsets.
   * @method
   * @memberof Popper.modifiers
   * @param {HTMLElement} reference - The reference element used to position the popper
   * @param {HTMLElement} popper - The HTML element used as popper.
   * @param {Object} options - Popper.js options
   */
  function applyStyleOnLoad(reference, popper, options, modifierOptions, state) {
    // compute reference element offsets
    var referenceOffsets = getReferenceOffsets(state, popper, reference);

    // compute auto placement, store placement inside the data object,
    // modifiers will be able to edit `placement` if needed
    // and refer to originalPlacement to know the original value
    var placement = computeAutoPlacement(options.placement, referenceOffsets, popper, reference, options.modifiers.flip.boundariesElement, options.modifiers.flip.padding);

    popper.setAttribute('x-placement', placement);

    // Apply `position` to popper before anything else because
    // without the position applied we can't guarantee correct computations
    setStyles(popper, { position: 'absolute' });

    return options;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by `update` method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function computeStyle(data, options) {
    var x = options.x,
      y = options.y;
    var popper = data.offsets.popper;

    // Remove this legacy support in Popper.js v2

    var legacyGpuAccelerationOption = find(data.instance.modifiers, function (modifier) {
      return modifier.name === 'applyStyle';
    }).gpuAcceleration;
    if (legacyGpuAccelerationOption !== undefined) {
      console.warn('WARNING: `gpuAcceleration` option moved to `computeStyle` modifier and will not be supported in future versions of Popper.js!');
    }
    var gpuAcceleration = legacyGpuAccelerationOption !== undefined ? legacyGpuAccelerationOption : options.gpuAcceleration;

    var offsetParent = getOffsetParent(data.instance.popper);
    var offsetParentRect = getBoundingClientRect(offsetParent);

    // Styles
    var styles = {
      position: popper.position
    };

    // floor sides to avoid blurry text
    var offsets = {
      left: Math.floor(popper.left),
      top: Math.floor(popper.top),
      bottom: Math.floor(popper.bottom),
      right: Math.floor(popper.right)
    };

    var sideA = x === 'bottom' ? 'top' : 'bottom';
    var sideB = y === 'right' ? 'left' : 'right';

    // if gpuAcceleration is set to `true` and transform is supported,
    //  we use `translate3d` to apply the position to the popper we
    // automatically use the supported prefixed version if needed
    var prefixedProperty = getSupportedPropertyName('transform');

    // now, let's make a step back and look at this code closely (wtf?)
    // If the content of the popper grows once it's been positioned, it
    // may happen that the popper gets misplaced because of the new content
    // overflowing its reference element
    // To avoid this problem, we provide two options (x and y), which allow
    // the consumer to define the offset origin.
    // If we position a popper on top of a reference element, we can set
    // `x` to `top` to make the popper grow towards its top instead of
    // its bottom.
    var left = void 0,
      top = void 0;
    if (sideA === 'bottom') {
      top = -offsetParentRect.height + offsets.bottom;
    } else {
      top = offsets.top;
    }
    if (sideB === 'right') {
      left = -offsetParentRect.width + offsets.right;
    } else {
      left = offsets.left;
    }
    if (gpuAcceleration && prefixedProperty) {
      styles[prefixedProperty] = 'translate3d(' + left + 'px, ' + top + 'px, 0)';
      styles[sideA] = 0;
      styles[sideB] = 0;
      styles.willChange = 'transform';
    } else {
      // othwerise, we use the standard `top`, `left`, `bottom` and `right` properties
      var invertTop = sideA === 'bottom' ? -1 : 1;
      var invertLeft = sideB === 'right' ? -1 : 1;
      styles[sideA] = top * invertTop;
      styles[sideB] = left * invertLeft;
      styles.willChange = sideA + ', ' + sideB;
    }

    // Attributes
    var attributes = {
      'x-placement': data.placement
    };

    // Update `data` attributes, styles and arrowStyles
    data.attributes = _extends({}, attributes, data.attributes);
    data.styles = _extends({}, styles, data.styles);
    data.arrowStyles = _extends({}, data.offsets.arrow, data.arrowStyles);

    return data;
  }

  /**
   * Helper used to know if the given modifier depends from another one.<br />
   * It checks if the needed modifier is listed and enabled.
   * @method
   * @memberof Popper.Utils
   * @param {Array} modifiers - list of modifiers
   * @param {String} requestingName - name of requesting modifier
   * @param {String} requestedName - name of requested modifier
   * @returns {Boolean}
   */
  function isModifierRequired(modifiers, requestingName, requestedName) {
    var requesting = find(modifiers, function (_ref) {
      var name = _ref.name;
      return name === requestingName;
    });

    var isRequired = !!requesting && modifiers.some(function (modifier) {
      return modifier.name === requestedName && modifier.enabled && modifier.order < requesting.order;
    });

    if (!isRequired) {
      var _requesting = '`' + requestingName + '`';
      var requested = '`' + requestedName + '`';
      console.warn(requested + ' modifier is required by ' + _requesting + ' modifier in order to work, be sure to include it before ' + _requesting + '!');
    }
    return isRequired;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function arrow(data, options) {
    // arrow depends on keepTogether in order to work
    if (!isModifierRequired(data.instance.modifiers, 'arrow', 'keepTogether')) {
      return data;
    }

    var arrowElement = options.element;

    // if arrowElement is a string, suppose it's a CSS selector
    if (typeof arrowElement === 'string') {
      arrowElement = data.instance.popper.querySelector(arrowElement);

      // if arrowElement is not found, don't run the modifier
      if (!arrowElement) {
        return data;
      }
    } else {
      // if the arrowElement isn't a query selector we must check that the
      // provided DOM node is child of its popper node
      if (!data.instance.popper.contains(arrowElement)) {
        console.warn('WARNING: `arrow.element` must be child of its popper element!');
        return data;
      }
    }

    var placement = data.placement.split('-')[0];
    var _data$offsets = data.offsets,
      popper = _data$offsets.popper,
      reference = _data$offsets.reference;

    var isVertical = ['left', 'right'].indexOf(placement) !== -1;

    var len = isVertical ? 'height' : 'width';
    var sideCapitalized = isVertical ? 'Top' : 'Left';
    var side = sideCapitalized.toLowerCase();
    var altSide = isVertical ? 'left' : 'top';
    var opSide = isVertical ? 'bottom' : 'right';
    var arrowElementSize = getOuterSizes(arrowElement)[len];

    //
    // extends keepTogether behavior making sure the popper and its
    // reference have enough pixels in conjunction
    //

    // top/left side
    if (reference[opSide] - arrowElementSize < popper[side]) {
      data.offsets.popper[side] -= popper[side] - (reference[opSide] - arrowElementSize);
    }
    // bottom/right side
    if (reference[side] + arrowElementSize > popper[opSide]) {
      data.offsets.popper[side] += reference[side] + arrowElementSize - popper[opSide];
    }

    // compute center of the popper
    var center = reference[side] + reference[len] / 2 - arrowElementSize / 2;

    // Compute the sideValue using the updated popper offsets
    // take popper margin in account because we don't have this info available
    var popperMarginSide = getStyleComputedProperty(data.instance.popper, 'margin' + sideCapitalized).replace('px', '');
    var sideValue = center - getClientRect(data.offsets.popper)[side] - popperMarginSide;

    // prevent arrowElement from being placed not contiguously to its popper
    sideValue = Math.max(Math.min(popper[len] - arrowElementSize, sideValue), 0);

    data.arrowElement = arrowElement;
    data.offsets.arrow = {};
    data.offsets.arrow[side] = Math.round(sideValue);
    data.offsets.arrow[altSide] = ''; // make sure to unset any eventual altSide value from the DOM node

    return data;
  }

  /**
   * Get the opposite placement variation of the given one
   * @method
   * @memberof Popper.Utils
   * @argument {String} placement variation
   * @returns {String} flipped placement variation
   */
  function getOppositeVariation(variation) {
    if (variation === 'end') {
      return 'start';
    } else if (variation === 'start') {
      return 'end';
    }
    return variation;
  }

  /**
   * List of accepted placements to use as values of the `placement` option.<br />
   * Valid placements are:
   * - `auto`
   * - `top`
   * - `right`
   * - `bottom`
   * - `left`
   *
   * Each placement can have a variation from this list:
   * - `-start`
   * - `-end`
   *
   * Variations are interpreted easily if you think of them as the left to right
   * written languages. Horizontally (`top` and `bottom`), `start` is left and `end`
   * is right.<br />
   * Vertically (`left` and `right`), `start` is top and `end` is bottom.
   *
   * Some valid examples are:
   * - `top-end` (on top of reference, right aligned)
   * - `right-start` (on right of reference, top aligned)
   * - `bottom` (on bottom, centered)
   * - `auto-right` (on the side with more space available, alignment depends by placement)
   *
   * @static
   * @type {Array}
   * @enum {String}
   * @readonly
   * @method placements
   * @memberof Popper
   */
  var placements = ['auto-start', 'auto', 'auto-end', 'top-start', 'top', 'top-end', 'right-start', 'right', 'right-end', 'bottom-end', 'bottom', 'bottom-start', 'left-end', 'left', 'left-start'];

  // Get rid of `auto` `auto-start` and `auto-end`
  var validPlacements = placements.slice(3);

  /**
   * Given an initial placement, returns all the subsequent placements
   * clockwise (or counter-clockwise).
   *
   * @method
   * @memberof Popper.Utils
   * @argument {String} placement - A valid placement (it accepts variations)
   * @argument {Boolean} counter - Set to true to walk the placements counterclockwise
   * @returns {Array} placements including their variations
   */
  function clockwise(placement) {
    var counter = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : false;

    var index = validPlacements.indexOf(placement);
    var arr = validPlacements.slice(index + 1).concat(validPlacements.slice(0, index));
    return counter ? arr.reverse() : arr;
  }

  var BEHAVIORS = {
    FLIP: 'flip',
    CLOCKWISE: 'clockwise',
    COUNTERCLOCKWISE: 'counterclockwise'
  };

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function flip(data, options) {
    // if `inner` modifier is enabled, we can't use the `flip` modifier
    if (isModifierEnabled(data.instance.modifiers, 'inner')) {
      return data;
    }

    if (data.flipped && data.placement === data.originalPlacement) {
      // seems like flip is trying to loop, probably there's not enough space on any of the flippable sides
      return data;
    }

    var boundaries = getBoundaries(data.instance.popper, data.instance.reference, options.padding, options.boundariesElement);

    var placement = data.placement.split('-')[0];
    var placementOpposite = getOppositePlacement(placement);
    var variation = data.placement.split('-')[1] || '';

    var flipOrder = [];

    switch (options.behavior) {
      case BEHAVIORS.FLIP:
        flipOrder = [placement, placementOpposite];
        break;
      case BEHAVIORS.CLOCKWISE:
        flipOrder = clockwise(placement);
        break;
      case BEHAVIORS.COUNTERCLOCKWISE:
        flipOrder = clockwise(placement, true);
        break;
      default:
        flipOrder = options.behavior;
    }

    flipOrder.forEach(function (step, index) {
      if (placement !== step || flipOrder.length === index + 1) {
        return data;
      }

      placement = data.placement.split('-')[0];
      placementOpposite = getOppositePlacement(placement);

      var popperOffsets = data.offsets.popper;
      var refOffsets = data.offsets.reference;

      // using floor because the reference offsets may contain decimals we are not going to consider here
      var floor = Math.floor;
      var overlapsRef = placement === 'left' && floor(popperOffsets.right) > floor(refOffsets.left) || placement === 'right' && floor(popperOffsets.left) < floor(refOffsets.right) || placement === 'top' && floor(popperOffsets.bottom) > floor(refOffsets.top) || placement === 'bottom' && floor(popperOffsets.top) < floor(refOffsets.bottom);

      var overflowsLeft = floor(popperOffsets.left) < floor(boundaries.left);
      var overflowsRight = floor(popperOffsets.right) > floor(boundaries.right);
      var overflowsTop = floor(popperOffsets.top) < floor(boundaries.top);
      var overflowsBottom = floor(popperOffsets.bottom) > floor(boundaries.bottom);

      var overflowsBoundaries = placement === 'left' && overflowsLeft || placement === 'right' && overflowsRight || placement === 'top' && overflowsTop || placement === 'bottom' && overflowsBottom;

      // flip the variation if required
      var isVertical = ['top', 'bottom'].indexOf(placement) !== -1;
      var flippedVariation = !!options.flipVariations && (isVertical && variation === 'start' && overflowsLeft || isVertical && variation === 'end' && overflowsRight || !isVertical && variation === 'start' && overflowsTop || !isVertical && variation === 'end' && overflowsBottom);

      if (overlapsRef || overflowsBoundaries || flippedVariation) {
        // this boolean to detect any flip loop
        data.flipped = true;

        if (overlapsRef || overflowsBoundaries) {
          placement = flipOrder[index + 1];
        }

        if (flippedVariation) {
          variation = getOppositeVariation(variation);
        }

        data.placement = placement + (variation ? '-' + variation : '');

        // this object contains `position`, we want to preserve it along with
        // any additional property we may add in the future
        data.offsets.popper = _extends({}, data.offsets.popper, getPopperOffsets(data.instance.popper, data.offsets.reference, data.placement));

        data = runModifiers(data.instance.modifiers, data, 'flip');
      }
    });
    return data;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function keepTogether(data) {
    var _data$offsets = data.offsets,
      popper = _data$offsets.popper,
      reference = _data$offsets.reference;

    var placement = data.placement.split('-')[0];
    var floor = Math.floor;
    var isVertical = ['top', 'bottom'].indexOf(placement) !== -1;
    var side = isVertical ? 'right' : 'bottom';
    var opSide = isVertical ? 'left' : 'top';
    var measurement = isVertical ? 'width' : 'height';

    if (popper[side] < floor(reference[opSide])) {
      data.offsets.popper[opSide] = floor(reference[opSide]) - popper[measurement];
    }
    if (popper[opSide] > floor(reference[side])) {
      data.offsets.popper[opSide] = floor(reference[side]);
    }

    return data;
  }

  /**
   * Converts a string containing value + unit into a px value number
   * @function
   * @memberof {modifiers~offset}
   * @private
   * @argument {String} str - Value + unit string
   * @argument {String} measurement - `height` or `width`
   * @argument {Object} popperOffsets
   * @argument {Object} referenceOffsets
   * @returns {Number|String}
   * Value in pixels, or original string if no values were extracted
   */
  function toValue(str, measurement, popperOffsets, referenceOffsets) {
    // separate value from unit
    var split = str.match(/((?:\-|\+)?\d*\.?\d*)(.*)/);
    var value = +split[1];
    var unit = split[2];

    // If it's not a number it's an operator, I guess
    if (!value) {
      return str;
    }

    if (unit.indexOf('%') === 0) {
      var element = void 0;
      switch (unit) {
        case '%p':
          element = popperOffsets;
          break;
        case '%':
        case '%r':
        default:
          element = referenceOffsets;
      }

      var rect = getClientRect(element);
      return rect[measurement] / 100 * value;
    } else if (unit === 'vh' || unit === 'vw') {
      // if is a vh or vw, we calculate the size based on the viewport
      var size = void 0;
      if (unit === 'vh') {
        size = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
      } else {
        size = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);
      }
      return size / 100 * value;
    } else {
      // if is an explicit pixel unit, we get rid of the unit and keep the value
      // if is an implicit unit, it's px, and we return just the value
      return value;
    }
  }

  /**
   * Parse an `offset` string to extrapolate `x` and `y` numeric offsets.
   * @function
   * @memberof {modifiers~offset}
   * @private
   * @argument {String} offset
   * @argument {Object} popperOffsets
   * @argument {Object} referenceOffsets
   * @argument {String} basePlacement
   * @returns {Array} a two cells array with x and y offsets in numbers
   */
  function parseOffset(offset, popperOffsets, referenceOffsets, basePlacement) {
    var offsets = [0, 0];

    // Use height if placement is left or right and index is 0 otherwise use width
    // in this way the first offset will use an axis and the second one
    // will use the other one
    var useHeight = ['right', 'left'].indexOf(basePlacement) !== -1;

    // Split the offset string to obtain a list of values and operands
    // The regex addresses values with the plus or minus sign in front (+10, -20, etc)
    var fragments = offset.split(/(\+|\-)/).map(function (frag) {
      return frag.trim();
    });

    // Detect if the offset string contains a pair of values or a single one
    // they could be separated by comma or space
    var divider = fragments.indexOf(find(fragments, function (frag) {
      return frag.search(/,|\s/) !== -1;
    }));

    if (fragments[divider] && fragments[divider].indexOf(',') === -1) {
      console.warn('Offsets separated by white space(s) are deprecated, use a comma (,) instead.');
    }

    // If divider is found, we divide the list of values and operands to divide
    // them by ofset X and Y.
    var splitRegex = /\s*,\s*|\s+/;
    var ops = divider !== -1 ? [fragments.slice(0, divider).concat([fragments[divider].split(splitRegex)[0]]), [fragments[divider].split(splitRegex)[1]].concat(fragments.slice(divider + 1))] : [fragments];

    // Convert the values with units to absolute pixels to allow our computations
    ops = ops.map(function (op, index) {
      // Most of the units rely on the orientation of the popper
      var measurement = (index === 1 ? !useHeight : useHeight) ? 'height' : 'width';
      var mergeWithPrevious = false;
      return op
        // This aggregates any `+` or `-` sign that aren't considered operators
        // e.g.: 10 + +5 => [10, +, +5]
        .reduce(function (a, b) {
          if (a[a.length - 1] === '' && ['+', '-'].indexOf(b) !== -1) {
            a[a.length - 1] = b;
            mergeWithPrevious = true;
            return a;
          } else if (mergeWithPrevious) {
            a[a.length - 1] += b;
            mergeWithPrevious = false;
            return a;
          } else {
            return a.concat(b);
          }
        }, [])
        // Here we convert the string values into number values (in px)
        .map(function (str) {
          return toValue(str, measurement, popperOffsets, referenceOffsets);
        });
    });

    // Loop trough the offsets arrays and execute the operations
    ops.forEach(function (op, index) {
      op.forEach(function (frag, index2) {
        if (isNumeric(frag)) {
          offsets[index] += frag * (op[index2 - 1] === '-' ? -1 : 1);
        }
      });
    });
    return offsets;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @argument {Number|String} options.offset=0
   * The offset value as described in the modifier description
   * @returns {Object} The data object, properly modified
   */
  function offset(data, _ref) {
    var offset = _ref.offset;
    var placement = data.placement,
      _data$offsets = data.offsets,
      popper = _data$offsets.popper,
      reference = _data$offsets.reference;

    var basePlacement = placement.split('-')[0];

    var offsets = void 0;
    if (isNumeric(+offset)) {
      offsets = [+offset, 0];
    } else {
      offsets = parseOffset(offset, popper, reference, basePlacement);
    }

    if (basePlacement === 'left') {
      popper.top += offsets[0];
      popper.left -= offsets[1];
    } else if (basePlacement === 'right') {
      popper.top += offsets[0];
      popper.left += offsets[1];
    } else if (basePlacement === 'top') {
      popper.left += offsets[0];
      popper.top -= offsets[1];
    } else if (basePlacement === 'bottom') {
      popper.left += offsets[0];
      popper.top += offsets[1];
    }

    data.popper = popper;
    return data;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by `update` method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function preventOverflow(data, options) {
    var boundariesElement = options.boundariesElement || getOffsetParent(data.instance.popper);

    // If offsetParent is the reference element, we really want to
    // go one step up and use the next offsetParent as reference to
    // avoid to make this modifier completely useless and look like broken
    if (data.instance.reference === boundariesElement) {
      boundariesElement = getOffsetParent(boundariesElement);
    }

    var boundaries = getBoundaries(data.instance.popper, data.instance.reference, options.padding, boundariesElement);
    options.boundaries = boundaries;

    var order = options.priority;
    var popper = data.offsets.popper;

    var check = {
      primary: function primary(placement) {
        var value = popper[placement];
        if (popper[placement] < boundaries[placement] && !options.escapeWithReference) {
          value = Math.max(popper[placement], boundaries[placement]);
        }
        return defineProperty({}, placement, value);
      },
      secondary: function secondary(placement) {
        var mainSide = placement === 'right' ? 'left' : 'top';
        var value = popper[mainSide];
        if (popper[placement] > boundaries[placement] && !options.escapeWithReference) {
          value = Math.min(popper[mainSide], boundaries[placement] - (placement === 'right' ? popper.width : popper.height));
        }
        return defineProperty({}, mainSide, value);
      }
    };

    order.forEach(function (placement) {
      var side = ['left', 'top'].indexOf(placement) !== -1 ? 'primary' : 'secondary';
      popper = _extends({}, popper, check[side](placement));
    });

    data.offsets.popper = popper;

    return data;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by `update` method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function shift(data) {
    var placement = data.placement;
    var basePlacement = placement.split('-')[0];
    var shiftvariation = placement.split('-')[1];

    // if shift shiftvariation is specified, run the modifier
    if (shiftvariation) {
      var _data$offsets = data.offsets,
        reference = _data$offsets.reference,
        popper = _data$offsets.popper;

      var isVertical = ['bottom', 'top'].indexOf(basePlacement) !== -1;
      var side = isVertical ? 'left' : 'top';
      var measurement = isVertical ? 'width' : 'height';

      var shiftOffsets = {
        start: defineProperty({}, side, reference[side]),
        end: defineProperty({}, side, reference[side] + reference[measurement] - popper[measurement])
      };

      data.offsets.popper = _extends({}, popper, shiftOffsets[shiftvariation]);
    }

    return data;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by update method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function hide(data) {
    if (!isModifierRequired(data.instance.modifiers, 'hide', 'preventOverflow')) {
      return data;
    }

    var refRect = data.offsets.reference;
    var bound = find(data.instance.modifiers, function (modifier) {
      return modifier.name === 'preventOverflow';
    }).boundaries;

    if (refRect.bottom < bound.top || refRect.left > bound.right || refRect.top > bound.bottom || refRect.right < bound.left) {
      // Avoid unnecessary DOM access if visibility hasn't changed
      if (data.hide === true) {
        return data;
      }

      data.hide = true;
      data.attributes['x-out-of-boundaries'] = '';
    } else {
      // Avoid unnecessary DOM access if visibility hasn't changed
      if (data.hide === false) {
        return data;
      }

      data.hide = false;
      data.attributes['x-out-of-boundaries'] = false;
    }

    return data;
  }

  /**
   * @function
   * @memberof Modifiers
   * @argument {Object} data - The data object generated by `update` method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {Object} The data object, properly modified
   */
  function inner(data) {
    var placement = data.placement;
    var basePlacement = placement.split('-')[0];
    var _data$offsets = data.offsets,
      popper = _data$offsets.popper,
      reference = _data$offsets.reference;

    var isHoriz = ['left', 'right'].indexOf(basePlacement) !== -1;

    var subtractLength = ['top', 'left'].indexOf(basePlacement) === -1;

    popper[isHoriz ? 'left' : 'top'] = reference[basePlacement] - (subtractLength ? popper[isHoriz ? 'width' : 'height'] : 0);

    data.placement = getOppositePlacement(placement);
    data.offsets.popper = getClientRect(popper);

    return data;
  }

  /**
   * Modifier function, each modifier can have a function of this type assigned
   * to its `fn` property.<br />
   * These functions will be called on each update, this means that you must
   * make sure they are performant enough to avoid performance bottlenecks.
   *
   * @function ModifierFn
   * @argument {dataObject} data - The data object generated by `update` method
   * @argument {Object} options - Modifiers configuration and options
   * @returns {dataObject} The data object, properly modified
   */

  /**
   * Modifiers are plugins used to alter the behavior of your poppers.<br />
   * Popper.js uses a set of 9 modifiers to provide all the basic functionalities
   * needed by the library.
   *
   * Usually you don't want to override the `order`, `fn` and `onLoad` props.
   * All the other properties are configurations that could be tweaked.
   * @namespace modifiers
   */
  var modifiers = {
    /**
     * Modifier used to shift the popper on the start or end of its reference
     * element.<br />
     * It will read the variation of the `placement` property.<br />
     * It can be one either `-end` or `-start`.
     * @memberof modifiers
     * @inner
     */
    shift: {
      /** @prop {number} order=100 - Index used to define the order of execution */
      order: 100,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: shift
    },

    /**
     * The `offset` modifier can shift your popper on both its axis.
     *
     * It accepts the following units:
     * - `px` or unitless, interpreted as pixels
     * - `%` or `%r`, percentage relative to the length of the reference element
     * - `%p`, percentage relative to the length of the popper element
     * - `vw`, CSS viewport width unit
     * - `vh`, CSS viewport height unit
     *
     * For length is intended the main axis relative to the placement of the popper.<br />
     * This means that if the placement is `top` or `bottom`, the length will be the
     * `width`. In case of `left` or `right`, it will be the height.
     *
     * You can provide a single value (as `Number` or `String`), or a pair of values
     * as `String` divided by a comma or one (or more) white spaces.<br />
     * The latter is a deprecated method because it leads to confusion and will be
     * removed in v2.<br />
     * Additionally, it accepts additions and subtractions between different units.
     * Note that multiplications and divisions aren't supported.
     *
     * Valid examples are:
     * ```
     * 10
     * '10%'
     * '10, 10'
     * '10%, 10'
     * '10 + 10%'
     * '10 - 5vh + 3%'
     * '-10px + 5vh, 5px - 6%'
     * ```
     * > **NB**: If you desire to apply offsets to your poppers in a way that may make them overlap
     * > with their reference element, unfortunately, you will have to disable the `flip` modifier.
     * > More on this [reading this issue](https://github.com/FezVrasta/popper.js/issues/373)
     *
     * @memberof modifiers
     * @inner
     */
    offset: {
      /** @prop {number} order=200 - Index used to define the order of execution */
      order: 200,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: offset,
      /** @prop {Number|String} offset=0
       * The offset value as described in the modifier description
       */
      offset: 0
    },

    /**
     * Modifier used to prevent the popper from being positioned outside the boundary.
     *
     * An scenario exists where the reference itself is not within the boundaries.<br />
     * We can say it has "escaped the boundaries" — or just "escaped".<br />
     * In this case we need to decide whether the popper should either:
     *
     * - detach from the reference and remain "trapped" in the boundaries, or
     * - if it should ignore the boundary and "escape with its reference"
     *
     * When `escapeWithReference` is set to`true` and reference is completely
     * outside its boundaries, the popper will overflow (or completely leave)
     * the boundaries in order to remain attached to the edge of the reference.
     *
     * @memberof modifiers
     * @inner
     */
    preventOverflow: {
      /** @prop {number} order=300 - Index used to define the order of execution */
      order: 300,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: preventOverflow,
      /**
       * @prop {Array} [priority=['left','right','top','bottom']]
       * Popper will try to prevent overflow following these priorities by default,
       * then, it could overflow on the left and on top of the `boundariesElement`
       */
      priority: ['left', 'right', 'top', 'bottom'],
      /**
       * @prop {number} padding=5
       * Amount of pixel used to define a minimum distance between the boundaries
       * and the popper this makes sure the popper has always a little padding
       * between the edges of its container
       */
      padding: 5,
      /**
       * @prop {String|HTMLElement} boundariesElement='scrollParent'
       * Boundaries used by the modifier, can be `scrollParent`, `window`,
       * `viewport` or any DOM element.
       */
      boundariesElement: 'scrollParent'
    },

    /**
     * Modifier used to make sure the reference and its popper stay near eachothers
     * without leaving any gap between the two. Expecially useful when the arrow is
     * enabled and you want to assure it to point to its reference element.
     * It cares only about the first axis, you can still have poppers with margin
     * between the popper and its reference element.
     * @memberof modifiers
     * @inner
     */
    keepTogether: {
      /** @prop {number} order=400 - Index used to define the order of execution */
      order: 400,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: keepTogether
    },

    /**
     * This modifier is used to move the `arrowElement` of the popper to make
     * sure it is positioned between the reference element and its popper element.
     * It will read the outer size of the `arrowElement` node to detect how many
     * pixels of conjunction are needed.
     *
     * It has no effect if no `arrowElement` is provided.
     * @memberof modifiers
     * @inner
     */
    arrow: {
      /** @prop {number} order=500 - Index used to define the order of execution */
      order: 500,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: arrow,
      /** @prop {String|HTMLElement} element='[x-arrow]' - Selector or node used as arrow */
      element: '[x-arrow]'
    },

    /**
     * Modifier used to flip the popper's placement when it starts to overlap its
     * reference element.
     *
     * Requires the `preventOverflow` modifier before it in order to work.
     *
     * **NOTE:** this modifier will interrupt the current update cycle and will
     * restart it if it detects the need to flip the placement.
     * @memberof modifiers
     * @inner
     */
    flip: {
      /** @prop {number} order=600 - Index used to define the order of execution */
      order: 600,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: flip,
      /**
       * @prop {String|Array} behavior='flip'
       * The behavior used to change the popper's placement. It can be one of
       * `flip`, `clockwise`, `counterclockwise` or an array with a list of valid
       * placements (with optional variations).
       */
      behavior: 'flip',
      /**
       * @prop {number} padding=5
       * The popper will flip if it hits the edges of the `boundariesElement`
       */
      padding: 5,
      /**
       * @prop {String|HTMLElement} boundariesElement='viewport'
       * The element which will define the boundaries of the popper position,
       * the popper will never be placed outside of the defined boundaries
       * (except if keepTogether is enabled)
       */
      boundariesElement: 'viewport'
    },

    /**
     * Modifier used to make the popper flow toward the inner of the reference element.
     * By default, when this modifier is disabled, the popper will be placed outside
     * the reference element.
     * @memberof modifiers
     * @inner
     */
    inner: {
      /** @prop {number} order=700 - Index used to define the order of execution */
      order: 700,
      /** @prop {Boolean} enabled=false - Whether the modifier is enabled or not */
      enabled: false,
      /** @prop {ModifierFn} */
      fn: inner
    },

    /**
     * Modifier used to hide the popper when its reference element is outside of the
     * popper boundaries. It will set a `x-out-of-boundaries` attribute which can
     * be used to hide with a CSS selector the popper when its reference is
     * out of boundaries.
     *
     * Requires the `preventOverflow` modifier before it in order to work.
     * @memberof modifiers
     * @inner
     */
    hide: {
      /** @prop {number} order=800 - Index used to define the order of execution */
      order: 800,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: hide
    },

    /**
     * Computes the style that will be applied to the popper element to gets
     * properly positioned.
     *
     * Note that this modifier will not touch the DOM, it just prepares the styles
     * so that `applyStyle` modifier can apply it. This separation is useful
     * in case you need to replace `applyStyle` with a custom implementation.
     *
     * This modifier has `850` as `order` value to maintain backward compatibility
     * with previous versions of Popper.js. Expect the modifiers ordering method
     * to change in future major versions of the library.
     *
     * @memberof modifiers
     * @inner
     */
    computeStyle: {
      /** @prop {number} order=850 - Index used to define the order of execution */
      order: 850,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: computeStyle,
      /**
       * @prop {Boolean} gpuAcceleration=true
       * If true, it uses the CSS 3d transformation to position the popper.
       * Otherwise, it will use the `top` and `left` properties.
       */
      gpuAcceleration: true,
      /**
       * @prop {string} [x='bottom']
       * Where to anchor the X axis (`bottom` or `top`). AKA X offset origin.
       * Change this if your popper should grow in a direction different from `bottom`
       */
      x: 'bottom',
      /**
       * @prop {string} [x='left']
       * Where to anchor the Y axis (`left` or `right`). AKA Y offset origin.
       * Change this if your popper should grow in a direction different from `right`
       */
      y: 'right'
    },

    /**
     * Applies the computed styles to the popper element.
     *
     * All the DOM manipulations are limited to this modifier. This is useful in case
     * you want to integrate Popper.js inside a framework or view library and you
     * want to delegate all the DOM manipulations to it.
     *
     * Note that if you disable this modifier, you must make sure the popper element
     * has its position set to `absolute` before Popper.js can do its work!
     *
     * Just disable this modifier and define you own to achieve the desired effect.
     *
     * @memberof modifiers
     * @inner
     */
    applyStyle: {
      /** @prop {number} order=900 - Index used to define the order of execution */
      order: 900,
      /** @prop {Boolean} enabled=true - Whether the modifier is enabled or not */
      enabled: true,
      /** @prop {ModifierFn} */
      fn: applyStyle,
      /** @prop {Function} */
      onLoad: applyStyleOnLoad,
      /**
       * @deprecated since version 1.10.0, the property moved to `computeStyle` modifier
       * @prop {Boolean} gpuAcceleration=true
       * If true, it uses the CSS 3d transformation to position the popper.
       * Otherwise, it will use the `top` and `left` properties.
       */
      gpuAcceleration: undefined
    }
  };

  /**
   * The `dataObject` is an object containing all the informations used by Popper.js
   * this object get passed to modifiers and to the `onCreate` and `onUpdate` callbacks.
   * @name dataObject
   * @property {Object} data.instance The Popper.js instance
   * @property {String} data.placement Placement applied to popper
   * @property {String} data.originalPlacement Placement originally defined on init
   * @property {Boolean} data.flipped True if popper has been flipped by flip modifier
   * @property {Boolean} data.hide True if the reference element is out of boundaries, useful to know when to hide the popper.
   * @property {HTMLElement} data.arrowElement Node used as arrow by arrow modifier
   * @property {Object} data.styles Any CSS property defined here will be applied to the popper, it expects the JavaScript nomenclature (eg. `marginBottom`)
   * @property {Object} data.arrowStyles Any CSS property defined here will be applied to the popper arrow, it expects the JavaScript nomenclature (eg. `marginBottom`)
   * @property {Object} data.boundaries Offsets of the popper boundaries
   * @property {Object} data.offsets The measurements of popper, reference and arrow elements.
   * @property {Object} data.offsets.popper `top`, `left`, `width`, `height` values
   * @property {Object} data.offsets.reference `top`, `left`, `width`, `height` values
   * @property {Object} data.offsets.arrow] `top` and `left` offsets, only one of them will be different from 0
   */

  /**
   * Default options provided to Popper.js constructor.<br />
   * These can be overriden using the `options` argument of Popper.js.<br />
   * To override an option, simply pass as 3rd argument an object with the same
   * structure of this object, example:
   * ```
   * new Popper(ref, pop, {
   *   modifiers: {
   *     preventOverflow: { enabled: false }
   *   }
   * })
   * ```
   * @type {Object}
   * @static
   * @memberof Popper
   */
  var Defaults = {
    /**
     * Popper's placement
     * @prop {Popper.placements} placement='bottom'
     */
    placement: 'bottom',

    /**
     * Whether events (resize, scroll) are initially enabled
     * @prop {Boolean} eventsEnabled=true
     */
    eventsEnabled: true,

    /**
     * Set to true if you want to automatically remove the popper when
     * you call the `destroy` method.
     * @prop {Boolean} removeOnDestroy=false
     */
    removeOnDestroy: false,

    /**
     * Callback called when the popper is created.<br />
     * By default, is set to no-op.<br />
     * Access Popper.js instance with `data.instance`.
     * @prop {onCreate}
     */
    onCreate: function onCreate() { },

    /**
     * Callback called when the popper is updated, this callback is not called
     * on the initialization/creation of the popper, but only on subsequent
     * updates.<br />
     * By default, is set to no-op.<br />
     * Access Popper.js instance with `data.instance`.
     * @prop {onUpdate}
     */
    onUpdate: function onUpdate() { },

    /**
     * List of modifiers used to modify the offsets before they are applied to the popper.
     * They provide most of the functionalities of Popper.js
     * @prop {modifiers}
     */
    modifiers: modifiers
  };

  /**
   * @callback onCreate
   * @param {dataObject} data
   */

  /**
   * @callback onUpdate
   * @param {dataObject} data
   */

  // Utils
  // Methods
  var Popper = function () {
    /**
     * Create a new Popper.js instance
     * @class Popper
     * @param {HTMLElement|referenceObject} reference - The reference element used to position the popper
     * @param {HTMLElement} popper - The HTML element used as popper.
     * @param {Object} options - Your custom options to override the ones defined in [Defaults](#defaults)
     * @return {Object} instance - The generated Popper.js instance
     */
    function Popper(reference, popper) {
      var _this = this;

      var options = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};
      classCallCheck(this, Popper);

      this.scheduleUpdate = function () {
        return requestAnimationFrame(_this.update);
      };

      // make update() debounced, so that it only runs at most once-per-tick
      this.update = debounce(this.update.bind(this));

      // with {} we create a new object with the options inside it
      this.options = _extends({}, Popper.Defaults, options);

      // init state
      this.state = {
        isDestroyed: false,
        isCreated: false,
        scrollParents: []
      };

      // get reference and popper elements (allow jQuery wrappers)
      this.reference = reference.jquery ? reference[0] : reference;
      this.popper = popper.jquery ? popper[0] : popper;

      // Deep merge modifiers options
      this.options.modifiers = {};
      Object.keys(_extends({}, Popper.Defaults.modifiers, options.modifiers)).forEach(function (name) {
        _this.options.modifiers[name] = _extends({}, Popper.Defaults.modifiers[name] || {}, options.modifiers ? options.modifiers[name] : {});
      });

      // Refactoring modifiers' list (Object => Array)
      this.modifiers = Object.keys(this.options.modifiers).map(function (name) {
        return _extends({
          name: name
        }, _this.options.modifiers[name]);
      })
        // sort the modifiers by order
        .sort(function (a, b) {
          return a.order - b.order;
        });

      // modifiers have the ability to execute arbitrary code when Popper.js get inited
      // such code is executed in the same order of its modifier
      // they could add new properties to their options configuration
      // BE AWARE: don't add options to `options.modifiers.name` but to `modifierOptions`!
      this.modifiers.forEach(function (modifierOptions) {
        if (modifierOptions.enabled && isFunction(modifierOptions.onLoad)) {
          modifierOptions.onLoad(_this.reference, _this.popper, _this.options, modifierOptions, _this.state);
        }
      });

      // fire the first update to position the popper in the right place
      this.update();

      var eventsEnabled = this.options.eventsEnabled;
      if (eventsEnabled) {
        // setup event listeners, they will take care of update the position in specific situations
        this.enableEventListeners();
      }

      this.state.eventsEnabled = eventsEnabled;
    }

    // We can't use class properties because they don't get listed in the
    // class prototype and break stuff like Sinon stubs


    createClass(Popper, [{
      key: 'update',
      value: function update$$1() {
        return update.call(this);
      }
    }, {
      key: 'destroy',
      value: function destroy$$1() {
        return destroy.call(this);
      }
    }, {
      key: 'enableEventListeners',
      value: function enableEventListeners$$1() {
        return enableEventListeners.call(this);
      }
    }, {
      key: 'disableEventListeners',
      value: function disableEventListeners$$1() {
        return disableEventListeners.call(this);
      }

      /**
       * Schedule an update, it will run on the next UI update available
       * @method scheduleUpdate
       * @memberof Popper
       */


      /**
       * Collection of utilities useful when writing custom modifiers.
       * Starting from version 1.7, this method is available only if you
       * include `popper-utils.js` before `popper.js`.
       *
       * **DEPRECATION**: This way to access PopperUtils is deprecated
       * and will be removed in v2! Use the PopperUtils module directly instead.
       * Due to the high instability of the methods contained in Utils, we can't
       * guarantee them to follow semver. Use them at your own risk!
       * @static
       * @private
       * @type {Object}
       * @deprecated since version 1.8
       * @member Utils
       * @memberof Popper
       */

    }]);
    return Popper;
  }();

  /**
   * The `referenceObject` is an object that provides an interface compatible with Popper.js
   * and lets you use it as replacement of a real DOM node.<br />
   * You can use this method to position a popper relatively to a set of coordinates
   * in case you don't have a DOM node to use as reference.
   *
   * ```
   * new Popper(referenceObject, popperNode);
   * ```
   *
   * NB: This feature isn't supported in Internet Explorer 10
   * @name referenceObject
   * @property {Function} data.getBoundingClientRect
   * A function that returns a set of coordinates compatible with the native `getBoundingClientRect` method.
   * @property {number} data.clientWidth
   * An ES6 getter that will return the width of the virtual reference element.
   * @property {number} data.clientHeight
   * An ES6 getter that will return the height of the virtual reference element.
   */


  Popper.Utils = (typeof window !== 'undefined' ? window : global).PopperUtils;
  Popper.placements = placements;
  Popper.Defaults = Defaults;

  return Popper;

})));
//# sourceMappingURL=popper.js.map

/**
 * --------------------------------------------------------------------------
 * Bootstrap (v4.0.0-beta): util.js
 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
 * --------------------------------------------------------------------------
 */

var Util = function ($) {

  /**
   * ------------------------------------------------------------------------
   * Private TransitionEnd Helpers
   * ------------------------------------------------------------------------
   */

  var transition = false;

  var MAX_UID = 1000000;

  var TransitionEndEvent = {
    WebkitTransition: 'webkitTransitionEnd',
    MozTransition: 'transitionend',
    OTransition: 'oTransitionEnd otransitionend',
    transition: 'transitionend'

    // shoutout AngusCroll (https://goo.gl/pxwQGp)
  }; function toType(obj) {
    return {}.toString.call(obj).match(/\s([a-zA-Z]+)/)[1].toLowerCase();
  }

  function isElement(obj) {
    return (obj[0] || obj).nodeType;
  }

  function getSpecialTransitionEndEvent() {
    return {
      bindType: transition.end,
      delegateType: transition.end,
      handle: function handle(event) {
        if ($(event.target).is(this)) {
          return event.handleObj.handler.apply(this, arguments); // eslint-disable-line prefer-rest-params
        }
        return undefined;
      }
    };
  }

  function transitionEndTest() {
    if (window.QUnit) {
      return false;
    }

    var el = document.createElement('bootstrap');

    for (var name in TransitionEndEvent) {
      if (el.style[name] !== undefined) {
        return {
          end: TransitionEndEvent[name]
        };
      }
    }

    return false;
  }

  function transitionEndEmulator(duration) {
    var _this = this;

    var called = false;

    $(this).one(Util.TRANSITION_END, function () {
      called = true;
    });

    setTimeout(function () {
      if (!called) {
        Util.triggerTransitionEnd(_this);
      }
    }, duration);

    return this;
  }

  function setTransitionEndSupport() {
    transition = transitionEndTest();

    $.fn.emulateTransitionEnd = transitionEndEmulator;

    if (Util.supportsTransitionEnd()) {
      $.event.special[Util.TRANSITION_END] = getSpecialTransitionEndEvent();
    }
  }

  /**
   * --------------------------------------------------------------------------
   * Public Util Api
   * --------------------------------------------------------------------------
   */

  var Util = {

    TRANSITION_END: 'bsTransitionEnd',

    getUID: function getUID(prefix) {
      do {
        // eslint-disable-next-line no-bitwise
        prefix += ~~(Math.random() * MAX_UID); // "~~" acts like a faster Math.floor() here
      } while (document.getElementById(prefix));
      return prefix;
    },
    getSelectorFromElement: function getSelectorFromElement(element) {
      var selector = element.getAttribute('data-target');
      if (!selector || selector === '#') {
        selector = element.getAttribute('href') || '';
      }

      try {
        var $selector = $(selector);
        return $selector.length > 0 ? selector : null;
      } catch (error) {
        return null;
      }
    },
    reflow: function reflow(element) {
      return element.offsetHeight;
    },
    triggerTransitionEnd: function triggerTransitionEnd(element) {
      $(element).trigger(transition.end);
    },
    supportsTransitionEnd: function supportsTransitionEnd() {
      return Boolean(transition);
    },
    typeCheckConfig: function typeCheckConfig(componentName, config, configTypes) {
      for (var property in configTypes) {
        if (configTypes.hasOwnProperty(property)) {
          var expectedTypes = configTypes[property];
          var value = config[property];
          var valueType = value && isElement(value) ? 'element' : toType(value);

          if (!new RegExp(expectedTypes).test(valueType)) {
            throw new Error(componentName.toUpperCase() + ': ' + ('Option "' + property + '" provided type "' + valueType + '" ') + ('but expected type "' + expectedTypes + '".'));
          }
        }
      }
    }
  };

  setTransitionEndSupport();

  return Util;
}(jQuery);
//# sourceMappingURL=util.js.map
var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

/**
 * --------------------------------------------------------------------------
 * Bootstrap (v4.0.0-beta): tooltip.js
 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
 * --------------------------------------------------------------------------
 */

var Tooltip = function ($) {

  /**
   * Check for Popper dependency
   * Popper - https://popper.js.org
   */
  if (typeof Popper === 'undefined') {
    throw new Error('Bootstrap tooltips require Popper.js (https://popper.js.org)');
  }

  /**
   * ------------------------------------------------------------------------
   * Constants
   * ------------------------------------------------------------------------
   */

  var NAME = 'tooltip';
  var VERSION = '4.0.0-beta';
  var DATA_KEY = 'bs.tooltip';
  var EVENT_KEY = '.' + DATA_KEY;
  var JQUERY_NO_CONFLICT = $.fn[NAME];
  var TRANSITION_DURATION = 150;
  var CLASS_PREFIX = 'bs-tooltip';
  var BSCLS_PREFIX_REGEX = new RegExp('(^|\\s)' + CLASS_PREFIX + '\\S+', 'g');

  var DefaultType = {
    animation: 'boolean',
    template: 'string',
    title: '(string|element|function)',
    trigger: 'string',
    delay: '(number|object)',
    html: 'boolean',
    selector: '(string|boolean)',
    placement: '(string|function)',
    offset: '(number|string)',
    container: '(string|element|boolean)',
    fallbackPlacement: '(string|array)'
  };

  var AttachmentMap = {
    AUTO: 'auto',
    TOP: 'top',
    RIGHT: 'right',
    BOTTOM: 'bottom',
    LEFT: 'left'
  };

  var Default = {
    animation: true,
    template: '<div class="tooltip" role="tooltip">' + '<div class="arrow"></div>' + '<div class="tooltip-inner"></div></div>',
    trigger: 'hover focus',
    title: '',
    delay: 0,
    html: false,
    selector: false,
    placement: 'top',
    offset: 0,
    container: false,
    fallbackPlacement: 'flip'
  };

  var HoverState = {
    SHOW: 'show',
    OUT: 'out'
  };

  var Event = {
    HIDE: 'hide' + EVENT_KEY,
    HIDDEN: 'hidden' + EVENT_KEY,
    SHOW: 'show' + EVENT_KEY,
    SHOWN: 'shown' + EVENT_KEY,
    INSERTED: 'inserted' + EVENT_KEY,
    CLICK: 'click' + EVENT_KEY,
    FOCUSIN: 'focusin' + EVENT_KEY,
    FOCUSOUT: 'focusout' + EVENT_KEY,
    MOUSEENTER: 'mouseenter' + EVENT_KEY,
    MOUSELEAVE: 'mouseleave' + EVENT_KEY
  };

  var ClassName = {
    FADE: 'fade',
    SHOW: 'show'
  };

  var Selector = {
    TOOLTIP: '.tooltip',
    TOOLTIP_INNER: '.tooltip-inner',
    ARROW: '.arrow'
  };

  var Trigger = {
    HOVER: 'hover',
    FOCUS: 'focus',
    CLICK: 'click',
    MANUAL: 'manual'

    /**
     * ------------------------------------------------------------------------
     * Class Definition
     * ------------------------------------------------------------------------
     */

  };
  var Tooltip = function () {
    function Tooltip(element, config) {
      _classCallCheck(this, Tooltip);

      // private
      this._isEnabled = true;
      this._timeout = 0;
      this._hoverState = '';
      this._activeTrigger = {};
      this._popper = null;

      // protected
      this.element = element;
      this.config = this._getConfig(config);
      this.tip = null;

      this._setListeners();
    }

    // getters

    // public

    Tooltip.prototype.enable = function enable() {
      this._isEnabled = true;
    };

    Tooltip.prototype.disable = function disable() {
      this._isEnabled = false;
    };

    Tooltip.prototype.toggleEnabled = function toggleEnabled() {
      this._isEnabled = !this._isEnabled;
    };

    Tooltip.prototype.toggle = function toggle(event) {
      if (event) {
        var dataKey = this.constructor.DATA_KEY;
        var context = $(event.currentTarget).data(dataKey);

        if (!context) {
          context = new this.constructor(event.currentTarget, this._getDelegateConfig());
          $(event.currentTarget).data(dataKey, context);
        }

        context._activeTrigger.click = !context._activeTrigger.click;

        if (context._isWithActiveTrigger()) {
          context._enter(null, context);
        } else {
          context._leave(null, context);
        }
      } else {

        if ($(this.getTipElement()).hasClass(ClassName.SHOW)) {
          this._leave(null, this);
          return;
        }

        this._enter(null, this);
      }
    };

    Tooltip.prototype.dispose = function dispose() {
      clearTimeout(this._timeout);

      $.removeData(this.element, this.constructor.DATA_KEY);

      $(this.element).off(this.constructor.EVENT_KEY);
      $(this.element).closest('.modal').off('hide.bs.modal');

      if (this.tip) {
        $(this.tip).remove();
      }

      this._isEnabled = null;
      this._timeout = null;
      this._hoverState = null;
      this._activeTrigger = null;
      if (this._popper !== null) {
        this._popper.destroy();
      }
      this._popper = null;

      this.element = null;
      this.config = null;
      this.tip = null;
    };

    Tooltip.prototype.show = function show() {
      var _this = this;

      if ($(this.element).css('display') === 'none') {
        throw new Error('Please use show on visible elements');
      }

      var showEvent = $.Event(this.constructor.Event.SHOW);
      if (this.isWithContent() && this._isEnabled) {
        $(this.element).trigger(showEvent);

        var isInTheDom = $.contains(this.element.ownerDocument.documentElement, this.element);

        if (showEvent.isDefaultPrevented() || !isInTheDom) {
          return;
        }

        var tip = this.getTipElement();
        var tipId = Util.getUID(this.constructor.NAME);

        tip.setAttribute('id', tipId);
        this.element.setAttribute('aria-describedby', tipId);

        this.setContent();

        if (this.config.animation) {
          $(tip).addClass(ClassName.FADE);
        }

        var placement = typeof this.config.placement === 'function' ? this.config.placement.call(this, tip, this.element) : this.config.placement;

        var attachment = this._getAttachment(placement);
        this.addAttachmentClass(attachment);

        var container = this.config.container === false ? document.body : $(this.config.container);

        $(tip).data(this.constructor.DATA_KEY, this);

        if (!$.contains(this.element.ownerDocument.documentElement, this.tip)) {
          $(tip).appendTo(container);
        }

        $(this.element).trigger(this.constructor.Event.INSERTED);

        this._popper = new Popper(this.element, tip, {
          placement: attachment,
          modifiers: {
            offset: {
              offset: this.config.offset
            },
            flip: {
              behavior: this.config.fallbackPlacement
            },
            arrow: {
              element: Selector.ARROW
            }
          },
          onCreate: function onCreate(data) {
            if (data.originalPlacement !== data.placement) {
              _this._handlePopperPlacementChange(data);
            }
          },
          onUpdate: function onUpdate(data) {
            _this._handlePopperPlacementChange(data);
          }
        });

        $(tip).addClass(ClassName.SHOW);

        // if this is a touch-enabled device we add extra
        // empty mouseover listeners to the body's immediate children;
        // only needed because of broken event delegation on iOS
        // https://www.quirksmode.org/blog/archives/2014/02/mouse_event_bub.html
        if ('ontouchstart' in document.documentElement) {
          $('body').children().on('mouseover', null, $.noop);
        }

        var complete = function complete() {
          if (_this.config.animation) {
            _this._fixTransition();
          }
          var prevHoverState = _this._hoverState;
          _this._hoverState = null;

          $(_this.element).trigger(_this.constructor.Event.SHOWN);

          if (prevHoverState === HoverState.OUT) {
            _this._leave(null, _this);
          }
        };

        if (Util.supportsTransitionEnd() && $(this.tip).hasClass(ClassName.FADE)) {
          $(this.tip).one(Util.TRANSITION_END, complete).emulateTransitionEnd(Tooltip._TRANSITION_DURATION);
        } else {
          complete();
        }
      }
    };

    Tooltip.prototype.hide = function hide(callback) {
      var _this2 = this;

      var tip = this.getTipElement();
      var hideEvent = $.Event(this.constructor.Event.HIDE);
      var complete = function complete() {
        if (_this2._hoverState !== HoverState.SHOW && tip.parentNode) {
          tip.parentNode.removeChild(tip);
        }

        _this2._cleanTipClass();
        _this2.element.removeAttribute('aria-describedby');
        $(_this2.element).trigger(_this2.constructor.Event.HIDDEN);
        if (_this2._popper !== null) {
          _this2._popper.destroy();
        }

        if (callback) {
          callback();
        }
      };

      $(this.element).trigger(hideEvent);

      if (hideEvent.isDefaultPrevented()) {
        return;
      }

      $(tip).removeClass(ClassName.SHOW);

      // if this is a touch-enabled device we remove the extra
      // empty mouseover listeners we added for iOS support
      if ('ontouchstart' in document.documentElement) {
        $('body').children().off('mouseover', null, $.noop);
      }

      this._activeTrigger[Trigger.CLICK] = false;
      this._activeTrigger[Trigger.FOCUS] = false;
      this._activeTrigger[Trigger.HOVER] = false;

      if (Util.supportsTransitionEnd() && $(this.tip).hasClass(ClassName.FADE)) {

        $(tip).one(Util.TRANSITION_END, complete).emulateTransitionEnd(TRANSITION_DURATION);
      } else {
        complete();
      }

      this._hoverState = '';
    };

    Tooltip.prototype.update = function update() {
      if (this._popper !== null) {
        this._popper.scheduleUpdate();
      }
    };

    // protected

    Tooltip.prototype.isWithContent = function isWithContent() {
      return Boolean(this.getTitle());
    };

    Tooltip.prototype.addAttachmentClass = function addAttachmentClass(attachment) {
      $(this.getTipElement()).addClass(CLASS_PREFIX + '-' + attachment);
    };

    Tooltip.prototype.getTipElement = function getTipElement() {
      return this.tip = this.tip || $(this.config.template)[0];
    };

    Tooltip.prototype.setContent = function setContent() {
      var $tip = $(this.getTipElement());
      this.setElementContent($tip.find(Selector.TOOLTIP_INNER), this.getTitle());
      $tip.removeClass(ClassName.FADE + ' ' + ClassName.SHOW);
    };

    Tooltip.prototype.setElementContent = function setElementContent($element, content) {
      var html = this.config.html;
      if ((typeof content === 'undefined' ? 'undefined' : _typeof(content)) === 'object' && (content.nodeType || content.jquery)) {
        // content is a DOM node or a jQuery
        if (html) {
          if (!$(content).parent().is($element)) {
            $element.empty().append(content);
          }
        } else {
          $element.text($(content).text());
        }
      } else {
        $element[html ? 'html' : 'text'](content);
      }
    };

    Tooltip.prototype.getTitle = function getTitle() {
      var title = this.element.getAttribute('data-original-title');

      if (!title) {
        title = typeof this.config.title === 'function' ? this.config.title.call(this.element) : this.config.title;
      }

      return title;
    };

    // private

    Tooltip.prototype._getAttachment = function _getAttachment(placement) {
      return AttachmentMap[placement.toUpperCase()];
    };

    Tooltip.prototype._setListeners = function _setListeners() {
      var _this3 = this;

      var triggers = this.config.trigger.split(' ');

      triggers.forEach(function (trigger) {
        if (trigger === 'click') {
          $(_this3.element).on(_this3.constructor.Event.CLICK, _this3.config.selector, function (event) {
            return _this3.toggle(event);
          });
        } else if (trigger !== Trigger.MANUAL) {
          var eventIn = trigger === Trigger.HOVER ? _this3.constructor.Event.MOUSEENTER : _this3.constructor.Event.FOCUSIN;
          var eventOut = trigger === Trigger.HOVER ? _this3.constructor.Event.MOUSELEAVE : _this3.constructor.Event.FOCUSOUT;

          $(_this3.element).on(eventIn, _this3.config.selector, function (event) {
            return _this3._enter(event);
          }).on(eventOut, _this3.config.selector, function (event) {
            return _this3._leave(event);
          });
        }

        $(_this3.element).closest('.modal').on('hide.bs.modal', function () {
          return _this3.hide();
        });
      });

      if (this.config.selector) {
        this.config = $.extend({}, this.config, {
          trigger: 'manual',
          selector: ''
        });
      } else {
        this._fixTitle();
      }
    };

    Tooltip.prototype._fixTitle = function _fixTitle() {
      var titleType = _typeof(this.element.getAttribute('data-original-title'));
      if (this.element.getAttribute('title') || titleType !== 'string') {
        this.element.setAttribute('data-original-title', this.element.getAttribute('title') || '');
        this.element.setAttribute('title', '');
      }
    };

    Tooltip.prototype._enter = function _enter(event, context) {
      var dataKey = this.constructor.DATA_KEY;

      context = context || $(event.currentTarget).data(dataKey);

      if (!context) {
        context = new this.constructor(event.currentTarget, this._getDelegateConfig());
        $(event.currentTarget).data(dataKey, context);
      }

      if (event) {
        context._activeTrigger[event.type === 'focusin' ? Trigger.FOCUS : Trigger.HOVER] = true;
      }

      if ($(context.getTipElement()).hasClass(ClassName.SHOW) || context._hoverState === HoverState.SHOW) {
        context._hoverState = HoverState.SHOW;
        return;
      }

      clearTimeout(context._timeout);

      context._hoverState = HoverState.SHOW;

      if (!context.config.delay || !context.config.delay.show) {
        context.show();
        return;
      }

      context._timeout = setTimeout(function () {
        if (context._hoverState === HoverState.SHOW) {
          context.show();
        }
      }, context.config.delay.show);
    };

    Tooltip.prototype._leave = function _leave(event, context) {
      var dataKey = this.constructor.DATA_KEY;

      context = context || $(event.currentTarget).data(dataKey);

      if (!context) {
        context = new this.constructor(event.currentTarget, this._getDelegateConfig());
        $(event.currentTarget).data(dataKey, context);
      }

      if (event) {
        context._activeTrigger[event.type === 'focusout' ? Trigger.FOCUS : Trigger.HOVER] = false;
      }

      if (context._isWithActiveTrigger()) {
        return;
      }

      clearTimeout(context._timeout);

      context._hoverState = HoverState.OUT;

      if (!context.config.delay || !context.config.delay.hide) {
        context.hide();
        return;
      }

      context._timeout = setTimeout(function () {
        if (context._hoverState === HoverState.OUT) {
          context.hide();
        }
      }, context.config.delay.hide);
    };

    Tooltip.prototype._isWithActiveTrigger = function _isWithActiveTrigger() {
      for (var trigger in this._activeTrigger) {
        if (this._activeTrigger[trigger]) {
          return true;
        }
      }

      return false;
    };

    Tooltip.prototype._getConfig = function _getConfig(config) {
      config = $.extend({}, this.constructor.Default, $(this.element).data(), config);

      if (config.delay && typeof config.delay === 'number') {
        config.delay = {
          show: config.delay,
          hide: config.delay
        };
      }

      if (config.title && typeof config.title === 'number') {
        config.title = config.title.toString();
      }

      if (config.content && typeof config.content === 'number') {
        config.content = config.content.toString();
      }

      Util.typeCheckConfig(NAME, config, this.constructor.DefaultType);

      return config;
    };

    Tooltip.prototype._getDelegateConfig = function _getDelegateConfig() {
      var config = {};

      if (this.config) {
        for (var key in this.config) {
          if (this.constructor.Default[key] !== this.config[key]) {
            config[key] = this.config[key];
          }
        }
      }

      return config;
    };

    Tooltip.prototype._cleanTipClass = function _cleanTipClass() {
      var $tip = $(this.getTipElement());
      var tabClass = $tip.attr('class').match(BSCLS_PREFIX_REGEX);
      if (tabClass !== null && tabClass.length > 0) {
        $tip.removeClass(tabClass.join(''));
      }
    };

    Tooltip.prototype._handlePopperPlacementChange = function _handlePopperPlacementChange(data) {
      this._cleanTipClass();
      this.addAttachmentClass(this._getAttachment(data.placement));
    };

    Tooltip.prototype._fixTransition = function _fixTransition() {
      var tip = this.getTipElement();
      var initConfigAnimation = this.config.animation;
      if (tip.getAttribute('x-placement') !== null) {
        return;
      }
      $(tip).removeClass(ClassName.FADE);
      this.config.animation = false;
      this.hide();
      this.show();
      this.config.animation = initConfigAnimation;
    };

    // static

    Tooltip._jQueryInterface = function _jQueryInterface(config) {
      return this.each(function () {
        var data = $(this).data(DATA_KEY);
        var _config = (typeof config === 'undefined' ? 'undefined' : _typeof(config)) === 'object' && config;

        if (!data && /dispose|hide/.test(config)) {
          return;
        }

        if (!data) {
          data = new Tooltip(this, _config);
          $(this).data(DATA_KEY, data);
        }

        if (typeof config === 'string') {
          if (data[config] === undefined) {
            throw new Error('No method named "' + config + '"');
          }
          data[config]();
        }
      });
    };

    _createClass(Tooltip, null, [{
      key: 'VERSION',
      get: function get() {
        return VERSION;
      }
    }, {
      key: 'Default',
      get: function get() {
        return Default;
      }
    }, {
      key: 'NAME',
      get: function get() {
        return NAME;
      }
    }, {
      key: 'DATA_KEY',
      get: function get() {
        return DATA_KEY;
      }
    }, {
      key: 'Event',
      get: function get() {
        return Event;
      }
    }, {
      key: 'EVENT_KEY',
      get: function get() {
        return EVENT_KEY;
      }
    }, {
      key: 'DefaultType',
      get: function get() {
        return DefaultType;
      }
    }]);

    return Tooltip;
  }();

  /**
   * ------------------------------------------------------------------------
   * jQuery
   * ------------------------------------------------------------------------
   */

  $.fn[NAME] = Tooltip._jQueryInterface;
  $.fn[NAME].Constructor = Tooltip;
  $.fn[NAME].noConflict = function () {
    $.fn[NAME] = JQUERY_NO_CONFLICT;
    return Tooltip._jQueryInterface;
  };

  return Tooltip;
}(jQuery); /* global Popper */
//# sourceMappingURL=tooltip.js.map
var _typeof = typeof Symbol === "function" && typeof Symbol.iterator === "symbol" ? function (obj) { return typeof obj; } : function (obj) { return obj && typeof Symbol === "function" && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; };

var _createClass = function () { function defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } } return function (Constructor, protoProps, staticProps) { if (protoProps) defineProperties(Constructor.prototype, protoProps); if (staticProps) defineProperties(Constructor, staticProps); return Constructor; }; }();

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _possibleConstructorReturn(self, call) { if (!self) { throw new ReferenceError("this hasn't been initialised - super() hasn't been called"); } return call && (typeof call === "object" || typeof call === "function") ? call : self; }

function _inherits(subClass, superClass) { if (typeof superClass !== "function" && superClass !== null) { throw new TypeError("Super expression must either be null or a function, not " + typeof superClass); } subClass.prototype = Object.create(superClass && superClass.prototype, { constructor: { value: subClass, enumerable: false, writable: true, configurable: true } }); if (superClass) Object.setPrototypeOf ? Object.setPrototypeOf(subClass, superClass) : subClass.__proto__ = superClass; }

/**
 * --------------------------------------------------------------------------
 * Bootstrap (v4.0.0-beta): popover.js
 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
 * --------------------------------------------------------------------------
 */

var Popover = function ($) {

  /**
   * ------------------------------------------------------------------------
   * Constants
   * ------------------------------------------------------------------------
   */

  var NAME = 'popover';
  var VERSION = '4.0.0-beta';
  var DATA_KEY = 'bs.popover';
  var EVENT_KEY = '.' + DATA_KEY;
  var JQUERY_NO_CONFLICT = $.fn[NAME];
  var CLASS_PREFIX = 'bs-popover';
  var BSCLS_PREFIX_REGEX = new RegExp('(^|\\s)' + CLASS_PREFIX + '\\S+', 'g');

  var Default = $.extend({}, Tooltip.Default, {
    placement: 'right',
    trigger: 'click',
    content: '',
    template: '<div class="popover" role="tooltip">' + '<div class="arrow"></div>' + '<h3 class="popover-header"></h3>' + '<div class="popover-body"></div></div>'
  });

  var DefaultType = $.extend({}, Tooltip.DefaultType, {
    content: '(string|element|function)'
  });

  var ClassName = {
    FADE: 'fade',
    SHOW: 'show'
  };

  var Selector = {
    TITLE: '.popover-header',
    CONTENT: '.popover-body'
  };

  var Event = {
    HIDE: 'hide' + EVENT_KEY,
    HIDDEN: 'hidden' + EVENT_KEY,
    SHOW: 'show' + EVENT_KEY,
    SHOWN: 'shown' + EVENT_KEY,
    INSERTED: 'inserted' + EVENT_KEY,
    CLICK: 'click' + EVENT_KEY,
    FOCUSIN: 'focusin' + EVENT_KEY,
    FOCUSOUT: 'focusout' + EVENT_KEY,
    MOUSEENTER: 'mouseenter' + EVENT_KEY,
    MOUSELEAVE: 'mouseleave' + EVENT_KEY

    /**
     * ------------------------------------------------------------------------
     * Class Definition
     * ------------------------------------------------------------------------
     */

  };
  var Popover = function (_Tooltip) {
    _inherits(Popover, _Tooltip);

    function Popover() {
      _classCallCheck(this, Popover);

      return _possibleConstructorReturn(this, _Tooltip.apply(this, arguments));
    }

    // overrides

    Popover.prototype.isWithContent = function isWithContent() {
      return this.getTitle() || this._getContent();
    };

    Popover.prototype.addAttachmentClass = function addAttachmentClass(attachment) {
      $(this.getTipElement()).addClass(CLASS_PREFIX + '-' + attachment);
    };

    Popover.prototype.getTipElement = function getTipElement() {
      return this.tip = this.tip || $(this.config.template)[0];
    };

    Popover.prototype.setContent = function setContent() {
      var $tip = $(this.getTipElement());

      // we use append for html objects to maintain js events
      this.setElementContent($tip.find(Selector.TITLE), this.getTitle());
      this.setElementContent($tip.find(Selector.CONTENT), this._getContent());

      $tip.removeClass(ClassName.FADE + ' ' + ClassName.SHOW);
    };

    // private

    Popover.prototype._getContent = function _getContent() {
      return this.element.getAttribute('data-content') || (typeof this.config.content === 'function' ? this.config.content.call(this.element) : this.config.content);
    };

    Popover.prototype._cleanTipClass = function _cleanTipClass() {
      var $tip = $(this.getTipElement());
      var tabClass = $tip.attr('class').match(BSCLS_PREFIX_REGEX);
      if (tabClass !== null && tabClass.length > 0) {
        $tip.removeClass(tabClass.join(''));
      }
    };

    // static

    Popover._jQueryInterface = function _jQueryInterface(config) {
      return this.each(function () {
        var data = $(this).data(DATA_KEY);
        var _config = (typeof config === 'undefined' ? 'undefined' : _typeof(config)) === 'object' ? config : null;

        if (!data && /destroy|hide/.test(config)) {
          return;
        }

        if (!data) {
          data = new Popover(this, _config);
          $(this).data(DATA_KEY, data);
        }

        if (typeof config === 'string') {
          if (data[config] === undefined) {
            throw new Error('No method named "' + config + '"');
          }
          data[config]();
        }
      });
    };

    _createClass(Popover, null, [{
      key: 'VERSION',


      // getters

      get: function get() {
        return VERSION;
      }
    }, {
      key: 'Default',
      get: function get() {
        return Default;
      }
    }, {
      key: 'NAME',
      get: function get() {
        return NAME;
      }
    }, {
      key: 'DATA_KEY',
      get: function get() {
        return DATA_KEY;
      }
    }, {
      key: 'Event',
      get: function get() {
        return Event;
      }
    }, {
      key: 'EVENT_KEY',
      get: function get() {
        return EVENT_KEY;
      }
    }, {
      key: 'DefaultType',
      get: function get() {
        return DefaultType;
      }
    }]);

    return Popover;
  }(Tooltip);

  /**
   * ------------------------------------------------------------------------
   * jQuery
   * ------------------------------------------------------------------------
   */

  $.fn[NAME] = Popover._jQueryInterface;
  $.fn[NAME].Constructor = Popover;
  $.fn[NAME].noConflict = function () {
    $.fn[NAME] = JQUERY_NO_CONFLICT;
    return Popover._jQueryInterface;
  };

  return Popover;
}(jQuery);
//# sourceMappingURL=popover.js.map
var bind = function (fn, me) { return function () { return fn.apply(me, arguments); }; };

(function (window, factory) {
  if (typeof define === 'function' && define.amd) {
    return define(['jquery'], function (jQuery) {
      return window.Tour = factory(jQuery);
    });
  } else if (typeof exports === 'object') {
    return module.exports = factory(require('jquery'));
  } else {
    return window.Tour = factory(window.jQuery);
  }
})(window, function ($) {
  var Tour, document;
  document = window.document;
  Tour = (function () {
    function Tour(options) {
      this._showPopoverAndOverlay = bind(this._showPopoverAndOverlay, this);
      var storage;
      try {
        storage = window.localStorage;
      } catch (error) {
        storage = false;
      }
      this._options = $.extend({
        name: 'tour',
        steps: [],
        container: 'body',
        autoscroll: true,
        keyboard: true,
        storage: storage,
        debug: false,
        backdrop: false,
        backdropContainer: 'body',
        backdropPadding: 0,
        redirect: true,
        orphan: false,
        duration: false,
        delay: false,
        basePath: '',
        template: '<div class="popover" role="tooltip"> <div class="arrow"></div> <h3 class="popover-header"></h3> <div class="popover-body"></div> <div class="popover-navigation"> <div class="btn-group"> <button class="btn btn-sm btn-outline-secondary" data-role="prev">&laquo; Prev</button> <button class="btn btn-sm btn-outline-secondary" data-role="next">Next &raquo;</button> <button class="btn btn-sm btn-outline-secondary" data-role="pause-resume" data-pause-text="Pause" data-resume-text="Resume">Pause</button> </div> <button class="btn btn-sm btn-outline-secondary" data-role="end">End tour</button> </div> </div>',
        afterSetState: function (key, value) { },
        afterGetState: function (key, value) { },
        afterRemoveState: function (key) { },
        onStart: function (tour) { },
        onEnd: function (tour) { },
        onShow: function (tour) { },
        onShown: function (tour) { },
        onHide: function (tour) { },
        onHidden: function (tour) { },
        onNext: function (tour) { },
        onPrev: function (tour) { },
        onPause: function (tour, duration) { },
        onResume: function (tour, duration) { },
        onRedirectError: function (tour) { }
      }, options);
      this._force = false;
      this._inited = false;
      this._current = null;
      this.backdrops = [];
      this;
    }

    Tour.prototype.addSteps = function (steps) {
      var j, len, step;
      for (j = 0, len = steps.length; j < len; j++) {
        step = steps[j];
        this.addStep(step);
      }
      return this;
    };

    Tour.prototype.addStep = function (step) {
      this._options.steps.push(step);
      return this;
    };

    Tour.prototype.getStep = function (i) {
      if (this._options.steps[i] != null) {
        return $.extend({
          id: "step-" + i,
          path: '',
          host: '',
          placement: 'right',
          title: '',
          content: '<p></p>',
          next: i === this._options.steps.length - 1 ? -1 : i + 1,
          prev: i - 1,
          animation: true,
          container: this._options.container,
          autoscroll: this._options.autoscroll,
          backdrop: this._options.backdrop,
          backdropContainer: this._options.backdropContainer,
          backdropPadding: this._options.backdropPadding,
          redirect: this._options.redirect,
          reflexElement: this._options.steps[i].element,
          backdropElement: this._options.steps[i].element,
          orphan: this._options.orphan,
          duration: this._options.duration,
          delay: this._options.delay,
          template: this._options.template,
          onShow: this._options.onShow,
          onShown: this._options.onShown,
          onHide: this._options.onHide,
          onHidden: this._options.onHidden,
          onNext: this._options.onNext,
          onPrev: this._options.onPrev,
          onPause: this._options.onPause,
          onResume: this._options.onResume,
          onRedirectError: this._options.onRedirectError
        }, this._options.steps[i]);
      }
    };

    Tour.prototype.init = function (force) {
      this._force = force;
      if (this.ended()) {
        this._debug('Tour ended, init prevented.');
        return this;
      }
      this.setCurrentStep();
      this._initMouseNavigation();
      this._initKeyboardNavigation();
      if (this._current !== null) {
        this.showStep(this._current);
      }
      this._inited = true;
      return this;
    };

    Tour.prototype.start = function (force) {
      var promise;
      if (force == null) {
        force = false;
      }
      if (!this._inited) {
        this.init(force);
      }
      if (this._current === null) {
        promise = this._makePromise(this._options.onStart != null ? this._options.onStart(this) : void 0);
        this._callOnPromiseDone(promise, this.showStep, 0);
      }
      return this;
    };

    Tour.prototype.next = function () {
      var promise;
      promise = this.hideStep(this._current, this._current + 1);
      return this._callOnPromiseDone(promise, this._showNextStep);
    };

    Tour.prototype.prev = function () {
      var promise;
      promise = this.hideStep(this._current, this._current - 1);
      return this._callOnPromiseDone(promise, this._showPrevStep);
    };

    Tour.prototype.goTo = function (i) {
      var promise;
      promise = this.hideStep(this._current, i);
      return this._callOnPromiseDone(promise, this.showStep, i);
    };

    Tour.prototype.end = function () {
      var endHelper, promise;
      endHelper = (function (_this) {
        return function (e) {
          $(document).off("click.tour-" + _this._options.name);
          $(document).off("keyup.tour-" + _this._options.name);
          _this._setState('end', 'yes');
          _this._inited = false;
          _this._force = false;
          _this._clearTimer();
          if (_this._options.onEnd != null) {
            return _this._options.onEnd(_this);
          }
        };
      })(this);
      promise = this.hideStep(this._current);
      return this._callOnPromiseDone(promise, endHelper);
    };

    Tour.prototype.ended = function () {
      return !this._force && !!this._getState('end');
    };

    Tour.prototype.restart = function () {
      this._removeState('current_step');
      this._removeState('end');
      this._removeState('redirect_to');
      return this.start();
    };

    Tour.prototype.pause = function () {
      var step;
      step = this.getStep(this._current);
      if (!(step && step.duration)) {
        return this;
      }
      this._paused = true;
      this._duration -= new Date().getTime() - this._start;
      window.clearTimeout(this._timer);
      this._debug("Paused/Stopped step " + (this._current + 1) + " timer (" + this._duration + " remaining).");
      if (step.onPause != null) {
        return step.onPause(this, this._duration);
      }
    };

    Tour.prototype.resume = function () {
      var step;
      step = this.getStep(this._current);
      if (!(step && step.duration)) {
        return this;
      }
      this._paused = false;
      this._start = new Date().getTime();
      this._duration = this._duration || step.duration;
      this._timer = window.setTimeout((function (_this) {
        return function () {
          if (_this._isLast()) {
            return _this.next();
          } else {
            return _this.end();
          }
        };
      })(this), this._duration);
      this._debug("Started step " + (this._current + 1) + " timer with duration " + this._duration);
      if ((step.onResume != null) && this._duration !== step.duration) {
        return step.onResume(this, this._duration);
      }
    };

    Tour.prototype.hideStep = function (i, iNext) {
      var hideDelay, hideStepHelper, promise, step;
      step = this.getStep(i);
      if (!step) {
        return;
      }
      this._clearTimer();
      promise = this._makePromise(step.onHide != null ? step.onHide(this, i) : void 0);
      hideStepHelper = (function (_this) {
        return function (e) {
          var $element, next_step;
          $element = $(step.element);
          if (!$element.data('bs.popover')) {
            $element = $('body');
          }
          $element.popover('dispose').removeClass("tour-" + _this._options.name + "-element tour-" + _this._options.name + "-" + i + "-element").removeData('bs.popover');
          if (step.reflex) {
            $(step.reflexElement).removeClass('tour-step-element-reflex').off((_this._reflexEvent(step.reflex)) + ".tour-" + _this._options.name);
          }
          if (step.backdrop) {
            next_step = (iNext != null) && _this.getStep(iNext);
            if (!next_step || !next_step.backdrop || next_step.backdropElement !== step.backdropElement) {
              _this._hideOverlayElement(step);
            }
          }
          if (step.onHidden != null) {
            return step.onHidden(_this);
          }
        };
      })(this);
      hideDelay = step.delay.hide || step.delay;
      if ({}.toString.call(hideDelay) === '[object Number]' && hideDelay > 0) {
        this._debug("Wait " + hideDelay + " milliseconds to hide the step " + (this._current + 1));
        window.setTimeout((function (_this) {
          return function () {
            return _this._callOnPromiseDone(promise, hideStepHelper);
          };
        })(this), hideDelay);
      } else {
        this._callOnPromiseDone(promise, hideStepHelper);
      }
      return promise;
    };

    Tour.prototype.showStep = function (i) {
      var path, promise, showDelay, showStepHelper, skipToPrevious, step;
      if (this.ended()) {
        this._debug('Tour ended, showStep prevented.');
        return this;
      }
      step = this.getStep(i);
      if (!step) {
        return;
      }
      skipToPrevious = i < this._current;
      promise = this._makePromise(step.onShow != null ? step.onShow(this, i) : void 0);
      this.setCurrentStep(i);
      path = (function () {
        switch ({}.toString.call(step.path)) {
          case '[object Function]':
            return step.path();
          case '[object String]':
            return this._options.basePath + step.path;
          default:
            return step.path;
        }
      }).call(this);
      if (step.redirect && this._isRedirect(step.host, path, document.location)) {
        this._redirect(step, i, path);
        if (!this._isJustPathHashDifferent(step.host, path, document.location)) {
          return;
        }
      }
      showStepHelper = (function (_this) {
        return function (e) {
          if (_this._isOrphan(step)) {
            if (step.orphan === false) {
              _this._debug("Skip the orphan step " + (_this._current + 1) + ".\nOrphan option is false and the element does not exist or is hidden.");
              if (skipToPrevious) {
                _this._showPrevStep();
              } else {
                _this._showNextStep();
              }
              return;
            }
            _this._debug("Show the orphan step " + (_this._current + 1) + ". Orphans option is true.");
          }
          if (step.autoscroll) {
            _this._scrollIntoView(i);
          } else {
            _this._showPopoverAndOverlay(i);
          }
          if (step.duration) {
            return _this.resume();
          }
        };
      })(this);
      showDelay = step.delay.show || step.delay;
      if ({}.toString.call(showDelay) === '[object Number]' && showDelay > 0) {
        this._debug("Wait " + showDelay + " milliseconds to show the step " + (this._current + 1));
        window.setTimeout((function (_this) {
          return function () {
            return _this._callOnPromiseDone(promise, showStepHelper);
          };
        })(this), showDelay);
      } else {
        this._callOnPromiseDone(promise, showStepHelper);
      }
      return promise;
    };

    Tour.prototype.getCurrentStep = function () {
      return this._current;
    };

    Tour.prototype.setCurrentStep = function (value) {
      if (value != null) {
        this._current = value;
        this._setState('current_step', value);
      } else {
        this._current = this._getState('current_step');
        this._current = this._current === null ? null : parseInt(this._current, 10);
      }
      return this;
    };

    Tour.prototype.redraw = function () {
      return this._showOverlayElement(this.getStep(this.getCurrentStep()));
    };

    Tour.prototype._setState = function (key, value) {
      var e, keyName;
      if (this._options.storage) {
        keyName = this._options.name + "_" + key;
        try {
          this._options.storage.setItem(keyName, value);
        } catch (error) {
          e = error;
          if (e.code === DOMException.QUOTA_EXCEEDED_ERR) {
            this._debug('LocalStorage quota exceeded. State storage failed.');
          }
        }
        return this._options.afterSetState(keyName, value);
      } else {
        if (this._state == null) {
          this._state = {};
        }
        return this._state[key] = value;
      }
    };

    Tour.prototype._removeState = function (key) {
      var keyName;
      if (this._options.storage) {
        keyName = this._options.name + "_" + key;
        this._options.storage.removeItem(keyName);
        return this._options.afterRemoveState(keyName);
      } else {
        if (this._state != null) {
          return delete this._state[key];
        }
      }
    };

    Tour.prototype._getState = function (key) {
      var keyName, value;
      if (this._options.storage) {
        keyName = this._options.name + "_" + key;
        value = this._options.storage.getItem(keyName);
      } else {
        if (this._state != null) {
          value = this._state[key];
        }
      }
      if (value === void 0 || value === 'null') {
        value = null;
      }
      this._options.afterGetState(key, value);
      return value;
    };

    Tour.prototype._showNextStep = function () {
      var promise, showNextStepHelper, step;
      step = this.getStep(this._current);
      showNextStepHelper = (function (_this) {
        return function (e) {
          return _this.showStep(step.next);
        };
      })(this);
      promise = this._makePromise(step.onNext != null ? step.onNext(this) : void 0);
      return this._callOnPromiseDone(promise, showNextStepHelper);
    };

    Tour.prototype._showPrevStep = function () {
      var promise, showPrevStepHelper, step;
      step = this.getStep(this._current);
      showPrevStepHelper = (function (_this) {
        return function (e) {
          return _this.showStep(step.prev);
        };
      })(this);
      promise = this._makePromise(step.onPrev != null ? step.onPrev(this) : void 0);
      return this._callOnPromiseDone(promise, showPrevStepHelper);
    };

    Tour.prototype._debug = function (text) {
      if (this._options.debug) {
        return window.console.log("Bootstrap Tour '" + this._options.name + "' | " + text);
      }
    };

    Tour.prototype._isRedirect = function (host, path, location) {
      var currentPath;
      if ((host != null) && host !== '' && (({}.toString.call(host) === '[object RegExp]' && !host.test(location.origin)) || ({}.toString.call(host) === '[object String]' && this._isHostDifferent(host, location)))) {
        return true;
      }
      currentPath = [location.pathname, location.search, location.hash].join('');
      return (path != null) && path !== '' && (({}.toString.call(path) === '[object RegExp]' && !path.test(currentPath)) || ({}.toString.call(path) === '[object String]' && this._isPathDifferent(path, currentPath)));
    };

    Tour.prototype._isHostDifferent = function (host, location) {
      switch ({}.toString.call(host)) {
        case '[object RegExp]':
          return !host.test(location.origin);
        case '[object String]':
          return this._getProtocol(host) !== this._getProtocol(location.href) || this._getHost(host) !== this._getHost(location.href);
        default:
          return true;
      }
    };

    Tour.prototype._isPathDifferent = function (path, currentPath) {
      return this._getPath(path) !== this._getPath(currentPath) || !this._equal(this._getQuery(path), this._getQuery(currentPath)) || !this._equal(this._getHash(path), this._getHash(currentPath));
    };

    Tour.prototype._isJustPathHashDifferent = function (host, path, location) {
      var currentPath;
      if ((host != null) && host !== '') {
        if (this._isHostDifferent(host, location)) {
          return false;
        }
      }
      currentPath = [location.pathname, location.search, location.hash].join('');
      if ({}.toString.call(path) === '[object String]') {
        return this._getPath(path) === this._getPath(currentPath) && this._equal(this._getQuery(path), this._getQuery(currentPath)) && !this._equal(this._getHash(path), this._getHash(currentPath));
      }
      return false;
    };

    Tour.prototype._redirect = function (step, i, path) {
      var href;
      if ($.isFunction(step.redirect)) {
        return step.redirect.call(this, path);
      } else {
        href = {}.toString.call(step.host) === '[object String]' ? "" + step.host + path : path;
        this._debug("Redirect to " + href);
        if (this._getState('redirect_to') === ("" + i)) {
          this._debug("Error redirection loop to " + path);
          this._removeState('redirect_to');
          if (step.onRedirectError != null) {
            return step.onRedirectError(this);
          }
        } else {
          this._setState('redirect_to', "" + i);
          return document.location.href = href;
        }
      }
    };

    Tour.prototype._isOrphan = function (step) {
      return (step.element == null) || !$(step.element).length || $(step.element).is(':hidden') && ($(step.element)[0].namespaceURI !== 'http://www.w3.org/2000/svg');
    };

    Tour.prototype._isLast = function () {
      return this._current < this._options.steps.length - 1;
    };

    Tour.prototype._showPopoverAndOverlay = function (i) {
      var step;
      if (this.getCurrentStep() !== i || this.ended()) {
        return;
      }
      step = this.getStep(i);
      if (step.backdrop) {
        this._showOverlayElement(step);
      }
      this._showPopover(step, i);
      if (step.onShown != null) {
        step.onShown(this);
      }
      return this._debug("Step " + (this._current + 1) + " of " + this._options.steps.length);
    };

    Tour.prototype._showPopover = function (step, i) {
      var $element, $tip, isOrphan, options;
      $(".tour-" + this._options.name).remove();
      options = $.extend({}, this._options);
      isOrphan = this._isOrphan(step);
      step.template = this._template(step, i);
      if (isOrphan) {
        step.element = 'body';
        step.placement = 'top';
      }
      $element = $(step.element);
      $element.addClass("tour-" + this._options.name + "-element tour-" + this._options.name + "-" + i + "-element");
      if (step.options) {
        $.extend(options, step.options);
      }
      if (step.reflex && !isOrphan) {
        $(step.reflexElement).addClass('tour-step-element-reflex').off((this._reflexEvent(step.reflex)) + ".tour-" + this._options.name).on((this._reflexEvent(step.reflex)) + ".tour-" + this._options.name, (function (_this) {
          return function () {
            if (_this._isLast()) {
              return _this.next();
            } else {
              return _this.end();
            }
          };
        })(this));
      }
      $element.popover({
        placement: step.placement,
        trigger: 'manual',
        title: step.title,
        content: step.content,
        html: true,
        animation: step.animation,
        container: step.container,
        template: step.template,
        selector: step.element
      }).popover('show');
      $tip = $($element.data('bs.popover').getTipElement());
      return $tip.attr('id', step.id);
    };

    Tour.prototype._template = function (step, i) {
      var $navigation, $next, $prev, $resume, $template, template;
      template = step.template;
      if (this._isOrphan(step) && {}.toString.call(step.orphan) !== '[object Boolean]') {
        template = step.orphan;
      }
      $template = $.isFunction(template) ? $(template(i, step)) : $(template);
      $navigation = $template.find('.popover-navigation');
      $prev = $navigation.find('[data-role="prev"]');
      $next = $navigation.find('[data-role="next"]');
      $resume = $navigation.find('[data-role="pause-resume"]');
      if (this._isOrphan(step)) {
        $template.addClass('orphan');
      }
      $template.addClass("tour-" + this._options.name + " tour-" + this._options.name + "-" + i);
      if (step.reflex) {
        $template.addClass("tour-" + this._options.name + "-reflex");
      }
      if (step.prev < 0) {
        $prev.addClass('disabled').prop('disabled', true).prop('tabindex', -1);
      }
      if (step.next < 0) {
        $next.addClass('disabled').prop('disabled', true).prop('tabindex', -1);
      }
      if (!step.duration) {
        $resume.remove();
      }
      return $template.clone().wrap('<div>').parent().html();
    };

    Tour.prototype._reflexEvent = function (reflex) {
      if ({}.toString.call(reflex) === '[object Boolean]') {
        return 'click';
      } else {
        return reflex;
      }
    };

    Tour.prototype._scrollIntoView = function (i) {
      var $element, $window, counter, height, offsetTop, scrollTop, step, windowHeight;
      step = this.getStep(i);
      $element = $(step.element);
      if (!$element.length) {
        return this._showPopoverAndOverlay(i);
      }
      $window = $(window);
      offsetTop = $element.offset().top;
      height = $element.outerHeight();
      windowHeight = $window.height();
      scrollTop = 0;
      switch (step.placement) {
        case 'top':
          scrollTop = Math.max(0, offsetTop - (windowHeight / 2));
          break;
        case 'left':
        case 'right':
          scrollTop = Math.max(0, (offsetTop + height / 2) - (windowHeight / 2));
          break;
        case 'bottom':
          scrollTop = Math.max(0, (offsetTop + height) - (windowHeight / 2));
      }
      this._debug("Scroll into view. ScrollTop: " + scrollTop + ". Element offset: " + offsetTop + ". Window height: " + windowHeight + ".");
      counter = 0;
      return $('body, html').stop(true, true).animate({
        scrollTop: Math.ceil(scrollTop)
      }, (function (_this) {
        return function () {
          if (++counter === 2) {
            _this._showPopoverAndOverlay(i);
            return _this._debug("Scroll into view.\nAnimation end element offset: " + ($element.offset().top) + ".\nWindow height: " + ($window.height()) + ".");
          }
        };
      })(this));
    };

    Tour.prototype._initMouseNavigation = function () {
      var _this;
      _this = this;
      return $(document).off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='prev']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='next']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='end']").off("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='pause-resume']").on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='next']", (function (_this) {
        return function (e) {
          e.preventDefault();
          return _this.next();
        };
      })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='prev']", (function (_this) {
        return function (e) {
          e.preventDefault();
          if (_this._current > 0) {
            return _this.prev();
          }
        };
      })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='end']", (function (_this) {
        return function (e) {
          e.preventDefault();
          return _this.end();
        };
      })(this)).on("click.tour-" + this._options.name, ".popover.tour-" + this._options.name + " *[data-role='pause-resume']", function (e) {
        var $this;
        e.preventDefault();
        $this = $(this);
        $this.text(_this._paused ? $this.data('pause-text') : $this.data('resume-text'));
        if (_this._paused) {
          return _this.resume();
        } else {
          return _this.pause();
        }
      });
    };

    Tour.prototype._initKeyboardNavigation = function () {
      if (!this._options.keyboard) {
        return;
      }
      return $(document).on("keyup.tour-" + this._options.name, (function (_this) {
        return function (e) {
          if (!e.which) {
            return;
          }
          switch (e.which) {
            case 39:
              e.preventDefault();
              if (_this._isLast()) {
                return _this.next();
              } else {
                return _this.end();
              }
              break;
            case 37:
              e.preventDefault();
              if (_this._current > 0) {
                return _this.prev();
              }
          }
        };
      })(this));
    };

    Tour.prototype._makePromise = function (result) {
      if (result && $.isFunction(result.then)) {
        return result;
      } else {
        return null;
      }
    };

    Tour.prototype._callOnPromiseDone = function (promise, cb, arg) {
      if (promise) {
        return promise.then((function (_this) {
          return function (e) {
            return cb.call(_this, arg);
          };
        })(this));
      } else {
        return cb.call(this, arg);
      }
    };

    Tour.prototype._showBackground = function (step, data) {
      var $backdrop, base, height, j, len, pos, ref, results, width;
      height = $(document).height();
      width = $(document).width();
      ref = ['top', 'bottom', 'left', 'right'];
      results = [];
      for (j = 0, len = ref.length; j < len; j++) {
        pos = ref[j];
        $backdrop = (base = this.backdrops)[pos] != null ? base[pos] : base[pos] = $('<div>', {
          "class": "tour-backdrop " + pos
        });
        $(step.backdropContainer).append($backdrop);
        switch (pos) {
          case 'top':
            results.push($backdrop.height(data.offset.top > 0 ? data.offset.top : 0).width(width).offset({
              top: 0,
              left: 0
            }));
            break;
          case 'bottom':
            results.push($backdrop.offset({
              top: data.offset.top + data.height,
              left: 0
            }).height(height - (data.offset.top + data.height)).width(width));
            break;
          case 'left':
            results.push($backdrop.offset({
              top: data.offset.top,
              left: 0
            }).height(data.height).width(data.offset.left > 0 ? data.offset.left : 0));
            break;
          case 'right':
            results.push($backdrop.offset({
              top: data.offset.top,
              left: data.offset.left + data.width
            }).height(data.height).width(width - (data.offset.left + data.width)));
            break;
          default:
            results.push(void 0);
        }
      }
      return results;
    };

    Tour.prototype._showOverlayElement = function (step) {
      var $backdropElement, elementData;
      $backdropElement = $(step.backdropElement);
      if ($backdropElement.length === 0) {
        elementData = {
          width: 0,
          height: 0,
          offset: {
            top: 0,
            left: 0
          }
        };
      } else {
        elementData = {
          width: $backdropElement.innerWidth(),
          height: $backdropElement.innerHeight(),
          offset: $backdropElement.offset()
        };
        $backdropElement.addClass('tour-step-backdrop');
        if (step.backdropPadding) {
          elementData = this._applyBackdropPadding(step.backdropPadding, elementData);
        }
      }
      return this._showBackground(step, elementData);
    };

    Tour.prototype._hideOverlayElement = function (step) {
      var $backdrop, pos, ref;
      $(step.backdropElement).removeClass('tour-step-backdrop');
      ref = this.backdrops;
      for (pos in ref) {
        $backdrop = ref[pos];
        if ($backdrop && $backdrop.remove !== void 0) {
          $backdrop.remove();
        }
      }
      return this.backdrops = [];
    };

    Tour.prototype._applyBackdropPadding = function (padding, data) {
      if (typeof padding === 'object') {
        if (padding.top == null) {
          padding.top = 0;
        }
        if (padding.right == null) {
          padding.right = 0;
        }
        if (padding.bottom == null) {
          padding.bottom = 0;
        }
        if (padding.left == null) {
          padding.left = 0;
        }
        data.offset.top = data.offset.top - padding.top;
        data.offset.left = data.offset.left - padding.left;
        data.width = data.width + padding.left + padding.right;
        data.height = data.height + padding.top + padding.bottom;
      } else {
        data.offset.top = data.offset.top - padding;
        data.offset.left = data.offset.left - padding;
        data.width = data.width + (padding * 2);
        data.height = data.height + (padding * 2);
      }
      return data;
    };

    Tour.prototype._clearTimer = function () {
      window.clearTimeout(this._timer);
      this._timer = null;
      return this._duration = null;
    };

    Tour.prototype._getProtocol = function (url) {
      url = url.split('://');
      if (url.length > 1) {
        return url[0];
      } else {
        return 'http';
      }
    };

    Tour.prototype._getHost = function (url) {
      url = url.split('//');
      url = url.length > 1 ? url[1] : url[0];
      return url.split('/')[0];
    };

    Tour.prototype._getPath = function (path) {
      return path.replace(/\/?$/, '').split('?')[0].split('#')[0];
    };

    Tour.prototype._getQuery = function (path) {
      return this._getParams(path, '?');
    };

    Tour.prototype._getHash = function (path) {
      return this._getParams(path, '#');
    };

    Tour.prototype._getParams = function (path, start) {
      var j, len, param, params, paramsObject;
      params = path.split(start);
      if (params.length === 1) {
        return {};
      }
      params = params[1].split('&');
      paramsObject = {};
      for (j = 0, len = params.length; j < len; j++) {
        param = params[j];
        param = param.split('=');
        paramsObject[param[0]] = param[1] || '';
      }
      return paramsObject;
    };

    Tour.prototype._equal = function (obj1, obj2) {
      var j, k, len, obj1Keys, obj2Keys, v;
      if ({}.toString.call(obj1) === '[object Object]' && {}.toString.call(obj2) === '[object Object]') {
        obj1Keys = Object.keys(obj1);
        obj2Keys = Object.keys(obj2);
        if (obj1Keys.length !== obj2Keys.length) {
          return false;
        }
        for (k in obj1) {
          v = obj1[k];
          if (!this._equal(obj2[k], v)) {
            return false;
          }
        }
        return true;
      } else if ({}.toString.call(obj1) === '[object Array]' && {}.toString.call(obj2) === '[object Array]') {
        if (obj1.length !== obj2.length) {
          return false;
        }
        for (k = j = 0, len = obj1.length; j < len; k = ++j) {
          v = obj1[k];
          if (!this._equal(v, obj2[k])) {
            return false;
          }
        }
        return true;
      } else {
        return obj1 === obj2;
      }
    };

    return Tour;

  })();
  return Tour;
});
var UcptourC = UCPMC.extend({
	init: function() {
		this.tour = null;
	},
	poll: function(data) {
		//console.log(data)
	},

});

$(document).bind("logIn", function( event ) {
	UCP.Modules.Ucptour.tour = new Tour({
		debug: false,
		storage: false,
		keyboard: false,
		onEnd: function (tour) {
			$.post( UCP.ajaxUrl + "?module=ucptour&command=tour", { state: 0 }, function( data ) {

			});
		},
		steps: [
			{
				orphan: true,
				title: sprintf(_("Welcome to %s!"),UCP.Modules.Ucptour.staticsettings.brand),
				content: _("Congratulations!")+"<br><br> "+_("You just successfully logged in for the first time!")+" <br>"+_("This tour will take you on a brief walkthrough of the new User Control Panel in a few simple steps.")+"<br><br>"+_("You can always exit the tour if you'd like, and you can restart the tour at anytime by clicking your User Settings and then 'Restart Tour'")+"<br><br><u>"+_("To continue just click Next")+"</u>",
				backdrop: true,
			}, {
				backdrop: true,
				backdropContainer: "#nav-bar-background",
				element: "#add_new_dashboard",
				placement: "left",
				title: _("Adding a dashboard"),
				content: _("The User Control Panel is now separated by 'Dashboards'. You can add a new dashboard by clicking this symbol")+"<br><br>"+_("Click this symbol to continue"),
				next: -1,
				reflex: true,
				onShow: function(tour) {
					$(".navbar.navbar-inverse.navbar-fixed-left").css("z-index","1029");
				},
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$("#add_dashboard").one("shown.bs.modal", function() {
						tour.goTo(step + 1);
					});
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_dashboard .modal-dialog",
				element: "#dashboard_name",
				placement: "bottom",
				title: _("Name your dashboard"),
				content: _("Enter a name for your dashboard in this input box"),
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$("#dashboard_name").keyup(function(e) {
						if (e.keyCode == '13') {
							$(document).one("addDashboard",function(e, id) {
								$(".dashboard-menu[data-id="+id+"]").addClass("tour-step");
								$(".dashboard-menu[data-id="+id+"] a").click();
								tour.goTo(step + 2);
							});
						}
					});
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_dashboard .modal-dialog",
				element: "#create_dashboard",
				placement: "bottom",
				title: _("Save your dashboard"),
				content: _("When you are finished simply hit 'Create Dashboard' to create your dashboard"),
				reflex: true,
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					if($("#dashboard_name").val() === "") {
						tour.goTo(step - 1);
					}
				},
				onNext: function(tour) {
					var step = tour.getCurrentStep();
					$(document).one("addDashboard",function(e, id) {
						$(".dashboard-menu[data-id="+id+"]").addClass("tour-step");
						$(".dashboard-menu[data-id="+id+"] a").click();
						tour.goTo(step + 1);
					});
					return (new jQuery.Deferred()).promise();
				}
			}, {
				backdrop: true,
				backdropContainer: "#nav-bar-background",
				element: ".dashboard-menu.tour-step",
				placement: "bottom",
				title: _("Dashboards"),
				content: _("Your dashboard has been added here"),
				previous: -1
			}, {
				backdrop: true,
				backdropContainer: ".main-content-object",
				element: "#dashboard-content",
				placement: "bottom",
				title: _("Dashboard Widgets"),
				content: _("Dashboard widgets will be displayed here"),
				previous: -1,
				onShown: function(tour) {
					$("#dashboard-content").css("height","calc(100vh - 66px)");
				},
				onNext: function(tour) {
					$("#dashboard-content").css("height","");
				}
			}, {
				backdrop: true,
				backdropContainer: "#nav-bar-background",
				element: ".dashboard-menu.tour-step .edit-dashboard",
				placement: "bottom",
				title: _("Editing a Dashboard"),
				content: _("The dashboard's name can be changed by clicking the pencil")
			}, {
				backdrop: true,
				backdropContainer: "#nav-bar-background",
				element: ".dashboard-menu.tour-step .remove-dashboard",
				placement: "left",
				title: _("Delete a Dashboard"),
				content: sprintf(_("A dashboard can be deleted by clicking the '%s'"),'X')
			}, {
				backdrop: true,
				backdropContainer: "#nav-bar-background",
				element: ".dashboard-menu.tour-step",
				placement: "bottom",
				title: _("Ordering dashboards"),
				content: _("Multiple dashboard can be re-ordered by hovering with your mouse until the move cursor is shown. Then clicking and dragging the dashboard in the order you want"),
				onHidden: function(tour) {
					$(".navbar.navbar-inverse.navbar-fixed-left").css("z-index","");
				}
			}, {
				backdrop: true,
				backdropContainer: "#side_bar_content",
				element: "#side_bar_content .add-widget",
				placement: "right",
				title: _("Adding Widgets"),
				content: sprintf(_("Widgets can be added by clicking the '%s' symbol"),'(+)')+"<br><br>"+_("Click this symbol to continue"),
				reflex: true,
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$("#add_widget").one("shown.bs.modal", function() {
						tour.goTo(step + 1);
					});
					$(".tour-step-background").css("background-color","white");
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .modal-body",
				element: "#add_widget .modal-body .nav-tabs",
				placement: "left",
				title: _("Selecting Widgets"),
				content: _("There are two different types of widgets. Dashboard Widgets and Side Bar widgets. Let's start with dashboard widgets"),
				previous: -1,
				onShown: function(tour) {
					$("a[href=#red]").click();
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-container",
				element: "#add_widget .tab-pane.active .list-group-item.active",
				placement: "right",
				title: _("Selecting Dashboard Widgets"),
				content: _("Dashboard Widgets are sorted into categories on the left. These widgets will appear directly on your dashboard. You can click on any category to get a listing of the widgets available"),
				previous: -1
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-container",
				element: "#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first",
				placement: "bottom",
				title: _("Selecting Widgets"),
				content: _("Widgets are listed on the right. The titles and descriptions will be shown for each widget"),
				onShown: function(tour) {
					$("#add_widget .modal-body").scrollTop(0);
					var myStep = tour.getCurrentStep();
					$("#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first .add-widget-button").one("click",function() {
						tour.goTo(myStep + 2);
					});
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first",
				element: "#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first .add-widget-button",
				placement: "right",
				title: _("Adding Widgets"),
				content: sprintf(_("Clicking the '%s' symbol will add this widget to the currently active dashboard."),'(+)')+"<br><br>"+_("Click this symbol to continue"),
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$("#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first .add-widget-button").off("click");
					$(document).one("post-body.widgets", function(e, widget_id) {
						$(".grid-stack-item[data-id="+widget_id+"]").addClass("tour-step");
						tour.goTo(step + 1);
					});
				}
			}, {
				element: ".grid-stack-item.tour-step",
				placement: "right",
				title: _("Dashboard Widget"),
				content: _("Widgets are placed automatically on the dashboard after they have been added")
			}, {
				element: ".grid-stack-item.tour-step .widget-title",
				placement: "bottom",
				title: _("Widget Placement"),
				content: _("Widgets can be moved around by clicking and dragging on the title bar"),
				onNext: function(tour) {
					$(".grid-stack-item.tour-step .ui-icon-gripsmall-diagonal-se").show();
				}
			}, {
				element: ".grid-stack-item.tour-step .ui-icon-gripsmall-diagonal-se",
				placement: "right",
				title: _("Widget Size"),
				content: _("Widgets can be resized by placing your mouse near the corner of the widget. Click and drag to resize the widget.")+"<br><br>"+_("Note: some widgets have size restrictions!"),
				onNext: function(tour) {
					$(".grid-stack-item.tour-step .ui-icon-gripsmall-diagonal-se").hide();
				}
			}, {
				element: ".grid-stack-item.tour-step .widget-title .lock-widget",
				placement: "right",
				title: _("Widget Locking"),
				content: _("Widgets can be locked into place to prevent their movement")
			}, {
				element: ".grid-stack-item.tour-step .widget-title .edit-widget",
				placement: "right",
				title: _("Widget Settings"),
				content: _("Widgets settings can be changed by clicking this icon")
			}, {
				element: ".grid-stack-item.tour-step .widget-title .remove-widget",
				placement: "right",
				title: _("Widget Removal"),
				content: sprintf(_("Widgets can also be removed by clicking the '%s' symbol"),'X')
			}, {
				element: ".dashboard-menu.active .lock-dashboard",
				placement: "bottom",
				title: _("Dashboard Locking"),
				content: sprintf(_("All widgets in a dashboard can also be locked globally by clicking the '%s' symbol on the dashboard tab"),'<i class="fa fa-unlock-alt" aria-hidden="true"></i>')
			}, {
				element: ".navbar.navbar-inverse.navbar-fixed-left",
				placement: "right",
				title: _("Side Bar Widgets"),
				content: _("This is where side bar widgets live. Side bar widgets do not change when you change dashboards. They are global throughout UCP")
			}, {
				backdrop: true,
				backdropContainer: "#side_bar_content",
				element: "#side_bar_content .add-widget",
				placement: "right",
				title: _("Adding Side Bar Widgets"),
				content: sprintf(_("Side bar Widgets can also be added by clicking the '%s' symbol. These appear under the '%s' symbol in this side bar"),'(+)','(+)')+"<br><br>"+_("Click this symbol to continue"),
				reflex: true,
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$("#add_widget").one("shown.bs.modal", function() {
						tour.goTo(step + 1);
					});
					$(".tour-step-background").css("background-color","white");
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .modal-body",
				element: "#add_widget .modal-body .nav-tabs",
				placement: "right",
				title: _("Selecting Side Bar Widgets"),
				content: _("Side Bar widgets are grouped in a single category called 'Side Bar Widgets'"),
				onShown: function(tour) {
					$("#add_widget .modal-body").scrollTop(0);
					$("a[href=#small]").click();
				}
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-container",
				element: "#add_widget .tab-pane.active .list-group-item.active",
				placement: "bottom",
				title: _("Selecting Small Widgets"),
				content: _("Small Widgets are listed on the right. The titles and descriptions will be shown for each widget"),
			}, {
				backdrop: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-container",
				element: "#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first",
				placement: "bottom",
				title: _("Selecting Widgets"),
				content: _("Widgets are listed on the right. The titles and descriptions will be shown for each widget"),
				onShown: function(tour) {
					$("#add_widget .modal-body").scrollTop(0);
					var myStep = tour.getCurrentStep();
					$("#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first .add-small-widget-button").one("click",function() {
						tour.goTo(myStep + 2);
					});
				}
			}, {
				backdrop: true,
				orphan: true,
				backdropContainer: "#add_widget .tab-pane.active .bhoechie-tab-content.active .ibox-content-widget:first",
				element: ".add-small-widget-button",
				placement: "right",
				title: _("Adding Small Widgets"),
				content: sprintf(_("Clicking the '%s' symbol will add this small widget to the display. It will be visible on all dashboards"),'(+)')+"<br><br>"+_("Click this symbol to continue"),
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$(document).one("post-body.addsimplewidget", function(e, widget_id) {
						$(".custom-widget[data-widget_id="+widget_id+"]").addClass("tour-step");
						tour.goTo(step + 1);
					});
				}
			}, {
				element: "#side_bar_content .custom-widget.tour-step",
				placement: "right",
				title: _("Small Widget Display"),
				content: _("Once a small widget has been added it will show up in the left sidebar")+"<br><br>"+_("Click the widget's icon to continue"),
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$(document).one("post-body.simplewidget", function(e, widget_id, widget_type_id) {
						tour.goTo(step + 1);
					});
				}
			}, {
				element: ".widget-extra-menu:visible .small-widget-content",
				placement: "right",
				title: _("Small Widget Display"),
				content: _("The widget's content is displayed here")
			}, {
				element: ".widget-extra-menu:visible .remove-small-widget",
				placement: "top",
				title: _("Small Widget Display"),
				content: _("To remove this widget from the side bar click 'Remove Widget'")
			}, {
				element: ".widget-extra-menu:visible .close-simple-widget-menu",
				placement: "bottom",
				title: _("Small Widget Display"),
				content: sprintf(_("To just close/hide the widget's content click the '%s' symbol"),'(X)')+"<br><br>"+_("Click this symbol to continue"),
				next: -1,
				onShown: function(tour) {
					var step = tour.getCurrentStep();
					$(document).one("post-body.closesimplewidget", function(e, widget_id, widget_type_id) {
						tour.goTo(step + 1);
					});
				}
			}, {
				element: "#side_bar_content .settings-widget",
				placement: "right",
				title: _("User Settings"),
				content: _("Your specific settings are defined when clicking the 'gear' icon in the side bar")
			}, {
				element: "#side_bar_content .logout-widget",
				placement: "right",
				title: _("Logout"),
				content: _("Your can logout of UCP by clicking this logout button")
			}, {
				orphan: true,
				title: _("End of tour"),
				content: sprintf(_("You have finished the tour of User Control Panel for %s 14. You can restart this tour at any time in your User Settings"),UCP.Modules.Ucptour.staticsettings.brand)
			}
		]
	});
	if(UCP.Modules.Ucptour.staticsettings.show) {
		// Initialize the tour
		UCP.Modules.Ucptour.tour.init();

		// Start the tour
		UCP.Modules.Ucptour.tour.start();
	}
});

function checkPasswordReminder() {

    return new Promise(function (resolve, reject) {

        let username = $("input[name=username]").val().trim();
        let password = $("input[name=password]").val().trim();
        password = encodeURIComponent(window.btoa(password))
        $.post(UCP.ajaxUrl + "?module=userman&command=checkPasswordReminder",
            {
                username: username,
                password: password,
                loginpanel: 'ucp'
            }).done(function (response) {

                if (response.isSessionAlreadyUnlocked) {

                    window.location.reload();

                } else if (response.loginfailed) {

                    $("#login-window").height("300");
                    $("#error-msg").html(response.message).fadeIn("fast");

                } else if (response.mustresetpassword) {

                    alert(_(response.message));

                    if (response.resetlink) {
                        setTimeout(() => {
                            window.location.href = response.resetlink;
                        }, 300);
                    }

                } else {

                    if (!response.status) {
                        alert(response.message);
                    }

                    resolve(true);
                }
            }).fail(function (xhr, status, error) {
                UCP.showAlert(_(error), "error");
                reject(true);
            });
    });
};
var VoicemailC = UCPMC.extend({
	init: function() {
		this.loaded = null;
		this.recording = false;
		this.recorder = null;
		this.recordTimer = null;
		this.startTime = null;
		this.soundBlobs = {};
		this.placeholders = [];
	},
	resize: function(widget_id) {
		$(".grid-stack-item[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('resetView',{height: $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content").height()});
	},
	findmeFollowState: function() {
		if (!$("#vmx-p1_enable").is(":checked") && $("#ddial").is(":checked") && $("#vmx-state").is(":checked")) {
			$("#vmxerror").text(_("Find me Follow me is enabled when VmX locator option 1 is disabled. This means VmX Locator will be skipped, instead going directly to Find Me/Follow Me")).addClass("alert-danger").fadeIn("fast");
		} else {
			$("#vmxerror").fadeOut("fast");
		}
	},
	saveVmXSettings: function(ext, key, value) {
		var data = { ext: ext, settings: { key: key, value: value } };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=vmxsettings", data, function( data ) {
			if (data.status) {
				$("#vmxmessage").text(data.message).addClass("alert-" + data.alert).fadeIn("fast", function() {

				});
			} else {
				return false;
			}
		});
	},
	poll: function(data) {
		if (typeof data.boxes === "undefined") {
			return;
		}

		var notify = false;
		var self = this;

		/**
		 * Check all extensions and boxes at once.
		 */
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=checkextensions",
			async: false,
			data: data.boxes,
			success: function(vm_data){				
				window.vm_data = vm_data;
			},
			error: function (xhr, ajaxOptions, thrownError) {
                console.error('Unable to check extensions', thrownError, xhr);
            },
		  });

		async.forEachOf(window.vm_data, function (value, extension, callback) {	
			var el = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .mailbox");
			self.refreshFolderCount(extension);
			if(el.length && el.data("inbox").status != value.status || window.update_table == true) {
				notify = false;
				if(el.data("inbox") < value){
					notify = true;
				}
				el.data("inbox",value);	
				if((typeof Cookies.get('vm-refresh-'+extension) === "undefined" && (typeof Cookies.get('vm-refresh-'+extension) === "undefined" || Cookies.get('vm-refresh-'+extension) == 1)) || Cookies.get('vm-refresh-'+extension) == 1) {
					$(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id='"+extension+"'] .voicemail-grid").bootstrapTable('refresh',{silent: true});
				}
			}			
			callback();
		}, function(err) {
			if( err ) {
			} else if(notify) {
				voicemailNotification = new Notify("Voicemail", {
					body: _("You have a new voicemail"),
					icon: "modules/Voicemail/assets/images/mail.png"
				});
				if (UCP.notify) {
					voicemailNotification.show();
				}
			}
		});
	},
	displayWidgetSettings: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");

		/* Settings changes binds */
		$("#widget_settings .widget-settings-content input[type!='checkbox'][id!=vm-refresh]").change(function() {
			$(this).blur(function() {
				self.saveVMSettings(extension);
				$(this).off("blur");
			});
		});
		$("#widget_settings .widget-settings-content input[type='checkbox'][id!=vm-refresh]").change(function() {
			self.saveVMSettings(extension);
		});

		$("#widget_settings .widget-settings-content input[id=vm-refresh]").change(function() {
			Cookies.remove('vm-refresh-'+extension, {path: ''});
			if($(this).is(":checked")) {
				Cookies.set('vm-refresh-'+extension, 1);
			} else {
				Cookies.set('vm-refresh-'+extension, 0);
			}
		});
		if((typeof Cookies.get('vm-refresh-'+extension) === "undefined" && (typeof Cookies.get('vm-refresh-'+extension) === "undefined" || Cookies.get('vm-refresh-'+extension) == 1)) || Cookies.get('vm-refresh-'+extension) == 1) {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",true);
		} else {
			$("#widget_settings .widget-settings-content input[id=vm-refresh]").prop("checked",false);
		}
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle('destroy');
		$("#widget_settings .widget-settings-content input[id=vm-refresh]").bootstrapToggle({
			on: _("Enable"),
			off: _("Disable")
		});
		this.greetingsDisplay(extension);
		this.bindGreetingPlayers(extension);
		$("#widget_settings .vmx-setting").change(function() {
			var name = $(this).attr("name"),
					val = $(this).val();
			if($(this).attr("type") == "checkbox") {
				self.saveVmXSettings(extension, name, $(this).is(":checked"));
			} else {
				self.saveVmXSettings(extension, name, val);
			}

		});
	},
	displayWidget: function(widget_id, dashboard_id) {
		var self = this,
				extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id='"+widget_id+"'] .voicemail-grid").one("post-body.bs.table", function() {
			setTimeout(function() {
				self.resize(widget_id);
			},250);
		});

		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("post-body.bs.table", function (e) {
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.listen").click(function() {
				var id = $(this).data("id"),
						select = '';
				$.each(self.staticsettings.extensions, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Listen to Voicemail"),
					_("On") + ':</label><select class="form-control" data-toggle="select" id="VMto">'+select+"</select>",
					'<button class="btn btn-default" id="listenVM">' + _("Listen") + "</button>",
					function() {
						$("#listenVM").click(function() {
							var recpt = $("#VMto").val();
							self.listenVoicemail(id,extension,recpt);
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.listenVoicemail(id,extension,recpt);
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
				var text = $(this).text();
				if (UCP.validMethod("Contactmanager", "showActionDialog")) {
					UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
				}
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.forward").click(function() {
				var id = $(this).data("id"),
						select = '';

				$.each(self.staticsettings.mailboxes, function(i,v) {
					select = select + "<option value='"+v+"'>"+v+"</option>";
				});
				UCP.showDialog(_("Forward Voicemail"),
					_("To")+':</label><select class="form-control" id="VMto">'+select+'</select>',
					'<button class="btn btn-default" id="forwardVM">' + _("Forward") + "</button>",
					function() {
						$("#forwardVM").click(function() {
							var recpt = $("#VMto").val();
							self.forwardVoicemail(id,extension,recpt, function(data) {
								if(data.status) {
									UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
									UCP.closeDialog();
								}
							});
						});
						$("#VMto").keypress(function(event) {
							if (event.keyCode == 13) {
								var recpt = $("#VMto").val();
								self.forwardVoicemail(id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										UCP.closeDialog();
									}
								});
							}
						});
					}
				);
			});
			$("div[data-id='"+widget_id+"'] .voicemail-grid a.delete").click(function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var id = $(this).data("id");
				UCP.showConfirm(_("Are you sure you wish to delete this voicemail?"),'warning',function() {
					self.deleteVoicemail(id, extension, function(data) {
						if(data.status) {
							$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(id)]});
						}
					});
				});
			});
			self.bindPlayers(widget_id);
		});
		$("div[data-id='"+widget_id+"'] .voicemail-grid").on("check.bs.table uncheck.bs.table check-all.bs.table uncheck-all.bs.table", function () {
			var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections'),
					dis = true;
			if(sel.length) {
				dis = false;
			}
			$("div[data-id='"+widget_id+"'] .delete-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .forward-selection").prop("disabled",dis);
			$("div[data-id='"+widget_id+"'] .move-selection").prop("disabled",dis);
		});

		$("div[data-id='"+widget_id+"'] .folder").click(function() {
			$("div[data-id='"+widget_id+"'] .folder").removeClass("active");
			$(this).addClass("active");
			folder = $(this).data("folder");
			$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('refreshOptions',{
				url: UCP.ajaxUrl+'?module=voicemail&command=grid&folder='+folder+'&ext='+extension
			});
		});

		$("div[data-id='"+widget_id+"'] .move-selection").click(function() {
			var opts = '', cur = (typeof $.url().param("folder") !== "undefined") ? $.url().param("folder") : "INBOX", sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');
			$.each($("div[data-id='"+widget_id+"'] .folder-list .folder"), function(i, v){
				var folder = $(v).data("folder");
				if(folder != cur) {
					opts += '<option>'+$(v).data("name")+'</option>';
				}
			});
			UCP.showDialog(_("Move Voicemail"),
				_("To")+':</label><select class="form-control" data-toggle="select" id="VMmove">'+opts+"</select>",
				'<button class="btn btn-default" id="moveVM"><span id="spin"></span>&nbsp;&nbsp;' + _("Move") + "</button>",
				function() {
					var total = sel.length;
					$("#moveVM").click(function() {
						$("#moveVM").prop("disabled",true);
						$("#spin").html('<i class="fa fa-spinner fa-spin"></i>')
						setTimeout(function () {
							let data = [];
							Object.keys(sel).forEach(key => {
								data.push({
									msg: sel[key].msg_id,
									folder: $("#VMmove").val(),
									ext: extension
								});
							})
							self.moveVoicemailBulk(data, extension, function (data) {
								if (data.status) {
									self.rebuildVM(extension);
									if (data.moveStatus.includes(false)) {
										UCP.showAlert('Not able to move some of the voicemails.');
									}
									setTimeout(function () {
										UCP.closeDialog();
									}, 2000);
								} else {
									$("#moveVM").prop("disabled", false);
									UCP.showAlert(data.error);
								}
							})
						}, 50);
					});
					$("#VMmove").keypress(function(event) {
						if (event.keyCode == 13) {
							$("#moveVM").prop("disabled",true);
							async.forEachOf(sel, function (v, i, callback) {
								self.moveVoicemail(v.msg_id, $("#VMmove").val(), extension, function(data) {
									if(data.status) {
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('remove', {field: "msg_id", values: [String(v.msg_id)]});
									}
									callback();
								})
							}, function(err) {
								if( err ) {
									$("#moveVM").prop("disabled",false);
									UCP.showAlert(err);
								} else {
									UCP.closeDialog();
									self.rebuildVM(extension);
								}
							});
						}
					});
					$(".delete-selection").prop("disabled",true);
					$(".forward-selection").prop("disabled",true);
					$(".move-selection").prop("disabled",true);
				}
			);
		});
		$("div[data-id='" + widget_id + "'] .delete-selection").click(function () {
			$('#modal_confirm_button').attr("data-dismiss", '');
			UCP.showConfirm(_("Are you sure you wish to delete these voicemails?"),'warning',function() {
				var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
				var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');
				var accept = $("#modal_confirm_button").text();
				$("#modal_confirm_button").html('<i class="fa fa-spinner fa-spin"></i>&nbsp;'+ accept);
				setTimeout(function () {
					let data = [];
					Object.keys(sel).forEach(key => {
						data.push({
							msg: sel[key].msg_id,
							folder: $("#VMmove").val(),
							ext: extension
						});
					});
					self.deleteVoicemailBulk(data, extension, function (data) {
						if (data.status) {
							self.rebuildVM(extension);
							if (data.deleteStatus.includes(false)) {
								UCP.showAlert('Not able to delete some of the voicemails.');
							}
							setTimeout(function () {
								$("#modal_confirm_button").html(accept);
								$("#confirm_modal").modal('toggle');
							}, 2000);
						} else {
							UCP.showAlert(data.error);
						}
					});
				}, 50);
				$(".delete-selection").prop("disabled",true);
				$(".forward-selection").prop("disabled",true);
				$(".move-selection").prop("disabled",true);
			});
		});
		$("div[data-id='"+widget_id+"'] .forward-selection").click(function() {
			var sel = $("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('getSelections');
			UCP.showDialog(_("Forward Voicemail"),
				_("To")+":</label><input type='text' class='form-control' id='VMto'>",
				'<button class="btn btn-default" id="forwardVM">' + _("Forward") + "</button>",
				function() {
					$("#forwardVM").click(function() {
						setTimeout(function() {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}, 50);
					});
					$("#VMto").keypress(function(event) {
						if (event.keyCode == 13) {
							var recpt = $("#VMto").val();
							$.each(sel, function(i, v){
								self.forwardVoicemail(v.msg_id,extension,recpt, function(data) {
									if(data.status) {
										UCP.showAlert(sprintf(_("Successfully forwarded voicemail to %s"),recpt));
										$("div[data-id='"+widget_id+"'] .voicemail-grid").bootstrapTable('uncheckAll');
										UCP.closeDialog();
									}
								});
							});
						}
					});
				}
			);
		});


		$("div[data-id='"+widget_id+"'] .voicemail-grid .clickable").click(function(e) {
			var text = $(this).text();
			if (UCP.validMethod("Contactmanager", "showActionDialog")) {
				UCP.Modules.Contactmanager.showActionDialog("number", text, "phone");
			}
		});
	},
	greetingsDisplay: function(extension) {
		var self = this;
		$("#widget_settings .recording-controls .save").click(function() {
			var id = $(this).data("id");
			self.saveRecording(extension,id);
		});
		$("#widget_settings .recording-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteRecording(extension,id);
		});
		$("#widget_settings .file-controls .record, .jp-record").click(function() {
			var id = $(this).data("id");
			self.recordGreeting(extension,id);
		});
		$("#widget_settings .file-controls .delete").click(function() {
			var id = $(this).data("id");
			self.deleteGreeting(extension,id);
		});
		$("#widget_settings .filedrop").on("dragover", function(event) {
			if (event.preventDefault) {
				event.preventDefault(); // Necessary. Allows us to drop.
			}
			$(this).addClass("hover");
		});
		$("#widget_settings .filedrop").on("dragleave", function(event) {
			$(this).removeClass("hover");
		});

		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragstart", function(event) {
			event.originalEvent.dataTransfer.effectAllowed = "move";
			event.originalEvent.dataTransfer.setData("type", $(this).data("type"))
			$(this).fadeTo( "fast", 0.5);
		});
		$("#widget_settings .greeting-control .jp-audio-freepbx").on("dragend", function(event) {
			$(this).fadeTo( "fast", 1.0);
		});
		$("#widget_settings .filedrop").on("drop", function(event) {
			if (event.originalEvent.dataTransfer.files.length === 0) {
				if (event.stopPropagation) {
					event.stopPropagation(); // Stops some browsers from redirecting.
				}
				if (event.preventDefault) {
					event.preventDefault(); // Necessary. Allows us to drop.
				}
				$(this).removeClass("hover");
				var target = $(this).data("type"),
				source = event.originalEvent.dataTransfer.getData("type");
				if (source === "") {
					alert(_("Not a valid Draggable Object"));
					return false;
				}
				if (source == target) {
					alert(_("Dragging to yourself is not allowed"));
					return false;
				}
				var data = { ext: extension, source: source, target: target },
				message = $(this).find(".message");
				message.text(_("Copying..."));
				$.post( UCP.ajaxUrl + "?module=voicemail&command=copy", data, function( data ) {
						if (data.status) {
							$("#"+target+" .filedrop .pbar").css("width", "0%");
							$("#"+target+" .filedrop .message").text($("#"+target+" .filedrop .message").data("message"));
							$("#freepbx_player_" + target).removeClass("greet-hidden");
							self.toggleGreeting(target, true);
						} else {
							return false;
						}
				});
			} else {}
		});
		$("#widget_settings .greeting-control").each(function() {
			var id = $(this).attr("id");
			$("#"+id+" input[type=\"file\"]").fileupload({
				url: UCP.ajaxUrl + "?module=voicemail&command=upload&type="+id+"&ext=" + extension,
				dropZone: $("#"+id+" .filedrop"),
				dataType: "json",
				add: function(e, data) {
					//TODO: Need to check all supported formats
					var sup = "\.("+self.staticsettings.supportedRegExp+")$",
							patt = new RegExp(sup),
							submit = true;
					$.each(data.files, function(k, v) {
						if(!patt.test(v.name)) {
							submit = false;
							UCP.showAlert(_("Unsupported file type"));
							return false;
						}
					});
					if(submit) {
						$("#"+id+" .filedrop .message").text(_("Uploading..."));
						data.submit();
					}
				},
				done: function(e, data) {
					if (data.result.status) {
						$("#"+id+" .filedrop .pbar").css("width", "0%");
						$("#"+id+" .filedrop .message").text($("#"+id+" .filedrop .message").data("message"));
						$("#freepbx_player_"+id).removeClass("greet-hidden");
						self.toggleGreeting(id, true);
					} else {
						console.warn(data.result.message);
					}
				},
				progressall: function(e, data) {
					var progress = parseInt(data.loaded / data.total * 100, 10);
					$("#"+id+" .filedrop .pbar").css("width", progress + "%");
				},
				drop: function(e, data) {
					$("#"+id+" .filedrop").removeClass("hover");
				}
			});
		});
		//If browser doesnt support get user media requests then just hide it from the display
		if (!Modernizr.getusermedia) {
			$("#widget_settings .jp-record-wrapper").hide();
			$("#widget_settings .record-greeting-btn").hide();
		} else {
			$("#widget_settings .jp-record-wrapper").show();
			$("#widget_settings .jp-stop-wrapper").hide();
			$("#widget_settings .record-greeting-btn").show();
		}
	},
	//Delete a voicemail greeting
	deleteGreeting: function(extension,type) {
		var self = this, data = { msg: type, ext: extension };
		$.post( UCP.ajaxUrl + "?module=voicemail&command=delete", data, function( data ) {
			if (data.status) {
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				self.toggleGreeting(type, false);
			} else {
				return false;
			}
		});
	},
	refreshFolderCount: function(extension) {
		var data = window.vm_data[extension];
		if(data.status) {
			window.update_table = false;
			$.each(data.folders, function(i,v) {		
				cur_val = $(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id="+extension+"] .mailbox .folder-list .folder[data-name='"+v.name+"'] .badge").text();
				if(cur_val != v.count){
					window.update_table = true;
				}
				$(".grid-stack-item[data-rawname='voicemail'][data-widget_type_id="+extension+"] .mailbox .folder-list .folder[data-name='"+v.name+"'] .badge").text(v.count);				
			});
		}

	},
	rebuildVM: function(extension){
		var data = {
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=rebuildVM",
			data: data,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	moveVoicemail: function(msgid, folder, extension, callback) {
		var data = {
			msg: msgid,
			folder: folder,
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=moveToFolder",
			data: data,
			async: false,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	moveVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		self = this;
		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=moveToFolderBulk",
			data: formData,
			async: false,
			processData: false,
			contentType: false,
			success: function (data) {
				self.refreshFolderCount(extension);
				if (typeof callback === "function") {
					callback(data);
				}
			},
			error: function (data) {
				if (typeof callback === "function") {
					callback({ status: false, error: data });
				}
			}
		});
	},
	forwardVoicemail: function(msgid, extension, recpt, callback) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=forward&ext="+extension, data, function(data) {
			if(typeof callback === "function") {
				callback(data);
			}
		}).fail(function() {
			if(typeof callback === "function") {
				callback({status: false});
			}
		});
	},
	//Used to delete a voicemail message
	deleteVoicemail: function(msgid, extension, callback) {
		var data = {
			msg: msgid,
			ext: extension
		},
		self = this;
		$.ajax({
			type: "POST",
			url: UCP.ajaxUrl + "?module=voicemail&command=delete",
			data: data,
			async: false,
			success: function(data) {
				self.refreshFolderCount(extension);
				if(typeof callback === "function") {
					callback(data);
				}	
			},
			error: function(data) {
				if(typeof callback === "function") {
					callback({status: false});
				}
			}
		});
	},
	deleteVoicemailBulk: function (data, extension, callback) {
		var formData = new FormData();
		formData.append('data', JSON.stringify(data));
		self = this;
		$.ajax({
			type: "POST",
			enctype: 'multipart/form-data',
			url: UCP.ajaxUrl + "?module=voicemail&command=deleteBulk",
			data: formData,
			async: false,
			processData: false,
			contentType: false,
			success: function (data) {
				self.refreshFolderCount(extension);
				if (typeof callback === "function") {
					callback(data);
				}
			},
			error: function (data) {
				if (typeof callback === "function") {
					callback({ status: false });
				}
			}
		});
	},
	//Toggle the html5 player for greeting
	toggleGreeting: function(type, visible) {
		if (visible === true) {
			$("#" + type + " button.delete").show();
			$("#jp_container_" + type).removeClass("greet-hidden");
			$("#freepbx_player_"+ type).jPlayer( "clearMedia" );
		} else {
			$("#" + type + " button.delete").hide();
			$("#jp_container_" + type).addClass("greet-hidden");
		}
	},
	//Save Voicemail Settings
	saveVMSettings: function(extension) {
		$("#message").fadeOut("slow");
		var data = { ext: extension };
		$("div[data-rawname='voicemail'] .widget-settings-content input[type!='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).val();
		});
		$("div[data-rawname='voicemail'] .widget-settings-content input[type='checkbox']").each(function( index ) {
			data[$( this ).attr("name")] = $( this ).is(":checked");
		});
		$.post( UCP.ajaxUrl + "?module=voicemail&command=savesettings", data, function( data ) {
			if (data.status) {
				$("#message").addClass("alert-success");
				$("#message").text(_("Your settings have been saved"));
				$("#message").fadeIn( "slow", function() {
					setTimeout(function() { $("#message").fadeOut("slow"); }, 2000);
				});
			} else {
				$("#message").addClass("alert-error");
				$("#message").text(data.message);
				return false;
			}
		});
	},
	recordGreeting: function(extension,type) {
		var self = this;
		if (!Modernizr.getusermedia) {
			UCP.showAlert(_("Direct Media Recording is Unsupported in your Broswer!"));
			return false;
		}
		counter = $("#jp_container_" + type + " .jp-current-time");
		title = $("#jp_container_" + type + " .title-text");
		filec = $("#" + type + " .file-controls");
		recc = $("#" + type + " .recording-controls");
		var controls = $("#jp_container_" + type + " .jp-controls");
		controls.toggleClass("recording");
		if (self.recording) {
			clearInterval(self.recordTimer);
			title.text(_("Recorded Message"));
			self.recorder.stop();
			self.recorder.exportWAV(function(blob) {
				self.soundBlobs[type] = blob;
				var url = (window.URL || window.webkitURL).createObjectURL(blob);
				$("#freepbx_player_" + type).jPlayer( "clearMedia" );
				$("#freepbx_player_" + type).jPlayer( "setMedia", {
					wav: url
				});
			});
			self.recording = false;
			recc.show();
			filec.hide();
		} else {
			window.AudioContext = window.AudioContext || window.webkitAudioContext;

			var context = new AudioContext();

			var gUM = Modernizr.prefixed("getUserMedia", navigator);
			gUM({ audio: true }, function(stream) {
				var mediaStreamSource = context.createMediaStreamSource(stream);
				self.recorder = new Recorder(mediaStreamSource,{ workerPath: "assets/js/recorderWorker.js" });
				self.recorder.record();
				self.startTime = new Date();
				self.recordTimer = setInterval(function () {
					var mil = (new Date() - self.startTime);
					var temp = (mil / 1000);
					var min = ("0" + Math.floor((temp %= 3600) / 60)).slice(-2);
					var sec = ("0" + Math.round(temp % 60)).slice(-2);
					counter.text(min + ":" + sec);
				}, 1000);
				title.text(_("Recording..."));
				self.recording = true;
				$("#jp_container_" + type).removeClass("greet-hidden");
				recc.hide();
				filec.show();
			}, function(e) {
				UCP.showAlert(_("Your Browser Blocked The Recording, Please check your settings"));
				self.recording = false;
			});
		}
	},
	saveRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
				title = $("#" + type + " .title-text");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to save"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			$("#" + type + " .filedrop .message").text(_("Uploading..."));
			var data = new FormData();
			data.append("file", self.soundBlobs[type]);
			$.ajax({
				type: "POST",
				url: UCP.ajaxUrl + "?module=voicemail&command=record&type=" + type + "&ext=" + extension,
				xhr: function()
				{
					var xhr = new window.XMLHttpRequest();
					//Upload progress
					xhr.upload.addEventListener("progress", function(evt) {
						if (evt.lengthComputable) {
							var percentComplete = evt.loaded / evt.total,
							progress = Math.round(percentComplete * 100);
							$("#" + type + " .filedrop .pbar").css("width", progress + "%");
						}
					}, false);
					return xhr;
				},
				data: data,
				processData: false,
				contentType: false,
				success: function(data) {
					$("#" + type + " .filedrop .message").text($("#" + type + " .filedrop .message").data("message"));
					$("#" + type + " .filedrop .pbar").css("width", "0%");
					self.soundBlobs[type] = null;
					$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
					$("#freepbx_player_" + type).jPlayer( "clearMedia" );
					title.text(title.data("title"));
					filec.show();
					recc.hide();
				},
				error: function() {
					//error
					filec.show();
					recc.hide();
				}
			});
		}
	},
	deleteRecording: function(extension,type) {
		var self = this,
				filec = $("#" + type + " .file-controls"),
				recc = $("#" + type + " .recording-controls");
		if (self.recording) {
			UCP.showAlert(_("Stop the Recording First before trying to delete"));
			return false;
		}
		if ((typeof(self.soundBlobs[type]) !== "undefined") && self.soundBlobs[type] !== null) {
			self.soundBlobs[type] = null;
			$("#freepbx_player_" + type).jPlayer("supplied",self.staticsettings.supportedHTML5);
			$("#freepbx_player_" + type).jPlayer( "clearMedia" );
			title.text(title.data("title"));
			filec.show();
			recc.hide();
			self.toggleGreeting(type, false);
		} else {
			UCP.showAlert(_("There is nothing to delete"));
		}
	},
	//This function is here solely because firefox caches media downloads so we have to force it to not do that
	generateRandom: function() {
		return Math.round(new Date().getTime() / 1000);
	},
	dateFormatter: function(value, row, index) {
		return UCP.dateTimeFormatter(value);
	},
	listenVoicemail: function(msgid, extension, recpt) {
		var data = {
			id: msgid,
			to: recpt
		};
		$.post( UCP.ajaxUrl + "?module=voicemail&command=callme&ext="+extension, data, function( data ) {
			UCP.closeDialog();
		});
	},
	playbackFormatter: function (value, row, index) {
		var settings = UCP.Modules.Voicemail.staticsettings,
				rand = Math.floor(Math.random() * 10000);
		if(settings.showPlayback == "0" || row.duration === 0) {
			return '';
		}
		return '<div id="jquery_jplayer_'+row.msg_id+'-'+rand+'" class="jp-jplayer" data-container="#jp_container_'+row.msg_id+'-'+rand+'" data-id="'+row.msg_id+'"></div><div id="jp_container_'+row.msg_id+'-'+rand+'" data-player="jquery_jplayer_'+row.msg_id+'-'+rand+'" class="jp-audio-freepbx" role="application" aria-label="media player">'+
			'<div class="jp-type-single">'+
				'<div class="jp-gui jp-interface">'+
					'<div class="jp-controls">'+
						'<i class="fa fa-play jp-play"></i>'+
						'<i class="fa fa-undo jp-restart"></i>'+
					'</div>'+
					'<div class="jp-progress">'+
						'<div class="jp-seek-bar progress">'+
							'<div class="jp-current-time" role="timer" aria-label="time">&nbsp;</div>'+
							'<div class="progress-bar progress-bar-striped active" style="width: 100%;"></div>'+
							'<div class="jp-play-bar progress-bar"></div>'+
							'<div class="jp-play-bar">'+
								'<div class="jp-ball"></div>'+
							'</div>'+
							'<div class="jp-duration" role="timer" aria-label="duration">&nbsp;</div>'+
						'</div>'+
					'</div>'+
					'<div class="jp-volume-controls">'+
						'<i class="fa fa-volume-up jp-mute"></i>'+
						'<i class="fa fa-volume-off jp-unmute"></i>'+
					'</div>'+
				'</div>'+
				'<div class="jp-no-solution">'+
					'<span>Update Required</span>'+
					sprintf(_("You are missing support for playback in this browser. To fully support HTML5 browser playback you will need to install programs that can not be distributed with the PBX. If you'd like to install the binaries needed for these conversions click <a href='%s'>here</a>"),"https://sangomakb.atlassian.net/wiki/spaces/FP/pages/10682566/Installing+Media+Conversion+Libraries")+
				'</div>'+
			'</div>'+
		'</div>';
	},
	durationFormatter: function (value, row, index) {
		return (typeof UCP.durationFormatter === 'function') ? UCP.durationFormatter(value) : sprintf(_("%s seconds"),value);
	},
	controlFormatter: function (value, row, index) {
		var html = '<a class="listen" alt="'+_('Listen on your handset')+'" data-id="'+row.msg_id+'"><i class="fa fa-phone"></i></a>'+
						'<a class="forward" alt="'+_('Forward')+'" data-id="'+row.msg_id+'"><i class="fa fa-share"></i></a>';
		var settings = UCP.Modules.Voicemail.staticsettings;
		if(settings.showDownload == "1") {
			html += '<a class="download" alt="'+_('Download')+'" href="'+ UCP.ajaxUrl +'?module=voicemail&amp;command=download&amp;msgid='+row.msg_id+'&amp;ext='+row.origmailbox+'"><i class="fa fa-cloud-download"></i></a>';
		}

		html += '<a class="delete" alt="'+_('Delete')+'" data-id="'+row.msg_id+'"><i class="fa fa-trash-o"></i></a>';

		if((row.converttotext !== undefined) && row.converttotext.transcriptionURL !== undefined && row.converttotext.transcriptionURL !== null && row.converttotext.transcriptionURL != '' && settings.isScribeEnabled) {
			html += '<a href="#" data-toggle="tooltip" class="transcript tool-tip" title="Read the voice transcription" title="Read the voice transcription" onclick="openmodal(\'' + UCP.ajaxUrl +row.converttotext.transcriptionURL + '\')"> <img src="'+row.converttotext.scribeIconURL+'" width="15px" height="15px" alt="PBX Scribe" /></a>';
		}

		return html;
	},
	bindPlayers: function(widget_id) {
		var extension = $("div[data-id='"+widget_id+"']").data("widget_type_id");
		$(".grid-stack-item[data-id="+widget_id+"] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: UCP.ajaxUrl,
								data: {module: "voicemail", command: "gethtml5", msg_id: msg_id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.warn(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										UCP.showAlert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var self = this;
					$(container).find(".jp-restart").click(function() {
						if($(self).data("jPlayer").status.paused) {
							$(self).jPlayer("pause",0);
						} else {
							$(self).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: UCP.Modules.Voicemail.staticsettings.supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('.grid-stack-item[data-rawname=voicemail] .jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	},
	bindGreetingPlayers: function(extension) {
		var settings = UCP.Modules.Voicemail.staticsettings,
				supportedHTML5 = settings.supportedHTML5,
				self = this;

		if(Modernizr.getusermedia) {
			supportedHTML5 = supportedHTML5.split(",");
			if(supportedHTML5.indexOf("wav") === -1) {
				supportedHTML5.push("wav");
			}
			supportedHTML5 = supportedHTML5.join(",");
		}

		$("#widget_settings .jp-jplayer, .grid-stack-item[data-rawname=voicemail] .jp-jplayer").each(function() {
			var container = $(this).data("container"),
					player = $(this),
					msg_id = $(this).data("id");
			$(this).jPlayer({
				ready: function() {
					$(container + " .jp-play").click(function() {
						if($(this).parents(".jp-controls").hasClass("recording")) {
							var type = $(this).parents(".jp-audio-freepbx").data("type");
							self.recordGreeting(extension,type);
							return;
						}
						if(!player.data("jPlayer").status.srcSet) {
							$(container).addClass("jp-state-loading");
							$.ajax({
								type: 'POST',
								url: UCP.ajaxUrl,
								data: {module: "voicemail", command: "gethtml5", msg_id: msg_id, ext: extension},
								dataType: 'json',
								timeout: 30000,
								success: function(data) {
									if(data.status) {
										player.on($.jPlayer.event.error, function(event) {
											$(container).removeClass("jp-state-loading");
											console.warn(event);
										});
										player.one($.jPlayer.event.canplay, function(event) {
											$(container).removeClass("jp-state-loading");
											player.jPlayer("play");
										});
										player.jPlayer( "setMedia", data.files);
									} else {
										UCP.showAlert(data.message);
										$(container).removeClass("jp-state-loading");
									}
								}
							});
						}
					});
					var self = this;
					$(container).find(".jp-restart").click(function() {
						if($(self).data("jPlayer").status.paused) {
							$(self).jPlayer("pause",0);
						} else {
							$(self).jPlayer("play",0);
						}
					});
				},
				timeupdate: function(event) {
					$(container).find(".jp-ball").css("left",event.jPlayer.status.currentPercentAbsolute + "%");
				},
				ended: function(event) {
					$(container).find(".jp-ball").css("left","0%");
				},
				swfPath: "/js",
				supplied: supportedHTML5,
				cssSelectorAncestor: container,
				wmode: "window",
				useStateClassSkin: true,
				remainingDuration: true,
				toggleDuration: true
			});
			$(this).on($.jPlayer.event.play, function(event) {
				$(this).jPlayer("pauseOthers");
			});
		});

		var acontainer = null;
		$('#widget_settings .jp-play-bar, .grid-stack-item[data-rawname=voicemail] .jp-play-bar').mousedown(function (e) {
			acontainer = $(this).parents(".jp-audio-freepbx");
			updatebar(e.pageX);
		});
		$(document).mouseup(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
				acontainer = null;
			}
		});
		$(document).mousemove(function (e) {
			if (acontainer) {
				updatebar(e.pageX);
			}
		});

		//update Progress Bar control
		var updatebar = function (x) {
			var player = $("#" + acontainer.data("player")),
					progress = acontainer.find('.jp-progress'),
					maxduration = player.data("jPlayer").status.duration,
					position = x - progress.offset().left,
					percentage = 100 * position / progress.width();

			//Check within range
			if (percentage > 100) {
				percentage = 100;
			}
			if (percentage < 0) {
				percentage = 0;
			}

			player.jPlayer("playHead", percentage);

			//Update progress bar and video currenttime
			acontainer.find('.jp-ball').css('left', percentage+'%');
			acontainer.find('.jp-play-bar').css('width', percentage + '%');
			player.jPlayer.currentTime = maxduration * percentage / 100;
		};
	}
});

function openmodal(turl) {
    var result = $.ajax({
        url: turl,
        type: 'POST',
        async: false
    });
    result = JSON.parse(result.responseText);
    $("#addtionalcontent").html(result.html);
    $("#addtionalcontent").appendTo("body");
    $("#datamodal").show();
}

function closemodal() {
	$('div#addtionalcontent:not(:first)').remove();
	$("#addtionalcontent").html("");
	$("#datamodal").hide();
}

var WidgetsC = Class.extend({
	init: function() {
		this.activeDashboard = null;
		this.widgetMenuOpen = false;
	},
	ready: function() {
		this.setupAddDashboard();
		this.loadDashboard();
		this.initMenuDragabble();
		this.initDashboardDragabble();
		this.initCategoriesWidgets();
		this.initAddWidgetsButtons();
		this.initRemoveItemButtons();
		this.initLockItemButtons();
		this.initLeftNavBarMenus();
		this.deactivateFullLoading();
		var $this = this;
		var total = $(".custom-widget").length;
		var count = 0;
		var resave = false;
		$(".custom-widget").each(function() {
			var widget_rawname = $(this).data("widget_rawname");
			var widget_id = $(this).data("widget_id");
			UCP.callModuleByMethod(widget_rawname,"addSimpleWidget",widget_id);
			$(document).trigger("post-body.addsimplewidget",[ widget_id, $this.activeDashboard ]);
			if(typeof $(this).find("a").data("regenuuid") !== "undefined" && $(this).find("a").data("regenuuid")) {
				resave = true;
			}
			count++;
			if(total == count) {
				if(resave) {
					$this.saveSidebarContent();
				}
			}
		});
		window.onpopstate = function(event) {
			if(typeof event.state !== "undefined" && event.state !== null && typeof event.state.activeDashboard !== "undefined") {
				var el = $("#all_dashboards .dashboard-menu[data-id="+event.state.activeDashboard+"] a");
				//set popstate event to true so we dont destroy history
				el.data("popstate",true);
				el.click();
			}
		};
		var title = $("#all_dashboards .dashboard-menu.active a").text();
		//set tab title
		if(title !== "") {
			$("title").text(_("User Control Panel") + " - " + title);
		}
	},
	loadDashboard: function() {
		var $this = this;

		$("#dashboard-content .dashboard-error.no-dash").click(function() {
			$("#add_new_dashboard").click();
		});

		$('#add_dashboard').on('shown.bs.modal', function () {
			$('#dashboard_name').focus();
			$("#add_dashboard").off("keydown");
			$("#add_dashboard").on('keydown', function(event) {
				switch(event.keyCode) {
					case 13:
						$("#create_dashboard").click();
					break;
				}
			});
		});

		$('#add_dashboard').on('hidden.bs.modal', function () {
			$('#dashboard_name').val("");
		});

		$('#edit_dashboard').on('shown.bs.modal', function () {
			$('#edit_dashboard_name').focus();
		});

		$('#edit_dashboard').on('hidden.bs.modal', function () {
			$('#edit_dashboard_name').val("");
		});

		$(document).on("click", ".edit-widget", function(){
			var settings_container = $('#widget_settings .modal-body'),
					parent = $(this).parents(".grid-stack-item"),
					rawname = parent.data("rawname"),
					widget_type_id = parent.data("widget_type_id"),
					widget_id = parent.data("id"),
					title = parent.data("widget_module_name"),
					name = parent.data("name");

			$('#widget_settings').attr("data-rawname",rawname);
			$('#widget_settings').data('rawname',rawname);

			$('#widget_settings').attr("data-id",widget_id);
			$('#widget_settings').data('id',widget_id);

			$('#widget_settings').attr("data-widget_type_id",widget_type_id);
			$('#widget_settings').data('widget_type_id',widget_type_id);

			$this.activateSettingsLoading();
			$("#widget_settings .modal-title").html('<i class="fa fa-cog" aria-hidden="true"></i> '+title+" "+_("Settings")+" ("+name+")");
			$('#widget_settings').modal('show');
			$('#widget_settings').one('shown.bs.modal', function() {
				$this.getSettingsContent(settings_container, widget_id, widget_type_id, rawname, function() {
					$("#widget_settings .modal-body .fa-question-circle").click(function(e) {
						e.preventDefault();
						e.stopPropagation();
						var f = $(this).parents("label").attr("for");
						$(".help-block").addClass('help-hidden');
						$('.help-block[data-for="'+f+'"]').removeClass('help-hidden');
					});
					$(document).trigger("post-body.widgetsettings",[ widget_id, $this.activeDashboard ]);
				});
			});
		});

		$(window).resize(function() {
			var gridstack = $(".grid-stack").data('gridstack');
			if(typeof gridstack === "undefined") {
				return;
			}
			setTimeout(function() {
				if(window.innerWidth <= 768) {
					gridstack.resizable($(".grid-stack-item").not('[data-gs-no-resize]'),false);
					gridstack.enableMove(false);
				} else {
					gridstack.resizable($(".grid-stack-item").not('[data-gs-no-resize]'),true);
					gridstack.enableMove(true);
				}
			},100);
		});

		if(!$(".grid-stack").length) {
			this.activeDashboard = null;
			$(document).trigger("post-body.widgets",[ null, this.activeDashboard ]);
		} else {
			var dashboard_id = $(".grid-stack").data("dashboard_id");
			//Are we looking a dashboard?
			this.activeDashboard = dashboard_id;

			$this.setupGridStack();
			$this.bindGridChanges();

			var gridstack = $(".grid-stack").data('gridstack');
			var total = gridstack.grid.nodes.length;
			var count = 0;
			var resave = false;
			if(total > 0) {
				$.each(gridstack.grid.nodes, function(i,v){
					var el = v.el;
					if(!el.hasClass("add-widget-widget")){
						var widget_id = $(el).data('id');
						var widget_type_id = $(el).data('widget_type_id');
						var widget_rawname = $(el).data('rawname');
						if(typeof $(el).data("regenuuid") !== "undefined" && $(el).data("regenuuid")) {
							resave = true;
						}
						$this.getWidgetContent(widget_id, widget_type_id, widget_rawname, function() {
							count++;
							if(count == total) {
								$(document).trigger("post-body.widgets",[ null, $this.activeDashboard ]);
								if(resave) {
									$this.saveLayoutContent();
								}
							}
						});
					}
				});
			} else {
				$(document).trigger("post-body.widgets",[ null, $this.activeDashboard ]);
			}


			$(".dashboard-menu").removeClass("active");

			$(".dashboard-menu[data-id='"+this.activeDashboard+"']").addClass("active");
			UCP.callModulesByMethod("showDashboard",this.activeDashboard);
		}
	},
	/**
	 * Save Dashboard Layout State
	 * @method saveLayoutContent
	 */
	saveLayoutContent: function() {
		this.activateFullLoading();

		var $this = this,
				grid = $('.grid-stack').data('gridstack');

		//TODO: lodash :-|
		var gridDataSerialized = lodash.map($('.grid-stack .grid-stack-item:visible').not(".grid-stack-placeholder"), function (el) {
			el = $(el);
			var node = el.data('_gridstack_node'),
					locked = el.find(".lock-widget i").hasClass("fa-lock");

			return {
				id: el.data('id'),
				widget_module_name: el.data('widget_module_name'),
				name: el.data('name'),
				rawname: el.data('rawname'),
				widget_type_id: el.data('widget_type_id'),
				has_settings: el.data('has_settings'),
				size_x: node.x,
				size_y: node.y,
				col: node.width,
				row: node.height,
				locked: locked,
				uuid: el.data('uuid')
			};
		});

		dashboards[$this.activeDashboard] = gridDataSerialized;

		$.post( UCP.ajaxUrl,
			{
				module: "Dashboards",
				command: "savedashlayout",
				id: $this.activeDashboard,
				data: JSON.stringify(gridDataSerialized)
			},
			function( data ) {
				if(data.status){
					console.log("saved grid");
				}else {
					UCP.showAlert(_("Something went wrong saving the information (grid)"), "danger");
				}
		}).always(function() {
			$this.deactivateFullLoading();
		}).fail(function(jqXHR, textStatus, errorThrown) {
			UCP.showAlert(textStatus,'warning');
		});
	},
	saveSidebarContent: function(callback) {
		this.activateFullLoading();

		var $this = this,
				sidebar_objects = $("#side_bar_content li.custom-widget a"),
				all_content = [];

		sidebar_objects.each(function(){

			var widget_id = $(this).data('id'),
					widget_type_id = $(this).data('widget_type_id'),
					widget_module_name = $(this).data('module_name'),
					widget_rawname = $(this).data('rawname'),
					widget_name = $(this).data('name'),
					widget_icon = $(this).data('icon'),
					small_widget = {
						id:widget_id,
						widget_type_id: widget_type_id,
						module_name: widget_module_name,
						rawname: widget_rawname,
						name: widget_name,
						icon: widget_icon
					};

			all_content.push(small_widget);

		});

		var gridDataSerialized = JSON.stringify(all_content);

		$.post( UCP.ajaxUrl ,
			{
				module: "Dashboards",
				command: "savesimplelayout",
				data: gridDataSerialized
			},
			function( data ) {
				if(data.status){
					console.log("sidebar saved");
				}else {
					UCP.showAlert(_("Something went wrong saving the information (sidebar)"), "danger");
				}
				if(typeof callback === "function") {
					callback();
				}
		}).always(function() {
			$this.deactivateFullLoading();
		}).fail(function(jqXHR, textStatus, errorThrown) {
			UCP.showAlert(textStatus,'warning');
		});
	},
	/**
	 * Show the full screen loading
	 * @method activateFullLoading
	 */
	activateFullLoading: function(){
		$(".main-block").removeClass("hidden");
		NProgress.start();
	},
	/**
	 * Hide the full screen loading
	 * @method deactivateFullLoading
	 */
	deactivateFullLoading: function(){
		$(".main-block").addClass("hidden");
		NProgress.done();
	},
	/**
	 * Show the widget loading screen
	 * @method activateWidgetLoading
	 * @param  {object}              widget_object jQuery object of the widget content
	 * @return {string}                            Returns the html if no object provided
	 */
	activateWidgetLoading: function(widget_object){

		var loading_html = '<div class="widget-loading-box">' +
			'					<span class="fa-stack fa">' +
			'						<i class="fa fa-cloud fa-stack-2x text-internal-blue"></i>' +
			'						<i class="fa fa-cog fa-spin fa-stack-1x secundary-color"></i>' +
			'					</span>' +
			'				</div>';

		if(typeof widget_object !== "undefined") {
			widget_object.html(loading_html);
		} else {
			return loading_html;
		}
	},
	/**
	 * Show the settings loading screen
	 * @method activateSettingsLoading
	 */
	activateSettingsLoading: function() {
		var loading_html = '<div class="settings-loading-box">' +
			'					<span class="fa-stack fa">' +
			'						<i class="fa fa-cloud fa-stack-2x text-internal-blue"></i>' +
			'						<i class="fa fa-cog fa-spin fa-stack-1x secundary-color"></i>' +
			'					</span>' +
			'				</div>';
		$("#widget_settings .modal-body").html(loading_html);
	},
	/**
	 * Generate Widget Layout
	 * @method widget_layout
	 * @param  {string}      widget_id           The widget ID
	 * @param  {string}      widget_module_name  The widget module name
	 * @param  {string}      widget_name         The widget name
	 * @param  {string}      widget_type_id      The widget sub ID
	 * @param  {string}      widget_rawname      The widget rawname
	 * @param  {Boolean}     widget_has_settings If the widget has settings or not
	 * @param  {string}      widget_content      The widget content
	 * @param  {Boolean}      resizable           is resizable
	 * @param  {Boolean}      locked              is locked
	 * @return {string}                          The finalized html
	 */
	widget_layout: function(widget_id, widget_module_name, widget_name, widget_type_id, widget_rawname, widget_has_settings, widget_content, resizable, locked){
		var cased = widget_rawname.modularize(),
				icon = allWidgets[cased].icon,
				lockIcon = locked ? 'fa-lock' : 'fa-unlock-alt',
				settings_html = '';

		//TODO: boolean is checking by string reference??
		if(widget_has_settings == "1"){
			settings_html = '<div class="widget-option edit-widget" data-rawname="'+widget_rawname+'" data-widget_type_id="'+widget_type_id+'">' +
								'<i class="fa fa-cog" aria-hidden="true"></i>' +
							'</div>';
		}
		var rs_html = '';
		if(!resizable) {
			rs_html = 'data-no-resize="true"';
		}

		var html = '' +
					'<div data-widget_module_name="'+widget_module_name+'" data-id="'+widget_id+'" data-name="'+widget_name+'" data-rawname="'+widget_rawname+'" data-widget_type_id="'+widget_type_id+'" data-has_settings="'+widget_has_settings+'" class="flip-container" '+rs_html+'>' +
						'<div class="grid-stack-item-content flipper">' +
							'<div class="front">' +
								'<div class="widget-title">' +
									'<div class="widget-module-name truncate-text"><i class="fa-fw '+icon+'"></i>' + widget_name + '</div>' +
									'<div class="widget-module-subname truncate-text">('+widget_module_name+')</div>' +
									'<div class="widget-options">' +
										'<div class="widget-option remove-widget" data-widget_id="'+widget_id+'" data-widget_type_id="'+widget_type_id+'" data-widget_rawname="'+widget_rawname+'">' +
											'<i class="fa fa-times" aria-hidden="true"></i>' +
										'</div>' +
										settings_html +
										'<div class="widget-option lock-widget" data-widget_id="'+widget_id+'" data-widget_type_id="'+widget_type_id+'" data-widget_rawname="'+widget_rawname+'">' +
											'<i class="fa '+lockIcon+'" aria-hidden="true"></i>' +
										'</div>' +
									'</div>' +
								'</div>' +
			'<div class="widget-content">' + widget_content + '</div>' +
							'</div>' +
							'<div class="back">' +
								'<div class="widget-title settings-title">' +
									'<div class="widget-module-name truncate-text">'+_('Settings')+'</div>' +
									'<div class="widget-module-subname truncate-text">(' + widget_module_name + ' '+widget_name+')</div>' +
									'<div class="widget-options">' +
										'<div class="widget-option close-settings" data-rawname="'+widget_rawname+'" data-widget_type_id="'+widget_type_id+'">' +
											'<i class="fa fa-times" aria-hidden="true"></i>' +
										'</div>' +
									'</div>' +
								'</div>' +
								'<div class="widget-settings-content">' +
								'</div>' +
							'</div>' +
						'</div>' +
					'</div>';

		return html;
	},
	/**
	 * Generate Side Bar Icon Layout
	 * @method smallWidgetLayout
	 * @param  {string}          widget_id          The widget ID
	 * @param  {string}          widget_rawname     The widget rawname
	 * @param  {string}          widget_name        The widget name
	 * @param  {string}          widget_type_id     The widget sub id
	 * @param  {string}          widget_icon        The widget icon class
	 * @return {string}                             The finalized HTML
	 */
	smallWidgetLayout: function(widget_id, widget_rawname, widget_name, widget_type_id, widget_icon){
		var html = '' +
			'<li class="custom-widget" data-widget_id="'+widget_id+'" data-widget_rawname="'+widget_rawname+'" data-widget_type_id="'+widget_type_id+'">' +
				'<a href="#" title="'+widget_rawname+' '+widget_type_id+'" data-id="'+widget_id+'" data-name="'+widget_name+'" data-rawname="'+widget_rawname+'" data-widget_type_id="'+widget_type_id+'" data-icon="' + widget_icon + '"><i class="' + widget_icon + '" aria-hidden="true"></i></a>' +
			'</li>';

		return html;
	},
	/**
	 * Small Widget Menu Layout
	 * @method smallWidgetMenuLayout
	 * @param  {string}              widget_id      The Widget ID
	 * @param  {string}              widget_rawname The widget rawname
	 * @param  {string}              widget_name    The widget name
	 * @param  {string}              widget_type_id The widget name
	 * @param  {string}              widget_icon    The widget icon class
	 * @param  {string}              widget_sub     The widget sub name
	 * @param  {Boolean}             hasSettings    If the settings COG should be generated
	 * @return {string}                             The finalized HTML
	 */
	smallWidgetMenuLayout: function(widget_id, widget_rawname, widget_name, widget_type_id, widget_icon, widget_sub, hasSettings){
		var settings_html = '';
		if(hasSettings) {
			settings_html = '<i class="fa fa-cog show-simple-widget-settings" aria-hidden="true"></i>';
		}

		var html = '' +
			'<div class="widget-extra-menu" id="menu_'+widget_id+'" data-id="'+widget_id+'" data-widget_type_id="'+widget_type_id+'" data-module="'+widget_rawname+'" data-name="'+widget_name+'" data-widget_name="'+widget_type_id+'" data-icon="'+widget_icon+'">' +
				'<div class="menu-actions">' +
					'<i class="fa fa-times-circle-o close-simple-widget-menu" aria-hidden="true"></i>' +
					settings_html +
				'</div>' +
				'<h5 class="small-widget-title"><i class="fa '+widget_icon+'"></i> <span>'+widget_sub+'</span> <small>('+widget_name+')</small></h5>' +
				'<div class="small-widget-content">' +
				'</div>' +
				'<button type="button" class="btn btn-xs btn-danger remove-small-widget" data-widget_id="'+widget_id+'" data-widget_rawname="'+widget_rawname+'">'+_('Remove Widget')+'</button>' +
			'</div>';

		return html;
	},
	/**
	 * Show dashboard error
	 * @method showDashboardError
	 * @param  {string}           message The message to show
	 */
	showDashboardError: function(message) {
		//TODO: should we destroy the gird if it exists?
		$("#dashboard-content .module-page-widgets").html('<div class="dashboard-error"><div class="message"><i class="fa fa-exclamation-circle" aria-hidden="true"></i><br/>'+message+'</div></div>');
	},
	/**
	 * Initalize Menu Dragging
	 * @method initMenuDragabble
	 */
	initMenuDragabble: function(){
		var $this = this,
				el = document.getElementById('side_bar_content');

		var sortable = Sortable.create(el, {
			draggable: ".custom-widget",
			filter: "i",
			onUpdate: function (evt) {
				sortable.option("disabled",true);
				$this.saveSidebarContent(function() {
					sortable.option("disabled",false);
				});
			}
		});
	},
	/**
	 * Initalize Dashboard Tab Dragging
	 * @method initDashboardDragabble
	 */
	initDashboardDragabble: function() {
		var $this = this,
				el = document.getElementById('all_dashboards');

		var sortable = Sortable.create(el, {
			draggable: ".dashboard-menu",
			filter: "i",
			onUpdate: function (evt) {
				sortable.option("disabled",true);
				$this.saveDashboardOrder(function() {
					sortable.option("disabled",false);
				});
			}
		});
	},
	/**
	 * Save Dashboard Tab order
	 * @method saveDashboardOrder
	 * @param  {Function}         callback Callback function when finished saving
	 */
	saveDashboardOrder: function(callback) {
		var dashboardOrder = [],
				$this = this;
		$this.activateFullLoading();
		$("#all_dashboards li").each(function() {
			dashboardOrder.push($(this).data("id"));
		});
		$.post( UCP.ajaxUrl,
			{
				module: "Dashboards",
				command: "reorder",
				order: dashboardOrder
			},
			function( data ) {
				if(typeof callback === "function") {
					callback();
				}
		}).always(function() {
			$this.deactivateFullLoading();
		}).fail(function(jqXHR, textStatus, errorThrown) {
			UCP.showAlert(textStatus,'warning');
		});
	},
	/**
	 * Open(Show) the extra widget menu
	 * @method openExtraWidgetMenu
	 * @param  {Function}          callback callback function when the menu is finished opening
	 */
	openExtraWidgetMenu: function(callback) {
		var previous = this.widgetMenuOpen;
		this.widgetMenuOpen = true;
		if(previous) {
			if(typeof callback === "function") {
				callback();
			}
			return;
		}
		$(".side-menu-widgets-container").one("transitionend",function() {
			if(typeof callback === "function") {
				callback();
			}
		});
		$(".side-menu-widgets-container").addClass("open");
	},
	/**
	 * Close the side bar menu
	 * @method closeExtraWidgetMenu
	 * @param  {Function}           callback Callback when the menu is finished closing
	 */
	closeExtraWidgetMenu: function(callback) {
		var previous = this.widgetMenuOpen;
		this.widgetMenuOpen = false;
		if(!previous) {
			$("#side_bar_content li.active").removeClass("active");
			if(typeof callback === "function") {
				callback();
			}
			return;
		}
		$(".side-menu-widgets-container").one("transitionend",function() {
			$(".widget-extra-menu:visible").addClass("hidden");
			$("#side_bar_content li.active").removeClass("active");
			$(document).trigger("post-body.closesimplewidget");
			if(typeof callback === "function") {
				callback();
			}
		});
		$(".side-menu-widgets-container").removeClass("open");
	},
	/**
	 * Initialize Side Bar Widgets
	 * @method initLeftNavBarMenus
	 */
	initLeftNavBarMenus: function(){
		var $this = this;

		$(document).on("click", ".close-simple-widget-menu", function() {
			$this.closeExtraWidgetMenu();
		});

		/**
		 * Click to show the simple widget settings
		 */
		$(document).on("click", ".show-simple-widget-settings", function() {
			var parent = $(this).parents(".widget-extra-menu"),
					rawname = parent.data("module"),
					widget_type_id = parent.data("widget_type_id"),
					widget_id = parent.data("id"),
					settings_container = $('#widget_settings .modal-body'),
					title = parent.data("name"),
					name = parent.data("widget_name");

			$('#widget_settings').attr("data-rawname",rawname);
			$('#widget_settings').data('rawname',rawname);

			$('#widget_settings').attr("data-id",widget_id);
			$('#widget_settings').data('id',widget_id);

			$('#widget_settings').attr("data-widget_type_id",widget_type_id);
			$('#widget_settings').data('widget_type_id',widget_type_id);

			$this.activateSettingsLoading();
			$("#widget_settings .modal-title").html('<i class="fa fa-cog" aria-hidden="true"></i> '+title+" "+_("Settings")+" ("+name+")");
			$('#widget_settings').modal('show');
			$('#widget_settings').one('shown.bs.modal', function() {
				$this.getSimpleSettingsContent(settings_container, widget_id, widget_type_id, rawname, function() {
					$("#widget_settings .modal-body .fa-question-circle").click(function(e) {
						e.preventDefault();
						e.stopPropagation();
						var f = $(this).parents("label").attr("for");
						$(".help-block").addClass('help-hidden');
						$('.help-block[data-for="'+f+'"]').removeClass('help-hidden');
					});
					$(document).trigger("post-body.simplewidgetsettings",[ widget_id ]);
				});
			});
		});

		/**
		 * Click the settings cog on a widget
		 */
		$(document).on("click", ".settings-widget", function(event){
			event.preventDefault();
			event.stopPropagation();

			var widget_type_id = 'user',
					widget_id = 'user',
					rawname = 'settings',
					settings_container = $('#widget_settings .modal-body');
			$this.activateSettingsLoading();
			$("#widget_settings .modal-title").html('<i class="fa fa-cog" aria-hidden="true"></i> '+_("User Settings"));
			$('#widget_settings').modal('show');
			$('#widget_settings').one('shown.bs.modal', function() {
				$this.getSimpleSettingsContent(settings_container, widget_id, widget_type_id, rawname, function() {
					$("#widget_settings .modal-body .fa-question-circle").click(function(e) {
						e.preventDefault();
						e.stopPropagation();
						var f = $(this).parents("label").attr("for");
						$(".help-block").addClass('help-hidden');
						$('.help-block[data-for="'+f+'"]').removeClass('help-hidden');
					});
					$(document).trigger("post-body.simplewidgetsettings",[ widget_id ]);
				});
			});
		});

		/**
		 * Click sidebar widgets (Simple widgets)
		 */
		$(document).on("click", ".custom-widget i", function(event){
			event.preventDefault();
			event.stopPropagation();

			var widget = $(this).parents(".custom-widget");

			//We are already looking at it so close it and move on
			if(widget.hasClass("active")) {
				$this.closeExtraWidgetMenu();
				return;
			}

			var clicked_module = widget.find("a").data("rawname"),
					clicked_id = widget.find("a").data("widget_type_id"),
					widget_id = widget.find("a").data("id"),
					content_object = $("#menu_"+widget_id).find(".small-widget-content");

			$("#side_bar_content li.active").removeClass("active");
			widget.addClass("active");

			$(".widget-extra-menu:visible").addClass("hidden");

			$this.activateWidgetLoading(content_object);
			$("#menu_"+widget_id).removeClass("hidden");
			$this.openExtraWidgetMenu();

			$.post( UCP.ajaxUrl,
				{
					module: "Dashboards",
					command: "getsimplewidgetcontent",
					id: clicked_id,
					rawname: clicked_module,
					uuid: uuid
				},
				function( data ) {
					
					if (data.hasError || typeof data.html !== "undefined") {
						let widget_html = data.html;
						if (data.hasError) {
							widget_html = '';
							data.errorMessages.forEach(errorMessage => {
								widget_html += '<div class="alert alert-danger">'+_(errorMessage)+'</div>';
							});
						}
						content_object.html(widget_html);

						UCP.callModuleByMethod(clicked_module,"displaySimpleWidget",widget_id);
						$(document).trigger("post-body.simplewidget",[ widget_id ]);
					} else {
						UCP.showAlert(_("There was an error getting the widget information, try again later"), "danger");
					}
				}).fail(function(jqXHR, textStatus, errorThrown) {
					UCP.showAlert(textStatus,'warning');
				});
		});
	},
	/**
	 * Initialize the item lock buttons
	 * @method initLockItemButtons
	 * @return {[type]}            [description]
	 */
	initLockItemButtons: function(){
		var $this = this;

		/**
		 * Lock a single widget on a dashboard
		 */
		$(document).on("click", ".lock-widget", function(event){
			event.preventDefault();
			event.stopPropagation();

			if(window.innerWidth <= 768) {
				UCP.showAlert(_("Widgets can not be locked on this device"),"warning");
				return;
			}

			var locked = $(this).find("i").hasClass("fa-lock"),
				id = $(this).data("widget_id"),
				grid = $('.grid-stack').data('gridstack');

			if(locked) {
				$(this).find("i").removeClass().addClass("fa fa-unlock-alt");
			} else {
				$(this).find("i").removeClass().addClass("fa fa-lock");
			}
			if($(".grid-stack-item[data-id="+id+"]").data("no-resize") != "true") {
				grid.resizable($(".grid-stack-item[data-id="+id+"]"), locked);
			}

			//set locking on widgets
			grid.movable($(".grid-stack-item[data-id="+id+"]"), locked);
			grid.locked($(".grid-stack-item[data-id="+id+"]"), !locked);

			//save layout
			$this.saveLayoutContent();
		});

		/**
		 * Lock all widgets on a dashboard
		 * TODO: this only works with the current dashboard for now
		 */
		$(document).on("click", ".lock-dashboard", function(event){
			event.preventDefault();
			event.stopPropagation();

			if($(this).hasClass("fa-unlock-alt")) {
				$(this).removeClass("fa-unlock-alt").addClass("fa-lock");
				$(".widget-options .fa-unlock-alt").click();
			} else {
				$(this).removeClass("fa-lock").addClass("fa-unlock-alt");
				$(".widget-options .fa-lock").click();
			}
		});
	},
	htmlEntities: function(str) {
		return $("<div/>").text(str).html();
	},
	/**
	 * Initalize the document remove buttons
	 * @method initRemoveItemButtons
	 */
	initRemoveItemButtons: function(){
		var $this = this;

		/**
		 * Remove widget button
		 */
		$(document).on("click", ".remove-widget", function(event){
			//stop browser
			event.preventDefault();
			event.stopPropagation();

			var widget_id = $(this).data("widget_id");
			var widget_rawname = $(this).data("widget_rawname");
			var widget_type_id = $(this).data("widget_type_id");

			UCP.showConfirm(_("Are you sure you want to delete this widget?"), "warning", function() {
				var grid = $('.grid-stack').data('gridstack');
				//remove widget
				grid.removeWidget($(".grid-stack-item[data-id='" + widget_id + "']"));
				//save layout
				$this.saveLayoutContent();
				//call module method
				UCP.callModuleByMethod(widget_rawname,"deleteWidget",widget_type_id,$this.activeDashboard);
				//TODO: does this need a document trigger?
			});
		});

		/**
		 * Remove small widget code
		 */
		$(document).on("click", ".remove-small-widget", function(event){
			//stop browser
			event.preventDefault();
			event.stopPropagation();

			var widget_to_remove = $(this).data("widget_id"),
					widget_rawname = $(this).data("widget_rawname"),
					sidebar_object_to_remove = $("#side_bar_content li.custom-widget[data-widget_id='" + widget_to_remove + "']"),
					sidebar_menu_to_remove = $(".side-menu-widgets-container .widget-extra-menu[data-id='menu_" + widget_rawname + "_"+widget_to_remove+"']");

			UCP.callModuleByMethod(widget_rawname,"deleteSimpleWidget",widget_to_remove);

			sidebar_object_to_remove.remove();

			//close the menu
			$this.closeExtraWidgetMenu(function() {
				sidebar_menu_to_remove.remove();
			});

			//save the page
			$this.saveSidebarContent();
		});

		/**
		 * Edit Dashboard Button
		 */
		$(document).on("click", ".edit-dashboard", function(event){
			//stop the browser
			event.preventDefault();
			event.stopPropagation();

			var parent = $(this).parents('.dashboard-menu'),
					dashboard_id = parent.data("id"),
					title = parent.find("a");

			//se the input to what we have now
			$('#edit_dashboard_name').val(title.text());

			//trigger when the modal is shown (once)
			$('#edit_dashboard').one('shown.bs.modal', function () {
				//unbind because we were bound previously
				$("#edit_dashboard").off("keydown");
				$("#edit_dashboard").on('keydown', function(event) {
					switch(event.keyCode) {
						case 13: //detect enter
							$("#edit_dashboard_btn").click();
						break;
					}
				});
				//click event
				$("#edit_dashboard_btn").one("click",function() {
					//get the new name
					var name = $this.htmlEntities($("#edit_dashboard_name").val());
					//show loading window so nothing changes
					$this.activateFullLoading();
					//send it off and save!
					$.post( UCP.ajaxUrl,
						{
							module: "Dashboards",
							command: "rename",
							id: dashboard_id,
							name: name
						},
						function( data ) {
							if(data.status) {
								title.replaceWith('<a data-dashboard>'+name+'</a>');
								$("#edit_dashboard").modal('hide');
							} else {
								UCP.showAlert(_("Something went wrong removing the dashboard"), "danger");
							}
					}).always(function() {
						$this.deactivateFullLoading();
					}).fail(function(jqXHR, textStatus, errorThrown) {
						UCP.showAlert(textStatus,'warning');
					});
				});
				//focus on the name
				$('#dashboard_name').focus();
			});
			//show the modal
			$("#edit_dashboard").modal('show');
		});

		/**
		 * Remve Dashboard
		 */
		$(document).on("click", ".remove-dashboard", function(event){
			//stop browser from doing what it wants
			event.preventDefault();
			event.stopPropagation();

			var dashboard_id = $(this).parents('.dashboard-actions').data("dashboard_id");

			//Check confirm
			UCP.showConfirm(_("Are you sure you want to delete this dashboard?"), "warning", function() {

				//show loading window so nothing changes
				$this.activateFullLoading();

				$.post( UCP.ajaxUrl ,
					{
						module: "Dashboards",
						command: "remove",
						id: dashboard_id
					},
					function( data ) {
						if (data.status) {
							$(".dashboard-menu[data-id='" + dashboard_id + "']").remove();

							if(dashboard_id == $this.activeDashboard) {
								if($(".dashboard-menu").length > 0) {
									$(".dashboard-menu").first().find("a").click();
								} else {
									$this.showDashboardError(_("You have no dashboards. Click here to add one"));
									$("#dashboard-content .dashboard-error").css("cursor","pointer");
									$("#dashboard-content .dashboard-error").click(function() {
										$("#add_new_dashboard").click();
									});
								}
							}

						} else {
							UCP.showAlert(_("Something went wrong removing the dashboard"), "danger");
						}
				}).always(function() {
					$this.deactivateFullLoading();
				}).fail(function(jqXHR, textStatus, errorThrown) {
					UCP.showAlert(textStatus,'warning');
				});
			});

		});
	},
	/**
	 * Initialize Widget Add Buttons
	 * TODO: needs cleanup
	 * @method initAddWidgetsButtons
	 */
	initAddWidgetsButtons: function(){
		$("#add_widget").on("show.bs.modal",function() {
			$this.closeExtraWidgetMenu();
			$(".navbar-nav .add-widget").addClass("active");
		});
		//tab select scroll position memory
		$('#add_widget .nav-tabs a[data-toggle=tab]').on('shown.bs.tab', function (e) {
			$("#add_widget .bhoechie-tab-menu .list-group-item").each(function() {
				$(this).data("position",$(this).position().top);
			});
			var container = $("#add_widget .tab-content");
			$(e.relatedTarget).data("scroll",container.scrollTop());

			var scroll = $(e.target).data("scroll");
			if(typeof scroll !== "undefined") {
				container.scrollTop(scroll);
			} else {
				container.scrollTop(0);
			}
		});
		$("#add_widget").on("shown.bs.modal",function() {
			$("#add_widget .bhoechie-tab-menu .list-group-item").each(function() {
				$(this).data("position",$(this).position().top);
			});
			$("#add_widget .tab-content").off("scroll");
			$("#add_widget .tab-content").scroll(function() {
				var top = $(this).scrollTop();
				var bottom = $(this).scrollTop() + $(this).height();
				if(($(this).find(".tab-pane.active .bhoechie-tab-menu").height() - (top - 30)) > $(this).height()) {
					$(this).find(".tab-pane.active .bhoechie-tab").css("top",top);
				}

				var active  = $(this).find(".tab-pane.active .list-group-item.active");
				active.removeClass("top-locked bottom-locked");
				if(top > (active.data("position") + 10)) {
					active.addClass("top-locked");
				} else if(bottom < (active.data("position") + active.height())) {
					active.addClass("bottom-locked");
				}
			});
		});
		$("#add_widget").on("hidden.bs.modal",function() {
			$(".navbar-nav .add-widget").removeClass("active top-locked bottom-locked");
		});
		var $this = this;
		$(document).on("click",".add-widget-button", function(){
			if($this.activeDashboard === null) {
				UCP.showAlert(_("There is no active dashboard to add widgets to"), "danger");
				return;
			}
			var current_dashboard_id = $this.activeDashboard,
					widget_id = $(this).data('widget_id'),
					widget_module_name = $(this).data('widget_module_name'),
					widget_rawname = $(this).data('rawname'),
					widget_name = $(this).data('widget_name'),
					new_widget_id = uuid.v4(),
					icon = allWidgets[widget_rawname.modularize()].icon,
					widget_info = allWidgets[widget_rawname.modularize()].list[widget_id],
					widget_has_settings = false,
					default_size_x = 2,
					default_size_y = 2,
					min_size_x = null,
					min_size_y = null,
					max_size_x = null,
					max_size_y = null,
					resizable = true,
					dynamic = false;

			if(typeof widget_info.defaultsize !== "undefined") {
				default_size_x = widget_info.defaultsize.width;
				default_size_y = widget_info.defaultsize.height;
			}

			if(typeof widget_info.maxsize !== "undefined") {
				max_size_x = widget_info.maxsize.width;
				max_size_y = widget_info.maxsize.height;
			}

			if(typeof widget_info.minsize !== "undefined") {
				min_size_x = widget_info.minsize.width;
				min_size_y = widget_info.minsize.height;
			}

			if(typeof widget_info.hasSettings !== "undefined") {
				widget_has_settings = widget_info.hasSettings;
			}

			if(typeof widget_info.resizable !== "undefined") {
				resizable = widget_info.resizable;
			}

			if(typeof widget_info.dynamic !== "undefined") {
				dynamic = widget_info.dynamic;
			}

			//Checking if the widget is already on the dashboard
			var object_on_dashboard = ($(".grid-stack-item[data-rawname='"+widget_rawname+"'][data-widget_type_id='"+widget_id+"']").length > 0);

			if(dynamic || !object_on_dashboard) {

				$this.activateFullLoading();

				$.post( UCP.ajaxUrl ,
					{
						module: "Dashboards",
						command: "getwidgetcontent",
						id: widget_id,
						rawname: widget_rawname,
						uuid: new_widget_id
					},
					function( data ) {
						if (typeof data.html !== "undefined") {
							$("#add_widget").modal("hide");
							//So first we go the HTML content to add it to the widget
							var widget_html = data.html;
							var full_widget_html = $this.widget_layout(new_widget_id, widget_module_name, widget_name, widget_id, widget_rawname, widget_has_settings, widget_html, resizable, false);
							var grid = $('.grid-stack').data('gridstack');
							//We are adding the widget always on the position 1,1
							grid.addWidget($(full_widget_html), 1, 1, default_size_x, default_size_y, true, min_size_x, max_size_x, min_size_y, max_size_y);
							grid.resizable($("div[data-id='" + new_widget_id + "']"), resizable);
							UCP.callModuleByMethod(widget_rawname, "displayWidget", new_widget_id, $this.activeDashboard);
							$(document).trigger("post-body.widgets", [new_widget_id, $this.activeDashboard]);
						} else if (data.hasError) {
							UCP.showAlert(data.errorMessages.join('<br/>'),'warning');
						} else {
							UCP.showAlert(_("There was an error getting the widget information, try again later"), "danger");
						}
					}).always(function() {
						$this.deactivateFullLoading();
					}).fail(function(jqXHR, textStatus, errorThrown) {
						UCP.showAlert(textStatus,'warning');
					});
			} else {
				UCP.showAlert(_("You already have this widget on this dashboard"), "info");
			}
		});

		/**
		 * Add Small Widget Button Bind
		 */
		$(".add-small-widget-button").click(function(){

			var widget_id = $(this).data('id'),
					widget_rawname = $(this).data('rawname'),
					widget_name = $(this).data('name'),
					widget_sub = $(this).data('widget_type_id'),
					new_widget_id = uuid.v4(),
					widget_info = allSimpleWidgets[widget_rawname.modularize()].list[widget_id],
					widget_icon = $(this).data('icon'),
					hasSettings = false,
					dynamic = false;

			if(typeof widget_info.hasSettings !== "undefined") {
				hasSettings = widget_info.hasSettings;
			}

			if(typeof widget_info.dynamic !== "undefined") {
				dynamic = widget_info.dynamic;
			}

			//Checking if the widget is already on the dashboard

			var object_on_dashboard = ($("#side_bar_content li.custom-widget[data-widget_rawname='"+widget_rawname+"'][data-widget_type_id='"+widget_id+"']").length > 0);

			//Checking if the widget is already on the bar
			if(dynamic || !object_on_dashboard){

				$this.activateFullLoading();

				$.post( UCP.ajaxUrl,
					{
						module: "Dashboards",
						command: "getsimplewidgetcontent",
						id: widget_id,
						rawname: widget_rawname,
						uuid: new_widget_id
					},
					function( data ) {
						$("#add_widget").modal("hide");

						if(typeof data.html !== "undefined"){
							$("#add_widget").modal("hide");
							//get small widget layout
							var full_widget_html = $this.smallWidgetLayout(new_widget_id, widget_rawname, widget_name, widget_id, widget_icon);
							//get small widget menu layout
							var menu_widget_html = $this.smallWidgetMenuLayout(new_widget_id, widget_rawname, widget_name, widget_id, widget_icon, widget_sub, hasSettings);

							//add icon to sidebar
							if($("#side_bar_content .custom-widget").length) {
								//we already have an element on the sidebar so add to the end
								$("#side_bar_content .custom-widget").last().after(full_widget_html);
							} else {
								//add widget after the add button because we dont have anything there
								$("#side_bar_content .add-widget").after(full_widget_html);
							}

							//now add the menu (hidden) to the widgets container for expansion later
							$(".side-menu-widgets-container").append(menu_widget_html);

							//execute module method
							UCP.callModuleByMethod(widget_rawname,"addSimpleWidget",new_widget_id);

							//execute trigger
							$(document).trigger("post-body.addsimplewidget",[ new_widget_id, $this.activeDashboard ]);

							//save side bar
							$this.saveSidebarContent();
						} else if (data.hasError) {
							UCP.showAlert(data.errorMessages.join('<br/>'),'warning');
						} else {
							UCP.showAlert(_("There was an error getting the widget information, try again later"), "danger");
						}
					}).always(function() {
						$this.deactivateFullLoading();
					}).fail(function(jqXHR, textStatus, errorThrown) {
						UCP.showAlert(textStatus,'warning');
					});
			}else {
				UCP.showAlert(_("You already have this widget on the side bar"), "info");
			}
		});
	},
	/**
	 * Initiate Category Binds
	 * @method initCategoriesWidgets
	 */
	initCategoriesWidgets: function(){
		$("#add_widget .bhoechie-tab-container").each(function() {
			var parent = $(this);
			$(this).find(".list-group-item").click(function(e) {
				e.preventDefault();
				$(this).siblings('a.active').removeClass("active top-locked bottom-locked");
				$(this).addClass("active");
				var id = $(this).data("id");
				parent.find(".bhoechie-tab-content").removeClass("active top-locked bottom-locked");
				parent.find(".bhoechie-tab-content[data-id='"+id+"']").addClass("active");
			});
		});
	},
	/**
	 * Get Widget content
	 * TODO: This is duplicated in certain places!!!
	 * @method getWidgetContent
	 * @param  {string}           widget_id             The widget ID
	 * @param  {string}           widget_type_id        The widget type ID
	 * @param  {string}           widget_rawname        The widget rawname
	 * @param  {Function}         callback              Callback Function when done (success + complete)
	 */
	getWidgetContent: function(widget_id, widget_type_id, widget_rawname, callback){
		var $this = this,
				widget_content_object = $(".grid-stack-item[data-id='"+widget_id+"'] .widget-content");
		this.activateWidgetLoading(widget_content_object);

		$.post( UCP.ajaxUrl,
			{
				module: "Dashboards",
				command: "getwidgetcontent",
				id: widget_type_id,
				rawname: widget_rawname,
				uuid: widget_id
			},
			function( data ) {

				var widget_html = data.html;

				if (data.hasError) {
					widget_html = '';
					data.errorMessages.forEach(errorMessage => {
						widget_html += '<div class="alert alert-danger">'+_(errorMessage)+'</div>';
					});
				}

				widget_content_object.html(widget_html);
				UCP.callModuleByMethod(widget_rawname,"displayWidget",widget_id,$this.activeDashboard);
				setTimeout(function() {
					UCP.callModuleByMethod(widget_rawname,"resize",widget_id,$this.activeDashboard);
				},100);

			}).done(function() {
				if(typeof callback === "function") {
					callback();
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				UCP.showAlert(textStatus,'warning');
			});
	},
	/**
	 * Get Simple Widget Settings Content
	 * @method getSimpleSettingsContent
	 * @param  {object}           widget_content_object jQuery object of the settings container
	 * @param  {string}           widget_id             The widget ID
	 * @param  {string}           widget_type_id        The widget type ID
	 * @param  {string}           widget_rawname        The widget rawname
	 * @param  {Function}         callback              Callback Function when done (success + complete)
	 */
	getSimpleSettingsContent: function(widget_content_object, widget_id, widget_type_id, widget_rawname, callback){
		var $this = this;

		$.post( UCP.ajaxUrl,
			{
				module: "Dashboards",
				command: "getsimplewidgetsettingscontent",
				id: widget_type_id,
				rawname: widget_rawname,
				uuid: widget_id
			},
			function( data ) {

				var widget_html = data.html;

				if(typeof data.html === "undefined"){
					widget_html = '<div class="alert alert-danger">'+_('Something went wrong getting the settings from the widget')+'</div>';
				}

				widget_content_object.html(widget_html);
				UCP.callModuleByMethod(widget_rawname,"displaySimpleWidgetSettings",widget_id);
			}).done(function() {
				if(typeof callback === "function") {
					callback();
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				UCP.showAlert(textStatus,'warning');
			});
	},
	/**
	 * Get Module Settings Content
	 * @method getSettingsContent
	 * @param  {object}           widget_content_object jQuery object of the settings container
	 * @param  {string}           widget_id             The widget ID
	 * @param  {string}           widget_type_id        The widget type ID
	 * @param  {string}           widget_rawname        The widget rawname
	 * @param  {Function}         callback              Callback Function when done (success + complete)
	 */
	getSettingsContent: function(widget_content_object, widget_id, widget_type_id, widget_rawname, callback){
		var $this = this;

		$.post( UCP.ajaxUrl,
			{
				module: "Dashboards",
				command: "getwidgetsettingscontent",
				id: widget_type_id,
				rawname: widget_rawname,
				uuid: widget_id
			},
			function( data ) {

				var widget_html = data.html;

				if(typeof data.html === "undefined"){
					widget_html = '<div class="alert alert-danger">'+_('Something went wrong getting the settings from the widget')+'</div>';
				}

				widget_content_object.html(widget_html);
				UCP.callModuleByMethod(widget_rawname,"displayWidgetSettings",widget_id,$this.activeDashboard);
			}).done(function() {
				if(typeof callback === "function") {
					callback();
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				UCP.showAlert(textStatus,'warning');
			});
	},
	/**
	 * Setup grid stack!
	 * @method setupGridStack
	 * @return {object}       The gridstack object!
	 */
	setupGridStack: function() {
		var gridstack = $(".grid-stack").data('gridstack');
		if(typeof gridstack === "undefined") {
			$('.grid-stack').gridstack({
				cellHeight: 35,
				verticalMargin: 10,
				animate: true,
				float: true,
				draggable: {
					handle: '.widget-title',
					scroll: false,
					appendTo: 'body'
				}
			});
			gridstack = $(".grid-stack").data('gridstack');
		}
		return gridstack;
	},
	/**
	 * Bind Grid Stack changes
	 * @method bindGridChanges
	 */
	bindGridChanges: function() {
		var $this = this;
		$('.grid-stack').on('resizestop', function(event, ui) {
			//Never on mobile, Always on Desktop
			if(window.innerWidth > 768) {
				UCP.callModulesByMethod("resize",ui.element.data("id"),$this.activeDashboard);
			}
		});

		$('.grid-stack').on('removed', function(event, items) {
			//Never on Desktop, Always on mobile
			if(window.innerWidth <= 768) {
				//save layout
				$this.saveLayoutContent();
			}
		});

		$('.grid-stack').on('added', function(event, items) {
			//Never on Desktop, Always on mobile
			if(window.innerWidth <= 768) {
				//save layout
				$this.saveLayoutContent();
			}
		});

		$('.grid-stack').on('change', function(event, items) {
			//This triggers on any bubbling change so if items
			//is undefined then return
			if(typeof items === "undefined") {
				return;
			}
			//Always on Desktop, Never on mobile
			if(window.innerWidth > 768) {
				//save layout
				$this.saveLayoutContent();
			}
		});
		//some gitchy crap going on here, we have to relock the widget
		$('.grid-stack').on('dragstop', function(event, ui) {
			var grid = $(".grid-stack").data('gridstack');
			$('.grid-stack .grid-stack-item:visible').not(".grid-stack-placeholder").each(function(){
				var el = $(this);
						locked = el.find(".lock-widget i").hasClass("fa-lock");
				grid.movable(el, !locked);
				grid.locked(el, locked);
				grid.resizable(el, !locked);
			});
		});
		//some gitchy crap going on here, we have to relock the widget
		$('.grid-stack').on('resizestop', function(event, ui) {
			var grid = $(".grid-stack").data('gridstack');
			$('.grid-stack .grid-stack-item:visible').not(".grid-stack-placeholder").each(function(){
				var el = $(this);
						locked = el.find(".lock-widget i").hasClass("fa-lock");
				grid.movable(el, !locked);
				grid.locked(el, locked);
				grid.resizable(el, !locked);
			});
		});
	},
	/**
	 * Setup Add Dashboard Button Binds
	 * @method setupAddDashboard
	 */
	setupAddDashboard: function() {
		var $this = this;
		$("#create_dashboard").click(function() {
			//make sure there is something in the name
			if ($("#dashboard_name").val().trim() === "") {
				//if empty then return back and focus on name
				UCP.showAlert(_("You must set a dashboard name!"),'warning', function() {
					$("#dashboard_name").focus();
				});
			} else {
				let dashboard_name = $this.htmlEntities($("#dashboard_name").val());
				//show loading screen while we save this dashboard
				$this.activateFullLoading();

				$.post( UCP.ajaxUrl, {module: "Dashboards", command: "add", name: dashboard_name}, function( data ) {
					if (!data.status) {
						UCP.showAlert(data.message,'warning');
					} else {
						var select = $("#all_dashboards li").length;
						var new_dashboard_html = '<li class="menu-order dashboard-menu" data-id="'+data.id+'"><a data-dashboard>'+dashboard_name+'</a> <div class="dashboard-actions" data-dashboard_id="'+data.id+'"><i class="fa fa-unlock-alt lock-dashboard" aria-hidden="true"></i><i class="fa fa-pencil edit-dashboard" aria-hidden="true"></i><i class="fa fa-times remove-dashboard" aria-hidden="true"></i></div></li>';
						$("#all_dashboards").append(new_dashboard_html);

						dashboards[data.id] = null;

						$(document).trigger("addDashboard",[data.id]);

						if(!select) {
							$("#all_dashboards li a").click();
						}

						//hide modal we are done
						$("#add_dashboard").modal("hide");
					}
				}).fail(function(jqXHR, textStatus, errorThrown) {
					UCP.showAlert(textStatus,'warning');
				}).always(function() {
					$this.deactivateFullLoading();
				});
			}
		});

		//dashboard tab click
		$(document).on("click",".dashboard-menu a[data-dashboard]", function(e) {
			//stop default browser actions
			e.preventDefault();
			e.stopPropagation();

			var gridstack = $(".grid-stack").data('gridstack'),
					id = $(this).parents(".dashboard-menu").data("id"),
					popstate = $(this).data("popstate");

			popstate = (typeof popstate !== "undefined") ? popstate : false;
			//we are on this dashboard. So do nothing
			if($this.activeDashboard == id) {
				return;
			}

			//remove active from any dashboard tab
			$(".dashboard-menu").removeClass("active");
			//remove the click block from all
			$(".dashboards a[data-dashboard]").removeClass("pjax-block");
			//add click block to this one
			$(this).addClass("pjax-block");
			//activate our tab
			$(".dashboard-menu[data-id='"+id+"']").addClass("active");
			//push browser history (pjax like) only if we aren't in a popstate event
			if(!popstate) {
				history.pushState({ activeDashboard: id }, $(this).text(), "?dashboard="+id);
			} else {
				$(this).data("popstate",false);
			}
			//set tab title
			$("title").text(_("User Control Panel") + " - " + $(this).text());
			//set our active dashboard
			$this.activeDashboard = id;

			if(typeof gridstack !== "undefined") {
				//destroy the grid (which also deletes the elements!)
				gridstack.destroy(true);
			}

			//add back grid container
			$("#module-page-widgets").html('<div class="grid-stack" data-dashboard_id="'+id+'">');

			//setup grid
			gridstack = $this.setupGridStack();

			//load widgets
			$this.activateFullLoading();
			var resave = false;
			async.each(dashboards[id], function(widget, callback) {
				//uppercase the module rawname
				var cased = widget.rawname.modularize();
				if(typeof allWidgets[cased] === "undefined") {
					callback();
					return;
				}
				//get loading html
				var widget_html = $this.activateWidgetLoading();
				//TODO: fix this
				widget.resizable = true;
				if(!widget.id.match(/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i)) {
					widget.id = uuid.v4();
					resave = true;
				}
				//get widget content
				var full_widget_html = $this.widget_layout(widget.id, widget.widget_module_name, widget.name, widget.widget_type_id, widget.rawname, widget.has_settings, widget_html, widget.resizable, widget.locked);
				//get max/min size of this widget
				var min_size_x = allWidgets[cased].list.length ? ((typeof allWidgets[cased].list[widget.widget_type_id].minsize !== "undefined" && typeof allWidgets[cased].list[widget.widget_type_id].minsize.width !== "undefined") ? allWidgets[cased].list[widget.widget_type_id].minsize.width : null) : null;
				var min_size_y = allWidgets[cased].list.length ? ((typeof allWidgets[cased].list[widget.widget_type_id].minsize !== "undefined" && typeof allWidgets[cased].list[widget.widget_type_id].minsize.height !== "undefined") ? allWidgets[cased].list[widget.widget_type_id].minsize.height : null) : null;
				var max_size_x = allWidgets[cased].list.length ? ((typeof allWidgets[cased].list[widget.widget_type_id].maxsize !== "undefined" && typeof allWidgets[cased].list[widget.widget_type_id].maxsize.width !== "undefined") ? allWidgets[cased].list[widget.widget_type_id].maxsize.width : null) : null;
				var max_size_y = allWidgets[cased].list.length ? ((typeof allWidgets[cased].list[widget.widget_type_id].maxsize !== "undefined" && typeof allWidgets[cased].list[widget.widget_type_id].maxsize.height !== "undefined") ? allWidgets[cased].list[widget.widget_type_id].maxsize.height : null) : null;
				//is this widget resizable?
				var resizable = allWidgets[cased].list.length ? ((typeof allWidgets[cased].list[widget.widget_type_id].resizable !== "undefined") ? allWidgets[cased].list[widget.widget_type_id].resizable : true) : true;

				//now add the widget
				gridstack.addWidget($(full_widget_html), widget.size_x, widget.size_y, widget.col, widget.row, false, min_size_x, max_size_x, min_size_y, max_size_y);

				//set resizable
				setTimeout(function() {
					gridstack.resizable($(".grid-stack-item[data-id="+widget.id+"]"), !widget.locked);
				});

				//set locked/or not
				gridstack.movable($(".grid-stack-item[data-id="+widget.id+"]"), !widget.locked);
				gridstack.locked($(".grid-stack-item[data-id="+widget.id+"]"), widget.locked);

				//get widget content
				$.post( UCP.ajaxUrl,
					{
						module: "Dashboards",
						command: "getwidgetcontent",
						id: widget.widget_type_id,
						rawname: widget.rawname,
						uuid: widget.id
					},
					function( data ) {
						//set the content from what we got
						const widget_content_object = $(".grid-stack .grid-stack-item[data-id="+widget.id+"] .widget-content");
						
						let widget_html = data.html;
						if (data.hasError) {
							widget_html = '';
							data.errorMessages.forEach(errorMessage => {
								widget_html += '<div class="alert alert-danger">'+_(errorMessage)+'</div>';
							});
						}
						
						widget_content_object.html(widget_html);

						//execute module method
						UCP.callModuleByMethod(widget.rawname,"displayWidget",widget.id,$this.activeDashboard);
						//execute resize module method
						setTimeout(function() {
							UCP.callModuleByMethod(widget.rawname,"resize",widget.id,$this.activeDashboard);
						},100);

						//trigger event
						$(document).trigger("post-body.widgets",[ widget.id, $this.activeDashboard ]);
					}
				).done(function() {
					callback(); //trigger callback to async
				}).fail(function(jqXHR, textStatus, errorThrown) {
					callback(textStatus); //trigger error to async
				});
			}, function(err) {
				if(err) {
					//show error because there was an error
					UCP.showAlert(err,'danger');
				} else {
					//hide loading window
					$this.deactivateFullLoading();
					//bind grid events
					$this.bindGridChanges();
					//execute module methods
					UCP.callModulesByMethod("showDashboard",$this.activeDashboard);
					//trigger all widgets loaded event
					$(document).trigger("post-body.widgets",[ null, $this.activeDashboard ]);
					if(resave) {
						$this.saveLayoutContent();
					}
				}
			});
		});
	}
});

