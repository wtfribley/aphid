<?php
	$title = 'Server Error';	
	include 'includes/header.php';
?>
	<section class="main">
		<h1>A Server Error has Occurred</h1>

<?php 	if (isset($data['error']) && Config::get('settings.env') == 'dev') { ?>
			<h3>Error:</h3>
			<code><?= $data['error']; ?></code>

<?php 		unset($data['error']);
			if (isset($data) && !empty($data)) { ?>
				<hr />
				<pre>
					<?php print_r($data); ?>
				</pre>
<?php 		}
		}
		else { ?>
			<h3>We're not sure what the problem is...</h3>
	    	<p>...but rest assured, we're going to find it and make it go away.</p>
<?php 	} ?>
	</section>

<?php
	include 'includes/footer.php';
?>