jQuery( function ( $ ) {
    var style = {
        css: { 
            border: 'none', 
            padding: '15px', 
            backgroundColor: '#000', 
            '-webkit-border-radius': '10px', 
            '-moz-border-radius': '10px', 
            opacity: .5, 
            color: '#fff' 
        },

    }

    $.blockUI({ 
        ...style,
        message: '<p> Please wait...</p>' 
    }); 

    setTimeout($.unblockUI, 4000);
	// $.blockUI({message: '<p> Please wait...</p>'});
	let payment_made = false;
	const redirectPost = function (location, args) {
		let form = "";
		$.each(args, function (key, value) {
			// value = value.split('"').join('\"')
			form += '<input type="hidden" name="' + key + '" value="' + value + '">';
		});
		$('<form action="' + location + '" method="POST">' + form + "</form>")
			.appendTo($(document.body))
			.submit();
	};

	const processData = () => {
        const { client_id, client_secret, redirect_uri, environment, phone_number, items, amount, tx_ref } = vida_args;
        console.log("current order items: ", items)
		return {
			"setup" : {
                clientId: client_id,
                clientSecret: client_secret,
                redirectUri: redirect_uri,
                environment,
            },
            "request": {
                profile: phone_number,
                reference: tx_ref,
                items,
                totalAmount: amount
            }
		}
	}
	let payload = processData();

    const vidaMerchant = new VidaMerchant(payload['setup']);
	vidaMerchant.createPurchase(payload['request']);
} );