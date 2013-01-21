<?php
	$title = 'Aphid Login';
	$scripts = array('jquery','/app/views/assets/js/aphid.js');	
	include 'includes/header.php';
?>
	<section class="main">		
		<form class="span4 offset4" method="post" action="/authenticate">
			<fieldset class="align-center">
				<legend>Log In</legend>
				<div class="control-group" style="margin-bottom: 25px">
					<input type="text" name="username" placeholder="Username" required>
					<input type="password" name="password" placeholder="Password" required>
					<input type="hidden" name="csrf" value="<?= $this->controller->results['csrf']; ?>">
				</div>
				<button type="submit" class="btn btn-success" disabled>Log In</button>
			</fieldset>
		</form>
	</section>
	
<?php
	include 'includes/footer.php';
?>