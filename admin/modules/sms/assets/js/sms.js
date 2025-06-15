$(document).ready(function () {
	$(".fpbx-help-icon").hover(function () {
		let id = "#" + $(this).data('for');
		console.log(id);
		$(id).toggleClass("active");  //Toggle the active class to the area is hovered
	});
});

//initailise data when modal opens
$('#webHookForm').on('show.bs.modal', function (e) {
	var id = $(e.relatedTarget).data('id');

	$('.element-container').removeClass('has-error');

	$(".input-warn").remove();

	if (id == null || id == undefined || id == "") {
		var webhookurl = "";
		var enablewebHook = "0";
		var dataToBeSentOn = "send";
	} else {
		var webhookurl = $(e.relatedTarget).data('webhookurl');
		var enablewebHook = $(e.relatedTarget).data('enablewebhook');
		var dataToBeSentOn = $(e.relatedTarget).data('datatobesenton');
	}

	$("#id").val(id);

	$("#webHookBaseurl").val(webhookurl);

	if (enablewebHook == 1) {
		$("#enablewebHookyes").val(enablewebHook);
		$('#enablewebHookyes').prop('checked', true);
	} else {

		$("#enablewebHookno").val(enablewebHook);
		$('#enablewebHookno').prop('checked', true);
	}

	if (dataToBeSentOn == "send") {
		$("#webhooksend").val(dataToBeSentOn);
		$('#webhooksend').prop('checked', true);

	} else if (dataToBeSentOn == "receive") {
		$("#webhookreceive").val(dataToBeSentOn);
		$('#webhookreceive').prop('checked', true);

	} else {
		$("#webhookboth").val('both');
		$('#webhookboth').prop('checked', true);
	}

});

//add / update webHook
$('#submitForm').on('click', function () {
	var id = $("#id").val();
	var webHookBaseurl = $("#webHookBaseurl").val();
	var enablewebHook = $('input[name="enablewebHook"]:checked').val();
	var dataToBeSentOn = $('input[name="dataToBeSentOn"]:checked').val();

	$this = this;

	if (webHookBaseurl === '') {
		warnInvalid($('#webHookBaseurl'), _('URL cannot be blank'));
		return;
	}

	if (dataToBeSentOn != 'send' && dataToBeSentOn != 'receive' && dataToBeSentOn != 'both') {
		warnInvalid($('input[name="dataToBeSentOn"]'), _('Please select when data has to be sent to webhook'));
		return;
	}

	$(this).blur();
	$(this).prop("disabled", true);
	if (id != '' && id != null && id != undefined) {
		$(this).text(_("Updating..."));
	} else {
		$(this).text(_("Adding..."));
	}

	var post_data = {
		module: 'sms',
		command: 'add',
		action: "add",
		id: id,
		webHookBaseurl: webHookBaseurl,
		enablewebHook: enablewebHook,
		dataToBeSentOn: dataToBeSentOn
	};


	$.post(window.FreePBX.ajaxurl, post_data, function (data) {
		console.log(data);
		$($this).prop("disabled", false);
		$($this).text(_("Save Changes"));
		if (data.status) {
			if (id != '' && id != null && id != undefined) {
				fpbxToast(_("Web hook updated successfully"), '', 'success');
			} else {
				fpbxToast(_("Web Hook created successfully"), '', 'success');
			}
			$('#blGrid').bootstrapTable('refresh', {});
			$("#webHookForm").modal('hide');
		} else {
			alert(data.message);
		}
	});
});

//Delete Web Hook
$(document).on('click', '[id="del"]', function () {
	var id = $(this).data('id');
	fpbxConfirm(
		_("Are you sure you want to delete the Web Hook?"),
		_("Yes"), _("No"),
		function () {
			var post_data = {
				module: 'sms',
				command: 'del',
				action: "delete",
				id: id,
			};
			$.post(window.FreePBX.ajaxurl, post_data)
				.done(function (data) {
					if (data.status == true) {
						$('#blGrid').bootstrapTable('refresh', {
							silent: true
						});
						fpbxToast(_('Web Hook deleted successfully'), '', 'success');
					} else {
						fpbxToast(data.message, '', 'error');
					}
				});
		}
	);
});

//Bulk Actions
$('#action-toggle-all').on("change", function () {
	var tval = $(this).prop('checked');
	$('input[id^="actonthis"]').each(function () {
		$(this).prop('checked', tval);
	});
});

$('input[id^="actonthis"],#action-toggle-all').change(function () {
	if ($('input[id^="actonthis"]').is(":checked")) {
		$("#trashchecked").removeClass("hidden");
	} else {
		$("#trashchecked").addClass("hidden");
	}

});

//This does the bulk delete...
$("#blkDelete").on("click", function (e) {
	e.preventDefault();
	var ids = [];
	$('input[name="btSelectItem"]:checked').each(function () {
		var idx = $(this).data('index');
		ids.push(cbrows[idx]);
	});
	if (ids.length == 0) {
		fpbxToast('There is no record selected!', '', 'warning');
	} else {
		fpbxConfirm(
			_("Are you sure to delete the selected records?"),
			_("Yes"), _("No"),
			function () {
				$('#blGrid').bootstrapTable('showLoading');
				var post_data = {
					module: 'sms',
					command: 'bulkdelete',
					ids: JSON.stringify(ids),
				};
				$.post(window.FreePBX.ajaxurl, post_data)
					.done(function () {
						ids = null;
						$('#blGrid').bootstrapTable('refresh');
						$('#blGrid').bootstrapTable('hideLoading');
					});

				//Reset ui elements
				//hide the action element in botnav
				$("#delchecked").addClass("hidden");
				//no boxes should be checked but if they are uncheck em.
				$('input[name="btSelectItem"]:checked').each(function () {
					$(this).prop('checked', false);
				});
				//Uncheck the "check all" box
				$('#action-toggle-all').prop('checked', false);
			}
		);
	}
});