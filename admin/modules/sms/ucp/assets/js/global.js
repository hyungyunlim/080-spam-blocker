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
