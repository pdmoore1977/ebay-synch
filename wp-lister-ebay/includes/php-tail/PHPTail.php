<?php

class PHPTail {
	
	## BEGIN PRO ##
	## remove PHPTail in WP-Lister Lite for now as it links to ajax.googleapis.com

	/**
	 * Location of the log file we're tailing
	 * @var string
	 */
	private	$log = "";
	/**
	 * The time between AJAX requests to the server. 
	 * 
	 * Setting this value too high with an extremly fast-filling log will cause your PHP application to hang.
	 * @var integer
	 */
	private $updateTime;
	/**
	 * 
	 * PHPTail constructor
	 * @param string $log the location of the log file
	 * @param integer $defaultUpdateTime The time between AJAX requests to the server. 
	 */
	public function __construct($log, $defaultUpdateTime = 2000) {
		$this->log = $log;
		$this->updateTime = $defaultUpdateTime;
	}
	/**
	 * This function is in charge of retrieving the latest lines from the log file
	 * @param string $lastFetchedSize The size of the file when we lasted tailed it.  
	 * @param string $grepKeyword The grep keyword. This will only return rows that contain this word
	 * @return Returns the JSON representation of the latest file size and appended lines.
	 */
	public function getNewLines($lastFetchedSize, $grepKeyword, $invert) {

		/**
		 * Clear the stat cache to get the latest results
		 */
		clearstatcache();
		/**
		 * Define how much we should load from the log file 
		 * @var 
		 */
		$fsize = filesize($this->log);
		$maxLength = ($fsize - $lastFetchedSize);
		/**
		 * Actually load the data
		 */
		$data = array();
		if($maxLength > 0) {
			
			$fp = fopen($this->log, 'r');
			fseek($fp, -$maxLength , SEEK_END); 
			$data = explode("\n", fread($fp, $maxLength));
			
		}
		/**
		 * Run the grep function to return only the lines we're interested in.
		 */
		if($invert == 0) {
			$data = preg_grep("/$grepKeyword/",$data);
		} else {
			$data = preg_grep("/$grepKeyword/",$data, PREG_GREP_INVERT);
		}
		/**
		 * If the last entry in the array is an empty string lets remove it.
		 */
		if(end($data) == "") {
			array_pop($data);
		}

		// replace tabs with spaces
		$data = str_replace( "  ", '&nbsp;&nbsp;', $data );

		return json_encode(array("size" => $fsize, "data" => $data));	
	}
	/**
	 * This function will print out the required HTML/CSS/JS
	 */
	public function generateGUI() {
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
	        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml">
			<head>
				<title>Log viewer</title> 
				<meta http-equiv="content-type" content="text/html;charset=utf-8" />

				<link type="text/css" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/themes/flick/jquery-ui.css" rel="stylesheet"></link>
				<style type="text/css">
					body {
						font-family: monospace;
					}
					#grepKeyword, #settings, #clearLog { 
						font-size: 80%; 
					}
					.float {
						background: white; 
						border-bottom: 1px solid black; 
						padding: 0 0 10px 0; 
						margin: 0px;  
						height: 30px;
						width: 100%; 
						text-align: left;
					}
					.results {
						padding-bottom: 20px;
					}
				</style>

				<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.4.4/jquery.min.js"></script>
				<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.9/jquery-ui.min.js"></script>
				
				<script type="text/javascript">
					/* <![CDATA[ */
					//Last know size of the file
					lastSize = <?php echo filesize($this->log) - 4096; ?>;
					//Grep keyword
					grep = "";
					//Should the Grep be inverted?
					invert = 0;
					//Last known document height
					documentHeight = 0; 
					//Last known scroll position
					scrollPosition = 0; 
					//Should we scroll to the bottom?
					scroll = true;
					$(document).ready(function(){

						// Setup the settings dialog
						$( "#settings" ).dialog({
							modal: true,
							resizable: false,
							autoOpen: false,
							width: 590,
							height: 270,
							buttons: {
								Close: function() {
									$( this ).dialog( "close" );
								}
							},
							close: function(event, ui) { 
								grep = $("#grep").val();
								invert = $('#invert input:radio:checked').val();
								$("#grepspan").html("Grep keyword: \"" + grep + "\"");
								$("#invertspan").html("Inverted: " + (invert == 1 ? 'true' : 'false'));
							}
						});
						$('#grep').keyup(function(e) {
							if(e.keyCode == 13) {
								$( "#settings" ).dialog('close');
							}
						});							
						$("#grep").focus();

						$("#grepKeyword").button();
						$("#grepKeyword").click(function(){
							$( "#settings" ).dialog('open');
							$("#grepKeyword").removeClass('ui-state-focus');
						});
						
						$("#clearLog").button();
						$("#clearLog").click(function(){
							documentHeight = 0; 
							scrollPosition = 0; 
							$("#results").html('');
							$("#clearLog").removeClass('ui-state-focus');
						});
						
						
						setInterval ( "updateLog()", <?php echo $this->updateTime; ?> );
						updateLog();

						$(window).scroll(function(e) {
						    if ($(window).scrollTop() > 0) { 
						        $('.float').css({
						            position: 'fixed',
						            top: '0',
						            left: 'auto'
						        });
						    } else {
						        $('.float').css({
						            position: 'static'
						        });
						    }
						});

						$(window).resize(function(){
							if(scroll) {
								scrollToBottom();
							}
						});
						$(window).scroll(function(){
							documentHeight = $(document).height(); 
							scrollPosition = $(window).height() + $(window).scrollTop(); 
							if(documentHeight == scrollPosition) {
								scroll = true;
							}
							else {
								scroll = false; 
							}
						});
						
												
					});
					function scrollToBottom() {
						$("html, body").animate({ 
							scrollTop: $(document).height() 
						}, "fast");
					}
					
					function updateLog() {
						// $.getJSON('Log.php?ajax=1&lastsize='+lastSize + '&grep='+grep + '&invert='+invert, function(data) {

						wp_ajax_url = 'admin-ajax.php?action=wple_tail_log&ajax=1' + '&_wpnonce=' + '<?php echo wp_create_nonce( 'wple_tail_log' ) ?>';
						$.getJSON( wp_ajax_url + '&lastsize=' + lastSize + '&grep=' + grep + '&invert=' + invert, function(data) {
							lastSize = data.size;
							$.each(data.data, function(key, value) { 
								$("#results").append('' + value + '<br/>');
							});
							if(scroll) {
								scrollToBottom();
							}
						});
					}
					/* ]]> */
				</script>
			</head> 
			<body>
				<div id="settings" title="PHPTail settings">
					<p>Grep keyword (return results that contain this keyword)</p>
					<input id="grep" type="text" value=""/>
					<p>Should the grep keyword be inverted? (Return results that do NOT contain the keyword)</p>
					<div id="invert">
						<input type="radio" value="1" id="invert1" name="invert" /><label for="invert1">Yes</label>
						<input type="radio" value="0" id="invert2" name="invert" checked="checked" /><label for="invert2">No</label>
					</div>
				</div>
				<div class="float">
					<button id="grepKeyword">Settings</button>
					<button id="clearLog">Clear</button>
					<span>Tailing file: <?php echo $this->log; ?></span> (<?php echo round(filesize($this->log)/1024,2); ?> kb) | <span id="grepspan">keyword: ""</span> | <span id="invertspan"></span>
				</div>
				<div id="results">
				</div>
			</body> 
		</html> 
 		<?php
	} // generateGUI()

	## END PRO ##

} // class PHPTail
