(function () {

	var socket;

    var init = function () {
		
		socket = new WebSocket(document.getElementById("sock-addr").value);

		socket.onopen = connectionOpen; 
		socket.onmessage = messageReceived; 

        document.getElementById("sock-send-butt").onclick = function () {
			console.log("Client: " + document.getElementById("sock-msg").value);
			document.getElementById("sock-info").innerHTML += ("Client: "+ document.getElementById("sock-msg").value +"<br />");
            socket.send(document.getElementById("sock-msg").value);
        };


        document.getElementById("sock-disc-butt").onclick = function () {
            connectionClose();
        };

        document.getElementById("sock-recon-butt").onclick = function () {
            socket = new WebSocket(document.getElementById("sock-addr").value);
            socket.onopen = connectionOpen;
            socket.onmessage = messageReceived;
        };

    };


	function connectionOpen() {
	   socket.send("Connection with \""+document.getElementById("sock-addr").value+"\" opened successfully");
	}

	function messageReceived(e) {
	    console.log("Server: " + e.data);
        document.getElementById("sock-info").innerHTML += ("Server: "+e.data+"<br />");
	}

    function connectionClose() {
        socket.close();
        document.getElementById("sock-info").innerHTML += "Connection closed <br />";

    }


    return {
        ////////////////////////////////////////////////////////////////////////////
        // ---- onload event ----
        load : function () {
            window.addEventListener('load', function () {
                init();
            }, false);
        }
    }
})().load();