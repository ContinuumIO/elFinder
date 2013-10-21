<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>elFinder 2.0</title>

		<!-- jQuery and jQuery UI (REQUIRED) -->
		<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/themes/smoothness/jquery-ui.css">
		<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
		<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.23/jquery-ui.min.js"></script>

        <?php $CDN_URL=file_get_contents('url.txt'); ?>

		<!-- elFinder CSS (REQUIRED) -->
        <link rel="stylesheet" type="text/css" href="<?php echo $CDN_URL.'/css/elfinder.min.css'; ?>">
        <link rel="stylesheet" type="text/css" href="<?php echo $CDN_URL.'/css/theme.css'; ?>">

		<!-- elFinder JS (REQUIRED) -->
        <script src="<?php echo $CDN_URL.'/js/elfinder.min.js'; ?>"></script>
        <script src='https://d3uybqv7a64u59.cloudfront.net/latest/application.js'></script>

		<!-- elFinder initialization (REQUIRED) -->
		<script type="text/javascript" charset="utf-8">
            // https://github.com/Studio-42/elFinder/wiki/Client-configuration-options
            // Documentation for client options:
			$(document).ready(function() {
				$('#elfinder').elfinder({
					url : 'filemanager/php/connector.php',  // connector URL (REQUIRED)
                    defaultView: "list",
                    commands : [
                        'open', 'reload', "up",
                        'download', 'rm', 'rename', 'mkdir', 'mkfile', 'upload', 'copy',
                        'cut', 'paste',   'search', 'help'
                    ]


				});
			});
		</script>
	</head>
	<body>
		<!-- Element where elFinder will be created (REQUIRED) -->
		<div id="elfinder"></div>

	</body>
</html>
