<?php
	$title = 'Unauthorized Access';	
	include 'includes/header.php';
?>
	<section class="main">
		<h1>You Sly Dog!</h1>

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
			<h3>We're onto you...</h3>
	    	<p>If you're seeing this, chances are you're up to no good. Watch your back.</p>
<?php 	} ?>
	</section>

<?php
	include 'includes/footer.php';
?>