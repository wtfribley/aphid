<?php
	$title = 'Server Error';	
	include 'includes/header.php';
?>
	<section class="main">
		<h1>An Exception Has Been Thrown</h1>
		
		<h3>Message:</h3>
		<p><?= $message ?> in <strong><?= $file ?></strong> on line <strong><?= $line ?></strong>.</p>
	</section>

<?php
	include 'includes/footer.php';
?>