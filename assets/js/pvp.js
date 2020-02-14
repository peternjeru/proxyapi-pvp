//set up this function
pvp = pvp || {};

pvp.createRequest = function()
{
    return {
        request_id: null,
        request_timestamp: null,
        callback_url: null,
        api_key: null,
        origin: null,
        amount: null,
        account_ref: null,
        description: null,
        callback: function(eventObject)
        {
            /**
             * Prints out:
             * {
             *      StatusCode: 0,                    // or -1 on failure
             *      StatusDescription: "Success",       //or other message on failure
             *      MerchantRequestID: "12801-2646216-1",           //or null on failure
             *      CheckoutRequestID: "ws_CO_040220202222099388"   //or null on failure
             * }
             */
            console.warn(eventObject);
        }
    };
};

function event()
{

}