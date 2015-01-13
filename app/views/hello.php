<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>SQLOnline</title>
	<style>
		@import url(//fonts.googleapis.com/css?family=Lato:700);
		body {
			margin:0;
			font-family:'Lato', sans-serif;
			text-align:center;
			color: #999;
		}

		.welcome {
			width: 300px;
			height: 200px;
			position: absolute;
			left: 50%;
			top: 50%;
			margin-left: -150px;
			margin-top: -100px;
		}

		a, a:visited {
			text-decoration:none;
		}

		h1 {
			font-size: 32px;
			margin: 16px 0 0 0;
		}
	</style>
	<script src="//code.jquery.com/jquery-1.11.2.min.js"></script>
	<script src="http://cdn.peerjs.com/0.3/peer.js"></script>
	<script src="/javascript/diff.js"></script>

<script>
	var clients=[];
	var changeHistory=[];
	var myKey="";
	var historyNumber=0;
	var oldText;

	(function($) {
		$.fn.getCursorPosition = function() {
			var input = this.get(0);
			if (!input) return; // No (input) element found
			if ('selectionStart' in input) {
				// Standard-compliant browsers
				return input.selectionStart;
			} else if (document.selection) {
				// IE
				input.focus();
				var sel = document.selection.createRange();
				var selLen = document.selection.createRange().text.length;
				sel.moveStart('character', -input.value.length);
				return sel.text.length - selLen;
			}
		}
	})(jQuery);

	function init() {
		oldText= $("#sqlBox").text();
		myKey=Math.floor((Math.random() * 1000) + 1);
		$("#myKey").html(myKey);

		var historyNote={
			"patch":[],
			"userId":myKey,
			"patchId":historyNumber
		};
		historyNumber=historyNumber+1;
		changeHistory.push(historyNote);


	}

	$( document ).ready(function(){
		init();



		var a = document.getElementById('sqlBox');
		a.onpaste = a.onchange = changed;
		if ('oninput' in a) {
			a.oninput = changed;
		} else {
			a.onkeyup = changed;
		}

		function changed(event) {

			var newText=$("#sqlBox").val();
			var diff = JsDiff["diffChars"](oldText,newText);

			console.log(changeHistory);
			var patchOn=changeHistory[changeHistory.length-1];

			oldText=newText;
			var msg={
				"type":"patch",
				"patchs":diff,
				"from":myKey,
				"nummber":historyNumber,
				"patchOn":{
					"userId":patchOn.userId,
					"patchId":patchOn.patchId
				}
			};
			var historyNote={
				"patch":diff,
				"userId":myKey,
				"patchId":historyNumber
			};

			changeHistory.push(historyNote);


			historyNumber=historyNumber+1;

			clients.forEach(function(client){
				client.send(JSON.stringify(msg));
			});

		};



		var peer = new Peer(myKey, {key: '9b406c3gm4jwcdi'});

		peer.on('connection', function(conn) {//a new connection
			console.log("new connection");
			var inList=false;
			clients.forEach(function(client){
				console.log(conn.peer+"=="+client.peer);
				if(conn.peer==client.peer){
					console.log("allrdy in list");
					inList=true;
				}
			});
			console.log("inlist "+inList);
			if(!inList) {


				var connNewPeer = peer.connect(conn.peer);


				clients.push(connNewPeer);
				connNewPeer.on('open', function () {


					console.log("open connection");
					listClients=[];
					clients.forEach(function(client){
						listClients.push(client.peer);
					})
					var msg={
						"type":"updateClients",
						"clients":listClients
					};

					connNewPeer.send(JSON.stringify(msg));
					var msg={
						"type":"fullHistory",
						"history":changeHistory
					};
					connNewPeer.send(JSON.stringify(msg));



				});

			}

			conn.on('data', function(data){
				console.log("get-data");
				console.log("|"+data+"|");
				console.log("==============");
				var obj = JSON.parse(data);
				console.log("------");
				if(obj.type=="patch") {
					console.log("get data");

					doPatch(obj.patchs)


					var historyNote={
						"patch":obj.patchs,
						"userId":obj.from,
						"patchId":obj.patchOn
					};
					changeHistory.push(historyNote);
				}else if(obj.type=="updateClients"){
					obj.clients.forEach(function(client){
						var exist=false;
						clients.forEach(function(localClient){
							if(localClient.peer==client){
								exist=true;
							}
						});
						if(!exist&&client!=myKey){
							var connNewPeer = peer.connect(client);
							clients.push(connNewPeer);
						}
					});
				}else if(obj.type=="fullHistory"){
					changeHistory=obj.history;

					changeHistory.forEach(function(commit){
						doPatch(commit.patch);
					});
				}
			});

		});

	function doPatch(patchs) {
		var text = $("#sqlBox").val();
		var whereInLine = 0;
		var newTextArr=[];
		patchs.forEach(function (entry) {
			console.log(entry);
			if (entry.added) {
				console.log("added");
				console.log(entry.value);
				newTextArr.push(entry.value);
				//		newText += entry.value;
			} else if (entry.removed) {
				console.log("removed");
				whereInLine += entry.count
			} else {
				console.log("unchanges");
				newTextArr.push(text.substr(whereInLine,whereInLine+entry.count));

				whereInLine += entry.count
			}

		});
		newTextArr.push(text.substr(whereInLine,text.length));


		oldText=newTextArr.join("");
		$("#sqlBox").val(oldText);

	}


	$("#connectToRTC").click(

		function (){

			console.log("connection");
			var connectToKey=$("#connectToKey").val();


			console.log("conect to:"+connectToKey);

			var conn = peer.connect(connectToKey);

			clients.push(conn);
			conn.on('open', function(){


				console.log("click");
				console.log(clients);

			});
		});
	});





</script>

	

</head>
<body>
	<H1>SQLOnline</H1>
	<div id="holder">	<div type="text" id="myKey" ></div>  <input type="text" id="connectToKey"> <button type="button" id="connectToRTC">save</button></div>
	<textarea id="sqlBox" rows="60" cols="200"></textarea>

</body>
</html>
