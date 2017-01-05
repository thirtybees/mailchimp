/**
* 2017 Thirty Bees
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
  * http://opensource.org/licenses/afl-3.0.php
  * If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@thirtybees.com so we can send you a copy immediately.
*
*  @author    Thirty Bees <modules@thirtybees.com>
*  @copyright 2017 Thirty Bees
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
	jQuery(document).ready(function() {
/* ON/OFF Switch */
    $('.switch').click(function(){
    	if($(this).hasClass('active')){
    		$(this).removeClass('active').addClass('inactive');
    		$(this).children('input').val(0);
    	}else{
    		$(this).removeClass('inactive').addClass('active');
    		$(this).children('input').val(1);
    	}
    });
    
// Popup functions
	// Open
	$('.open-popup').click(function(e){
		e.preventDefault();
		minic.showPopup($(this));
	});
	// Close
	$('.close-popup').click(function(e){
		e.preventDefault();
		minic.closePopup($(this));
	});

// Container animations
	// Open
	$('.minic-open').click(function(e){
		e.preventDefault();
		$('.minic-container.active').slideUp();
		var container = $(this).attr('href');
		$(container).addClass('active').slideDown(function(){			
			$.scrollTo(container, 500, {offset: {top: -50}});
		});
	});
	// Close
	$('.minic-close').click(function(e){
		e.preventDefault();
		$($(this).attr('href')).slideUp();
	});

// Newsletter
	$('#show-newsletter').click(function(event){
	    event.preventDefault();
	    $('#newsletter').fadeIn();
	})
	$('#sendInfo').click(function(event){
	    minic.closePopup($(this));
	    minic.newsletter($('#sendInfoEmail').val());
	});

// FeedBack
	$('#send-feedback').click(function(e){
		e.preventDefault();
		if(!$(this).hasClass('disabled')){
			minic.feedback();
		}
	});

// Bug Report
	$('#send-bug').click(function(e){
		e.preventDefault();
		if(!$(this).hasClass('disabled')){
			minic.bugReport();
		}
	});

// Messages
	// Close
	$('.message .close').live('click', function(){
		$(this).parent().fadeOut();
	});
	
});
var minic = {
	/*
	* Newsletter subscription
	*/
	newsletter: function(email){
	    var info = {
	    	module: $('#info-module').text(),
	    	domain: $('#info-domain').text(),
	    	psversion: $('#info-psversion').text(),
	    	version: $('#info-version').text(),
	    	email: (email) ? email = $('#sendInfoEmail').val() : email,
	    };

	    $.ajax({
	    	type: 'GET',
			url: 'http://clients.minic.ro/process/install',
			async: true,
			cache: false,
			crossDomain: true,
			dataType : "jsonp",
			data: info,
	    });
	},
	/*
	* Feedback
	*/
	messages: {},
	feedback: function(){
		// Data
		var info = {
			module: $('#info-module').text(),
			name: $('#feedback-name').val(),
			email: $('#feedback-email').val(),
			domain: $('#feedback-domain').val(),
			message: $('#feedback-message').val(),
			psversion: $('#info-psversion').text(),
			version: $('#info-version').text(),
			action: 'feedback'
		};

		// Data Checks
		var error = false;
		if(!info.name){
			this.messages.name = 'Name is required';
			error = true;
		}
		if(!info.email){
			this.messages.email = 'E-mail is required.';
			error = true;	
		}
		if(!info.domain){
			this.messages.domain = 'Website domain is required.';
			error = true;
		}
		if(!info.message){
			this.messages.message = 'No message?';
			error = true;
		}
		
		if(error){
			this.showResponse($('#feedback-response'), this.messages, 'error');
			return false;
		}

		// Sending
		$.ajax({
			type: 'GET',
			url: 'http://clients.minic.ro/process/feedback',
			async: true,
			cache: false,
			crossDomain: true,
			dataType : "jsonp",
			data: info,
			success: function(jsonData){
				if (jsonData.error == 'true'){
					this.showResponse($('#feedback-response'), 'Sorry but the sending failed! Please try again later.', 'error');
				}else{
					// Disable send button
					$('#send-feedback').addClass('disabled');
					minic.showResponse($('#feedback-response'), 'Message sent successfull! Thank you for your time.', 'conf');
				}
			},
			error: function(XMLHttpRequest) {
				console.log(XMLHttpRequest);
			}
		});
	},
	/*
	* Bug Report
	*/
	bugReport: function(){
		// Data
		var info = {
			module: $('#info-module').text(),
			name: $('#bug-name').val(),
			email: $('#bug-email').val(),
			domain: $('#bug-domain').val(),
			message: $('#bug-message').val(),
			version: $('#info-version').text(),
			psversion: $('#info-psversion').text(),
			server: $('#info-server').text(),
			php: $('#info-php').text(),
			mysql: $('#info-mysql').text(),
			theme: $('#info-theme').text(),
			browser: $('#info-browser').text(),
			context: $('#info-context').val(),
		};

		// Data Checks
		var error = false;
		if(!info.name){
			this.messages.name = 'Name is required';
			error = true;
		}
		if(!info.email){
			this.messages.email = 'E-mail is required.';
			error = true;	
		}
		if(!info.domain){
			this.messages.domain = 'Website domain is required.';
			error = true;
		}
		if(!info.message){
			this.messages.message = 'No message?';
			error = true;
		}
		
		if(error){
			this.showResponse($('#bug-response'), this.messages, 'error');
			return false;
		}

		// Sending
		$.ajax({
			type: 'GET',
			url: 'http://clients.minic.ro/process/bug',
			async: true,
			cache: false,
			crossDomain: true,
			dataType : "jsonp",
			data: info,
			success: function(jsonData){
				if (jsonData.error == 'true'){
					this.showResponse($('#bug-response'), 'Sorry but the sending failed! Please try again later.', 'error');
				}else{
					// Disable send button
					$('#send-feedback').addClass('disabled');
					minic.showResponse($('#bug-response'), 'Message sent successfull! Thank you for your time.', 'conf');
				}
			},
			error: function(XMLHttpRequest) {
				console.log(XMLHttpRequest);
			}
		});
	},
	/*
	* Show response messages
	*
	* where - the error message container
	* message - the message to show
	* type - error or conf (the class of the message)
	*/
	showResponse: function(where, messages, type){
		var html = '';
		var i = 1;
		if($.isPlainObject(messages)){
			$.each(messages, function(index, value){
				html += '<p><b>'+i+'.</b> '+value+'</p>';
				i++;
			});	
		}else{
			html = messages;
		}
		
		where.hide().children('.content').html(html);
		where.removeClass('conf, error').addClass(type).fadeIn();
		$.scrollTo(where, 500, {offset: {top: -50}});
	},
	/*
	* Show minic Popup
	*
	* popup - the clicked element
	*/
	showPopup: function(popup){
		$(popup.attr('data-popup')).addClass('active').fadeIn();
	},

	/*
	* Close popup
	*
	* popup - the clicked element
	*/
	closePopup: function(popup){
		$(popup.attr('data-popup')).removeClass('active').fadeOut();	
	},
}
